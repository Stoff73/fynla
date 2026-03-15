<?php

declare(strict_types=1);

namespace App\Services\Estate;

use App\Models\User;
use App\Services\Settings\AssumptionsService;
use App\Services\TaxConfigService;

/**
 * Calculate Whole of Life Insurance Strategy vs. Self-Insurance
 *
 * This service calculates premiums for whole of life policies and compares
 * with the alternative of investing premiums over the user's expected lifetime.
 */
class LifePolicyStrategyService
{
    /**
     * UK Whole of Life Insurance Premium Table (per £1,000 of cover per month)
     *
     * Based on 2025 UK market averages for non-smokers in good health
     * Data sourced from major UK insurers (Aviva, LegalGeneral, Royal London)
     *
     * Structure: [age => [male_rate, female_rate]]
     */
    private const PREMIUM_TABLE = [
        // Ages 18-29
        18 => [0.80, 0.65],
        20 => [0.80, 0.65],
        25 => [0.85, 0.70],

        // Ages 30-39
        30 => [0.95, 0.80],
        35 => [1.10, 0.95],

        // Ages 40-49
        40 => [1.40, 1.20],
        45 => [1.85, 1.55],

        // Ages 50-59
        50 => [2.55, 2.10],
        55 => [3.60, 2.95],

        // Ages 60-69
        60 => [5.20, 4.15],
        65 => [7.80, 6.20],

        // Ages 70-79
        70 => [12.50, 9.80],
        75 => [19.50, 15.20],

        // Ages 80+
        80 => [31.00, 24.00],
        85 => [48.00, 37.00],
        90 => [75.00, 58.00],
    ];

    /**
     * Fallback investment return rate if AssumptionsService unavailable
     */
    private const FALLBACK_INVESTMENT_RETURN_RATE = 0.047;

    public function __construct(
        private readonly AssumptionsService $assumptionsService,
        private readonly TaxConfigService $taxConfig
    ) {}

    /**
     * Calculate whole of life policy strategy with self-insurance comparison
     *
     * @param  float  $coverAmount  The IHT liability amount to insure
     * @param  int  $yearsUntilDeath  Years until expected death
     * @param  int  $currentAge  User's current age
     * @param  string  $gender  User's gender ('male' or 'female')
     * @param  int|null  $spouseAge  Spouse age for joint life second death policy
     * @param  string|null  $spouseGender  Spouse gender for joint life policy
     * @return array Strategy comparison
     */
    public function calculateStrategy(
        float $coverAmount,
        int $yearsUntilDeath,
        int $currentAge,
        string $gender,
        ?int $spouseAge = null,
        ?string $spouseGender = null,
        ?User $user = null
    ): array {
        // Determine if this is a joint life policy
        $isJointPolicy = $spouseAge !== null && $spouseGender !== null;

        // Calculate whole of life policy costs
        $policyData = $this->calculateWholeOfLifePolicy(
            $coverAmount,
            $yearsUntilDeath,
            $currentAge,
            $gender,
            $spouseAge,
            $spouseGender
        );

        // Calculate self-insurance alternative
        $investmentReturnRate = $this->getInvestmentReturnRate($user);
        $selfInsuranceData = $this->calculateSelfInsurance(
            $policyData['annual_premium'],
            $yearsUntilDeath,
            $coverAmount,
            $investmentReturnRate
        );

        // Generate comparison and recommendation
        $comparison = $this->generateComparison($policyData, $selfInsuranceData, $coverAmount);

        return [
            'success' => true,
            'cover_amount' => round($coverAmount, 2),
            'years_until_death' => $yearsUntilDeath,
            'current_age' => $currentAge,
            'gender' => $gender,
            'is_joint_policy' => $isJointPolicy,
            'spouse_age' => $spouseAge,
            'spouse_gender' => $spouseGender,

            'whole_of_life_policy' => $policyData,
            'self_insurance' => $selfInsuranceData,
            'comparison' => $comparison,
        ];
    }

    /**
     * Calculate whole of life insurance policy costs and details
     */
    private function calculateWholeOfLifePolicy(
        float $coverAmount,
        int $yearsUntilDeath,
        int $currentAge,
        string $gender,
        ?int $spouseAge,
        ?string $spouseGender
    ): array {
        $isJointPolicy = $spouseAge !== null && $spouseGender !== null;

        if ($isJointPolicy) {
            // Joint Life Second Death policy - cheaper than single life
            $monthlyPremiumPerThousand = $this->calculateJointLifePremium(
                $currentAge,
                $gender,
                $spouseAge,
                $spouseGender
            );
            $policyType = 'Joint Life Second Death';
            $policyDescription = 'Pays out on the second death only. Specifically designed for IHT planning for married couples.';
        } else {
            // Single life policy
            $monthlyPremiumPerThousand = $this->getPremiumRate($currentAge, $gender);
            $policyType = 'Whole of Life (Single Life)';
            $policyDescription = 'Guaranteed payout whenever death occurs. Premium fixed for life.';
        }

        $monthlyPremium = ($coverAmount / 1000) * $monthlyPremiumPerThousand;
        $annualPremium = $monthlyPremium * 12;
        $totalPremiumsPaid = $annualPremium * $yearsUntilDeath;

        $costBenefitRatio = $totalPremiumsPaid > 0 ? $coverAmount / $totalPremiumsPaid : 0;

        return [
            'policy_type' => $policyType,
            'description' => $policyDescription,
            'cover_amount' => round($coverAmount, 2),
            'monthly_premium' => round($monthlyPremium, 2),
            'annual_premium' => round($annualPremium, 2),
            'term_years' => $yearsUntilDeath,
            'total_premiums_paid' => round($totalPremiumsPaid, 2),
            'cost_benefit_ratio' => round($costBenefitRatio, 2),
            'guaranteed_payout' => round($coverAmount, 2),

            'key_features' => [
                'Guaranteed payout at death (whenever that occurs)',
                'Must be written in trust to keep outside estate',
                $isJointPolicy ?
                    'Joint life second death policy pays out on second death' :
                    'Can convert to joint policy if marrying in future',
            ],

            'implementation_steps' => [
                '1. Obtain quotes from 3-5 UK insurers (Aviva, Legal & General, Royal London, etc.)',
                '2. Complete medical underwriting (questionnaire or medical exam)',
                '3. Set up policy written in trust (keep proceeds outside estate)',
                '4. Arrange monthly Direct Debit for premium payments',
                '5. Review annually - increase cover for inflation if needed',
                '6. Notify trustees and beneficiaries of policy details',
            ],
        ];
    }

    /**
     * Calculate self-insurance alternative (invest premiums instead)
     */
    private function calculateSelfInsurance(
        float $annualPremium,
        int $years,
        float $targetAmount,
        float $investmentReturnRate
    ): array {
        // Calculate future value of annual premium investments at assumed return rate
        $futureValue = $this->calculateFutureValueOfAnnuity(
            $annualPremium,
            $investmentReturnRate,
            $years
        );

        $totalInvested = $annualPremium * $years;
        $investmentGrowth = $futureValue - $totalInvested;

        $shortfall = max(0, $targetAmount - $futureValue);
        $surplus = max(0, $futureValue - $targetAmount);
        $coveragePercentage = $targetAmount > 0 ? ($futureValue / $targetAmount) * 100 : 0;

        return [
            'strategy_name' => 'Self-Insurance (Invest Premiums)',
            'description' => 'Instead of buying insurance, invest the equivalent premium amount and build your own fund to cover IHT liability.',

            'monthly_investment' => round($annualPremium / 12, 2),
            'annual_investment' => round($annualPremium, 2),
            'investment_term_years' => $years,
            'assumed_return_rate' => $investmentReturnRate,
            'assumed_return_percentage' => $investmentReturnRate * 100,

            'total_invested' => round($totalInvested, 2),
            'investment_growth' => round($investmentGrowth, 2),
            'projected_fund_value' => round($futureValue, 2),

            'target_amount' => round($targetAmount, 2),
            'shortfall' => round($shortfall, 2),
            'surplus' => round($surplus, 2),
            'coverage_percentage' => round($coveragePercentage, 1),

            'is_sufficient' => $futureValue >= $targetAmount,
            'confidence_level' => $this->getConfidenceLevel($coveragePercentage),

            'pros' => [
                'Funds remain accessible if needed before death',
                'Potential for higher returns than insurance cost',
                'Flexibility to adjust contributions up or down',
                'No medical underwriting required',
                'Can benefit from tax-efficient wrappers (ISA, Bond, Pension)',
                'Surplus can be passed to beneficiaries if target exceeded',
            ],

            'cons' => [
                'Investment risk - markets may underperform '.round($investmentReturnRate * 100, 1).'% assumption',
                'No guaranteed payout like insurance provides',
                'Requires financial discipline to maintain contributions',
                'Early death means insufficient time to accumulate funds',
                'Inflation risk - target IHT may increase over time',
                'Temptation to access funds for other purposes',
            ],

            'implementation_steps' => [
                '1. Open tax-efficient investment wrapper (ISA, Investment Bond, or Pension)',
                '2. Set up monthly Direct Debit for £'.number_format($annualPremium / 12, 2),
                '3. Invest in diversified portfolio matching risk tolerance',
                '4. Review quarterly and rebalance portfolio',
                '5. Increase contributions for inflation (3-4% annually)',
                '6. Monitor progress vs. target - adjust if underperforming',
                '7. Ring-fence funds specifically for IHT (don\'t raid for other goals)',
            ],

            'recommended_investment_approach' => [
                'Asset allocation' => 'Balanced portfolio (60% equities, 40% bonds) for long-term growth',
                'Tax wrapper' => 'Investment Bond (for IHT planning) or ISA (if allowance available)',
                'Review frequency' => 'Quarterly portfolio review, annual contribution increase',
                'Risk management' => 'De-risk portfolio as you age (shift to bonds in final 10 years)',
            ],
        ];
    }

    /**
     * Generate comparison between insurance and self-insurance
     */
    private function generateComparison(
        array $policyData,
        array $selfInsuranceData,
        float $coverAmount
    ): array {
        $recommendations = [];
        $recommendedApproach = '';

        // Check self-insurance viability
        if ($selfInsuranceData['coverage_percentage'] >= 100) {
            $recommendations[] = [
                'priority' => 1,
                'option' => 'Self-Insurance',
                'rationale' => 'Projected investment returns cover '.round($selfInsuranceData['coverage_percentage'], 0).'% of IHT liability. You keep control of funds and potential surplus.',
                'suitability' => 'Best if you have financial discipline and comfortable with investment risk',
            ];
            $recommendedApproach = 'Self-Insurance';
        }

        // Life insurance recommendation
        if ($policyData['cost_benefit_ratio'] >= 1.5) {
            $recommendations[] = [
                'priority' => $selfInsuranceData['coverage_percentage'] >= 100 ? 2 : 1,
                'option' => 'Whole of Life Insurance',
                'rationale' => 'Excellent value - you get £'.number_format($policyData['cost_benefit_ratio'], 2).' of cover for every £1 of premiums paid. Guaranteed payout.',
                'suitability' => 'Best if you want certainty and guaranteed IHT coverage',
            ];

            if ($recommendedApproach === '') {
                $recommendedApproach = 'Whole of Life Insurance';
            }
        } else {
            $recommendations[] = [
                'priority' => $selfInsuranceData['coverage_percentage'] >= 100 ? 2 : 1,
                'option' => 'Whole of Life Insurance',
                'rationale' => 'Guaranteed coverage but less cost-effective (£'.number_format($policyData['cost_benefit_ratio'], 2).' cover per £1 premiums).',
                'suitability' => 'Suitable if you prioritize certainty over cost-effectiveness',
            ];

            if ($recommendedApproach === '') {
                $recommendedApproach = 'Hybrid Approach';
            }
        }

        // Hybrid approach recommendation
        $recommendations[] = [
            'priority' => 3,
            'option' => 'Hybrid Approach',
            'rationale' => 'Buy reduced insurance (e.g., 50% of IHT) AND invest remaining premium equivalent. Balances certainty with flexibility.',
            'suitability' => 'Best of both worlds - guaranteed base cover plus investment upside',
        ];

        // Determine overall recommendation
        if ($recommendedApproach === 'Self-Insurance' && $selfInsuranceData['coverage_percentage'] < 110) {
            $recommendedApproach = 'Hybrid Approach'; // Too close to target for comfort
        }

        return [
            'recommended_approach' => $recommendedApproach,
            'all_recommendations' => $recommendations,

            'key_differences' => [
                [
                    'aspect' => 'Certainty',
                    'insurance' => 'Guaranteed payout regardless of when death occurs',
                    'self_insurance' => 'Depends on investment performance and time horizon',
                ],
                [
                    'aspect' => 'Flexibility',
                    'insurance' => 'Premiums must be paid; cancellation loses all value',
                    'self_insurance' => 'Can access funds anytime; adjust contributions freely',
                ],
                [
                    'aspect' => 'Cost Effectiveness',
                    'insurance' => '£'.number_format($policyData['cost_benefit_ratio'], 2).' coverage per £1 of premiums',
                    'self_insurance' => '£'.number_format($selfInsuranceData['coverage_percentage'] / 100, 2).' coverage per £1 invested (projected)',
                ],
                [
                    'aspect' => 'Early Death Risk',
                    'insurance' => 'Full cover from day one',
                    'self_insurance' => 'Insufficient funds if death occurs early',
                ],
                [
                    'aspect' => 'Longevity Risk',
                    'insurance' => 'Premiums continue for longer than expected',
                    'self_insurance' => 'More time to accumulate; likely surplus',
                ],
                [
                    'aspect' => 'Tax Efficiency',
                    'insurance' => 'Proceeds tax-free if written in trust',
                    'self_insurance' => 'Can use ISA/Bond wrappers for tax-efficient growth',
                ],
            ],

            'decision_framework' => [
                'Choose Insurance if:' => [
                    'You want guaranteed coverage from day one',
                    'You prefer certainty over potential returns',
                    'You have health conditions (lock in rates now)',
                    'You lack discipline to maintain investments',
                    'Cost-benefit ratio is very favorable (>2.0)',
                ],
                'Choose Self-Insurance if:' => [
                    'Projected returns cover 110%+ of target',
                    'You have strong financial discipline',
                    'You want to retain control of capital',
                    'You have long time horizon (20+ years)',
                    'You\'re comfortable with investment risk',
                ],
                'Choose Hybrid if:' => [
                    'You want some guaranteed base cover',
                    'Projected returns are 90-110% of target',
                    'You want balance between certainty and flexibility',
                    'You can afford split approach',
                ],
            ],

            'summary' => $this->generateSummary(
                $recommendedApproach,
                $policyData,
                $selfInsuranceData,
                $coverAmount
            ),
        ];
    }

    /**
     * Get premium rate per £1,000 of cover per month
     */
    private function getPremiumRate(int $age, string $gender): float
    {
        $genderIndex = strtolower($gender) === 'female' ? 1 : 0;

        // Find exact match
        if (isset(self::PREMIUM_TABLE[$age])) {
            return self::PREMIUM_TABLE[$age][$genderIndex];
        }

        // Linear interpolation between known ages
        $ages = array_keys(self::PREMIUM_TABLE);
        sort($ages);

        // If younger than minimum age
        if ($age < $ages[0]) {
            return self::PREMIUM_TABLE[$ages[0]][$genderIndex];
        }

        // If older than maximum age
        if ($age > $ages[count($ages) - 1]) {
            return self::PREMIUM_TABLE[$ages[count($ages) - 1]][$genderIndex];
        }

        // Find surrounding ages
        $lowerAge = null;
        $upperAge = null;

        foreach ($ages as $tableAge) {
            if ($tableAge < $age) {
                $lowerAge = $tableAge;
            } elseif ($tableAge > $age) {
                $upperAge = $tableAge;
                break;
            }
        }

        // Interpolate
        $lowerRate = self::PREMIUM_TABLE[$lowerAge][$genderIndex];
        $upperRate = self::PREMIUM_TABLE[$upperAge][$genderIndex];

        $fraction = ($age - $lowerAge) / ($upperAge - $lowerAge);

        return $lowerRate + (($upperRate - $lowerRate) * $fraction);
    }

    /**
     * Calculate joint life second death premium (approximately 25% cheaper)
     */
    private function calculateJointLifePremium(
        int $userAge,
        string $userGender,
        int $spouseAge,
        string $spouseGender
    ): float {
        // Get individual rates
        $userRate = $this->getPremiumRate($userAge, $userGender);
        $spouseRate = $this->getPremiumRate($spouseAge, $spouseGender);

        // Joint second death is approximately 75% of the average of two single rates
        $averageRate = ($userRate + $spouseRate) / 2;
        $jointDiscount = 0.75;

        return $averageRate * $jointDiscount;
    }

    /**
     * Calculate future value of annuity (regular investments)
     *
     * FV = PMT × [(1 + r)^n - 1] / r
     */
    private function calculateFutureValueOfAnnuity(
        float $payment,
        float $rate,
        int $periods
    ): float {
        if ($rate == 0) {
            return $payment * $periods;
        }

        return $payment * ((pow(1 + $rate, $periods) - 1) / $rate);
    }

    /**
     * Get confidence level based on coverage percentage
     */
    private function getConfidenceLevel(float $coveragePercentage): string
    {
        if ($coveragePercentage >= 120) {
            return 'Very High';
        } elseif ($coveragePercentage >= 110) {
            return 'High';
        } elseif ($coveragePercentage >= 100) {
            return 'Medium-High';
        } elseif ($coveragePercentage >= 90) {
            return 'Medium';
        } elseif ($coveragePercentage >= 75) {
            return 'Medium-Low';
        } else {
            return 'Low';
        }
    }

    /**
     * Generate summary text
     */
    private function generateSummary(
        string $recommendedApproach,
        array $policyData,
        array $selfInsuranceData,
        float $coverAmount
    ): string {
        $summaries = [
            'Whole of Life Insurance' => sprintf(
                'Based on your age and life expectancy, whole of life insurance offers the best value. '.
                'You would pay £%s annually for guaranteed £%s coverage - a cost-benefit ratio of %.2f:1. '.
                'This provides certainty and peace of mind for your beneficiaries.',
                number_format($policyData['annual_premium'], 0),
                number_format($coverAmount, 0),
                $policyData['cost_benefit_ratio']
            ),

            'Self-Insurance' => sprintf(
                'Self-insurance appears viable for your situation. Investing £%s annually at %s%% returns '.
                'is projected to accumulate £%s by expected death - covering %d%% of your IHT liability. '.
                'This approach offers flexibility and potential surplus for beneficiaries.',
                number_format($selfInsuranceData['annual_investment'], 0),
                number_format($selfInsuranceData['assumed_return_percentage'], 1),
                number_format($selfInsuranceData['projected_fund_value'], 0),
                (int) $selfInsuranceData['coverage_percentage']
            ),

            'Hybrid Approach' => sprintf(
                'A balanced approach is recommended. Consider purchasing 50-60%% insurance coverage (£%s-£%s) '.
                'for guaranteed base protection, and investing the remaining premium equivalent to potentially '.
                'cover the rest. This balances certainty with flexibility and cost-effectiveness.',
                number_format($coverAmount * 0.5, 0),
                number_format($coverAmount * 0.6, 0)
            ),
        ];

        return $summaries[$recommendedApproach] ?? $summaries['Hybrid Approach'];
    }

    /**
     * Get investment return rate from AssumptionsService.
     * Falls back to default rate if no user-specific assumption is configured.
     */
    private function getInvestmentReturnRate(?User $user): float
    {
        if ($user === null) {
            return self::FALLBACK_INVESTMENT_RETURN_RATE;
        }

        $assumptions = $this->assumptionsService->getEstateAssumptions($user);

        if (($assumptions['investment_growth_method'] ?? 'monte_carlo') === 'custom'
            && isset($assumptions['custom_investment_rate'])) {
            return (float) $assumptions['custom_investment_rate'] / 100;
        }

        return self::FALLBACK_INVESTMENT_RETURN_RATE;
    }
}
