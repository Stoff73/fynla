<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Chattel\StoreChattelRequest;
use App\Http\Requests\Chattel\UpdateChattelRequest;
use App\Models\Chattel;
use App\Services\Chattel\ChattelCGTService;
use App\Traits\CalculatesOwnershipShare;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Chattel Controller
 *
 * Single-Record Architecture:
 * - ONE database record stores the FULL chattel value in current_value
 * - user_id = primary owner (can edit/delete)
 * - joint_owner_id = secondary owner (view access, sees in their dashboard)
 * - ownership_percentage = primary owner's share (default 50 for joint)
 * - Query pattern: where('user_id', $id)->orWhere('joint_owner_id', $id)
 */
class ChattelController extends Controller
{
    use CalculatesOwnershipShare;

    public function __construct(
        private ChattelCGTService $cgtService
    ) {}

    /**
     * Get all chattels for the authenticated user
     *
     * Returns chattels where user is either primary owner (user_id) or
     * joint owner (joint_owner_id). Each chattel includes user_share calculated
     * from the full value and ownership percentage.
     *
     * GET /api/chattels
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        // Single-record pattern: Get chattels where user is owner OR joint_owner
        $chattels = Chattel::where('user_id', $user->id)
            ->orWhere('joint_owner_id', $user->id)
            ->with(['jointOwner', 'trust'])
            ->orderBy('current_value', 'desc')
            ->get();

        // Add calculated fields for each chattel
        $chattels = $chattels->map(function ($chattel) use ($user) {
            return $this->enrichChattelData($chattel, $user->id);
        });

        return response()->json($chattels);
    }

    /**
     * Store a new chattel
     *
     * Single-record pattern: Store FULL value directly, no splitting.
     * Joint owner is linked via joint_owner_id field.
     *
     * POST /api/chattels
     */
    public function store(StoreChattelRequest $request): JsonResponse
    {
        $user = $request->user();
        $validated = $request->validated();

        // Set defaults
        $validated['user_id'] = $user->id;
        $validated['household_id'] = $validated['household_id'] ?? $user->household_id;
        $validated['ownership_type'] = $validated['ownership_type'] ?? 'individual';
        $validated['ownership_percentage'] = $validated['ownership_percentage'] ?? 100.00;
        $validated['valuation_date'] = $validated['valuation_date'] ?? now();
        $validated['country'] = $validated['country'] ?? 'United Kingdom';

        // For joint ownership, default to 50/50 split if not specified
        if (in_array($validated['ownership_type'], ['joint']) && $validated['ownership_percentage'] == 100.00) {
            $validated['ownership_percentage'] = 50.00;
        }

        $chattel = Chattel::create($validated);
        $chattel->load(['jointOwner', 'trust']);

        $chattelData = $this->enrichChattelData($chattel, $user->id);

        return response()->json($chattelData, 201);
    }

    /**
     * Get a single chattel
     *
     * Returns chattel if user is primary owner OR joint owner.
     *
     * GET /api/chattels/{id}
     */
    public function show(Request $request, int $id): JsonResponse
    {
        $user = $request->user();

        // Single-record pattern: Allow access if user is owner OR joint_owner
        $chattel = Chattel::where('id', $id)
            ->where(function ($query) use ($user) {
                $query->where('user_id', $user->id)
                    ->orWhere('joint_owner_id', $user->id);
            })
            ->with(['jointOwner', 'trust', 'household'])
            ->firstOrFail();

        $chattelData = $this->enrichChattelData($chattel, $user->id);

        // Add CGT exemption status
        $chattelData['cgt_status'] = $this->cgtService->wouldBeExempt($chattel, (float) $chattel->current_value);

        return response()->json([
            'success' => true,
            'data' => $chattelData,
        ]);
    }

    /**
     * Update a chattel
     *
     * Only primary owner (user_id) can update.
     * Single-record pattern: Update the single record directly.
     *
     * PUT /api/chattels/{id}
     */
    public function update(UpdateChattelRequest $request, int $id): JsonResponse
    {
        $user = $request->user();

        // Only primary owner can update
        $chattel = Chattel::where('id', $id)
            ->where('user_id', $user->id)
            ->firstOrFail();

        $validated = $request->validated();

        // Single-record pattern: Update directly
        $chattel->update($validated);
        $chattel->load(['jointOwner', 'trust']);

        $chattelData = $this->enrichChattelData($chattel, $user->id);

        return response()->json([
            'success' => true,
            'message' => 'Chattel updated successfully',
            'data' => $chattelData,
        ]);
    }

    /**
     * Delete a chattel
     *
     * Only primary owner (user_id) can delete.
     * Single-record pattern: Delete the single record.
     *
     * DELETE /api/chattels/{id}
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        $user = $request->user();

        // Only primary owner can delete
        $chattel = Chattel::where('id', $id)
            ->where('user_id', $user->id)
            ->firstOrFail();

        $chattel->delete();

        return response()->json([
            'success' => true,
            'message' => 'Chattel deleted successfully',
        ]);
    }

    /**
     * Calculate CGT for a chattel disposal
     *
     * POST /api/chattels/{id}/calculate-cgt
     */
    public function calculateCGT(Request $request, int $id): JsonResponse
    {
        $user = $request->user();

        $request->validate([
            'disposal_price' => 'required|numeric|min:0',
            'disposal_costs' => 'sometimes|numeric|min:0',
        ]);

        // Allow access if user is owner OR joint_owner
        $chattel = Chattel::where('id', $id)
            ->where(function ($query) use ($user) {
                $query->where('user_id', $user->id)
                    ->orWhere('joint_owner_id', $user->id);
            })
            ->firstOrFail();

        $cgt = $this->cgtService->calculateCGT(
            $chattel,
            (float) $request->input('disposal_price'),
            (float) $request->input('disposal_costs', 0),
            $user
        );

        return response()->json([
            'success' => true,
            'data' => $cgt,
        ]);
    }

    /**
     * Enrich chattel data with calculated fields
     */
    private function enrichChattelData(Chattel $chattel, int $userId): array
    {
        $chattelData = $chattel->toArray();

        // Calculate user's share from full value
        $chattelData['user_share'] = $this->calculateUserShare($chattel, $userId);
        $chattelData['full_value'] = (float) $chattel->current_value;
        $chattelData['is_primary_owner'] = $this->isPrimaryOwner($chattel, $userId);
        $chattelData['is_shared'] = $this->isSharedOwnership($chattel);
        $chattelData['is_wasting_asset'] = $this->cgtService->isWastingAsset($chattel);

        return $chattelData;
    }
}
