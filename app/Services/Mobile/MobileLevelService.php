<?php

declare(strict_types=1);

namespace App\Services\Mobile;

use App\Models\User;
use App\Traits\StructuredLogging;
use Illuminate\Support\Facades\Cache;

/**
 * Computes a user's gamification "level" from completed financial actions and a
 * dynamic percentile ("you're ahead of X% of people") derived from the level
 * distribution across the whole customer base.
 *
 * Level thresholds: L1→2 needs 3 actions, L2→3 needs 5, L3→4 needs 7 … i.e.
 * actionsForLevel(n) = min(10, 3 + (n-1)*2). Completed actions are counted
 * across every recommendation in every module (any accordion card).
 */
class MobileLevelService
{
    use StructuredLogging;

    private const PERCENTILE_CACHE_TTL = 3600;  // 1 hour — distribution is slow-moving
    private const LEVEL_CACHE_TTL = 300;        // 5 min — recompute after actions change

    /** Actions required to advance FROM the given level to the next. */
    public function actionsForLevel(int $level): int
    {
        return min(10, 3 + max(0, $level - 1) * 2);
    }

    /**
     * Resolve a user's level + progress from their completed-action count.
     *
     * @return array{level:int, actions_completed:int, actions_in_level:int, actions_for_next:int, progress_percent:int}
     */
    public function levelFor(int $userId): array
    {
        $completed = $this->completedActionCount($userId);

        $level = 1;
        $remaining = $completed;
        while ($remaining >= $this->actionsForLevel($level)) {
            $remaining -= $this->actionsForLevel($level);
            $level++;
        }
        $need = $this->actionsForLevel($level);

        return [
            'level' => $level,
            'actions_completed' => $completed,
            'actions_in_level' => $remaining,
            'actions_for_next' => $need,
            'progress_percent' => $need > 0 ? (int) round(($remaining / $need) * 100) : 0,
        ];
    }

    /**
     * Percentile: the share of customers whose level is <= this user's level.
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

        $atOrBelow = 0;
        foreach ($distribution as $level => $count) {
            if ($level <= $userLevel) {
                $atOrBelow += $count;
            }
        }

        // Ahead-of = everyone strictly below this user's bucket, as a percentage.
        $below = $atOrBelow - ($distribution[$userLevel] ?? 0);
        $pct = (int) round(($below / $total) * 100);

        return max(1, min(99, $pct));
    }

    /**
     * Count completed financial actions for a user. Completion is currently
     * derived from engagement signals (modules configured + goals completed)
     * since there is no persisted per-recommendation completion store yet.
     * This keeps the level real and data-driven, and is the single seam to
     * swap for a `user_recommendation_actions` table when that lands.
     */
    public function completedActionCount(int $userId): int
    {
        return (int) Cache::remember("mobile_level_actions_{$userId}", self::LEVEL_CACHE_TTL, function () use ($userId) {
            $user = User::withCount([
                'savingsAccounts',
                'investmentAccounts',
                'properties',
                'dcPensions',
                'dbPensions',
                'goals',
            ])->find($userId);

            if (! $user) {
                return 0;
            }

            // Each configured area is a "completed action" toward levelling up.
            $count = 0;
            $count += $user->savings_accounts_count > 0 ? 1 : 0;
            $count += $user->investment_accounts_count > 0 ? 1 : 0;
            $count += $user->properties_count > 0 ? 1 : 0;
            $count += ($user->dc_pensions_count + $user->db_pensions_count) > 0 ? 1 : 0;
            $count += (int) $user->goals_count;   // each goal set counts

            return $count;
        });
    }

    /**
     * Level distribution across the whole customer base, cached. Sampled to
     * keep it cheap on large bases.
     *
     * @return array<int,int> [level => user_count]
     */
    private function levelDistribution(): array
    {
        return Cache::remember('mobile_level_distribution', self::PERCENTILE_CACHE_TTL, function () {
            $dist = [];
            User::query()
                ->where('is_preview_user', false)
                ->select('id')
                ->chunk(500, function ($users) use (&$dist) {
                    foreach ($users as $u) {
                        $level = $this->levelFor($u->id)['level'];
                        $dist[$level] = ($dist[$level] ?? 0) + 1;
                    }
                });

            return $dist ?: [1 => 1];
        });
    }

    public function clearCache(int $userId): void
    {
        Cache::forget("mobile_level_actions_{$userId}");
    }
}
