<?php

declare(strict_types=1);

namespace App\Services\Goals;

use App\Models\Goal;

class GoalCalculationService
{
    /**
     * Calculate the progress percentage toward the goal target.
     */
    public function calculateProgressPercentage(Goal $goal): float
    {
        if ($goal->target_amount <= 0) {
            return 0;
        }

        $percentage = ($goal->current_amount / $goal->target_amount) * 100;

        return min(round($percentage, 2), 100);
    }

    /**
     * Calculate the number of days remaining until the target date.
     */
    public function calculateDaysRemaining(Goal $goal): int
    {
        if (! $goal->target_date) {
            return 0;
        }

        $diff = now()->startOfDay()->diffInDays($goal->target_date, false);

        return max(0, (int) $diff);
    }

    /**
     * Calculate the number of months remaining until the target date.
     */
    public function calculateMonthsRemaining(Goal $goal): int
    {
        if (! $goal->target_date) {
            return 0;
        }

        $diff = now()->startOfMonth()->diffInMonths($goal->target_date, false);

        return max(0, (int) ceil($diff));
    }

    /**
     * Determine whether the goal is on track based on linear projection.
     */
    public function calculateIsOnTrack(Goal $goal): bool
    {
        if ($goal->status !== 'active') {
            return $goal->status === 'completed';
        }

        // Can't be "on track" if nothing has been saved yet
        if ((float) $goal->current_amount <= 0) {
            return false;
        }

        if (! $goal->start_date || ! $goal->target_date) {
            return false;
        }

        $totalDays = $goal->start_date->diffInDays($goal->target_date);
        if ($totalDays <= 0) {
            return $this->calculateProgressPercentage($goal) >= 100;
        }

        $daysElapsed = $goal->start_date->diffInDays(now());
        $expectedProgress = min(($daysElapsed / $totalDays) * 100, 100);

        // Allow 10% margin for being "on track"
        return $this->calculateProgressPercentage($goal) >= ($expectedProgress - 10);
    }

    /**
     * Calculate the amount remaining to reach the target.
     */
    public function calculateAmountRemaining(Goal $goal): float
    {
        return max(0, (float) $goal->target_amount - (float) $goal->current_amount);
    }

    /**
     * Calculate the required monthly contribution to reach the target on time.
     */
    public function calculateRequiredMonthlyContribution(Goal $goal): float
    {
        $monthsRemaining = $this->calculateMonthsRemaining($goal);
        if ($monthsRemaining <= 0) {
            return 0;
        }

        return round($this->calculateAmountRemaining($goal) / $monthsRemaining, 2);
    }

    /**
     * Get the current milestone reached (25, 50, 75, or 100).
     */
    public function calculateCurrentMilestone(Goal $goal): ?int
    {
        $progress = $this->calculateProgressPercentage($goal);

        if ($progress >= 100) {
            return 100;
        }
        if ($progress >= 75) {
            return 75;
        }
        if ($progress >= 50) {
            return 50;
        }
        if ($progress >= 25) {
            return 25;
        }

        return null;
    }

    /**
     * Get the next milestone target (25, 50, 75, or 100).
     */
    public function calculateNextMilestone(Goal $goal): ?int
    {
        $progress = $this->calculateProgressPercentage($goal);

        if ($progress >= 100) {
            return null;
        }
        if ($progress >= 75) {
            return 100;
        }
        if ($progress >= 50) {
            return 75;
        }
        if ($progress >= 25) {
            return 50;
        }

        return 25;
    }
}
