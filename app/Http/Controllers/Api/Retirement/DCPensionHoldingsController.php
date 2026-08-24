<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Retirement;

use App\Constants\HoldingSubTypes;
use App\Http\Controllers\Controller;
use App\Http\Traits\SanitizedErrorResponse;
use App\Models\DCPension;
use App\Models\Investment\Holding;
use App\Services\Cache\CacheInvalidationService;
use App\Services\Stores\PensionStore;
use App\Support\HoldingValuation;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * DC Pension Holdings Controller
 *
 * Manages holdings (individual funds/investments) within DC pension pots
 */
class DCPensionHoldingsController extends Controller
{
    use SanitizedErrorResponse;

    public function __construct(
        private readonly CacheInvalidationService $cacheInvalidation,
        private readonly PensionStore $pensionStore,
    ) {}

    /**
     * Ownership-check helper. Replaces five inline
     * DCPension::where('user_id', ...)->firstOrFail() sites — every entry
     * point now routes through the canonical store's joint-aware read.
     * Holdings mutation itself stays in this controller until Pass 6.
     *
     * **`ModelNotFoundException` was thrown here without being imported**, so it
     * resolved to `App\Http\Controllers\Api\Retirement\ModelNotFoundException`,
     * which does not exist: every not-found path on all five endpoints raised a
     * fatal `Error` and returned 500 rather than the 404 the caller expects
     * (W-0444). It survived because `DCPensionHoldingValuationTest` gives every
     * case a pension its own user owns, so nothing ever entered this branch —
     * the Fixture variant in `tests/CLAUDE.md` §4.
     */
    private function pensionForUserOr404(int $dcPensionId, $user): DCPension
    {
        $pension = $this->pensionStore->find($dcPensionId, 'dc', $user);
        if ($pension === null) {
            throw new ModelNotFoundException('DC pension not found or unauthorized');
        }

        return $pension;
    }

    /**
     * Get all holdings for a DC pension
     */
    public function index(Request $request, int $dcPensionId): JsonResponse
    {
        $user = $request->user();
        $pension = $this->pensionForUserOr404($dcPensionId, $user);

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
        $pension = $this->pensionForUserOr404($dcPensionId, $user);

        $validated = $request->validate([
            'security_name' => 'required|string|max:255',
            'ticker' => 'nullable|string|max:255',
            'isin' => 'nullable|string|max:255',
            'asset_type' => 'required|in:equity,bond,fund,etf,alternative,uk_equity,us_equity,international_equity,cash,property',
            // `HoldingForm` requires a fund type whenever the asset type is Fund,
            // and this endpoint had no rule for the column at all — so
            // `validated()` dropped it and a fund type the user was made to choose
            // was reported as saved and never stored. The vocabulary is
            // `HoldingSubTypes`, which both investment holding requests read too
            // (Rule 20).
            //
            // Accepted, NOT `required_if:asset_type,fund`. The investment holding
            // requests carry that because they serve one form that always sends it;
            // this endpoint has other callers, and refusing a fund holding that
            // states no sub-type would reject what those paths legitimately produce
            // — the "column wider than the rule" direction in `app/Http/CLAUDE.md`,
            // where the answer depends on whether anything offers the excluded
            // value. Something does: `DCPensionHoldingValuationTest` creates a fund
            // holding with no sub-type, and requiring one turned that 201 into a 422.
            'sub_type' => ['nullable', 'string', Rule::in(HoldingSubTypes::ALL)],
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

        // Units, price, value and cost basis are reconciled in the ONE place every
        // holding write path reads (Rule 20, W-0126). This carried its own
        // `cost_basis = quantity x purchase_price`, written out line for line, and
        // derived no unit count at all when the caller gave a value and a price.
        $validated = HoldingValuation::reconcile($validated);

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
        $pension = $this->pensionForUserOr404($dcPensionId, $user);

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
            // `HoldingForm` requires a fund type whenever the asset type is Fund,
            // and this endpoint had no rule for the column at all — so
            // `validated()` dropped it and a fund type the user was made to choose
            // was reported as saved and never stored. The vocabulary is
            // `HoldingSubTypes`, which both investment holding requests read too
            // (Rule 20).
            //
            // Accepted, NOT `required_if:asset_type,fund`. The investment holding
            // requests carry that because they serve one form that always sends it;
            // this endpoint has other callers, and refusing a fund holding that
            // states no sub-type would reject what those paths legitimately produce
            // — the "column wider than the rule" direction in `app/Http/CLAUDE.md`,
            // where the answer depends on whether anything offers the excluded
            // value. Something does: `DCPensionHoldingValuationTest` creates a fund
            // holding with no sub-type, and requiring one turned that 201 into a 422.
            'sub_type' => ['nullable', 'string', Rule::in(HoldingSubTypes::ALL)],
            'allocation_percent' => 'nullable|numeric|min:0|max:100',
            'quantity' => 'nullable|numeric|min:0',
            'purchase_price' => 'nullable|numeric|min:0',
            'purchase_date' => 'nullable|date',
            'current_price' => 'nullable|numeric|min:0',
            'current_value' => 'sometimes|required|numeric|min:0',
            'ocf_percent' => 'nullable|numeric|min:0|max:100',
        ]);

        // The same ONE reconciliation as the create path, resolved against the stored
        // holding so a partial edit keeps the fields it left alone. This hand-rolled
        // that fallback — `$validated['quantity'] ?? $holding->quantity` — which is
        // precisely the construct that let an inherited unit count overwrite a value
        // the user had just typed (W-0121). Resolving it here means the shared class
        // decides which of the two the caller actually asserted.
        $validated = HoldingValuation::reconcile($validated, $holding);

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
        $pension = $this->pensionForUserOr404($dcPensionId, $user);

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
        $pension = $this->pensionForUserOr404($dcPensionId, $user);

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

                // A bulk re-valuation states a new value and sometimes a new price.
                // It never states units, so the value the user typed stands and the
                // unit count is back-calculated from it — rather than being left to
                // contradict the figure now stored beside it (W-0121). Fields the
                // caller omitted are not written at all; the shared rule resolves
                // them against the stored row.
                $payload = ['current_value' => $holdingData['current_value']];
                foreach (['current_price', 'allocation_percent'] as $field) {
                    if (isset($holdingData[$field])) {
                        $payload[$field] = $holdingData[$field];
                    }
                }

                $holding->update(HoldingValuation::reconcile($payload, $holding));
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
