<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreLifeEventRequest;
use App\Http\Requests\UpdateLifeEventRequest;
use App\Http\Traits\SanitizedErrorResponse;
use App\Models\LifeEvent;
use App\Services\Goals\LifeEventService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Life Event Controller
 *
 * Handles CRUD operations for life events - future occurrences
 * that impact a user's financial position (inheritance, large purchases, etc.).
 */
class LifeEventController extends Controller
{
    use SanitizedErrorResponse;

    public function __construct(
        private readonly LifeEventService $lifeEventService
    ) {}

    /**
     * Get all life events for the authenticated user.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $includeHousehold = $request->boolean('household', false);

        try {
            $events = $this->lifeEventService->getEvents($user->id, $includeHousehold);

            return response()->json([
                'success' => true,
                'data' => [
                    'events' => $events,
                    'count' => $events->count(),
                ],
            ]);
        } catch (\Exception $e) {
            return $this->errorResponse($e, 'Fetch life events', 500, ['user_id' => $user->id]);
        }
    }

    /**
     * Get available event types with metadata.
     */
    public function getEventTypes(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => [
                'event_types' => $this->lifeEventService->getEventTypes(),
                'certainty_levels' => $this->lifeEventService->getCertaintyLevels(),
            ],
        ]);
    }

    /**
     * Store a new life event.
     */
    public function store(StoreLifeEventRequest $request): JsonResponse
    {
        $user = $request->user();
        $data = $request->validated();

        try {
            $event = $this->lifeEventService->createEvent($user->id, $data);

            return response()->json([
                'success' => true,
                'message' => 'Life event created successfully.',
                'data' => $event,
            ], 201);
        } catch (\Exception $e) {
            return $this->errorResponse($e, 'Create life event', 500, ['user_id' => $user->id]);
        }
    }

    /**
     * Get a specific life event.
     */
    public function show(Request $request, int $id): JsonResponse
    {
        $user = $request->user();

        $event = LifeEvent::where('id', $id)
            ->where(function ($query) use ($user) {
                $query->where('user_id', $user->id)
                    ->orWhere('joint_owner_id', $user->id);
            })
            ->first();

        if (! $event) {
            return response()->json([
                'success' => false,
                'message' => 'Life event not found.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $event,
        ]);
    }

    /**
     * Update a life event.
     */
    public function update(UpdateLifeEventRequest $request, int $id): JsonResponse
    {
        $user = $request->user();

        $event = LifeEvent::where('id', $id)
            ->where('user_id', $user->id)
            ->first();

        if (! $event) {
            return response()->json([
                'success' => false,
                'message' => 'Life event not found or you do not have permission to update it.',
            ], 404);
        }

        try {
            $data = $request->validated();
            $event = $this->lifeEventService->updateEvent($event, $data);

            return response()->json([
                'success' => true,
                'message' => 'Life event updated successfully.',
                'data' => $event,
            ]);
        } catch (\Exception $e) {
            return $this->errorResponse($e, 'Update life event', 500, ['event_id' => $id]);
        }
    }

    /**
     * Delete a life event.
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        $user = $request->user();

        $event = LifeEvent::where('id', $id)
            ->where('user_id', $user->id)
            ->first();

        if (! $event) {
            return response()->json([
                'success' => false,
                'message' => 'Life event not found or you do not have permission to delete it.',
            ], 404);
        }

        try {
            $this->lifeEventService->deleteEvent($event);

            return response()->json([
                'success' => true,
                'message' => 'Life event deleted successfully.',
            ]);
        } catch (\Exception $e) {
            return $this->errorResponse($e, 'Delete life event', 500, ['event_id' => $id]);
        }
    }

    /**
     * Mark a life event as completed.
     */
    public function markCompleted(Request $request, int $id): JsonResponse
    {
        $user = $request->user();

        $request->validate([
            'occurred_at' => 'nullable|date',
        ]);

        $event = LifeEvent::where('id', $id)
            ->where('user_id', $user->id)
            ->first();

        if (! $event) {
            return response()->json([
                'success' => false,
                'message' => 'Life event not found.',
            ], 404);
        }

        try {
            $occurredAt = $request->input('occurred_at')
                ? \Carbon\Carbon::parse($request->input('occurred_at'))
                : null;

            $event = $this->lifeEventService->markCompleted($event, $occurredAt);

            return response()->json([
                'success' => true,
                'message' => 'Life event marked as completed.',
                'data' => $event,
            ]);
        } catch (\Exception $e) {
            return $this->errorResponse($e, 'Mark life event completed', 500, ['event_id' => $id]);
        }
    }

    /**
     * Get events grouped by age for chart display.
     */
    public function getByAge(Request $request): JsonResponse
    {
        $user = $request->user();
        $includeHousehold = $request->boolean('household', false);

        try {
            $eventsByAge = $this->lifeEventService->getEventsByAge($user->id, $includeHousehold);

            return response()->json([
                'success' => true,
                'data' => $eventsByAge,
            ]);
        } catch (\Exception $e) {
            return $this->errorResponse($e, 'Fetch events by age', 500, ['user_id' => $user->id]);
        }
    }
}
