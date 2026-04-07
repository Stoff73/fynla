<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Traits\SanitizedErrorResponse;
use App\Models\RecommendationTracking;
use App\Services\Coordination\RecommendationsAggregatorService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RecommendationsController extends Controller
{
    use SanitizedErrorResponse;

    public function __construct(
        private readonly RecommendationsAggregatorService $aggregatorService
    ) {}

    /**
     * Get all recommendations for the authenticated user.
     *
     * GET /api/recommendations
     */
    public function index(Request $request): JsonResponse
    {
        $userId = $request->user()->id;

        // Validate query parameters
        $request->validate([
            'module' => 'sometimes|string|in:protection,savings,investment,retirement,estate,property',
            'priority' => 'sometimes|string|in:high,medium,low',
            'timeline' => 'sometimes|string|in:immediate,short_term,medium_term,long_term',
            'status' => 'sometimes|string|in:pending,in_progress,completed,dismissed',
            'limit' => 'sometimes|integer|min:1|max:100',
        ]);

        try {
            $recommendations = $this->aggregatorService->aggregateRecommendations($userId);

            // Apply filters
            if ($request->has('module')) {
                $recommendations = array_filter($recommendations, fn ($rec) => $rec['module'] === $request->module);
            }

            if ($request->has('priority')) {
                $recommendations = array_filter($recommendations, fn ($rec) => $rec['impact'] === $request->priority);
            }

            if ($request->has('timeline')) {
                $recommendations = array_filter($recommendations, fn ($rec) => $rec['timeline'] === $request->timeline);
            }

            if ($request->has('status')) {
                $recommendations = array_filter($recommendations, fn ($rec) => $rec['status'] === $request->status);
            }

            // Apply limit
            if ($request->has('limit')) {
                $recommendations = array_slice($recommendations, 0, (int) $request->limit);
            }

            // Re-index array after filtering
            $recommendations = array_values($recommendations);

            return response()->json([
                'success' => true,
                'data' => $recommendations,
                'count' => count($recommendations),
            ]);
        } catch (\Exception $e) {
            return $this->errorResponse($e, 'Fetching recommendations');
        }
    }

    /**
     * Get recommendations summary with counts.
     *
     * GET /api/recommendations/summary
     */
    public function summary(Request $request): JsonResponse
    {
        $userId = $request->user()->id;

        try {
            $summary = $this->aggregatorService->getSummary($userId);

            return response()->json([
                'success' => true,
                'data' => $summary,
            ]);
        } catch (\Exception $e) {
            return $this->errorResponse($e, 'Fetching recommendations summary');
        }
    }

    /**
     * Get top N recommendations.
     *
     * GET /api/recommendations/top
     */
    public function top(Request $request): JsonResponse
    {
        $userId = $request->user()->id;

        $request->validate([
            'limit' => 'sometimes|integer|min:1|max:20',
        ]);

        try {
            $limit = (int) $request->input('limit', 5);
            $recommendations = $this->aggregatorService->getTopRecommendations($userId, $limit);

            return response()->json([
                'success' => true,
                'data' => $recommendations,
                'count' => count($recommendations),
            ]);
        } catch (\Exception $e) {
            return $this->errorResponse($e, 'Fetching top recommendations');
        }
    }

    /**
     * Mark recommendation as done.
     *
     * POST /api/recommendations/{id}/mark-done
     */
    public function markDone(Request $request, string $recommendationId): JsonResponse
    {
        $userId = $request->user()->id;

        try {
            $tracking = RecommendationTracking::where('user_id', $userId)
                ->where('recommendation_id', $recommendationId)
                ->first();

            if (! $tracking) {
                // Create new tracking record
                $tracking = RecommendationTracking::create([
                    'user_id' => $userId,
                    'recommendation_id' => $recommendationId,
                    'module' => $request->input('module', 'general'),
                    'recommendation_text' => $request->input('recommendation_text', ''),
                    'priority_score' => $request->input('priority_score', 50.0),
                    'timeline' => $request->input('timeline', 'medium_term'),
                    'status' => 'completed',
                    'completed_at' => now(),
                ]);
            } else {
                $tracking->markAsCompleted();
            }

            return response()->json([
                'success' => true,
                'message' => 'Recommendation marked as completed',
                'data' => $tracking,
            ]);
        } catch (\Exception $e) {
            return $this->errorResponse($e, 'Marking recommendation as done');
        }
    }

    /**
     * Mark recommendation as in progress.
     *
     * POST /api/recommendations/{id}/in-progress
     */
    public function markInProgress(Request $request, string $recommendationId): JsonResponse
    {
        $userId = $request->user()->id;

        try {
            $tracking = RecommendationTracking::where('user_id', $userId)
                ->where('recommendation_id', $recommendationId)
                ->first();

            if (! $tracking) {
                // Create new tracking record
                $tracking = RecommendationTracking::create([
                    'user_id' => $userId,
                    'recommendation_id' => $recommendationId,
                    'module' => $request->input('module', 'general'),
                    'recommendation_text' => $request->input('recommendation_text', ''),
                    'priority_score' => $request->input('priority_score', 50.0),
                    'timeline' => $request->input('timeline', 'medium_term'),
                    'status' => 'in_progress',
                ]);
            } else {
                $tracking->markAsInProgress();
            }

            return response()->json([
                'success' => true,
                'message' => 'Recommendation marked as in progress',
                'data' => $tracking,
            ]);
        } catch (\Exception $e) {
            return $this->errorResponse($e, 'Marking recommendation as in progress');
        }
    }

    /**
     * Dismiss recommendation.
     *
     * POST /api/recommendations/{id}/dismiss
     */
    public function dismiss(Request $request, string $recommendationId): JsonResponse
    {
        $userId = $request->user()->id;

        try {
            $tracking = RecommendationTracking::where('user_id', $userId)
                ->where('recommendation_id', $recommendationId)
                ->first();

            if (! $tracking) {
                // Create new tracking record
                $tracking = RecommendationTracking::create([
                    'user_id' => $userId,
                    'recommendation_id' => $recommendationId,
                    'module' => $request->input('module', 'general'),
                    'recommendation_text' => $request->input('recommendation_text', ''),
                    'priority_score' => $request->input('priority_score', 50.0),
                    'timeline' => $request->input('timeline', 'medium_term'),
                    'status' => 'dismissed',
                ]);
            } else {
                $tracking->dismiss();
            }

            return response()->json([
                'success' => true,
                'message' => 'Recommendation dismissed',
                'data' => $tracking,
            ]);
        } catch (\Exception $e) {
            return $this->errorResponse($e, 'Dismissing recommendation');
        }
    }

    /**
     * Update recommendation notes.
     *
     * PATCH /api/recommendations/{id}/notes
     */
    public function updateNotes(Request $request, string $recommendationId): JsonResponse
    {
        $userId = $request->user()->id;

        $validated = $request->validate([
            'notes' => 'required|string|max:1000',
        ]);

        try {
            $tracking = RecommendationTracking::where('user_id', $userId)
                ->where('recommendation_id', $recommendationId)
                ->firstOrFail();

            $tracking->update([
                'notes' => $validated['notes'],
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Notes updated successfully',
                'data' => $tracking,
            ]);
        } catch (\Exception $e) {
            return $this->errorResponse($e, 'Updating recommendation notes');
        }
    }

    /**
     * Get completed recommendations.
     *
     * GET /api/recommendations/completed
     */
    public function completed(Request $request): JsonResponse
    {
        $userId = $request->user()->id;

        try {
            $completed = RecommendationTracking::where('user_id', $userId)
                ->completed()
                ->orderBy('completed_at', 'desc')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $completed,
                'count' => $completed->count(),
            ]);
        } catch (\Exception $e) {
            return $this->errorResponse($e, 'Fetching completed recommendations');
        }
    }
}
