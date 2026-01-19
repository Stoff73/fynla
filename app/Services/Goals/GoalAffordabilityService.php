<?php

declare(strict_types=1);

namespace App\Services\Goals;

use App\Models\Goal;
use App\Models\User;

/**
 * Service for analyzing goal affordability based on user's financial situation.
 */
class GoalAffordabilityService
{
    /**
     * Analyze affordability of a goal for a user.
     */
    public function analyzeAffordability(Goal $goal, User $user): array
    {
        $monthlySurplus = $this->calculateMonthlySurplus($user);
        $requiredMonthly = $this->calculateRequiredMonthly($goal);
        $currentCommitments = $this->getCurrentGoalCommitments($user, $goal->id);

        $availableSurplus = $monthlySurplus - $currentCommitments;
        $affordabilityRatio = $availableSurplus > 0 ? $requiredMonthly / $availableSurplus : 0;

        $category = $this->categorizeAffordability($affordabilityRatio, $availableSurplus, $requiredMonthly);

        return [
            'monthly_surplus' => round($monthlySurplus, 2),
            'current_goal_commitments' => round($currentCommitments, 2),
            'available_surplus' => round($availableSurplus, 2),
            'required_monthly' => round($requiredMonthly, 2),
            'affordability_ratio' => round($affordabilityRatio, 4),
            'category' => $category['category'],
            'category_label' => $category['label'],
            'category_color' => $category['color'],
            'message' => $category['message'],
            'is_achievable' => $category['is_achievable'],
            'suggested_monthly' => round($this->suggestMonthlyContribution($availableSurplus, $requiredMonthly), 2),
            'suggested_target_date' => $this->suggestTargetDate($goal, $availableSurplus),
        ];
    }

    /**
     * Calculate user's monthly surplus (income - expenditure).
     */
    public function calculateMonthlySurplus(User $user): float
    {
        $annualIncome = (float) ($user->annual_gross_salary ?? 0);
        $monthlyIncome = $annualIncome / 12;

        // Get monthly expenditure from user profile
        $monthlyExpenditure = $this->getMonthlyExpenditure($user);

        // Estimate tax for a rough net income
        $estimatedTax = $this->estimateAnnualTax($annualIncome);
        $monthlyNetIncome = ($annualIncome - $estimatedTax) / 12;

        return max(0, $monthlyNetIncome - $monthlyExpenditure);
    }

    /**
     * Get user's total monthly expenditure.
     */
    private function getMonthlyExpenditure(User $user): float
    {
        // Sum up all monthly expenditure fields from user profile
        $expenditure = 0;
        $expenditure += (float) ($user->monthly_mortgage_rent ?? 0);
        $expenditure += (float) ($user->monthly_utilities ?? 0);
        $expenditure += (float) ($user->monthly_council_tax ?? 0);
        $expenditure += (float) ($user->monthly_insurance ?? 0);
        $expenditure += (float) ($user->monthly_transport ?? 0);
        $expenditure += (float) ($user->monthly_food ?? 0);
        $expenditure += (float) ($user->monthly_childcare ?? 0);
        $expenditure += (float) ($user->monthly_entertainment ?? 0);
        $expenditure += (float) ($user->monthly_other ?? 0);
        $expenditure += (float) ($user->monthly_debt_repayments ?? 0);

        return $expenditure;
    }

    /**
     * Estimate annual tax (simplified calculation).
     */
    private function estimateAnnualTax(float $annualIncome): float
    {
        // Simplified UK tax estimate
        $personalAllowance = 12570;
        $basicRateLimit = 50270;
        $higherRateLimit = 125140;

        if ($annualIncome <= $personalAllowance) {
            return 0;
        }

        $tax = 0;

        // Basic rate (20%)
        if ($annualIncome > $personalAllowance) {
            $basicBand = min($annualIncome, $basicRateLimit) - $personalAllowance;
            $tax += $basicBand * 0.20;
        }

        // Higher rate (40%)
        if ($annualIncome > $basicRateLimit) {
            $higherBand = min($annualIncome, $higherRateLimit) - $basicRateLimit;
            $tax += $higherBand * 0.40;
        }

        // Additional rate (45%)
        if ($annualIncome > $higherRateLimit) {
            $additionalBand = $annualIncome - $higherRateLimit;
            $tax += $additionalBand * 0.45;
        }

        // NI estimate (simplified)
        if ($annualIncome > 12570) {
            $niablePay = min($annualIncome, 50270) - 12570;
            $tax += $niablePay * 0.08;

            if ($annualIncome > 50270) {
                $tax += ($annualIncome - 50270) * 0.02;
            }
        }

        return $tax;
    }

    /**
     * Calculate required monthly contribution to reach goal on time.
     */
    private function calculateRequiredMonthly(Goal $goal): float
    {
        $remaining = (float) $goal->target_amount - (float) $goal->current_amount;
        $monthsRemaining = $goal->months_remaining;

        if ($monthsRemaining <= 0 || $remaining <= 0) {
            return 0;
        }

        return $remaining / $monthsRemaining;
    }

    /**
     * Get total monthly contributions to other active goals.
     */
    private function getCurrentGoalCommitments(User $user, ?int $excludeGoalId = null): float
    {
        $query = Goal::where('user_id', $user->id)
            ->where('status', 'active')
            ->whereNotNull('monthly_contribution');

        if ($excludeGoalId) {
            $query->where('id', '!=', $excludeGoalId);
        }

        return (float) $query->sum('monthly_contribution');
    }

    /**
     * Categorize affordability level.
     */
    public function categorizeAffordability(float $ratio, float $availableSurplus, float $requiredMonthly): array
    {
        if ($availableSurplus <= 0) {
            return [
                'category' => 'unaffordable',
                'label' => 'Not Currently Achievable',
                'color' => 'red',
                'message' => 'Your current expenses exceed your income. Review your budget before setting savings goals.',
                'is_achievable' => false,
            ];
        }

        if ($requiredMonthly <= 0) {
            return [
                'category' => 'completed',
                'label' => 'Already Achieved',
                'color' => 'green',
                'message' => 'This goal has already been reached.',
                'is_achievable' => true,
            ];
        }

        if ($ratio <= 0.3) {
            return [
                'category' => 'comfortable',
                'label' => 'Comfortable',
                'color' => 'green',
                'message' => 'This goal fits comfortably within your budget.',
                'is_achievable' => true,
            ];
        }

        if ($ratio <= 0.5) {
            return [
                'category' => 'moderate',
                'label' => 'Moderate',
                'color' => 'blue',
                'message' => 'This goal is achievable but will require consistent saving.',
                'is_achievable' => true,
            ];
        }

        if ($ratio <= 0.75) {
            return [
                'category' => 'challenging',
                'label' => 'Challenging',
                'color' => 'yellow',
                'message' => 'This goal will require significant commitment. Consider extending your timeline.',
                'is_achievable' => true,
            ];
        }

        if ($ratio <= 1.0) {
            return [
                'category' => 'stretch',
                'label' => 'Stretch Goal',
                'color' => 'orange',
                'message' => 'This goal uses most of your available savings capacity. Any unexpected expenses could derail progress.',
                'is_achievable' => true,
            ];
        }

        return [
            'category' => 'overcommitted',
            'label' => 'Over Budget',
            'color' => 'red',
            'message' => 'The required monthly savings exceeds your available surplus. Consider a longer timeline or smaller target.',
            'is_achievable' => false,
        ];
    }

    /**
     * Suggest a realistic monthly contribution based on available surplus.
     */
    private function suggestMonthlyContribution(float $availableSurplus, float $requiredMonthly): float
    {
        if ($availableSurplus <= 0) {
            return 0;
        }

        // Suggest 50% of available surplus or required amount, whichever is less
        $suggested = min($requiredMonthly, $availableSurplus * 0.5);

        // Round to nearest £10 for user-friendly amounts
        return ceil($suggested / 10) * 10;
    }

    /**
     * Suggest an achievable target date based on available surplus.
     */
    private function suggestTargetDate(Goal $goal, float $availableSurplus): ?string
    {
        if ($availableSurplus <= 0) {
            return null;
        }

        $remaining = (float) $goal->target_amount - (float) $goal->current_amount;
        if ($remaining <= 0) {
            return null;
        }

        // Use 50% of surplus as sustainable contribution
        $sustainableMonthly = $availableSurplus * 0.5;
        if ($sustainableMonthly <= 0) {
            return null;
        }

        $monthsNeeded = ceil($remaining / $sustainableMonthly);
        $suggestedDate = now()->addMonths((int) $monthsNeeded);

        return $suggestedDate->format('Y-m-d');
    }

    /**
     * Analyze all goals for a user and provide summary.
     */
    public function analyzeAllGoals(User $user): array
    {
        $goals = Goal::where('user_id', $user->id)
            ->where('status', 'active')
            ->get();

        $monthlySurplus = $this->calculateMonthlySurplus($user);
        $totalCommitments = $goals->sum('monthly_contribution');
        $remainingSurplus = $monthlySurplus - $totalCommitments;

        $goalAnalyses = $goals->map(fn ($goal) => [
            'goal_id' => $goal->id,
            'goal_name' => $goal->goal_name,
            'monthly_contribution' => $goal->monthly_contribution,
            'percentage_of_surplus' => $monthlySurplus > 0
                ? round(($goal->monthly_contribution / $monthlySurplus) * 100, 1)
                : 0,
        ]);

        return [
            'monthly_surplus' => round($monthlySurplus, 2),
            'total_goal_commitments' => round($totalCommitments, 2),
            'remaining_surplus' => round($remainingSurplus, 2),
            'commitment_ratio' => $monthlySurplus > 0 ? round($totalCommitments / $monthlySurplus, 4) : 0,
            'goals_count' => $goals->count(),
            'goals' => $goalAnalyses,
            'status' => $remainingSurplus >= 0 ? 'sustainable' : 'overcommitted',
            'can_add_more' => $remainingSurplus > 100,
        ];
    }
}
