<?php

declare(strict_types=1);

namespace App\Services\Mobile;

use App\Models\User;
use App\Models\UserMilestone;
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
}
