<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Chattel\StoreChattelRequest;
use App\Http\Requests\Chattel\UpdateChattelRequest;
use App\Http\Resources\ChattelResource;
use App\Http\Traits\SanitizedErrorResponse;
use App\Models\Chattel;
use App\Services\Chattel\ChattelCGTService;
use App\Services\NetWorth\NetWorthService;
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
    use SanitizedErrorResponse;

    public function __construct(
        private ChattelCGTService $cgtService,
        private NetWorthService $netWorthService
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
        $chattels = Chattel::forUserOrJoint($user->id)
            ->with(['jointOwner', 'trust'])
            ->orderBy('current_value', 'desc')
            ->get();

        return response()->json(ChattelResource::collection($chattels));
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

        // Invalidate net worth cache
        $this->netWorthService->invalidateCache($user->id);
        if ($chattel->joint_owner_id) {
            $this->netWorthService->invalidateCache($chattel->joint_owner_id);
        }

        return response()->json(new ChattelResource($chattel), 201);
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

        $chattelResource = new ChattelResource($chattel);
        $chattelData = $chattelResource->toArray($request);

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

        // Single-record pattern: Handle ownership percentage when changing to/from joint
        $ownershipType = $validated['ownership_type'] ?? $chattel->ownership_type;
        $jointOwnerId = $validated['joint_owner_id'] ?? $chattel->joint_owner_id;

        if ($ownershipType === 'joint' && $jointOwnerId) {
            // Switching to joint or already joint - default to 50% if not specified
            if (! isset($validated['ownership_percentage'])) {
                $validated['ownership_percentage'] = 50.00;
            }
        } elseif ($ownershipType === 'individual') {
            // Switching to individual - reset to 100%
            $validated['ownership_percentage'] = 100.00;
            $validated['joint_owner_id'] = null;
        }

        // Single-record pattern: Update directly
        $chattel->update($validated);
        $chattel->load(['jointOwner', 'trust']);

        // Invalidate net worth cache
        $this->netWorthService->invalidateCache($user->id);
        if ($chattel->joint_owner_id) {
            $this->netWorthService->invalidateCache($chattel->joint_owner_id);
        }

        return response()->json([
            'success' => true,
            'message' => 'Chattel updated successfully',
            'data' => new ChattelResource($chattel),
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

        // Capture joint owner before delete
        $jointOwnerId = $chattel->joint_owner_id;

        $chattel->delete();

        // Invalidate net worth cache
        $this->netWorthService->invalidateCache($user->id);
        if ($jointOwnerId) {
            $this->netWorthService->invalidateCache($jointOwnerId);
        }

        return response()->noContent();
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
}
