<?php

declare(strict_types=1);

namespace App\Services\Coordination;

use App\Models\CriticalIllnessPolicy;
use App\Models\DCPension;
use App\Models\DisabilityPolicy;
use App\Models\IncomeProtectionPolicy;
use App\Models\LifeInsurancePolicy;
use App\Models\SavingsAccount;
use App\Models\SicknessIllnessPolicy;
use App\Models\User;
use App\Services\Plans\DisposableIncomeAccessor;
use App\Traits\ResolvesExpenditure;

/**
 * CashFlowCoordinator
 *
 * Coordinates cashflow allocation across all modules.
 * Calculates available surplus and optimizes contribution allocation.
 */
class CashFlowCoordinator
{
    use ResolvesExpenditure;

    /**
     * Calculate available monthly surplus using the same disposable income
     * figure shown on the user's income tab (net income minus expenditure),
     * then deducting committed contributions.
     *
     * @return float Monthly surplus after all expenses and contributions
     */
    public function calculateAvailableSurplus(int $userId): float
    {
        $user = User::find($userId);
        if (! $user) {
            return 0.0;
        }

        $disposableAccessor = app(DisposableIncomeAccessor::class);
        $monthlyDisposable = $disposableAccessor->getMonthlyForUser($user);
        $committedContributions = $this->calculateCommittedContributions($userId);

        return round($monthlyDisposable - $committedContributions, 2);
    }

    /**
     * Optimize contribution allocation across all needs
     *
     * @param  float  $surplus  Available monthly surplus
     * @param  array  $demands  Contribution demands from all modules
     * @return array Optimized allocation
     */
    public function optimizeContributionAllocation(float $surplus, array $demands): array
    {
        // Priority order: Emergency fund → Protection → Pension → Investment → Estate → Goals
        $priorityOrder = [
            'emergency_fund' => 1,
            'protection' => 2,
            'pension' => 3,
            'investment' => 4,
            'estate' => 5,
            'goals' => 6,
        ];

        // Sort demands by priority
        $sortedDemands = [];
        foreach ($demands as $category => $demand) {
            $sortedDemands[] = [
                'category' => $category,
                'amount' => $demand['amount'] ?? 0,
                'urgency' => $demand['urgency'] ?? 50,
                'priority' => $priorityOrder[$category] ?? 999,
            ];
        }

        // Sort by urgency first (if critical), then by priority
        usort($sortedDemands, function ($a, $b) {
            if ($a['urgency'] >= 80 && $b['urgency'] < 80) {
                return -1;
            }
            if ($b['urgency'] >= 80 && $a['urgency'] < 80) {
                return 1;
            }

            return $a['priority'] <=> $b['priority'];
        });

        // Allocate surplus in priority order
        $allocation = [];
        $remaining = $surplus;

        foreach ($sortedDemands as $demand) {
            $category = $demand['category'];

            if ($remaining <= 0) {
                $allocation[$category] = [
                    'allocated' => 0,
                    'requested' => $demand['amount'],
                    'shortfall' => $demand['amount'],
                    'percent_funded' => 0,
                ];

                continue;
            }

            if ($remaining >= $demand['amount']) {
                // Fully fund this demand
                $allocation[$category] = [
                    'allocated' => $demand['amount'],
                    'requested' => $demand['amount'],
                    'shortfall' => 0,
                    'percent_funded' => 100,
                ];
                $remaining -= $demand['amount'];
            } else {
                // Partially fund with remaining surplus
                $allocation[$category] = [
                    'allocated' => $remaining,
                    'requested' => $demand['amount'],
                    'shortfall' => $demand['amount'] - $remaining,
                    'percent_funded' => round(($remaining / $demand['amount']) * 100, 2),
                ];
                $remaining = 0;
            }
        }

        $totalDemand = array_sum(array_column($sortedDemands, 'amount'));

        return [
            'total_demand' => $totalDemand,
            'available_surplus' => $surplus,
            'allocation' => $allocation,
            'total_shortfall' => max(0, $totalDemand - $surplus),
            'surplus_remaining' => max(0, $remaining),
            'allocation_efficiency' => $surplus > 0 ? round((($surplus - $remaining) / $surplus) * 100, 2) : 0,
        ];
    }

    /**
     * Identify cashflow shortfalls
     *
     * @param  array  $allocation  Allocation result
     * @return array Shortfall analysis
     */
    public function identifyCashFlowShortfalls(array $allocation): array
    {
        $shortfalls = [];

        if ($allocation['total_shortfall'] <= 0) {
            return [
                'has_shortfall' => false,
                'total_shortfall' => 0,
                'shortfalls' => [],
                'recommendations' => ['Your cashflow is sufficient to meet all recommended contributions.'],
            ];
        }

        // Identify specific shortfalls
        foreach ($allocation['allocation'] as $category => $details) {
            if ($details['shortfall'] > 0) {
                $shortfalls[] = [
                    'category' => $category,
                    'shortfall' => $details['shortfall'],
                    'percent_funded' => $details['percent_funded'],
                ];
            }
        }

        $recommendations = $this->generateShortfallRecommendations($allocation['total_shortfall'], $shortfalls);

        return [
            'has_shortfall' => true,
            'total_shortfall' => $allocation['total_shortfall'],
            'shortfalls' => $shortfalls,
            'recommendations' => $recommendations,
        ];
    }

    /**
     * Get monthly income, expenses, and surplus for a user.
     *
     * @return array{monthly_income: float, monthly_expenses: float, monthly_surplus: float}
     */
    public function getMonthlyFinancials(int $userId): array
    {
        $user = User::find($userId);
        if (! $user) {
            return ['monthly_income' => 0.0, 'monthly_expenses' => 0.0, 'monthly_surplus' => 0.0];
        }

        $monthlyIncome = $this->calculateMonthlyIncome($user);
        $resolved = $this->resolveMonthlyExpenditure($user);
        $monthlyExpenses = $resolved['amount'];
        $surplus = max(0.0, round($monthlyIncome - $monthlyExpenses, 2));

        return [
            'monthly_income' => $monthlyIncome,
            'monthly_expenses' => $monthlyExpenses,
            'monthly_surplus' => $surplus,
        ];
    }

    /**
     * Calculate monthly income from employment and other sources.
     */
    private function calculateMonthlyIncome(User $user): float
    {
        $annualIncome = (float) ($user->annual_employment_income ?? 0)
            + (float) ($user->annual_self_employment_income ?? 0)
            + (float) ($user->annual_rental_income ?? 0)
            + (float) ($user->annual_dividend_income ?? 0)
            + (float) ($user->annual_interest_income ?? 0)
            + (float) ($user->annual_other_income ?? 0)
            + (float) ($user->annual_trust_income ?? 0);

        return round($annualIncome / 12, 2);
    }

    /**
     * Calculate total committed monthly contributions across all modules.
     */
    private function calculateCommittedContributions(int $userId): float
    {
        $total = 0.0;

        // Pension contributions (DC pensions with monthly contributions)
        $total += (float) DCPension::where('user_id', $userId)
            ->sum('monthly_contribution_amount');

        // Protection premiums (convert to monthly based on frequency)
        $total += $this->sumMonthlyPremiums(LifeInsurancePolicy::class, $userId);
        $total += $this->sumMonthlyPremiums(CriticalIllnessPolicy::class, $userId);
        $total += $this->sumMonthlyPremiums(IncomeProtectionPolicy::class, $userId);
        $total += $this->sumMonthlyPremiums(DisabilityPolicy::class, $userId);
        $total += $this->sumMonthlyPremiums(SicknessIllnessPolicy::class, $userId);

        // Regular savings contributions (monthly equivalent)
        $savingsAccounts = SavingsAccount::where('user_id', $userId)
            ->whereNotNull('regular_contribution_amount')
            ->where('regular_contribution_amount', '>', 0)
            ->get();

        foreach ($savingsAccounts as $account) {
            $total += $this->toMonthly(
                (float) $account->regular_contribution_amount,
                $account->contribution_frequency ?? 'monthly'
            );
        }

        return round($total, 2);
    }

    /**
     * Sum monthly-equivalent premiums for a protection policy model.
     */
    private function sumMonthlyPremiums(string $modelClass, int $userId): float
    {
        $policies = $modelClass::where('user_id', $userId)->get();
        $total = 0.0;

        foreach ($policies as $policy) {
            $amount = (float) ($policy->premium_amount ?? 0);
            $frequency = $policy->premium_frequency ?? 'monthly';
            $total += $this->toMonthly($amount, $frequency);
        }

        return $total;
    }

    /**
     * Convert an amount to monthly based on payment frequency.
     */
    private function toMonthly(float $amount, string $frequency): float
    {
        return match ($frequency) {
            'monthly' => $amount,
            'quarterly' => $amount / 3,
            'annually', 'annual' => $amount / 12,
            'weekly' => $amount * 52 / 12,
            default => $amount,
        };
    }

    /**
     * Generate recommendations to address shortfalls
     */
    private function generateShortfallRecommendations(float $totalShortfall, array $shortfalls): array
    {
        $recommendations = [];

        // Recommend income increase
        $recommendations[] = 'Consider increasing income by £'.number_format($totalShortfall, 2).' per month to fully fund all recommendations.';

        // Recommend expense reduction
        $recommendations[] = 'Review monthly expenses to identify £'.number_format($totalShortfall, 2).' in potential savings.';

        // Prioritize critical areas
        $criticalShortfalls = array_filter($shortfalls, fn ($s) => in_array($s['category'], ['emergency_fund', 'protection']));
        if (count($criticalShortfalls) > 0) {
            $recommendations[] = 'Priority should be given to funding critical areas: '.implode(', ', array_column($criticalShortfalls, 'category')).'.';
        }

        // Phased approach
        if ($totalShortfall > 500) {
            $recommendations[] = 'Consider a phased approach: start with highest priority items and gradually increase contributions as income grows.';
        }

        // One-time windfall
        $recommendations[] = 'Use any bonuses, tax refunds, or windfalls to fund initial gaps or build reserves.';

        return $recommendations;
    }

    /**
     * Create cashflow allocation chart data
     *
     * @return array Chart data for ApexCharts
     */
    public function createCashFlowChartData(int $userId, array $allocation): array
    {
        $user = User::find($userId);
        $monthlyIncome = $user ? $this->calculateMonthlyIncome($user) : 0.0;
        $resolved = $user ? $this->resolveMonthlyExpenditure($user) : ['amount' => 0.0, 'source' => 'none', 'label' => 'Not Set'];
        $monthlyExpenses = $resolved['amount'];

        $categories = [];
        $allocatedAmounts = [];

        // Base expenses
        $categories[] = 'Living Expenses';
        $allocatedAmounts[] = $monthlyExpenses;

        // Allocated contributions
        foreach ($allocation['allocation'] as $category => $details) {
            if ($details['allocated'] > 0) {
                $categories[] = ucwords(str_replace('_', ' ', $category));
                $allocatedAmounts[] = $details['allocated'];
            }
        }

        // Remaining surplus
        if ($allocation['surplus_remaining'] > 0) {
            $categories[] = 'Unallocated Surplus';
            $allocatedAmounts[] = $allocation['surplus_remaining'];
        }

        return [
            'series' => [
                [
                    'name' => 'Monthly Allocation',
                    'data' => $allocatedAmounts,
                ],
            ],
            'categories' => $categories,
            'total_income' => $monthlyIncome,
            'total_allocated' => array_sum($allocatedAmounts),
            'allocation_percent' => $monthlyIncome > 0 ? round((array_sum($allocatedAmounts) / $monthlyIncome) * 100, 2) : 0,
        ];
    }

    /**
     * Calculate sustainable contribution level
     *
     * Based on 50/30/20 rule: 50% needs, 30% wants, 20% savings/investments
     */
    public function calculateSustainableContributions(float $monthlyIncome, float $monthlyExpenses): array
    {
        $needsPercent = 0.50;
        $wantsPercent = 0.30;
        $savingsPercent = 0.20;

        $maxNeeds = $monthlyIncome * $needsPercent;
        $maxWants = $monthlyIncome * $wantsPercent;
        $recommendedSavings = $monthlyIncome * $savingsPercent;

        $currentExpenseRatio = $monthlyIncome > 0 ? $monthlyExpenses / $monthlyIncome : 0;

        return [
            'monthly_income' => $monthlyIncome,
            'current_expenses' => $monthlyExpenses,
            'current_expense_ratio' => round($currentExpenseRatio * 100, 2),
            'recommended_savings_amount' => $recommendedSavings,
            'recommended_savings_percent' => 20,
            'is_sustainable' => $monthlyExpenses <= $maxNeeds + $maxWants,
            'expense_reduction_needed' => max(0, $monthlyExpenses - ($maxNeeds + $maxWants)),
        ];
    }
}
