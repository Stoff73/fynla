<?php

declare(strict_types=1);

namespace App\Services\Retirement;

use App\Models\User;
use App\Services\TaxConfigService;
use Illuminate\Support\Collection;

/**
 * Decumulation Planner Service
 *
 * Plans retirement income drawdown strategies including sustainable withdrawal rates,
 * annuity vs drawdown comparison, PCLS strategy, and income phasing.
 */
class DecumulationPlanner
{
    /** Joint annuity rates are typically ~15% lower than single-life rates */
    private const JOINT_ANNUITY_RATE_REDUCTION = 0.85;

    public function __construct(
        private readonly TaxConfigService $taxConfig
    ) {}

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
     * Optionally accepts a User to check for enhanced annuity eligibility via their
     * ProtectionProfile (smoker status, health status). Enhanced annuity rates offer
     * better income for individuals with reduced life expectancy.
     *
     * @param  float  $pensionPot  DC pension pot value
     * @param  int  $age  Current age
     * @param  bool  $spouse  Whether to include spouse benefits
     * @param  User|null  $user  Optional user for enhanced annuity assessment
     */
    public function compareAnnuityVsDrawdown(float $pensionPot, int $age, bool $spouse = false, ?User $user = null): array
    {
        // Base annuity rate from TaxConfigService
        $annuityRate = $this->getAnnuityRate($age, $spouse);

        // Check for enhanced annuity eligibility via smoker/health status
        $enhancedInfo = $this->assessEnhancedAnnuityEligibility($user);
        $enhancedAnnuityRate = $annuityRate * $enhancedInfo['enhancement_factor'];

        $annuityIncome = $pensionPot * $enhancedAnnuityRate;

        // Drawdown scenario using safe withdrawal rate
        $drawdownRate = (float) $this->taxConfig->get('retirement.withdrawal_rates.safe', 0.04);
        $drawdownIncome = $pensionPot * $drawdownRate;

        return [
            'annuity' => [
                'annual_income' => round($annuityIncome, 2),
                'guaranteed' => true,
                'inflation_protected' => false, // Level annuity
                'death_benefits' => $spouse ? 'Spouse pension included' : 'No death benefits',
                'flexibility' => 'None - irreversible decision',
                'enhanced_annuity_eligible' => $enhancedInfo['is_eligible'],
                'enhanced_annuity_reason' => $enhancedInfo['reason'],
                'enhancement_factor' => $enhancedInfo['enhancement_factor'],
                'base_annuity_rate' => round($annuityRate * 100, 2),
                'effective_annuity_rate' => round($enhancedAnnuityRate * 100, 2),
                'pros' => [
                    'Guaranteed income for life',
                    'No investment risk',
                    'Simplicity',
                    ...($enhancedInfo['is_eligible'] ? ['Enhanced rate may offer significantly higher income'] : []),
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
     * Assess whether the user is eligible for enhanced annuity rates.
     *
     * Smokers and those with certain health conditions typically receive higher
     * annuity rates because of reduced life expectancy. The enhancement factor
     * reflects the typical 15-25% increase in annuity income.
     *
     * Smoker and health status are stored on the ProtectionProfile (captured
     * during onboarding), not on the User model directly.
     *
     * @return array{is_eligible: bool, reason: string|null, enhancement_factor: float}
     */
    public function assessEnhancedAnnuityEligibility(?User $user): array
    {
        if ($user === null) {
            return [
                'is_eligible' => false,
                'reason' => null,
                'enhancement_factor' => 1.0,
            ];
        }

        $user->loadMissing('protectionProfile');
        $protectionProfile = $user->protectionProfile;

        if ($protectionProfile === null) {
            return [
                'is_eligible' => false,
                'reason' => null,
                'enhancement_factor' => 1.0,
            ];
        }

        $smokerStatus = $protectionProfile->smoker_status;
        $healthStatus = $protectionProfile->health_status;

        $isSmoker = (bool) $smokerStatus;
        $hasHealthCondition = in_array($healthStatus, ['poor', 'fair'], true);

        if (! $isSmoker && ! $hasHealthCondition) {
            return [
                'is_eligible' => false,
                'reason' => null,
                'enhancement_factor' => 1.0,
            ];
        }

        // Calculate enhancement factor
        // Smokers typically receive 15-25% higher annuity rates
        // Health conditions add a further 10-15% enhancement
        $factor = 1.0;
        $reasons = [];

        if ($isSmoker) {
            $factor *= 1.20; // 20% enhancement for smokers (middle of 15-25% range)
            $reasons[] = 'smoker status';
        }

        if ($hasHealthCondition) {
            $factor *= 1.15; // 15% enhancement for health conditions
            $reasons[] = sprintf('%s health status', $healthStatus);
        }

        return [
            'is_eligible' => true,
            'reason' => sprintf(
                'You may qualify for enhanced annuity rates due to %s. Enhanced annuities offer higher income because providers factor in reduced life expectancy.',
                implode(' and ', $reasons)
            ),
            'enhancement_factor' => round($factor, 4),
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

        // Calculate income from remaining pot using safe withdrawal rate
        $safeWithdrawalRate = (float) $this->taxConfig->get('retirement.withdrawal_rates.safe', 0.04);
        $annualIncomeFromRemainingPot = $remainingPot * $safeWithdrawalRate;

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

        $incomeTax = $this->taxConfig->getIncomeTax();
        $personalAllowance = (int) ($incomeTax['personal_allowance'] ?? 12570);
        $higherRateThreshold = (int) ($incomeTax['higher_rate_threshold'] ?? 50270);

        return [
            'phasing_strategy' => $phasingStrategy,
            'tax_efficiency_tips' => [
                'Use personal allowance (£'.number_format($personalAllowance).') efficiently each year',
                'Avoid breaching higher rate threshold (£'.number_format($higherRateThreshold).') if possible',
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
     *
     * Uses annuity rate estimates from TaxConfigService, keyed by age bracket.
     * Falls back to hardcoded estimates if config is unavailable.
     */
    private function getAnnuityRate(int $age, bool $spouse): float
    {
        $annuityRates = $this->taxConfig->get('retirement.annuity_rate_estimates', []);

        if (! empty($annuityRates)) {
            $type = $spouse ? 'joint' : 'single';

            // Find the closest age bracket (keys are '55', '60', '65', '70', '75')
            $closestAge = null;
            foreach (array_keys($annuityRates) as $bracketAge) {
                $bracketAge = (int) $bracketAge;
                if ($closestAge === null || abs($age - $bracketAge) < abs($age - $closestAge)) {
                    $closestAge = $bracketAge;
                }
            }

            if ($closestAge !== null && isset($annuityRates[(string) $closestAge][$type])) {
                return (float) $annuityRates[(string) $closestAge][$type];
            }
        }

        // Fallback: hardcoded estimates if config unavailable
        $baseRate = match (true) {
            $age < 60 => 0.04,
            $age < 65 => 0.045,
            $age < 70 => 0.055,
            $age < 75 => 0.065,
            default => 0.075,
        };

        if ($spouse) {
            $baseRate *= self::JOINT_ANNUITY_RATE_REDUCTION;
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
