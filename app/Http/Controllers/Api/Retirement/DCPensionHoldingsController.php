<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Retirement;

use App\Http\Controllers\Controller;
use App\Http\Traits\SanitizedErrorResponse;
use App\Models\DCPension;
use App\Models\Investment\Holding;
use App\Services\Cache\CacheInvalidationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * DC Pension Holdings Controller
 *
 * Manages holdings (individual funds/investments) within DC pension pots
 */
class DCPensionHoldingsController extends Controller
{
    use SanitizedErrorResponse;

    public function __construct(
        private readonly CacheInvalidationService $cacheInvalidation
    ) {}

    /**
     * Get all holdings for a DC pension
     */
    public function index(Request $request, int $dcPensionId): JsonResponse
    {
        $user = $request->user();
        $pension = DCPension::where('user_id', $user->id)
            ->where('id', $dcPensionId)
            ->firstOrFail();

        $holdings = $pension->holdings()
            ->orderBy('current_value', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $holdings,
            'total_value' => $holdings->sum('current_value'),
            'holdings_count' => $holdings->count(),
        ]);
    }

    /**
     * Store a new holding for a DC pension
     */
    public function store(Request $request, int $dcPensionId): JsonResponse
    {
        $user = $request->user();

        // Verify pension ownership
        $pension = DCPension::where('user_id', $user->id)
            ->where('id', $dcPensionId)
            ->firstOrFail();

        $validated = $request->validate([
            'security_name' => 'required|string|max:255',
            'ticker' => 'nullable|string|max:255',
            'isin' => 'nullable|string|max:255',
            'asset_type' => 'required|in:equity,bond,fund,etf,alternative,uk_equity,us_equity,international_equity,cash,property',
            'allocation_percent' => 'nullable|numeric|min:0|max:100',
            'quantity' => 'nullable|numeric|min:0',
            'purchase_price' => 'nullable|numeric|min:0',
            'purchase_date' => 'nullable|date',
            'current_price' => 'nullable|numeric|min:0',
            'current_value' => 'required|numeric|min:0',
            'ocf_percent' => 'nullable|numeric|min:0|max:100',
        ]);

        // Add polymorphic relationship data
        $validated['holdable_id'] = $pension->id;
        $validated['holdable_type'] = DCPension::class;

        // Calculate cost basis if missing
        if (isset($validated['quantity']) && isset($validated['purchase_price'])) {
            $validated['cost_basis'] = $validated['quantity'] * $validated['purchase_price'];
        }

        $holding = Holding::create($validated);

        // Clear caches
        $this->cacheInvalidation->invalidateForUser($user->id);
        Cache::forget("dc_pension_{$pension->id}_portfolio");

        return response()->json([
            'success' => true,
            'message' => 'Holding created successfully',
            'data' => $holding,
        ], 201);
    }

    /**
     * Update a DC pension holding
     */
    public function update(Request $request, int $dcPensionId, int $holdingId): JsonResponse
    {
        $user = $request->user();

        // Verify pension ownership
        $pension = DCPension::where('user_id', $user->id)
            ->where('id', $dcPensionId)
            ->firstOrFail();

        // Get holding and verify it belongs to this pension
        $holding = Holding::where('id', $holdingId)
            ->where('holdable_id', $pension->id)
            ->where('holdable_type', DCPension::class)
            ->firstOrFail();

        $validated = $request->validate([
            'security_name' => 'sometimes|required|string|max:255',
            'ticker' => 'nullable|string|max:255',
            'isin' => 'nullable|string|max:255',
            'asset_type' => 'sometimes|required|in:equity,bond,fund,etf,alternative,uk_equity,us_equity,international_equity,cash,property',
            'allocation_percent' => 'nullable|numeric|min:0|max:100',
            'quantity' => 'nullable|numeric|min:0',
            'purchase_price' => 'nullable|numeric|min:0',
            'purchase_date' => 'nullable|date',
            'current_price' => 'nullable|numeric|min:0',
            'current_value' => 'sometimes|required|numeric|min:0',
            'ocf_percent' => 'nullable|numeric|min:0|max:100',
        ]);

        // Recalculate cost basis if quantity or purchase price changed
        if (isset($validated['quantity']) || isset($validated['purchase_price'])) {
            $quantity = $validated['quantity'] ?? $holding->quantity;
            $purchasePrice = $validated['purchase_price'] ?? $holding->purchase_price;
            if ($quantity && $purchasePrice) {
                $validated['cost_basis'] = $quantity * $purchasePrice;
            }
        }

        $holding->update($validated);

        // Clear caches
        $this->cacheInvalidation->invalidateForUser($user->id);
        Cache::forget("dc_pension_{$pension->id}_portfolio");

        return response()->json([
            'success' => true,
            'message' => 'Holding updated successfully',
            'data' => $holding->fresh(),
        ]);
    }

    /**
     * Delete a DC pension holding
     */
    public function destroy(Request $request, int $dcPensionId, int $holdingId): JsonResponse
    {
        $user = $request->user();

        // Verify pension ownership
        $pension = DCPension::where('user_id', $user->id)
            ->where('id', $dcPensionId)
            ->firstOrFail();

        // Get holding and verify it belongs to this pension
        $holding = Holding::where('id', $holdingId)
            ->where('holdable_id', $pension->id)
            ->where('holdable_type', DCPension::class)
            ->firstOrFail();

        $holding->delete();

        // Clear caches
        $this->cacheInvalidation->invalidateForUser($user->id);
        Cache::forget("dc_pension_{$pension->id}_portfolio");

        return response()->json([
            'success' => true,
            'message' => 'Holding deleted successfully',
        ]);
    }

    /**
     * Bulk update holdings (for rebalancing)
     */
    public function bulkUpdate(Request $request, int $dcPensionId): JsonResponse
    {
        $user = $request->user();

        // Verify pension ownership
        $pension = DCPension::where('user_id', $user->id)
            ->where('id', $dcPensionId)
            ->firstOrFail();

        $request->validate([
            'holdings' => 'required|array',
            'holdings.*.id' => 'required|exists:holdings,id',
            'holdings.*.current_value' => 'required|numeric|min:0',
            'holdings.*.current_price' => 'nullable|numeric|min:0',
            'holdings.*.allocation_percent' => 'nullable|numeric|min:0|max:100',
        ]);

        DB::beginTransaction();
        try {
            foreach ($request->holdings as $holdingData) {
                $holding = Holding::where('id', $holdingData['id'])
                    ->where('holdable_id', $pension->id)
                    ->where('holdable_type', DCPension::class)
                    ->firstOrFail();

                $holding->update([
                    'current_value' => $holdingData['current_value'],
                    'current_price' => $holdingData['current_price'] ?? $holding->current_price,
                    'allocation_percent' => $holdingData['allocation_percent'] ?? $holding->allocation_percent,
                ]);
            }

            DB::commit();

            $this->cacheInvalidation->invalidateForUser($user->id);
            Cache::forget("dc_pension_{$pension->id}_portfolio");

            return response()->json([
                'success' => true,
                'message' => 'Holdings updated successfully',
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            return $this->errorResponse($e, 'Bulk updating pension holdings');
        }
    }
}
