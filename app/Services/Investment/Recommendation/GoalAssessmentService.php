<?php

declare(strict_types=1);

namespace App\Services\Investment\Recommendation;

use Carbon\Carbon;

/**
 * Maps active goals to suitable investment wrappers based on timeline.
 *
 * Timeline bands:
 *  SHORT (0-3 years):  Cash ISA, savings — no equities exposure
 *  MEDIUM (3-7 years): Stocks & Shares ISA, balanced funds
 *  LONG (7-15 years):  Stocks & Shares ISA, pension, growth funds
 *  VERY_LONG (15+ years): Pension, Stocks & Shares ISA, VCT/EIS
 */
class GoalAssessmentService
{
    /** Timeline band boundaries in years. */
    private const BAND_SHORT_MAX = 3;

    private const BAND_MEDIUM_MAX = 7;

    private const BAND_LONG_MAX = 15;

    /**
     * Assess all goals and return wrapper preferences and modifiers per goal.
     *
     * @param  array  $context  User context from UserContextBuilder
     * @return array{
     *     goal_wrappers: array,
     *     aggregate_blocked_wrappers: string[],
     *     aggregate_prioritised_wrappers: string[],
     *     has_short_term_goals: bool,
     *     has_house_purchase_goal: bool,
     *     goals_assessed: int
     * }
     */
    public function assess(array $context): array
    {
        $goals = $context['goals'] ?? [];
        $age = $context['personal']['age'] ?? null;
        $emergencyShortfall = $context['emergency_fund']['shortfall'] ?? 0;

        $goalWrappers = [];
        $aggregateBlocked = [];
        $aggregatePrioritised = [];
        $hasShortTermGoals = false;
        $hasHousePurchaseGoal = false;

        foreach ($goals as $goal) {
            $assessment = $this->assessGoal($goal, $age, $context);

            if ($assessment === null) {
                continue;
            }

            $goalWrappers[] = $assessment;
            $aggregateBlocked = array_merge($aggregateBlocked, $assessment['blocked_wrappers']);
            $aggregatePrioritised = array_merge($aggregatePrioritised, $assessment['suitable_wrappers']);

            if ($assessment['timeline_band'] === 'short') {
                $hasShortTermGoals = true;
            }

            if ($this->isHousePurchaseGoal($goal)) {
                $hasHousePurchaseGoal = true;
            }
        }

        // Implicit emergency fund goal if shortfall exists
        if ($emergencyShortfall > 0) {
            $emergencyGoal = $this->buildImplicitEmergencyFundGoal($emergencyShortfall, $context);
            $goalWrappers[] = $emergencyGoal;
            $hasShortTermGoals = true;
        }

        return [
            'goal_wrappers' => $goalWrappers,
            'aggregate_blocked_wrappers' => array_values(array_unique($aggregateBlocked)),
            'aggregate_prioritised_wrappers' => array_values(array_unique($aggregatePrioritised)),
            'has_short_term_goals' => $hasShortTermGoals,
            'has_house_purchase_goal' => $hasHousePurchaseGoal,
            'goals_assessed' => count($goalWrappers),
        ];
    }

    /**
     * Assess a single goal for wrapper suitability.
     */
    private function assessGoal(array $goal, ?int $age, array $context): ?array
    {
        $targetDate = $goal['target_date'] ?? null;
        $yearsToTarget = null;

        if ($targetDate) {
            $target = Carbon::parse($targetDate);
            $yearsToTarget = max(0, (int) now()->diffInYears($target, false));
        }

        $timelineBand = $this->determineTimelineBand($yearsToTarget);
        $goalType = $goal['goal_type'] ?? 'custom';
        $targetAmount = (float) ($goal['target_amount'] ?? 0);
        $currentAmount = (float) ($goal['current_amount'] ?? 0);
        $shortfall = max(0, $targetAmount - $currentAmount);
        $isFirstTimeBuyer = (bool) ($goal['is_first_time_buyer'] ?? false);

        // Determine suitable and blocked wrappers
        $suitableWrappers = $this->getSuitableWrappers($timelineBand, $goalType, $age, $isFirstTimeBuyer);
        $blockedWrappers = $this->getBlockedWrappers($timelineBand);

        return [
            'goal_id' => $goal['id'] ?? null,
            'goal_name' => $goal['goal_name'] ?? 'Unnamed goal',
            'goal_type' => $goalType,
            'target_amount' => round($targetAmount, 2),
            'current_amount' => round($currentAmount, 2),
            'shortfall' => round($shortfall, 2),
            'years_to_target' => $yearsToTarget,
            'timeline_band' => $timelineBand,
            'suitable_wrappers' => $suitableWrappers,
            'blocked_wrappers' => $blockedWrappers,
            'is_first_time_buyer' => $isFirstTimeBuyer,
        ];
    }

    /**
     * Determine the timeline band from years to target.
     */
    private function determineTimelineBand(?int $years): string
    {
        if ($years === null) {
            return 'long'; // Default to long if no target date
        }

        if ($years <= self::BAND_SHORT_MAX) {
            return 'short';
        }

        if ($years <= self::BAND_MEDIUM_MAX) {
            return 'medium';
        }

        if ($years <= self::BAND_LONG_MAX) {
            return 'long';
        }

        return 'very_long';
    }

    /**
     * Get suitable wrappers for a goal based on its timeline band and type.
     */
    private function getSuitableWrappers(string $band, string $goalType, ?int $age, bool $isFirstTimeBuyer): array
    {
        $wrappers = match ($band) {
            'short' => ['cash_isa', 'savings_account', 'premium_bonds'],
            'medium' => ['stocks_shares_isa', 'cash_isa', 'premium_bonds'],
            'long' => ['stocks_shares_isa', 'pension', 'premium_bonds'],
            'very_long' => ['pension', 'stocks_shares_isa', 'vct', 'eis'],
            default => ['stocks_shares_isa'],
        };

        // Add Lifetime ISA if eligible (age < 40, first-time buyer goal)
        if ($isFirstTimeBuyer && $age !== null && $age < 40) {
            array_unshift($wrappers, 'lisa');
        }

        // House purchase goals — prioritise liquid wrappers
        if ($this->isHousePurchaseGoalType($goalType) && $band !== 'very_long') {
            $wrappers = array_unique(array_merge(['lisa', 'cash_isa'], $wrappers));
        }

        return array_values($wrappers);
    }

    /**
     * Get wrappers that should be blocked for a given timeline band.
     */
    private function getBlockedWrappers(string $band): array
    {
        return match ($band) {
            'short' => ['pension', 'vct', 'eis', 'seis', 'offshore_bond', 'onshore_bond'],
            'medium' => ['pension', 'vct', 'eis', 'seis', 'offshore_bond', 'onshore_bond'],
            'long' => ['vct', 'eis', 'seis'],
            'very_long' => [],
            default => [],
        };
    }

    /**
     * Build an implicit emergency fund goal entry if there is a shortfall.
     *
     * This does NOT produce a standalone recommendation — it only returns
     * wrapper preferences so the pipeline knows to prioritise liquid wrappers.
     */
    private function buildImplicitEmergencyFundGoal(float $shortfall, array $context): array
    {
        return [
            'goal_id' => null,
            'goal_name' => 'Emergency fund shortfall (implicit)',
            'goal_type' => 'emergency_fund',
            'target_amount' => $context['emergency_fund']['target_amount'] ?? 0,
            'current_amount' => $context['emergency_fund']['total_savings'] ?? 0,
            'shortfall' => round($shortfall, 2),
            'years_to_target' => 1,
            'timeline_band' => 'short',
            'suitable_wrappers' => ['savings_account', 'cash_isa'],
            'blocked_wrappers' => ['pension', 'vct', 'eis', 'seis', 'offshore_bond', 'onshore_bond'],
            'is_first_time_buyer' => false,
        ];
    }

    /**
     * Check if a goal is a house purchase goal.
     */
    private function isHousePurchaseGoal(array $goal): bool
    {
        return $this->isHousePurchaseGoalType($goal['goal_type'] ?? '');
    }

    /**
     * Check if a goal type represents a house purchase.
     */
    private function isHousePurchaseGoalType(string $goalType): bool
    {
        return in_array($goalType, [
            'house_purchase',
            'home_purchase',
            'first_home',
            'property_purchase',
            'house_deposit',
        ], true);
    }
}
