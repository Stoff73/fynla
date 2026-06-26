<?php

declare(strict_types=1);

namespace App\Services\Mobile;

use App\Models\RecommendationTracking;
use App\Models\UserGamification;
use App\Services\Gamification\LevelService;
use App\Traits\StructuredLogging;

/**
 * Thin reader over the gamification engine for the /m dashboard wheel.
 *
 * The user's LEVEL + ring progress come from the points engine
 * (user_gamification.total_points -> LevelService), the single source of truth.
 * The wheel's "X of Y actions complete" heading (a CSJ-approved gamification
 * element — CLAUDE.md Rule #12) is derived from the unified next-actions list
 * passed in by the dashboard controller (the SAME <=4 list that drives the
 * recommendations below the wheel). The "you're ahead of X% of people"
 * percentile now lives in PlanningProgressService.
 */
class MobileLevelService
{
    use StructuredLogging;

    public function __construct(private readonly LevelService $levels) {}

    /**
     * Resolve a user's level + progress from the points engine, plus the
     * "X of Y actions complete" counts derived from the passed next-actions
     * list. Returns a superset of the legacy keys so any consumer keeps working.
     *
     * @param  array<int,array<string,mixed>>  $nextActions  the unified <=4 list
     * @return array{level:int, level_name:string, next_level_name:?string,
     *               progress_percent:int, actions_completed:int, actions_total:int,
     *               actions_in_level:int, actions_for_next:int}
     */
    public function levelFor(int $userId, array $nextActions = []): array
    {
        $points = (int) (UserGamification::where('user_id', $userId)->value('total_points') ?? 0);
        $progress = $this->levels->progress($points);

        // "X of Y actions complete" (Rule #12 display) as a running tally:
        // X = every action the user has banked (completed recommendations,
        // tracked), Y = those plus the open actions currently shown. Completed
        // actions are replaced in the list by the next-best (NextActionsService),
        // so they're counted here rather than shown ticked (CSJ 4.1/4.4).
        $completed = (int) RecommendationTracking::where('user_id', $userId)
            ->where('status', 'completed')
            ->count();
        $total = $completed + count($nextActions);

        return [
            'level' => $progress['level'],
            'level_name' => $progress['level_name'],
            'next_level_name' => $progress['next_level_name'],
            'progress_percent' => $progress['progress_percent'],
            // "X of Y actions complete" heading (Rule #12-approved display).
            'actions_completed' => $completed,
            'actions_total' => $total,
            // Legacy aliases so the pre-Phase-5 /m bundle never reads an undefined key.
            'actions_in_level' => $completed,
            'actions_for_next' => $total,
        ];
    }

    public function clearCache(int $userId): void
    {
        // Distribution caching moved to PlanningProgressService.
    }
}
