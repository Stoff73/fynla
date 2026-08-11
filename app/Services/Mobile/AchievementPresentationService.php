<?php

declare(strict_types=1);

namespace App\Services\Mobile;

use App\Models\PointAward;
use App\Models\User;
use App\Models\UserGamification;
use App\Models\UserMilestone;
use App\Services\Gamification\LevelService;

/**
 * Server-owned, additive presentation contract for achievement records.
 */
class AchievementPresentationService
{
    public function __construct(
        private readonly LevelService $levels,
    ) {}

    /**
     * @return array<int,array<string,mixed>>
     */
    public function badges(User $user): array
    {
        $gamification = UserGamification::query()
            ->where('user_id', $user->id)
            ->first(['level', 'updated_at']);
        $level = $gamification?->level ?? 1;

        $dataBadges = [
            'protection_policy' => 'protection',
            'savings_account' => 'savings',
            'investment_account' => 'investment',
            'pension' => 'retirement',
            'estate' => 'estate',
            'goal' => 'goals',
        ];
        $dataAwards = PointAward::query()
            ->where('user_id', $user->id)
            ->whereIn('dedup_key', array_map(
                static fn (string $category): string => "data:{$category}:first",
                array_keys($dataBadges),
            ))
            ->get(['id', 'dedup_key', 'source_type', 'created_at'])
            ->keyBy('dedup_key');
        $firstRecommendationAward = PointAward::query()
            ->where('user_id', $user->id)
            ->where('source_type', 'recommendation')
            ->oldest('id')
            ->first(['id', 'dedup_key', 'source_type', 'created_at']);
        $firstStreakAward = PointAward::query()
            ->where('user_id', $user->id)
            ->where('dedup_key', 'like', 'streak:%')
            ->oldest('id')
            ->first(['id', 'dedup_key', 'source_type', 'created_at']);

        $levelCrossingAward = $level > 1 ? $this->levelCrossingAward($user, $level) : null;

        $badges = [
            $this->badge(
                key: 'level',
                title: 'Reached '.$this->levels->levelName($level),
                description: 'Your current planning level.',
                earned: $level > 1,
                provenance: $this->awardProvenance($levelCrossingAward),
            ),
        ];

        foreach ($dataBadges as $category => $label) {
            $award = $dataAwards->get("data:{$category}:first");
            $badges[] = $this->badge(
                key: 'data_'.$category,
                title: 'Added '.$label.' details',
                description: 'You started building your '.$label.' picture.',
                earned: $award !== null,
                provenance: $this->awardProvenance($award),
            );
        }

        $badges[] = $this->badge(
            key: 'recs_actioned',
            title: 'First action completed',
            description: 'You completed your first recommended action.',
            earned: $firstRecommendationAward !== null,
            provenance: $this->awardProvenance($firstRecommendationAward),
        );
        $badges[] = $this->badge(
            key: 'streak',
            title: '3-day check-in streak',
            description: 'Check in three days in a row.',
            earned: $firstStreakAward !== null,
            provenance: $this->awardProvenance($firstStreakAward),
        );

        return $badges;
    }

    /**
     * @return array<string,mixed>
     */
    public function milestone(UserMilestone $milestone, string $title): array
    {
        $event = $milestone->milestone_type.':'.($milestone->reference_id ?? 0).':'.(int) $milestone->threshold;

        return [
            'key' => $event,
            'title' => $title,
            // Legacy fields remain for mobile clients that have not adopted
            // the canonical state enum yet.
            'achieved' => true,
            'achieved_at' => $milestone->achieved_at?->toIso8601String(),
            'state' => 'earned',
            'provenance' => [
                'kind' => 'user_milestone',
                'event' => $event,
                'occurred_at' => $milestone->achieved_at?->toIso8601String(),
            ],
            'progress' => null,
            'next_action' => null,
        ];
    }

    /**
     * @param  array{kind:string,event:string,occurred_at:?string}|null  $provenance
     * @return array<string,mixed>
     */
    private function badge(string $key, string $title, string $description, bool $earned, ?array $provenance): array
    {
        return [
            'key' => $key,
            'title' => $title,
            'description' => $description,
            // Keep these legacy fields while clients migrate to state.
            'earned' => $earned,
            'earned_at' => $provenance['occurred_at'] ?? null,
            'state' => $earned ? 'earned' : 'locked',
            'provenance' => $provenance,
            'progress' => null,
            'next_action' => null,
        ];
    }

    /** @return array{kind:string,event:string,occurred_at:?string}|null */
    private function awardProvenance(?PointAward $award): ?array
    {
        if ($award === null) {
            return null;
        }

        return [
            'kind' => 'point_award',
            'event' => $award->dedup_key,
            'occurred_at' => $award->created_at?->toIso8601String(),
        ];
    }

    /**
     * Finds the immutable ledger event whose cumulative points first reached
     * the current level's configured threshold. The database calculates the
     * running total and returns at most one presentation row.
     */
    private function levelCrossingAward(User $user, int $level): ?PointAward
    {
        $threshold = (int) (config('gamification.levels')[$level]['min_points'] ?? 0);
        if ($threshold <= 0) {
            return null;
        }

        $ledger = PointAward::query()
            ->select(['id', 'dedup_key', 'source_type', 'created_at'])
            ->selectRaw('SUM(points) OVER (ORDER BY id) AS running_points')
            ->where('user_id', $user->id);

        return PointAward::query()
            ->fromSub($ledger, 'level_ledger')
            ->where('running_points', '>=', $threshold)
            ->orderBy('id')
            ->first(['id', 'dedup_key', 'source_type', 'created_at']);
    }
}
