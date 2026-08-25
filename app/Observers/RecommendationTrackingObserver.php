<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\RecommendationTracking;
use App\Services\Gamification\PointsService;
use App\Services\Mobile\MilestoneDetectionService;

class RecommendationTrackingObserver
{
    public function __construct(
        private readonly PointsService $points,
        private readonly MilestoneDetectionService $milestones,
    ) {}

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

        // WP-5c — event-minted milestones: named strategy firsts + estate
        // start. Never let milestone minting break the save path.
        try {
            $this->milestones->detectStrategyFirst($user, (string) $tracking->recommendation_id);
            if ($tracking->module === 'estate') {
                $this->milestones->detectEstateActionCompleted($user);
            }
        } catch (\Throwable) {
            // best-effort
        }
    }
}
