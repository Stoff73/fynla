<?php

declare(strict_types=1);

namespace App\Services\Coordination;

use App\Models\RecommendationTracking;
use App\Models\User;

/**
 * The one way a dashboard recommendation is marked done — from the mark-done
 * endpoint (web, /m, native) and from Fyn when a recommendation-driven
 * capture finishes. Creating the tracking row on first completion is what
 * lets the gamification observer award the points and the dashboard replace
 * the item with the next-best.
 */
final class RecommendationCompletionService
{
    public function complete(
        User $user,
        string $recommendationId,
        string $module = 'general',
        string $recommendationText = '',
        float $priorityScore = 50.0,
        string $timeline = 'medium_term',
    ): RecommendationTracking {
        $tracking = RecommendationTracking::where('user_id', $user->id)
            ->where('recommendation_id', $recommendationId)
            ->first();

        if ($tracking === null) {
            return RecommendationTracking::create([
                'user_id' => $user->id,
                'recommendation_id' => $recommendationId,
                'module' => $module,
                'recommendation_text' => $recommendationText,
                'priority_score' => $priorityScore,
                'timeline' => $timeline,
                'status' => 'completed',
                'completed_at' => now(),
            ]);
        }

        $tracking->markAsCompleted();

        return $tracking;
    }
}
