<?php

declare(strict_types=1);

namespace App\Services\Mobile;

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

        // Each action represents a quarter of a level (CSJ): the hero card shows
        // the actions still needed to reach the next level, derived from the
        // level's points-based progress and capped at 4 — always 4 at the start
        // of a level, 3/2/1 at 25/50/75%, never a static number. The actions
        // LIST is unchanged (complete one, it's replaced by the next-best); the
        // points awarded per action move the progress this reflects. The top
        // level (no next level) has nothing left to earn.
        $progressPercent = (int) $progress['progress_percent'];
        $actionsToNext = $progress['next_level_name'] !== null
            ? (int) max(0, min(4, (int) ceil((100 - $progressPercent) / 25)))
            : 0;
        $actionsInLevel = 4 - $actionsToNext;

        return [
            'level' => $progress['level'],
            'level_name' => $progress['level_name'],
            'next_level_name' => $progress['next_level_name'],
            'progress_percent' => $progressPercent,
            // "X of 4 actions to your next level" (Rule #12-approved display):
            // X grows as the user progresses through the level, capped at 4.
            'actions_completed' => $actionsInLevel,
            'actions_total' => 4,
            'actions_to_next' => $actionsToNext,
            // Legacy aliases (pre-Phase-5 /m bundle) on the same per-level basis.
            'actions_in_level' => $actionsInLevel,
            'actions_for_next' => 4,
        ];
    }

    public function clearCache(int $userId): void
    {
        // Distribution caching moved to PlanningProgressService.
    }
}
