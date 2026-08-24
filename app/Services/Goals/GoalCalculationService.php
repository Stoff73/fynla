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
     *
     * Floors at zero, so a date that has gone reads as none left rather than as
     * a negative. `isOverdue()` is what distinguishes "none left" from "gone" —
     * do not read a zero here as either.
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
     * Has the goal's target date already passed?
     *
     * Making past-dated goals creatable (W-0029) was right; the on-track maths
     * was never updated for them. `start_date` is stamped with today at
     * creation, so a goal recorded against a date already gone stores a span
     * that runs BACKWARDS — and on Carbon 2 `diffInDays()` is ABSOLUTE, so the
     * inverted range came back positive (21, not −21) and the `$totalDays <= 0`
     * guard in `calculateIsOnTrack()`, which exists precisely to catch a
     * non-positive span, never fired. Elapsed read as nothing, expected progress
     * as nothing, and any progress at all passed as on track: a goal four and a
     * half months past its date at 75% announced itself "On track", and a page
     * whose banner is `on_track_count === total_goals` congratulated the user
     * on it (W-0411).
     *
     * A completed goal is never overdue — it was reached, and when it was
     * reached relative to its date is what `status_label` discloses.
     */
    public function isOverdue(Goal $goal): bool
    {
        if (! $goal->target_date || $goal->status === 'completed') {
            return false;
        }

        return $goal->target_date->lt(now()->startOfDay());
    }

    /**
     * Determine whether the goal is on track based on linear projection.
     */
    public function calculateIsOnTrack(Goal $goal): bool
    {
        if ($goal->status !== 'active') {
            return $goal->status === 'completed';
        }

        // The date has gone. Funded or not, nothing is still on its way there —
        // overdue at 100% is achieved late, overdue at 75% is missed, and
        // neither of those is "on track".
        if ($this->isOverdue($goal)) {
            return false;
        }

        // Can't be "on track" if nothing has been saved yet
        if ((float) $goal->current_amount <= 0) {
            return false;
        }

        if (! $goal->start_date || ! $goal->target_date) {
            return false;
        }

        // Signed, so an inverted range reads as the non-positive span it is
        // rather than as its own mirror image.
        $totalDays = $goal->start_date->diffInDays($goal->target_date, false);
        if ($totalDays <= 0) {
            return $this->calculateProgressPercentage($goal) >= 100;
        }

        $daysElapsed = max(0, $goal->start_date->diffInDays(now(), false));
        $expectedProgress = min(($daysElapsed / $totalDays) * 100, 100);

        // Allow 10% margin for being "on track"
        return $this->calculateProgressPercentage($goal) >= ($expectedProgress - 10);
    }

    /**
     * The one vocabulary for how a goal's standing is stated to the user.
     *
     * Every surface used to compose its own from the `is_on_track` boolean and
     * whatever else it had to hand, so the same goal read "On Track" on a card,
     * "On track" in an overview and "Yes" in a plan — and an overdue goal had
     * no word at all, because a boolean has only two. This returns the whole
     * vocabulary from one place so web, `/m`, the plan panels, the print pack
     * and Fyn all say the same thing about the same goal (Rule 20).
     *
     * The distinction that did not exist before: a goal whose date has passed
     * is `Achieved late` when it is funded and `Overdue` when it is not.
     * Neither is "on track", and neither is a plain "Behind schedule" — a goal
     * four months past its date is not behind a schedule that has ended.
     */
    public function calculateStatusLabel(Goal $goal): string
    {
        if ($goal->status === 'completed') {
            return 'Completed';
        }
        if ($goal->status === 'paused') {
            return 'Paused';
        }
        if ($goal->status === 'abandoned') {
            return 'Abandoned';
        }

        $isFunded = $this->calculateProgressPercentage($goal) >= 100;

        if ($this->isOverdue($goal)) {
            return $isFunded ? 'Achieved late' : 'Overdue';
        }

        if ($isFunded) {
            return 'Goal achieved';
        }

        if ((float) $goal->current_amount <= 0) {
            return 'Not started';
        }

        return $this->calculateIsOnTrack($goal) ? 'On track' : 'Behind schedule';
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
