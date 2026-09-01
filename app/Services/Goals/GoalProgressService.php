<?php

declare(strict_types=1);

namespace App\Services\Goals;

use App\Models\Goal;
use App\Models\GoalContribution;

/**
 * Service for calculating goal progress, streaks, and milestones.
 */
class GoalProgressService
{
    private const MILESTONES = [25, 50, 75, 100];

    public function __construct(
        private readonly GoalCalculationService $calculationService
    ) {}

    /**
     * Calculate detailed progress for a goal.
     *
     * `is_on_track` used to be decided here, a second time, by its own rule
     * (`$currentAmount > 0 && $progressDelta >= -10`) over its own span — so
     * the same goal could be on track on a card and behind in a plan, and the
     * overdue blindness in `GoalCalculationService` had to be fixed twice to be
     * fixed at all. There is one home for that judgement now, and this asks it
     * (Rule 20, W-0411).
     *
     * The spans below are SIGNED. They were absolute, which on Carbon 2 turns a
     * goal recorded against a past date — its `start_date` stamped with today,
     * so the range runs backwards — into its own mirror image: three weeks gone
     * read as three weeks of runway, and elapsed time read as almost none.
     */
    public function calculateProgress(Goal $goal): array
    {
        $targetAmount = (float) $goal->target_amount;
        $currentAmount = (float) $goal->current_amount;
        $progressPercentage = $targetAmount > 0 ? ($currentAmount / $targetAmount) * 100 : 0;

        $startDate = $goal->start_date ?? $goal->created_at;
        $targetDate = $goal->target_date;
        $isOverdue = $this->calculationService->isOverdue($goal);

        $totalDays = $startDate && $targetDate ? max(0, $startDate->diffInDays($targetDate, false)) : 0;
        $daysElapsed = $startDate ? max(0, $startDate->diffInDays(now(), false)) : 0;
        $daysRemaining = $targetDate ? max(0, now()->startOfDay()->diffInDays($targetDate, false)) : 0;

        // Past its date, the whole of the period has been spent, whatever the
        // stored span says about it. Leaving this at 0 — which is what an
        // inverted range produced — made an overdue goal read as ahead.
        $timeProgress = $isOverdue
            ? 100.0
            : ($totalDays > 0 ? min(($daysElapsed / $totalDays) * 100, 100) : 0);

        $expectedProgress = $timeProgress;
        $progressDelta = $progressPercentage - $expectedProgress;

        return [
            'current_amount' => round($currentAmount, 2),
            'target_amount' => round($targetAmount, 2),
            'amount_remaining' => round(max(0, $targetAmount - $currentAmount), 2),
            'progress_percentage' => round(min($progressPercentage, 100), 2),
            'expected_progress' => round($expectedProgress, 2),
            'progress_delta' => round($progressDelta, 2),
            'is_on_track' => $this->calculationService->calculateIsOnTrack($goal),
            'is_overdue' => $isOverdue,
            'status_label' => $this->calculationService->calculateStatusLabel($goal),
            'status' => $this->getProgressStatus($progressDelta),
            'days_elapsed' => $daysElapsed,
            'days_remaining' => $daysRemaining,
            // W-0414. This array returned days and never months, while
            // `GoalPlanService` read `months_remaining` from it at two sites — so one
            // was always null and the other always fell back to a plausible, unvarying
            // **12 months**. A goal nine years out and a goal three months out were
            // planned on the same horizon, and nothing said so: the silent-absence
            // family in `tests/CLAUDE.md` §4, where a missing key reads as null and a
            // `??` default supplies a number that never moves.
            //
            // Derived from the same `GoalCalculationService` that backs the
            // `Goal::months_remaining` accessor, so there is one derivation reachable
            // two ways rather than a second implementation (Rule 20).
            'months_remaining' => $this->calculationService->calculateMonthsRemaining($goal),
            'total_days' => $totalDays,
            'time_progress_percentage' => round($timeProgress, 2),
        ];
    }

    /**
     * Get progress status label.
     */
    private function getProgressStatus(float $delta): string
    {
        if ($delta >= 10) {
            return 'ahead';
        }
        if ($delta >= -5) {
            return 'on_track';
        }
        if ($delta >= -15) {
            return 'slightly_behind';
        }

        return 'behind';
    }

    /**
     * Record a contribution and update streak.
     */
    public function recordContribution(
        Goal $goal,
        float $amount,
        string $type = 'manual',
        ?string $notes = null
    ): GoalContribution {
        $newBalance = (float) $goal->current_amount + $amount;

        $contribution = GoalContribution::create([
            'goal_id' => $goal->id,
            'user_id' => $goal->user_id,
            'amount' => $amount,
            'contribution_date' => now()->toDateString(),
            'contribution_type' => $type,
            'notes' => $notes,
            'goal_balance_after' => $newBalance,
            'streak_qualifying' => $this->isStreakQualifying($goal, $amount),
        ]);

        // Update goal
        $goal->current_amount = $newBalance;
        $goal->last_contribution_date = now()->toDateString();

        // Update streak if qualifying
        if ($contribution->streak_qualifying) {
            $this->updateContributionStreak($goal);
        }

        // Save once after all updates, then check milestones
        $goal->save();
        $this->checkMilestones($goal->fresh());

        return $contribution;
    }

    /**
     * Check if contribution qualifies for streak.
     */
    private function isStreakQualifying(Goal $goal, float $amount): bool
    {
        $expectedContribution = $goal->monthly_contribution ?? 0;

        // Must be at least 80% of expected contribution
        return $expectedContribution <= 0 || $amount >= ($expectedContribution * 0.8);
    }

    /**
     * Update the contribution streak for a goal.
     */
    public function updateContributionStreak(Goal $goal): void
    {
        $lastContributionDate = $goal->last_contribution_date;
        $frequency = $goal->contribution_frequency ?? 'monthly';

        $expectedInterval = match ($frequency) {
            'weekly' => 10, // Allow 3 extra days grace
            'monthly' => 35, // Allow 5 extra days grace
            'quarterly' => 95, // Allow 5 extra days grace
            'annually' => 370, // Allow 5 extra days grace
            default => 35,
        };

        if ($lastContributionDate === null) {
            // First contribution
            $goal->contribution_streak = 1;
        } elseif ($lastContributionDate->diffInDays(now()) <= $expectedInterval) {
            // Within expected interval - increment streak
            $goal->contribution_streak++;
        } else {
            // Streak broken - reset to 1
            $goal->contribution_streak = 1;
        }

        // Update longest streak if current is higher
        if ($goal->contribution_streak > $goal->longest_streak) {
            $goal->longest_streak = $goal->contribution_streak;
        }
    }

    /**
     * Check and update milestones for a goal.
     */
    public function checkMilestones(Goal $goal): array
    {
        $progressPercentage = $goal->progress_percentage;
        $milestones = $goal->milestones ?? [];
        $newlyReached = [];

        foreach (self::MILESTONES as $milestone) {
            $milestoneKey = "milestone_{$milestone}";

            if ($progressPercentage >= $milestone && ! isset($milestones[$milestoneKey])) {
                $milestones[$milestoneKey] = [
                    'reached' => true,
                    'reached_at' => now()->toIso8601String(),
                    'amount_at_milestone' => $goal->current_amount,
                ];
                $newlyReached[] = $milestone;
            }
        }

        if (! empty($newlyReached)) {
            $goal->milestones = $milestones;
            $goal->save();
        }

        return [
            'milestones' => $milestones,
            'newly_reached' => $newlyReached,
            'current_milestone' => $goal->current_milestone,
            'next_milestone' => $goal->next_milestone,
            'progress_to_next' => $this->calculateProgressToNextMilestone($goal),
        ];
    }

    /**
     * Calculate progress percentage towards next milestone.
     */
    private function calculateProgressToNextMilestone(Goal $goal): ?float
    {
        $currentProgress = $goal->progress_percentage;
        $nextMilestone = $goal->next_milestone;

        if ($nextMilestone === null) {
            return null;
        }

        $currentMilestone = $goal->current_milestone ?? 0;
        $milestoneRange = $nextMilestone - $currentMilestone;

        if ($milestoneRange <= 0) {
            return 100;
        }

        $progressInRange = $currentProgress - $currentMilestone;

        return round(($progressInRange / $milestoneRange) * 100, 2);
    }

    /**
     * Get contribution history for a goal.
     */
    public function getContributionHistory(Goal $goal, int $limit = 12): array
    {
        $contributions = $goal->contributions()
            ->orderBy('contribution_date', 'desc')
            ->limit($limit)
            ->get();

        return $contributions->map(fn ($c) => [
            'id' => $c->id,
            'amount' => round((float) $c->amount, 2),
            'date' => $c->contribution_date->format('Y-m-d'),
            'type' => $c->contribution_type,
            'notes' => $c->notes,
            'balance_after' => round((float) $c->goal_balance_after, 2),
            'streak_qualifying' => $c->streak_qualifying,
        ])->toArray();
    }

    /**
     * Get monthly contribution summary for a goal.
     */
    public function getMonthlySummary(Goal $goal, int $months = 12): array
    {
        $startDate = now()->subMonths($months - 1)->startOfMonth();
        $endDate = now()->endOfMonth();

        // Single query for the full date range
        $contributions = $goal->contributions()
            ->whereBetween('contribution_date', [$startDate, $endDate])
            ->get()
            ->groupBy(fn ($c) => $c->contribution_date->format('Y-m'));

        $summary = [];
        $monthlyTarget = $goal->monthly_contribution ?? 0;

        for ($i = 0; $i < $months; $i++) {
            $monthStart = $startDate->copy()->addMonths($i);
            $monthKey = $monthStart->format('Y-m');
            $monthContributions = $contributions->get($monthKey, collect());

            $summary[] = [
                'month' => $monthKey,
                'month_label' => $monthStart->format('M Y'),
                'total_contributed' => round($monthContributions->sum('amount'), 2),
                'contribution_count' => $monthContributions->count(),
                'met_target' => $monthContributions->sum('amount') >= $monthlyTarget,
            ];
        }

        return $summary;
    }

    /**
     * Complete a goal.
     */
    public function completeGoal(Goal $goal, ?string $notes = null): Goal
    {
        $goal->status = 'completed';
        $goal->completed_at = now();
        $goal->completion_notes = $notes;

        // Ensure 100% milestone is recorded
        $milestones = $goal->milestones ?? [];
        if (! isset($milestones['milestone_100'])) {
            $milestones['milestone_100'] = [
                'reached' => true,
                'reached_at' => now()->toIso8601String(),
                'amount_at_milestone' => $goal->current_amount,
            ];
            $goal->milestones = $milestones;
        }

        $goal->save();

        return $goal;
    }

    /**
     * Get streak display data.
     */
    public function getStreakDisplay(Goal $goal): array
    {
        $streak = $goal->contribution_streak ?? 0;
        $longestStreak = $goal->longest_streak ?? 0;

        return [
            'current_streak' => $streak,
            'longest_streak' => $longestStreak,
            'is_best_streak' => $streak > 0 && $streak >= $longestStreak,
            'streak_label' => $this->getStreakLabel($streak, $goal->contribution_frequency ?? 'monthly'),
            'intensity' => $this->getStreakIntensity($streak),
        ];
    }

    /**
     * Get human-readable streak label.
     */
    private function getStreakLabel(int $streak, string $frequency): string
    {
        if ($streak === 0) {
            return 'No streak';
        }

        $unit = match ($frequency) {
            'weekly' => $streak === 1 ? 'week' : 'weeks',
            'quarterly' => $streak === 1 ? 'quarter' : 'quarters',
            'annually' => $streak === 1 ? 'year' : 'years',
            default => $streak === 1 ? 'month' : 'months',
        };

        return "{$streak} {$unit}";
    }

    /**
     * Get streak intensity level for visual display.
     */
    private function getStreakIntensity(int $streak): string
    {
        if ($streak >= 12) {
            return 'blazing';
        }
        if ($streak >= 6) {
            return 'hot';
        }
        if ($streak >= 3) {
            return 'warm';
        }
        if ($streak >= 1) {
            return 'starting';
        }

        return 'cold';
    }
}
