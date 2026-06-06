<?php

declare(strict_types=1);

namespace App\Services\Mobile;

use App\Models\RecommendationTracking;
use App\Models\UserGamification;
use App\Services\Gamification\LevelService;
use App\Traits\StructuredLogging;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Thin reader over the gamification engine for the /m dashboard wheel.
 *
 * The user's LEVEL + ring progress come from the points engine
 * (user_gamification.total_points -> LevelService), the single source of truth.
 * The wheel's "X of Y actions complete" heading (a CSJ-approved gamification
 * element — CLAUDE.md Rule #12) is fed from real recommendation completion
 * (completed vs open+completed), NOT from points. The "you're ahead of X% of
 * people" percentile is derived from the points-based level distribution.
 */
class MobileLevelService
{
    use StructuredLogging;

    private const PERCENTILE_CACHE_TTL = 3600;  // 1 hour — distribution is slow-moving

    public function __construct(private readonly LevelService $levels) {}

    /**
     * Resolve a user's level + progress from the points engine, plus the
     * recommendation-completion counts that feed the "X of Y actions complete"
     * heading. Returns a superset of the legacy keys so any consumer keeps working.
     *
     * @return array{level:int, level_name:string, next_level_name:?string,
     *               progress_percent:int, actions_completed:int, actions_total:int,
     *               actions_in_level:int, actions_for_next:int}
     */
    public function levelFor(int $userId): array
    {
        $points = (int) (UserGamification::where('user_id', $userId)->value('total_points') ?? 0);
        $progress = $this->levels->progress($points);

        $completed = RecommendationTracking::where('user_id', $userId)
            ->where('status', 'completed')
            ->count();
        $total = RecommendationTracking::where('user_id', $userId)
            ->whereIn('status', ['pending', 'in_progress', 'completed'])
            ->count();

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

    /**
     * Percentile: the share of customers strictly below this user's level.
     * "You're ahead of X% of people." Bounded 1–99 so it never reads 0% or 100%.
     */
    public function percentile(int $userId): int
    {
        $userLevel = $this->levelFor($userId)['level'];
        $distribution = $this->levelDistribution();   // [level => count]

        $total = array_sum($distribution);
        if ($total <= 1) {
            return 57; // sensible default for an empty/single-user base
        }

        $below = 0;
        foreach ($distribution as $level => $count) {
            if ($level < $userLevel) {
                $below += $count;
            }
        }

        $pct = (int) round(($below / $total) * 100);

        return max(1, min(99, $pct));
    }

    /**
     * Level distribution across the whole (non-preview) customer base, read
     * directly from the points-based user_gamification.level column.
     *
     * @return array<int,int> [level => user_count]
     */
    private function levelDistribution(): array
    {
        return Cache::remember('mobile_level_distribution', self::PERCENTILE_CACHE_TTL, function () {
            $dist = UserGamification::query()
                ->join('users', 'users.id', '=', 'user_gamification.user_id')
                ->where('users.is_preview_user', false)
                ->select('user_gamification.level', DB::raw('COUNT(*) as c'))
                ->groupBy('user_gamification.level')
                ->pluck('c', 'level')
                ->map(fn ($c) => (int) $c)
                ->toArray();

            return $dist ?: [1 => 1];
        });
    }

    public function clearCache(int $userId): void
    {
        Cache::forget('mobile_level_distribution');
    }
}
