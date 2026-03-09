<?php

declare(strict_types=1);

namespace App\Services\Retirement;

use Illuminate\Support\Collection;

/**
 * Decumulation Planner Service
 *
 * Plans retirement income drawdown strategies including sustainable withdrawal rates,
 * annuity vs drawdown comparison, PCLS strategy, and income phasing.
 */
class DecumulationPlanner
{
    /**
     * Calculate sustainable withdrawal rate scenarios.
     *
     * Tests 3%, 4%, and 5% withdrawal rates to determine portfolio sustainability.
     *
     * @param  float  $portfolioValue  Total DC pension pot value
     * @param  int  $yearsInRetirement  Expected years in retirement
     * @param  float  $growthRate  Expected annual growth rate
     * @param  float  $inflationRate  Expected annual inflation rate
     */
    public function calculateSustainableWithdrawalRate(
        float $portfolioValue,
        int $yearsInRetirement,
        float $growthRate = 0.05,
        float $inflationRate = 0.025,
        float $careCostAnnual = 0,
        int $careStartsAfterYear = 0
    ): array {
        $scenarios = [];
        $withdrawalRates = [0.03, 0.04, 0.05]; // 3%, 4%, 5%

        foreach ($withdrawalRates as $rate) {
            $initialWithdrawal = $portfolioValue * $rate;
            $survivalAnalysis = $this->simulatePortfolioSurvival(
                $portfolioValue,
                $initialWithdrawal,
                $yearsInRetirement,
                $growthRate,
                $inflationRate,
                $careCostAnnual,
                $careStartsAfterYear
            );

            $scenarios[] = [
                'withdrawal_rate' => $rate * 100, // As percentage
                'initial_annual_income' => round($initialWithdrawal, 2),
                'survives' => $survivalAnalysis['survives'],
                'final_balance' => $survivalAnalysis['final_balance'],
                'years_survived' => $survivalAnalysis['years_survived'],
                'recommendation' => $this->getWithdrawalRecommendation($rate, $survivalAnalysis),
            ];
        }

        return [
            'scenarios' => $scenarios,
            'recommended_rate' => $this->determineRecommendedRate($scenarios),
            'care_costs_included' => $careCostAnnual > 0,
        ];
    }

    /**
     * Compare annuity purchase vs flexible drawdown.
     *
     * @param  float  $pensionPot  DC pension pot value
     * @param  int  $age  Current age
     * @param  bool  $spouse  Whether to include spouse benefits
     */
    public function compareAnnuityVsDrawdown(float $pensionPot, int $age, bool $spouse = false): array
    {
        // Annuity rates (simplified - real rates vary by provider and health)
        // Rough estimates: 5-6% for age 65-70, decreasing with age
        $annuityRate = $this->getAnnuityRate($age, $spouse);
        $annuityIncome = $pensionPot * $annuityRate;

        // Drawdown scenario using 4% withdrawal rate
        $drawdownRate = 0.04;
        $drawdownIncome = $pensionPot * $drawdownRate;

        return [
            'annuity' => [
                'annual_income' => round($annuityIncome, 2),
                'guaranteed' => true,
                'inflation_protected' => false, // Level annuity
                'death_benefits' => $spouse ? 'Spouse pension included' : 'No death benefits',
                'flexibility' => 'None - irreversible decision',
                'pros' => [
                    'Guaranteed income for life',
                    'No investment risk',
                    'Simplicity',
                ],
                'cons' => [
                    'Irreversible',
                    'No access to capital',
                    'Poor value if you die early',
                    'Income eroded by inflation',
                ],
            ],
            'drawdown' => [
                'annual_income' => round($drawdownIncome, 2),
                'guaranteed' => false,
                'flexibility' => 'Full - adjust withdrawals as needed',
                'death_benefits' => 'Full remaining pot passes to beneficiaries',
                'investment_risk' => 'Yes - returns not guaranteed',
                'pros' => [
                    'Flexibility to adjust income',
                    'Access to capital for emergencies',
                    'Potential for growth',
                    'Death benefits for beneficiaries',
                ],
                'cons' => [
                    'Investment risk',
                    'Risk of running out of money',
                    'Requires active management',
                ],
            ],
            'recommendation' => $this->getAnnuityVsDrawdownRecommendation($pensionPot, $age),
        ];
    }

    /**
     * Calculate Pension Commencement Lump Sum (PCLS) strategy.
     *
     * PCLS = 25% of pension value, tax-free.
     *
     * @param  float  $pensionValue  Total DC pension value
     */
    public function calculatePCLSStrategy(float $pensionValue): array
    {
        $pclsAmount = $pensionValue * 0.25;
        $remainingPot = $pensionValue - $pclsAmount;

        // Calculate income from remaining pot (4% withdrawal rate)
        $annualIncomeFromRemainingPot = $remainingPot * 0.04;

        return [
            'pension_value' => round($pensionValue, 2),
            'pcls_amount' => round($pclsAmount, 2),
            'remaining_pot' => round($remainingPot, 2),
            'estimated_annual_income' => round($annualIncomeFromRemainingPot, 2),
            'tax_saving' => round($pclsAmount * 0.20, 2), // Minimum 20% basic rate saving
            'options' => [
                'Take full PCLS upfront',
                'Take PCLS in stages (Uncrystallised Funds Pension Lump Sum)',
                'Leave PCLS invested and withdraw gradually',
            ],
            'recommendation' => 'Consider your immediate cash needs, debt repayment opportunities, and tax position before deciding.',
        ];
    }

    /**
     * Model income phasing strategy for tax efficiency.
     *
     * Optimizes withdrawal order from multiple pension sources.
     *
     * @param  Collection  $pensions  All pension sources
     * @param  int  $retirementAge  Target retirement age
     */
    public function modelIncomePhasing(Collection $pensions, int $retirementAge): array
    {
        $phasingStrategy = [];

        // Phase 1: State Pension Age (typically 67)
        $phasingStrategy[] = [
            'phase' => 'Early Retirement (before State Pension Age)',
            'age_range' => sprintf('%d-%d', $retirementAge, 66),
            'income_sources' => [
                'DC pension drawdown',
                'PCLS (tax-free)',
            ],
            'strategy' => 'Draw from DC pensions, maximize use of personal allowance',
        ];

        // Phase 2: State Pension Age onwards
        $phasingStrategy[] = [
            'phase' => 'State Pension Age onwards',
            'age_range' => '67+',
            'income_sources' => [
                'State Pension',
                'DB pension (if applicable)',
                'DC pension drawdown (reduced)',
            ],
            'strategy' => 'Reduce DC drawdown once State Pension and DB pensions commence',
        ];

        // Phase 3: Later retirement
        $phasingStrategy[] = [
            'phase' => 'Later Retirement (75+)',
            'age_range' => '75+',
            'income_sources' => [
                'State Pension',
                'DB pension',
                'Reduced DC drawdown or annuity',
            ],
            'strategy' => 'Consider purchasing annuity with remaining DC pot for security',
        ];

        return [
            'phasing_strategy' => $phasingStrategy,
            'tax_efficiency_tips' => [
                'Use personal allowance (£12,570) efficiently each year',
                'Avoid breaching higher rate threshold (£50,270) if possible',
                'Coordinate pension income with part-time work if applicable',
                'Consider spousal income splitting opportunities',
            ],
        ];
    }

    /**
     * Simulate portfolio survival over retirement period.
     */
    private function simulatePortfolioSurvival(
        float $startingBalance,
        float $initialWithdrawal,
        int $years,
        float $growthRate,
        float $inflationRate,
        float $careCostAnnual = 0,
        int $careStartsAfterYear = 0
    ): array {
        $balance = $startingBalance;
        $withdrawal = $initialWithdrawal;
        $careCost = $careCostAnnual;
        $yearsSurvived = 0;

        for ($year = 1; $year <= $years; $year++) {
            // Withdraw at start of year
            $balance -= $withdrawal;

            // Add care costs if applicable
            if ($careCost > 0 && $year > $careStartsAfterYear) {
                $balance -= $careCost;
            }

            if ($balance <= 0) {
                return [
                    'survives' => false,
                    'final_balance' => 0,
                    'years_survived' => $yearsSurvived,
                ];
            }

            // Apply growth
            $balance *= (1 + $growthRate);

            // Increase withdrawal and care costs for inflation
            $withdrawal *= (1 + $inflationRate);
            if ($careCost > 0) {
                $careCost *= (1 + $inflationRate);
            }

            $yearsSurvived++;
        }

        return [
            'survives' => true,
            'final_balance' => round($balance, 2),
            'years_survived' => $yearsSurvived,
        ];
    }

    /**
     * Get withdrawal rate recommendation based on simulation.
     */
    private function getWithdrawalRecommendation(float $rate, array $analysis): string
    {
        if (! $analysis['survives']) {
            return 'Not sustainable - portfolio depleted';
        }

        if ($rate <= 0.03) {
            return 'Very conservative - high likelihood of leaving large legacy';
        }

        if ($rate <= 0.04) {
            return 'Balanced approach - widely considered sustainable';
        }

        return 'Aggressive - higher risk of portfolio depletion';
    }

    /**
     * Determine recommended withdrawal rate from scenarios.
     */
    private function determineRecommendedRate(array $scenarios): float
    {
        // Recommend highest sustainable rate
        foreach (array_reverse($scenarios) as $scenario) {
            if ($scenario['survives']) {
                return $scenario['withdrawal_rate'];
            }
        }

        return 3.0; // Default to conservative 3%
    }

    /**
     * Get annuity rate based on age and spouse benefits.
     */
    private function getAnnuityRate(int $age, bool $spouse): float
    {
        // Simplified rates - real rates vary significantly
        $baseRate = match (true) {
            $age < 60 => 0.04,
            $age < 65 => 0.045,
            $age < 70 => 0.055,
            $age < 75 => 0.065,
            default => 0.075,
        };

        // Reduce rate if spouse benefits included
        if ($spouse) {
            $baseRate *= 0.85;
        }

        return $baseRate;
    }

    /**
     * Get recommendation for annuity vs drawdown decision.
     */
    private function getAnnuityVsDrawdownRecommendation(float $pensionPot, int $age): string
    {
        if ($pensionPot < 100000) {
            return 'With a smaller pot, consider drawdown for flexibility. Annuity income may be too low to justify loss of flexibility.';
        }

        if ($age < 70) {
            return 'At your age, drawdown offers flexibility and growth potential. Consider annuity later if circumstances change.';
        }

        return 'Consider a hybrid approach: use part of pot for drawdown (flexibility) and part for annuity (guaranteed income).';
    }
}
