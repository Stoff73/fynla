<?php

declare(strict_types=1);

namespace App\Services\Mobile;

use App\Models\RecommendationTracking;
use App\Models\User;
use App\Models\UserMilestone;
use App\Services\Gamification\PointsService;
use Illuminate\Support\Carbon;

/**
 * Detects financial milestones a user has newly crossed and records them once.
 *
 * "Newly crossed" = a threshold at or below the current value that has not yet
 * been recorded for this user. Each milestone is persisted (unique per
 * user/type/reference/threshold) so the celebratory share prompt fires a single
 * time, even though detection runs on every dashboard/goals read.
 *
 * Share content itself comes from ShareContentGenerator (generic, no monetary
 * values — Rule #12); these methods only return what to celebrate + which share
 * type to use.
 */
class MilestoneDetectionService
{
    /** Net-worth thresholds in GBP. */
    private const NET_WORTH_THRESHOLDS = [
        10000, 25000, 50000, 100000, 250000, 500000, 750000, 1000000, 2000000, 5000000,
    ];

    /** Goal-progress thresholds in percent. */
    private const GOAL_THRESHOLDS = [25, 50, 75, 100];

    public function __construct(
        private readonly PointsService $points,
    ) {}

    /**
     * Detect net-worth milestones newly crossed at the given total.
     *
     * @return array<int,array<string,mixed>> Newly-crossed milestones (may be empty)
     */
    public function detectNetWorth(User $user, float $netWorth): array
    {
        $new = [];

        foreach (self::NET_WORTH_THRESHOLDS as $threshold) {
            if ($netWorth < $threshold) {
                break; // ascending — nothing higher can be crossed
            }

            $record = UserMilestone::firstOrCreate(
                [
                    'user_id' => $user->id,
                    'milestone_type' => 'net_worth',
                    'reference_id' => null,
                    'threshold' => $threshold,
                ],
                ['achieved_at' => Carbon::now()],
            );

            if ($record->wasRecentlyCreated) {
                $this->points->award(
                    $user,
                    'milestone',
                    "milestone:net_worth:0:{$threshold}",
                    (int) config('gamification.points.milestone'),
                    ['threshold' => $threshold],
                );

                $new[] = [
                    'type' => 'net_worth',
                    'threshold' => $threshold,
                    'label' => 'Your net worth has passed £'.number_format($threshold).'.',
                    'share_type' => 'net_worth_milestone',
                ];
            }
        }

        return $new;
    }

    /**
     * Detect goal-progress milestones newly crossed for one goal.
     *
     * @return array<int,array<string,mixed>> Newly-crossed milestones (may be empty)
     */
    public function detectGoal(User $user, int $goalId, float $progressPercent, string $goalName): array
    {
        $new = [];

        foreach (self::GOAL_THRESHOLDS as $threshold) {
            if ($progressPercent < $threshold) {
                break;
            }

            $record = UserMilestone::firstOrCreate(
                [
                    'user_id' => $user->id,
                    'milestone_type' => 'goal',
                    'reference_id' => $goalId,
                    'threshold' => $threshold,
                ],
                ['achieved_at' => Carbon::now()],
            );

            if ($record->wasRecentlyCreated) {
                $this->points->award(
                    $user,
                    'milestone',
                    "milestone:goal:{$goalId}:{$threshold}",
                    (int) config('gamification.points.milestone'),
                    ['goal_id' => $goalId, 'threshold' => $threshold],
                );

                $label = $threshold >= 100
                    ? sprintf('You\'ve reached your goal: %s.', $goalName)
                    : sprintf('You\'re %d%% of the way to %s.', $threshold, $goalName);

                $new[] = [
                    'type' => 'goal',
                    'reference_id' => $goalId,
                    'threshold' => $threshold,
                    'label' => $label,
                    'share_type' => 'goal_milestone',
                ];
            }
        }

        return $new;
    }

    /**
     * Detect milestones across a collection of goals.
     *
     * @param  iterable<int,array<string,mixed>>  $goals  Each with id, name|goal_name, progress_percentage
     * @return array<int,array<string,mixed>>
     */
    public function detectGoals(User $user, iterable $goals): array
    {
        $new = [];

        foreach ($goals as $goal) {
            $id = (int) ($goal['id'] ?? 0);
            if ($id === 0) {
                continue;
            }
            $progress = (float) ($goal['progress_percentage'] ?? 0);
            $name = (string) ($goal['name'] ?? $goal['goal_name'] ?? 'your goal');
            $new = array_merge($new, $this->detectGoal($user, $id, $progress, $name));
        }

        return $new;
    }

    /**
     * WP-5 — journey milestones: completing the onboarding profile and
     * completing a first action. Cheap queries, safe to run on every
     * dashboard/progress-page read — which also makes the milestones page
     * self-healing (detection previously ran only on the /m dashboard read,
     * so a user who never opened it earned nothing).
     *
     * @return array<int,array<string,mixed>>
     */
    public function detectJourney(User $user): array
    {
        $new = [];

        if ((bool) $user->onboarding_completed && ! empty($user->funnel_answers)) {
            $new = array_merge($new, $this->recordOnce(
                $user,
                'campaign',
                null,
                1,
                'You completed your tax profile.',
            ));
        }

        $hasCompletedAction = RecommendationTracking::where('user_id', $user->id)
            ->completed()
            ->exists();
        if ($hasCompletedAction) {
            $new = array_merge($new, $this->recordOnce(
                $user,
                'action',
                null,
                1,
                'You completed your first action.',
            ));
        }

        return $new;
    }

    /**
     * WP-5 — the first time the composed tax plan quantifies an annual
     * saving. Called from the tax-strategy read, where the plan is already
     * computed (composing it again here would double the cost). The
     * threshold stores the rounded amount so the milestone title can quote
     * it.
     *
     * @return array<int,array<string,mixed>>
     */
    public function detectTaxSavingsIdentified(User $user, float $combinedAnnualSaving): array
    {
        if ($combinedAnnualSaving <= 0) {
            return [];
        }

        // One per user — the FIRST quantified find is the milestone; the
        // figure evolving later is normal plan drift, not a new milestone.
        if (UserMilestone::where('user_id', $user->id)->where('milestone_type', 'tax_savings')->exists()) {
            return [];
        }

        $amount = (int) round($combinedAnnualSaving);

        return $this->recordOnce(
            $user,
            'tax_savings',
            null,
            $amount,
            'We found £'.number_format($amount).' a year you could save in tax.',
        );
    }

    /**
     * firstOrCreate + award plumbing shared by the journey milestone
     * flavours.
     *
     * @return array<int,array<string,mixed>>
     */
    private function recordOnce(User $user, string $type, ?int $referenceId, int|float $threshold, string $label): array
    {
        $record = UserMilestone::firstOrCreate(
            [
                'user_id' => $user->id,
                'milestone_type' => $type,
                'reference_id' => $referenceId,
                'threshold' => $threshold,
            ],
            ['achieved_at' => Carbon::now()],
        );

        if (! $record->wasRecentlyCreated) {
            return [];
        }

        $this->points->award(
            $user,
            'milestone',
            'milestone:'.$type.':'.($referenceId ?? 0).':'.$threshold,
            (int) config('gamification.points.milestone'),
            ['threshold' => $threshold],
        );

        return [[
            'type' => $type,
            'threshold' => $threshold,
            'label' => $label,
            'share_type' => 'app_referral',
        ]];
    }
}
