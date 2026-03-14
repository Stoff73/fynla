<?php

declare(strict_types=1);

namespace App\Services\Estate;

use App\Models\LifeInsurancePolicy;
use App\Models\User;
use App\Services\Settings\AssumptionsService;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class LifeCoverCalculator
{
    public function __construct(
        private readonly AssumptionsService $assumptionsService
    ) {}

    /**
     * Calculate life cover recommendations for IHT liability
     *
     * Provides three scenarios:
     * 1. Full life cover (covers entire IHT liability)
     * 2. Life cover less gifting (covers IHT after gifting strategy)
     * 3. Self-insurance option (invest premiums instead of buying cover)
     *
     * @param  float  $ihtLiability  IHT liability at expected death
     * @param  float  $ihtLiabilityAfterGifting  IHT liability after gifting strategy applied
     * @param  int  $yearsUntilDeath  Years until expected death
     * @param  User  $user  User (for age calculation)
     * @param  User|null  $spouse  Spouse (for joint life second death policy)
     * @param  float  $existingCover  Existing life cover already in place
     * @return array Life cover recommendations
     */
    public function calculateLifeCoverRecommendations(
        float $ihtLiability,
        float $ihtLiabilityAfterGifting,
        int $yearsUntilDeath,
        User $user,
        ?User $spouse = null,
        float $existingCover = 0
    ): array {
        if (! $user->date_of_birth) {
            return [
                'success' => false,
                'error' => 'User date of birth required for life cover calculation',
            ];
        }

        $userAge = Carbon::parse($user->date_of_birth)->age;

        // If spouse exists and married, calculate joint life second death
        $isJointPolicy = $spouse !== null && $user->marital_status === 'married';
        $spouseAge = null;

        if ($isJointPolicy && $spouse->date_of_birth) {
            $spouseAge = Carbon::parse($spouse->date_of_birth)->age;
        }

        // Scenario 1: Full Life Cover
        $fullCoverScenario = $this->calculateFullLifeCover(
            $ihtLiability,
            $yearsUntilDeath,
            $userAge,
            $spouseAge,
            $isJointPolicy
        );

        // Scenario 2: Life Cover Less Gifting
        $coverLessGiftingScenario = $this->calculateLifeCoverLessGifting(
            $ihtLiabilityAfterGifting,
            $yearsUntilDeath,
            $userAge,
            $spouseAge,
            $isJointPolicy
        );

        // Scenario 3: Self-Insurance Option
        $investmentReturn = $this->getInvestmentReturnRate($user);
        $selfInsuranceScenario = $this->calculateSelfInsuranceOption(
            $coverLessGiftingScenario['annual_premium'],
            $yearsUntilDeath,
            $ihtLiabilityAfterGifting,
            $investmentReturn
        );

        return [
            'success' => true,
            'is_joint_policy' => $isJointPolicy,
            'user_age' => $userAge,
            'spouse_age' => $spouseAge,
            'years_until_death' => $yearsUntilDeath,
            'existing_cover' => $existingCover,
            'cover_gap' => max(0, $ihtLiability - $existingCover),

            'scenarios' => [
                'full_cover' => $fullCoverScenario,
                'cover_less_gifting' => $coverLessGiftingScenario,
                'self_insurance' => $selfInsuranceScenario,
            ],

            'recommendation' => $this->generateRecommendation(
                $fullCoverScenario,
                $coverLessGiftingScenario,
                $selfInsuranceScenario,
                $ihtLiability,
                $ihtLiabilityAfterGifting,
                $existingCover
            ),
        ];
    }

    /**
     * Calculate full life cover scenario
     */
    private function calculateFullLifeCover(
        float $coverAmount,
        int $term,
        int $userAge,
        ?int $spouseAge,
        bool $isJointPolicy
    ): array {
        // Estimate premium using industry average rates
        // Joint life second death policies are cheaper than single life
        $annualPremium = $this->estimateWholeOfLifePremium(
            $coverAmount,
            $userAge,
            $spouseAge,
            $isJointPolicy
        );

        $totalPremiumsPaid = $annualPremium * $term;

        return [
            'scenario_name' => 'Full Life Cover',
            'description' => $isJointPolicy ?
                'Joint Life Second Death policy - pays out on second death only' :
                'Whole of Life policy - guaranteed payout',
            'cover_amount' => round($coverAmount, 2),
            'annual_premium' => round($annualPremium, 2),
            'monthly_premium' => round($annualPremium / 12, 2),
            'term_years' => $term,
            'total_premiums_paid' => round($totalPremiumsPaid, 2),
            'cost_benefit_ratio' => $totalPremiumsPaid > 0 ?
                round($coverAmount / $totalPremiumsPaid, 2) : 0,
            'implementation' => [
                'Write policy in trust to keep outside estate',
                'Review annually and adjust cover for inflation',
                'Consider increasing life cover policy for maximum tax efficiency',
                $isJointPolicy ?
                    'Joint Life Second Death provides lower premiums than two single policies' :
                    'Consider converting to joint policy if marrying in future',
            ],
        ];
    }

    /**
     * Calculate life cover less gifting scenario
     */
    private function calculateLifeCoverLessGifting(
        float $coverAmount,
        int $term,
        int $userAge,
        ?int $spouseAge,
        bool $isJointPolicy
    ): array {
        // If gifting has eliminated IHT, no cover needed
        if ($coverAmount <= 0) {
            return [
                'scenario_name' => 'Life Cover Less Gifting',
                'description' => 'No life cover needed - gifting strategy eliminates IHT liability',
                'cover_amount' => 0,
                'annual_premium' => 0,
                'monthly_premium' => 0,
                'term_years' => $term,
                'total_premiums_paid' => 0,
                'cost_benefit_ratio' => 0,
                'implementation' => [
                    'Focus on implementing gifting strategy',
                    'Review IHT position annually as estate value changes',
                    'Consider small policy for unexpected estate growth',
                ],
            ];
        }

        $annualPremium = $this->estimateWholeOfLifePremium(
            $coverAmount,
            $userAge,
            $spouseAge,
            $isJointPolicy
        );

        $totalPremiumsPaid = $annualPremium * $term;

        return [
            'scenario_name' => 'Life Cover Less Gifting',
            'description' => 'Reduced life cover after implementing gifting strategy',
            'cover_amount' => round($coverAmount, 2),
            'annual_premium' => round($annualPremium, 2),
            'monthly_premium' => round($annualPremium / 12, 2),
            'term_years' => $term,
            'total_premiums_paid' => round($totalPremiumsPaid, 2),
            'cost_benefit_ratio' => $totalPremiumsPaid > 0 ?
                round($coverAmount / $totalPremiumsPaid, 2) : 0,
            'implementation' => [
                'Implement gifting strategy first to reduce required cover',
                'Write policy in trust',
                'Review cover annually - reduce as gifts become exempt',
                'Most cost-effective option combining gifting and insurance',
            ],
        ];
    }

    /**
     * Calculate self-insurance option (invest premiums instead)
     */
    private function calculateSelfInsuranceOption(
        float $annualPremium,
        int $years,
        float $targetAmount,
        float $investmentReturn = 0.047
    ): array {

        // Calculate future value of premiums invested annually
        $futureValue = $this->calculateFutureValueOfAnnuity(
            $annualPremium,
            $investmentReturn,
            $years
        );

        $totalInvested = $annualPremium * $years;
        $investmentGrowth = $futureValue - $totalInvested;

        // How much of target is covered?
        $coveragePercentage = $targetAmount > 0 ?
            ($futureValue / $targetAmount) * 100 : 0;

        // Break-even analysis
        $shortfall = max(0, $targetAmount - $futureValue);
        $surplus = max(0, $futureValue - $targetAmount);

        return [
            'scenario_name' => 'Self-Insurance Option',
            'description' => 'Invest premiums instead of buying life cover',
            'annual_investment' => round($annualPremium, 2),
            'monthly_investment' => round($annualPremium / 12, 2),
            'investment_term_years' => $years,
            'assumed_return_rate' => $investmentReturn,
            'total_invested' => round($totalInvested, 2),
            'investment_growth' => round($investmentGrowth, 2),
            'projected_value_at_death' => round($futureValue, 2),
            'target_amount' => round($targetAmount, 2),
            'shortfall' => round($shortfall, 2),
            'surplus' => round($surplus, 2),
            'coverage_percentage' => round($coveragePercentage, 1),
            'break_even_analysis' => [
                'is_sufficient' => $futureValue >= $targetAmount,
                'confidence_level' => $coveragePercentage >= 100 ? 'High' : 'Medium-Low',
            ],
            'implementation' => [
                'Set up monthly standing order to investment account',
                'Use tax-efficient wrapper (ISA, pension, bond)',
                'Invest in diversified portfolio matching risk tolerance',
                'Review quarterly and adjust if markets underperform',
                'Consider hybrid approach: part insurance, part investment',
            ],
            'pros' => [
                'Keep invested funds accessible if not needed',
                'Potential for higher returns than insurance',
                'Flexibility to adjust contributions',
                'No medical underwriting required',
            ],
            'cons' => [
                'Investment risk - markets may underperform',
                'No guaranteed payout like insurance',
                'Requires discipline to maintain contributions',
                'Early death means insufficient funds accumulated',
            ],
            'notes' => $coveragePercentage >= 100 ?
                'Self-insurance appears viable based on projected returns' :
                'Self-insurance may leave a shortfall - consider hybrid approach',
        ];
    }

    /**
     * Estimate whole of life premium (industry averages)
     *
     * Uses approximate industry rates for whole of life cover
     */
    private function estimateWholeOfLifePremium(
        float $coverAmount,
        int $userAge,
        ?int $spouseAge,
        bool $isJointPolicy
    ): float {
        // Base rate per £1,000 of cover (monthly)
        // These are approximate industry averages for non-smokers in good health
        $baseRatePerThousand = 1.50; // £1.50 per £1,000 cover per month

        // Age loading factor (increases with age)
        $ageLoadingFactor = 1.0;
        $averageAge = $isJointPolicy && $spouseAge ?
            ($userAge + $spouseAge) / 2 :
            $userAge;

        if ($averageAge < 40) {
            $ageLoadingFactor = 0.8;
        } elseif ($averageAge >= 40 && $averageAge < 50) {
            $ageLoadingFactor = 1.0;
        } elseif ($averageAge >= 50 && $averageAge < 60) {
            $ageLoadingFactor = 1.5;
        } elseif ($averageAge >= 60 && $averageAge < 70) {
            $ageLoadingFactor = 2.5;
        } else {
            $ageLoadingFactor = 4.0;
        }

        // Joint life second death discount (cheaper than single life)
        $jointDiscount = $isJointPolicy ? 0.75 : 1.0;

        // Calculate monthly premium
        $monthlyPremium = ($coverAmount / 1000) *
                         $baseRatePerThousand *
                         $ageLoadingFactor *
                         $jointDiscount;

        // Return annual premium
        return $monthlyPremium * 12;
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
     * Generate overall recommendation based on scenarios
     */
    private function generateRecommendation(
        array $fullCoverScenario,
        array $coverLessGiftingScenario,
        array $selfInsuranceScenario,
        float $originalIHT,
        float $ihtAfterGifting,
        float $existingCover = 0
    ): array {
        $recommendations = [];

        // Check if existing cover is sufficient
        if ($existingCover >= $originalIHT) {
            $recommendations[] = [
                'priority' => 1,
                'recommendation' => 'Existing Cover is Sufficient',
                'rationale' => 'You have £'.number_format($existingCover, 0).' life cover, which exceeds your IHT liability of £'.number_format($originalIHT, 0),
                'action' => 'Ensure policies are written in trust and review beneficiary nominations',
            ];

            return [
                'recommended_approach' => 'Existing Cover is Sufficient',
                'all_recommendations' => $recommendations,
                'summary' => 'Your existing life cover is adequate. Focus on ensuring policies are written in trust to keep proceeds outside your estate.',
            ];
        }

        // Recommend gifting if it reduces IHT significantly
        if ($ihtAfterGifting < $originalIHT * 0.5) {
            $recommendations[] = [
                'priority' => 1,
                'recommendation' => 'Implement Gifting Strategy First',
                'rationale' => 'Gifting reduces IHT by '.round((($originalIHT - $ihtAfterGifting) / $originalIHT) * 100, 0).'% - most tax-efficient approach',
                'action' => 'Use annual exemptions and PETs to reduce estate value',
            ];
        }

        // Calculate gap after existing cover
        $coverGap = max(0, $ihtAfterGifting - $existingCover);

        // Recommend life cover if significant gap remains after existing cover and gifting
        if ($coverGap > 50000) {
            $recommendations[] = [
                'priority' => 2,
                'recommendation' => 'Life Cover Less Gifting',
                'rationale' => 'Most cost-effective insurance option after gifting strategy. You need £'.number_format($coverGap, 0).' additional cover.',
                'action' => 'Arrange £'.number_format($coverGap, 0).' whole of life cover written in trust (in addition to existing £'.number_format($existingCover, 0).')',
            ];
        } elseif ($existingCover > 0 && $coverGap > 0) {
            $recommendations[] = [
                'priority' => 2,
                'recommendation' => 'Small Additional Cover Needed',
                'rationale' => 'Your existing £'.number_format($existingCover, 0).' cover leaves a gap of £'.number_format($coverGap, 0),
                'action' => 'Consider arranging an additional £'.number_format($coverGap, 0).' cover or using self-insurance',
            ];
        }

        // Consider self-insurance if coverage is sufficient
        if ($selfInsuranceScenario['coverage_percentage'] >= 90) {
            $recommendations[] = [
                'priority' => 3,
                'recommendation' => 'Self-Insurance Option',
                'rationale' => 'Investment approach covers '.round($selfInsuranceScenario['coverage_percentage'], 0).'% of IHT - more flexible',
                'action' => 'Invest £'.number_format($selfInsuranceScenario['annual_investment'], 0).'/year in tax-efficient wrapper',
            ];
        }

        // Default recommendation if nothing else applies
        if (empty($recommendations)) {
            $recommendations[] = [
                'priority' => 1,
                'recommendation' => 'Full Life Cover',
                'rationale' => 'Guaranteed protection against full IHT liability',
                'action' => 'Arrange £'.number_format($fullCoverScenario['cover_amount'], 0).' whole of life cover written in trust',
            ];
        }

        return [
            'recommended_approach' => $recommendations[0]['recommendation'],
            'all_recommendations' => $recommendations,
            'summary' => 'Consider combining gifting strategy with reduced life cover for optimal IHT planning',
        ];
    }

    /**
     * Get investment return rate from AssumptionsService.
     * Falls back to 4.7% if no user-specific assumption is configured.
     */
    private function getInvestmentReturnRate(User $user): float
    {
        $assumptions = $this->assumptionsService->getEstateAssumptions($user);

        if (($assumptions['investment_growth_method'] ?? 'monte_carlo') === 'custom'
            && isset($assumptions['custom_investment_rate'])) {
            return (float) $assumptions['custom_investment_rate'] / 100;
        }

        return 0.047;
    }

    /**
     * Assess existing life insurance policies for Inheritance Tax planning suitability.
     *
     * Checks each policy for:
     * - Trust status: is the policy written in trust (keeps proceeds outside the estate)?
     * - Joint life: is it a first-death or second-death policy (second death is more
     *   Inheritance Tax-efficient for married couples)?
     * - Policy type: whole of life vs term (term policies expire and may leave gaps)
     *
     * @param  Collection  $policies  Collection of LifeInsurancePolicy models
     * @param  User  $user  The primary user
     * @return array Assessment with warnings and recommendations
     */
    public function assessExistingPolicies(Collection $policies, User $user): array
    {
        $warnings = [];
        $isMarried = in_array($user->marital_status, ['married'], true);

        foreach ($policies as $policy) {
            // Check trust status
            if (! $policy->in_trust) {
                $sumAssured = number_format((float) $policy->sum_assured);
                $policyName = $policy->provider ?? 'Life Insurance Policy';
                $warnings[] = [
                    'type' => 'not_in_trust',
                    'severity' => 'high',
                    'policy_id' => $policy->id,
                    'message' => "{$policyName} (£{$sumAssured}) is not written in trust. Without trust placement, the policy proceeds will form part of your taxable estate and may be subject to Inheritance Tax. Contact your provider to place this policy in trust.",
                ];
            }

            // Check joint life status for married users
            if (! $policy->joint_life && $isMarried) {
                $warnings[] = [
                    'type' => 'single_life_married',
                    'severity' => 'medium',
                    'policy_id' => $policy->id,
                    'message' => 'This is a single life policy. For Inheritance Tax planning, a joint life second death policy is typically more cost-effective as it pays out on the second death when the Inheritance Tax liability actually arises.',
                ];
            }

            // Check policy type: whole of life vs term
            if ($policy->policy_type !== 'whole_of_life') {
                $endDate = $policy->policy_end_date;
                $expiryWarning = '';

                if ($endDate) {
                    $yearsUntilExpiry = now()->diffInYears($endDate, false);

                    if ($yearsUntilExpiry <= 0) {
                        $expiryWarning = ' This policy has already expired.';
                    } elseif ($yearsUntilExpiry <= 5) {
                        $expiryWarning = ' This policy expires on '.Carbon::parse($endDate)->format('j F Y').' (within '.(int) ceil($yearsUntilExpiry).' years). Review whether replacement cover is needed.';
                    }
                }

                $warnings[] = [
                    'type' => 'not_whole_of_life',
                    'severity' => $endDate && now()->diffInYears($endDate, false) <= 5 ? 'high' : 'low',
                    'policy_id' => $policy->id,
                    'message' => 'This is a term policy, not whole of life cover. Inheritance Tax cover requires whole of life insurance to guarantee a payout whenever death occurs.'.$expiryWarning,
                ];
            }
        }

        return [
            'policy_count' => $policies->count(),
            'warnings' => $warnings,
            'warning_count' => count($warnings),
            'has_critical_warnings' => collect($warnings)->contains('severity', 'high'),
            'summary' => count($warnings) > 0
                ? count($warnings).' potential issue'.($warnings !== 1 ? 's' : '').' found with your existing life insurance policies for Inheritance Tax planning.'
                : 'Your existing life insurance policies are well-structured for Inheritance Tax planning.',
        ];
    }
}
