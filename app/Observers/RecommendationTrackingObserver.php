<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\RecommendationTracking;
use App\Services\Gamification\PointsService;

class RecommendationTrackingObserver
{
    public function __construct(private readonly PointsService $points) {}

    public function saved(RecommendationTracking $tracking): void
    {
        if ($tracking->status !== 'completed') {
            return;
        }
        $user = $tracking->user;
        if (! $user) {
            return;
        }

        // Dedup keyed by the business recommendation id -> awards exactly once.
        $this->points->award(
            $user,
            'recommendation',
            "recommendation:{$tracking->recommendation_id}",
            (int) config('gamification.points.recommendation'),
            ['module' => $tracking->module],
        );
    }
}
