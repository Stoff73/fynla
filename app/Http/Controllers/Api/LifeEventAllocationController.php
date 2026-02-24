<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Traits\SanitizedErrorResponse;
use App\Models\LifeEvent;
use App\Models\LifeEventAllocation;
use App\Services\Goals\LifeEventAllocationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LifeEventAllocationController extends Controller
{
    use SanitizedErrorResponse;

    public function __construct(
        private readonly LifeEventAllocationService $allocationService
    ) {}

    /**
     * Get allocations for a life event (auto-generates on first access).
     */
    public function index(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        $event = $this->findEventForUser($id, $user->id);

        if (! $event) {
            return $this->notFoundResponse('Life event');
        }

        try {
            $allocations = $this->allocationService->getAllocations($event, $user);

            $enabledTotal = $allocations->where('enabled', true)->sum('amount');

            return response()->json([
                'success' => true,
                'data' => [
                    'allocations' => $allocations->values(),
                    'summary' => [
                        'event_amount' => (float) $event->getAmountForUser($user->id),
                        'total_allocated' => round((float) $enabledTotal, 2),
                        'allocation_count' => $allocations->count(),
                        'enabled_count' => $allocations->where('enabled', true)->count(),
                    ],
                ],
            ]);
        } catch (\Exception $e) {
            return $this->errorResponse($e, 'Fetch allocations', 500, ['event_id' => $id]);
        }
    }

    /**
     * Update a single allocation (amount and/or enabled status).
     */
    public function update(Request $request, int $id, int $allocationId): JsonResponse
    {
        $user = $request->user();
        $event = $this->findEventForUser($id, $user->id);

        if (! $event) {
            return $this->notFoundResponse('Life event');
        }

        $validated = $request->validate([
            'amount' => 'required|numeric|min:0|max:999999999.99',
            'enabled' => 'required|boolean',
        ]);

        $allocation = LifeEventAllocation::where('id', $allocationId)
            ->where('life_event_id', $event->id)
            ->where('user_id', $user->id)
            ->first();

        if (! $allocation) {
            return $this->notFoundResponse('Allocation');
        }

        try {
            $updated = $this->allocationService->updateAllocation(
                $allocation,
                (float) $validated['amount'],
                (bool) $validated['enabled']
            );

            return response()->json([
                'success' => true,
                'data' => $updated,
            ]);
        } catch (\Exception $e) {
            return $this->errorResponse($e, 'Update allocation', 500, ['allocation_id' => $allocationId]);
        }
    }

    /**
     * Regenerate allocation suggestions (clears and re-runs waterfall).
     */
    public function regenerate(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        $event = $this->findEventForUser($id, $user->id);

        if (! $event) {
            return $this->notFoundResponse('Life event');
        }

        try {
            $allocations = $this->allocationService->generateAllocations($event, $user);

            $enabledTotal = $allocations->where('enabled', true)->sum('amount');

            return response()->json([
                'success' => true,
                'message' => 'Allocations regenerated successfully.',
                'data' => [
                    'allocations' => $allocations->values(),
                    'summary' => [
                        'event_amount' => (float) $event->getAmountForUser($user->id),
                        'total_allocated' => round((float) $enabledTotal, 2),
                        'allocation_count' => $allocations->count(),
                        'enabled_count' => $allocations->where('enabled', true)->count(),
                    ],
                ],
            ]);
        } catch (\Exception $e) {
            return $this->errorResponse($e, 'Regenerate allocations', 500, ['event_id' => $id]);
        }
    }

    /**
     * Find a life event that belongs to the user (or their joint owner).
     */
    private function findEventForUser(int $eventId, int $userId): ?LifeEvent
    {
        return LifeEvent::where('id', $eventId)
            ->where(function ($query) use ($userId) {
                $query->where('user_id', $userId)
                    ->orWhere('joint_owner_id', $userId);
            })
            ->first();
    }
}
