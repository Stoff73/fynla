<?php

declare(strict_types=1);

namespace App\Services\Goals;

use App\Models\Goal;
use App\Models\User;
use App\Services\UKTaxCalculator;
use App\Traits\ResolvesExpenditure;
use App\Traits\ResolvesIncome;
use Carbon\Carbon;

/**
 * Service for analyzing goal affordability based on user's financial situation.
 */
class GoalAffordabilityService
{
    use ResolvesExpenditure;
    use ResolvesIncome;

    public function __construct(
        private readonly UKTaxCalculator $taxCalculator,
        private readonly LifeEventService $lifeEventService
    ) {}

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
        $monthlyNetIncome = $this->resolveNetAnnualIncome($user) / 12;
        $monthlyExpenditure = $this->resolveMonthlyExpenditure($user)['amount'];

        return max(0, $monthlyNetIncome - $monthlyExpenditure);
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
                'color' => 'blue',
                'message' => 'This goal will require significant commitment. Consider extending your timeline.',
                'is_achievable' => true,
            ];
        }

        if ($ratio <= 1.0) {
            return [
                'category' => 'stretch',
                'label' => 'Stretch Goal',
                'color' => 'red',
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
     * Analyze affordability factoring in upcoming life events within the goal's time horizon.
     *
     * Large upcoming expenses reduce available surplus for goal contributions.
     * Large upcoming income events are flagged as lump-sum contribution opportunities.
     */
    public function analyzeAffordabilityWithLifeEvents(Goal $goal, User $user): array
    {
        $baseAnalysis = $this->analyzeAffordability($goal, $user);

        $events = $this->lifeEventService->getActiveEventsForProjection($user->id);
        $goalTargetDate = $goal->target_date ? Carbon::parse($goal->target_date) : null;

        if ($events->isEmpty() || ! $goalTargetDate) {
            return array_merge($baseAnalysis, [
                'life_event_adjustment' => null,
                'lump_sum_opportunities' => [],
                'expense_warnings' => [],
            ]);
        }

        $certaintyWeights = [
            'confirmed' => 1.0,
            'likely' => 0.75,
            'possible' => 0.5,
            'speculative' => 0.25,
        ];

        $totalExpenseImpact = 0;
        $totalIncomeOpportunity = 0;
        $lumpSumOpportunities = [];
        $expenseWarnings = [];

        foreach ($events as $event) {
            // Only consider events within the goal's time horizon
            if ($event->expected_date->gt($goalTargetDate) || $event->expected_date->lt(Carbon::now())) {
                continue;
            }

            $weight = $certaintyWeights[$event->certainty] ?? 0.5;
            $weightedAmount = (float) $event->amount * $weight;

            if ($event->impact_type === 'expense') {
                $totalExpenseImpact += $weightedAmount;

                // Warn about large expenses that could derail the goal
                if ($weightedAmount > $baseAnalysis['monthly_surplus'] * 3) {
                    $expenseWarnings[] = [
                        'event_name' => $event->event_name,
                        'amount' => (float) $event->amount,
                        'weighted_amount' => round($weightedAmount, 2),
                        'expected_date' => $event->expected_date->toDateString(),
                        'certainty' => $event->certainty,
                        'months_of_savings' => $baseAnalysis['monthly_surplus'] > 0
                            ? round($weightedAmount / $baseAnalysis['monthly_surplus'], 1)
                            : null,
                    ];
                }
            } else {
                $totalIncomeOpportunity += $weightedAmount;

                // Flag income events as lump-sum contribution opportunities
                if ($weightedAmount >= 1000) {
                    $lumpSumOpportunities[] = [
                        'event_name' => $event->event_name,
                        'amount' => (float) $event->amount,
                        'weighted_amount' => round($weightedAmount, 2),
                        'expected_date' => $event->expected_date->toDateString(),
                        'certainty' => $event->certainty,
                        'suggested_contribution' => round(min($weightedAmount * 0.5, (float) $goal->target_amount - (float) $goal->current_amount), 2),
                    ];
                }
            }
        }

        // Calculate months in goal horizon
        $monthsRemaining = max(1, (int) Carbon::now()->diffInMonths($goalTargetDate));

        // Spread expense impact across remaining months to see adjusted surplus
        $monthlyExpenseImpact = $totalExpenseImpact / $monthsRemaining;
        $adjustedSurplus = max(0, $baseAnalysis['available_surplus'] - $monthlyExpenseImpact);

        // Recategorize with adjusted surplus
        $adjustedRatio = $adjustedSurplus > 0
            ? $baseAnalysis['required_monthly'] / $adjustedSurplus
            : 0;
        $adjustedCategory = $this->categorizeAffordability(
            $adjustedRatio,
            $adjustedSurplus,
            $baseAnalysis['required_monthly']
        );

        return array_merge($baseAnalysis, [
            'life_event_adjustment' => [
                'total_expense_impact' => round($totalExpenseImpact, 2),
                'total_income_opportunity' => round($totalIncomeOpportunity, 2),
                'monthly_expense_spread' => round($monthlyExpenseImpact, 2),
                'adjusted_surplus' => round($adjustedSurplus, 2),
                'adjusted_category' => $adjustedCategory['category'],
                'adjusted_category_label' => $adjustedCategory['label'],
                'adjusted_is_achievable' => $adjustedCategory['is_achievable'],
                'events_in_horizon' => count($expenseWarnings) + count($lumpSumOpportunities),
            ],
            'lump_sum_opportunities' => $lumpSumOpportunities,
            'expense_warnings' => $expenseWarnings,
        ]);
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
