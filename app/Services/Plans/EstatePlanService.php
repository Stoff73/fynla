<?php

declare(strict_types=1);

namespace App\Services\Plans;

use App\Agents\EstateAgent;
use App\Constants\TaxDefaults;
use App\Models\Estate\Will;
use App\Models\LifeInsurancePolicy;
use App\Models\User;
use App\Services\Estate\IHTCalculationService;
use App\Services\TaxConfigService;

class EstatePlanService extends BasePlanService
{
    public function __construct(
        private readonly EstateAgent $estateAgent,
        private readonly IHTCalculationService $ihtCalculator,
        private readonly TaxConfigService $taxConfig,
        private readonly PlanConfigService $planConfig,
        private readonly DisposableIncomeAccessor $disposableIncome
    ) {}

    public function generatePlan(int $userId, array $options = []): array
    {
        $user = User::with(['spouse'])->findOrFail($userId);
        $completeness = $this->checkDataCompleteness($userId);

        // Gate check: age >= 35
        $currentAge = $user->date_of_birth
            ? (int) $user->date_of_birth->diffInYears(now())
            : null;

        if ($currentAge !== null && $currentAge < $this->planConfig->getEstateAgeGate()) {
            return [
                'metadata' => $this->buildPlanMetadata($user, 'estate', $completeness),
                'not_applicable' => true,
                'not_applicable_reason' => 'Estate planning typically becomes relevant from age 35 onwards. As you build assets and your financial situation evolves, this plan will help you manage your Inheritance Tax position.',
            ];
        }

        // Run analysis once and reuse throughout
        $analysis = $this->estateAgent->analyze($userId);
        $data = $analysis['data'] ?? [];

        // Check for analysis failure first (before gate checks)
        if (! ($analysis['success'] ?? false)) {
            return [
                'metadata' => $this->buildPlanMetadata($user, 'estate', $completeness),
                'completeness_warning' => $this->buildCompletenessWarning($completeness),
                'executive_summary' => $this->buildEmptyExecutiveSummary(),
                'current_situation' => [],
                'actions' => [],
                'what_if' => null,
                'conclusion' => $this->generateDynamicConclusion([], [], 'estate'),
                'error' => $analysis['message'] ?? 'Unable to generate estate analysis.',
            ];
        }

        // Gate check: IHT liability > 0 (use analysis data, no separate calculation)
        $ihtLiability = (float) ($data['summary']['iht_liability'] ?? 0);

        if ($ihtLiability <= 0) {
            return [
                'metadata' => $this->buildPlanMetadata($user, 'estate', $completeness),
                'not_applicable' => true,
                'not_applicable_reason' => 'Based on your current estate position, there is no projected Inheritance Tax liability. If your circumstances change, this plan will provide mitigation strategies to protect your estate.',
            ];
        }

        // Generate recommendations from the same analysis (no redundant analyze() call)
        $recommendations = $this->buildRecommendationsFromAnalysis($analysis);
        $recommendations = $this->enrichRecommendations($recommendations, $user, $data);
        ['actions' => $actions, 'enabledActions' => $enabledActions] = $this->prepareActions($recommendations, 'estate', $options);

        $currentSituation = $this->buildCurrentSituation($data);
        $whatIf = $this->buildWhatIfData($data, $enabledActions);
        $conclusion = $this->generateDynamicConclusion($currentSituation, $enabledActions, 'estate');

        // Build executive summary using the already-computed recommendations count
        $executiveSummary = $this->buildExecutiveSummary($user, $data, count($recommendations));

        // Build joint estate view if married with spouse data
        $jointEstateView = $this->buildJointEstateView($user, $data);

        return [
            'metadata' => $this->buildPlanMetadata($user, 'estate', $completeness),
            'completeness_warning' => $this->buildCompletenessWarning($completeness),
            'executive_summary' => $executiveSummary,
            'current_situation' => $currentSituation,
            'joint_estate_view' => $jointEstateView,
            'actions' => $actions,
            'what_if' => $whatIf,
            'conclusion' => $conclusion,
        ];
    }

    public function getRecommendations(int $userId): array
    {
        $analysis = $this->estateAgent->analyze($userId);

        return $this->buildRecommendationsFromAnalysis($analysis);
    }

    /**
     * Extract recommendations from an existing analysis result.
     */
    private function buildRecommendationsFromAnalysis(array $analysis): array
    {
        if (empty($analysis['data'] ?? [])) {
            return [];
        }

        $result = $this->estateAgent->generateRecommendations($analysis);

        return $result['data']['recommendations'] ?? $result['recommendations'] ?? [];
    }

    /**
     * Enrich recommendations with funding sources, affordability checks, and detailed guidance.
     */
    private function enrichRecommendations(array $recommendations, User $user, array $data): array
    {
        $monthlyDisposable = $this->disposableIncome->getMonthlyForUser($user);
        $liquidAssets = (float) ($data['asset_breakdown']['liquid'] ?? 0);

        foreach ($recommendations as &$rec) {
            $category = $rec['category'] ?? '';

            // Add funding source for charitable and gifting recommendations
            if (in_array($category, ['charitable_bequest', 'annual_gifting', 'pet_gifting', 'clt_trust'])) {
                $rec['funding_source'] = $this->identifyFundingSource($category, $rec, $liquidAssets);
            }

            // Add affordability check for life cover recommendations
            if (in_array($category, ['new_life_cover'])) {
                $estimatedPremium = (float) ($rec['estimated_premium'] ?? 0);
                $monthlyPremium = $estimatedPremium > 0 ? $estimatedPremium / 12 : 0;
                $isAffordable = $monthlyDisposable > 0 && $monthlyPremium <= ($monthlyDisposable * 0.15);

                $rec['affordability'] = [
                    'monthly_premium_estimate' => $this->roundToPenny($monthlyPremium),
                    'monthly_disposable_income' => $this->roundToPenny($monthlyDisposable),
                    'is_affordable' => $isAffordable,
                    'affordability_ratio' => $monthlyDisposable > 0
                        ? round($monthlyPremium / $monthlyDisposable * 100, 1)
                        : 0,
                ];

                if (! $isAffordable && $monthlyPremium > 0) {
                    $rec['affordability_warning'] = sprintf(
                        'The estimated monthly premium of %s represents %.0f%% of your disposable income. Consider a lower cover amount or alternative strategies.',
                        $this->formatCurrency($monthlyPremium),
                        $monthlyDisposable > 0 ? ($monthlyPremium / $monthlyDisposable * 100) : 0
                    );
                }
            }

            // Add detailed "what to do" guidance for each recommendation
            $rec['guidance'] = $this->buildActionGuidance($category, $rec);
        }
        unset($rec);

        return $recommendations;
    }

    /**
     * Identify which accounts a charitable or gifting amount would come from.
     */
    private function identifyFundingSource(string $category, array $rec, float $liquidAssets): array
    {
        $ihtConfig = $this->taxConfig->getInheritanceTax();
        $ihtRate = (float) ($ihtConfig['standard_rate'] ?? TaxDefaults::IHT_RATE);
        $giftingConfig = $this->taxConfig->getGiftingExemptions();
        $annualExemption = (float) ($giftingConfig['annual_exemption'] ?? TaxDefaults::ANNUAL_GIFT_EXEMPTION);

        $amount = match ($category) {
            'charitable_bequest' => (float) ($rec['shortfall'] ?? $rec['potential_saving'] ?? 0),
            'annual_gifting' => $annualExemption,
            'pet_gifting' => $ihtRate > 0 ? (float) ($rec['potential_saving'] ?? 0) / $ihtRate : 0,
            'clt_trust' => (float) ($rec['amount'] ?? 0),
            default => 0,
        };

        return [
            'recommended_from' => $liquidAssets >= $amount ? 'liquid_assets' : 'mixed_assets',
            'liquid_assets_available' => $this->roundToPenny($liquidAssets),
            'amount_needed' => $this->roundToPenny($amount),
            'note' => $liquidAssets >= $amount
                ? 'Can be funded from existing liquid assets (savings and investments).'
                : 'May require restructuring assets or phasing the strategy over time.',
        ];
    }

    /**
     * Build step-by-step guidance for a recommendation.
     */
    private function buildActionGuidance(string $category, array $rec): array
    {
        return match ($category) {
            'charitable_bequest' => [
                'steps' => [
                    'Review your current will with a solicitor.',
                    'Discuss adding or increasing charitable bequests to reach the 10% threshold.',
                    'Ensure charities named are registered with the Charity Commission.',
                    'Update your will and store a copy securely.',
                ],
                'timeframe' => 'Can be completed within 2-4 weeks.',
                'professional_advice' => 'Solicitor or will writer recommended.',
            ],
            'annual_gifting' => [
                'steps' => [
                    'Set up a standing order or annual reminder for gift payments.',
                    'Use your annual exemption each tax year before 5 April.',
                    'Keep records of all gifts including dates, amounts, and recipients.',
                    'Consider gifts from surplus income for additional exemptions.',
                ],
                'timeframe' => 'Start immediately. Review annually before 5 April.',
                'professional_advice' => 'No professional advice typically needed for annual exemptions.',
            ],
            'new_life_cover' => [
                'steps' => [
                    'Obtain quotes from at least 3 life insurance providers.',
                    'Request whole of life cover for the required amount.',
                    'Ensure the policy is written in trust from the outset.',
                    'Consider joint life second death cover if married (usually cheaper).',
                    'Review cover amount periodically as your estate value changes.',
                ],
                'timeframe' => 'Allow 4-8 weeks for medical underwriting and policy setup.',
                'professional_advice' => 'Independent financial adviser recommended for policy selection.',
            ],
            'pet_gifting' => [
                'steps' => [
                    'Identify assets or cash to gift to beneficiaries.',
                    'Ensure you can maintain your standard of living after gifting.',
                    'Make the gift and record the date and amount.',
                    'Survive 7 years for the gift to become fully exempt.',
                    'Consider taper relief if concerned about the 7-year period.',
                ],
                'timeframe' => '7 years for full exemption. Taper relief applies from year 3.',
                'professional_advice' => 'Financial adviser recommended for larger amounts.',
            ],
            'clt_trust' => [
                'steps' => [
                    'Consult a trust specialist or solicitor.',
                    'Determine the trust type (discretionary is most common for Inheritance Tax planning).',
                    'Prepare a trust deed naming trustees and beneficiaries.',
                    'Transfer assets into the trust.',
                    'Register the trust with HMRC if required.',
                    'Budget for the immediate 20% charge on amounts exceeding the Nil Rate Band.',
                ],
                'timeframe' => 'Allow 6-12 weeks for trust establishment.',
                'professional_advice' => 'Specialist trust solicitor essential. Ongoing trustee responsibilities.',
            ],
            'liquidity' => [
                'steps' => [
                    'Review your asset allocation for liquidity.',
                    'Consider whole of life insurance written in trust to cover the Inheritance Tax liability.',
                    'Explore partial property sale or equity release as a last resort.',
                    'Build liquid savings over time to improve your position.',
                ],
                'timeframe' => 'Ongoing. Life insurance can be arranged within 4-8 weeks.',
                'professional_advice' => 'Financial adviser recommended.',
            ],
            default => [
                'steps' => $rec['actions'] ?? [],
                'timeframe' => 'Discuss with your financial adviser.',
                'professional_advice' => 'Seek professional advice before proceeding.',
            ],
        };
    }

    public function checkDataCompleteness(int $userId): array
    {
        $missing = [];

        $hasWill = Will::where('user_id', $userId)->exists();
        if (! $hasWill) {
            $missing[] = [
                'field' => 'will',
                'label' => 'Will',
                'description' => 'Add your will details for charitable bequest analysis.',
                'link' => '/estate',
            ];
        }

        $user = User::find($userId);
        $hasAssets = $user && (
            $user->properties()->exists() ||
            $user->investmentAccounts()->exists() ||
            $user->savingsAccounts()->exists()
        );
        if (! $hasAssets) {
            $missing[] = [
                'field' => 'estate_assets',
                'label' => 'Estate assets',
                'description' => 'Add your properties, savings, and other assets.',
                'link' => '/estate',
            ];
        }

        $hasLifeInsurance = LifeInsurancePolicy::where('user_id', $userId)->exists();
        if (! $hasLifeInsurance) {
            $missing[] = [
                'field' => 'life_insurance',
                'label' => 'Life insurance policies',
                'description' => 'Add your life insurance policies to analyse trust placement opportunities.',
                'link' => '/protection',
            ];
        }

        $total = 3;
        $present = $total - count($missing);

        return [
            'percentage' => (int) round(($present / $total) * 100),
            'missing' => $missing,
            'complete' => empty($missing),
        ];
    }

    /**
     * Build executive summary using pre-computed data (no redundant API calls).
     */
    private function buildExecutiveSummary(User $user, array $data, int $recCount): array
    {
        $firstName = $this->getUserFirstName($user);
        $summary = $data['summary'] ?? [];
        $ihtCalc = $data['iht_calculation'] ?? [];
        $lifeCover = $data['life_cover'] ?? [];
        $profile = $data['profile'] ?? [];

        $grossEstate = $summary['gross_estate'] ?? 0;
        $netEstate = $summary['net_estate'] ?? 0;
        $ihtLiability = $summary['iht_liability'] ?? 0;
        $effectiveRate = $summary['effective_tax_rate'] ?? 0;

        $nrb = $ihtCalc['nrb_available'] ?? 0;
        $rnrb = $ihtCalc['rnrb_available'] ?? 0;
        $spouseExemption = $ihtCalc['spouse_net_estate'] ?? 0;

        $lines = [];
        $lines[] = "Dear {$firstName},";
        $lines[] = '';
        $lines[] = 'Thank you for using Fynla. Here is your personalised Estate Plan based on your assets, liabilities, and Inheritance Tax position.';
        $lines[] = '';

        // Estate overview
        $lines[] = sprintf(
            'Your gross estate is valued at %s with total liabilities of %s, giving a net estate of %s.',
            $this->formatCurrency($grossEstate),
            $this->formatCurrency($grossEstate - $netEstate),
            $this->formatCurrency($netEstate)
        );

        // IHT position
        $lines[] = sprintf(
            'Based on your current position, your estimated Inheritance Tax liability is %s, representing an effective tax rate of %s%% on your gross estate.',
            $this->formatCurrency($ihtLiability),
            number_format($effectiveRate, 1)
        );

        // Allowances
        $allowanceParts = [];
        if ($nrb > 0) {
            $allowanceParts[] = sprintf('a Nil Rate Band of %s', $this->formatCurrency($nrb));
        }
        if ($rnrb > 0) {
            $allowanceParts[] = sprintf('a Residence Nil Rate Band of %s', $this->formatCurrency($rnrb));
        }
        if (! empty($allowanceParts)) {
            $lines[] = sprintf('Your estate benefits from %s.', implode(' and ', $allowanceParts));
        }

        // Spouse exemption
        if ($spouseExemption > 0) {
            $lines[] = sprintf(
                'Spouse exemption of %s applies to assets passing to your spouse.',
                $this->formatCurrency($spouseExemption)
            );
        } elseif ($profile['has_spouse'] ?? false) {
            $lines[] = 'As a married individual, assets passing to your spouse are exempt from Inheritance Tax.';
        }

        // Life cover
        $coverInTrust = $lifeCover['total_cover_in_trust'] ?? 0;
        if ($coverInTrust > 0) {
            $lines[] = sprintf(
                'You have %s in life cover written in trust, which can help provide liquidity for any Inheritance Tax liability.',
                $this->formatCurrency($coverInTrust)
            );
        }

        // Recommendations count (passed in, not recalculated)
        if ($recCount > 0) {
            $lines[] = '';
            $lines[] = sprintf(
                'We have identified %d mitigation %s to help reduce your Inheritance Tax liability. The sections below detail your current estate position and specific actions you can take.',
                $recCount,
                $recCount === 1 ? 'strategy' : 'strategies'
            );
        }

        return [
            'narrative' => implode("\n", $lines),
            'key_metrics' => [],
        ];
    }

    private function buildEmptyExecutiveSummary(): array
    {
        return [
            'narrative' => 'Set up your Inheritance Tax profile and add your estate assets to receive a personalised estate plan.',
            'key_metrics' => [],
        ];
    }

    private function buildCurrentSituation(array $data): array
    {
        $summary = $data['summary'] ?? [];
        $ihtCalc = $data['iht_calculation'] ?? [];
        $assetBreakdown = $data['asset_breakdown'] ?? [];
        $lifeCover = $data['life_cover'] ?? [];
        $charitableAnalysis = $data['charitable_analysis'] ?? [];

        return [
            'estate_value' => [
                'gross' => $this->roundToPenny((float) ($summary['gross_estate'] ?? 0)),
                'net' => $this->roundToPenny((float) ($summary['net_estate'] ?? 0)),
                'liabilities' => $this->roundToPenny((float) ($summary['total_liabilities'] ?? 0)),
            ],
            'iht_calculation' => [
                'liability' => $this->roundToPenny((float) ($summary['iht_liability'] ?? 0)),
                'nil_rate_band' => $this->roundToPenny((float) ($ihtCalc['nrb_available'] ?? 0)),
                'residence_nil_rate_band' => $this->roundToPenny((float) ($ihtCalc['rnrb_available'] ?? 0)),
                'spouse_exemption' => $this->roundToPenny((float) ($ihtCalc['spouse_net_estate'] ?? 0)),
                'effective_rate' => round((float) ($summary['effective_tax_rate'] ?? 0), 1),
            ],
            'asset_breakdown' => [
                'liquid' => $this->roundToPenny((float) ($assetBreakdown['liquid'] ?? 0)),
                'semi_liquid' => $this->roundToPenny((float) ($assetBreakdown['semi_liquid'] ?? 0)),
                'illiquid' => $this->roundToPenny((float) ($assetBreakdown['illiquid'] ?? 0)),
            ],
            'life_cover' => [
                'cover_in_trust' => $this->roundToPenny((float) ($lifeCover['total_cover_in_trust'] ?? 0)),
                'cover_not_in_trust' => $this->roundToPenny((float) ($lifeCover['total_cover_not_in_trust'] ?? 0)),
                'policy_count' => ($lifeCover['policy_count'] ?? 0) + ($lifeCover['policies_not_in_trust_count'] ?? 0),
                'policies_in_trust' => $lifeCover['policy_count'] ?? 0,
                'policies_not_in_trust' => $lifeCover['policies_not_in_trust_count'] ?? 0,
            ],
            'charitable_giving' => [
                'status' => $charitableAnalysis['status'] ?? 'none',
                'current_percentage' => round((float) ($charitableAnalysis['current_percentage'] ?? 0), 1),
                'threshold' => $this->planConfig->getCharitableGivingThreshold(),
                'shortfall' => $this->roundToPenny((float) ($charitableAnalysis['shortfall'] ?? 0)),
                'potential_saving' => $this->roundToPenny((float) ($charitableAnalysis['potential_saving'] ?? 0)),
            ],
        ];
    }

    /**
     * Build joint estate view for married users with spouse data.
     */
    private function buildJointEstateView(User $user, array $data): ?array
    {
        $profile = $data['profile'] ?? [];

        if (! ($profile['has_spouse'] ?? false) || ! $user->spouse) {
            return null;
        }

        $spouse = $user->spouse;
        $ihtCalc = $data['iht_calculation'] ?? [];

        // Primary user figures from analysis
        $primaryGross = (float) ($ihtCalc['user_gross_assets'] ?? $data['summary']['gross_estate'] ?? 0);
        $primaryLiabilities = (float) ($ihtCalc['user_total_liabilities'] ?? $data['summary']['total_liabilities'] ?? 0);
        $primaryNet = $primaryGross - $primaryLiabilities;

        // Spouse figures from IHT calculation (if data sharing enabled)
        $spouseGross = (float) ($ihtCalc['spouse_gross_assets'] ?? 0);
        $spouseLiabilities = (float) ($ihtCalc['spouse_total_liabilities'] ?? 0);
        $spouseNet = $spouseGross - $spouseLiabilities;

        // Combined figures
        $combinedGross = $primaryGross + $spouseGross;
        $combinedLiabilities = $primaryLiabilities + $spouseLiabilities;
        $combinedNet = $primaryNet + $spouseNet;

        // Life cover split
        $lifeCover = $data['life_cover'] ?? [];

        return [
            'is_joint_view' => true,
            'primary' => [
                'name' => $user->first_name ?? $user->name,
                'gross_estate' => $this->roundToPenny($primaryGross),
                'liabilities' => $this->roundToPenny($primaryLiabilities),
                'net_estate' => $this->roundToPenny($primaryNet),
                'cover_in_trust' => $this->roundToPenny((float) ($lifeCover['user_cover_in_trust'] ?? 0)),
            ],
            'spouse' => [
                'name' => $spouse->first_name ?? $spouse->name,
                'gross_estate' => $this->roundToPenny($spouseGross),
                'liabilities' => $this->roundToPenny($spouseLiabilities),
                'net_estate' => $this->roundToPenny($spouseNet),
                'cover_in_trust' => $this->roundToPenny((float) ($lifeCover['spouse_cover_in_trust'] ?? 0)),
            ],
            'combined' => [
                'gross_estate' => $this->roundToPenny($combinedGross),
                'liabilities' => $this->roundToPenny($combinedLiabilities),
                'net_estate' => $this->roundToPenny($combinedNet),
                'nil_rate_band' => $this->roundToPenny((float) ($ihtCalc['nrb_available'] ?? 0)),
                'residence_nil_rate_band' => $this->roundToPenny((float) ($ihtCalc['rnrb_available'] ?? 0)),
            ],
            'spouse_exemption_note' => 'Assets passing between spouses are exempt from Inheritance Tax. The Inheritance Tax liability shown is calculated on the second death.',
        ];
    }

    private function buildWhatIfData(array $data, array $enabledActions): array
    {
        $summary = $data['summary'] ?? [];
        $ihtLiability = (float) ($summary['iht_liability'] ?? 0);
        $netEstate = (float) ($summary['net_estate'] ?? 0);
        $grossEstate = (float) ($summary['gross_estate'] ?? 0);

        $currentToBeneficiaries = max(0, $netEstate - $ihtLiability);
        $currentEffectiveRate = $grossEstate > 0 ? ($ihtLiability / $grossEstate) * 100 : 0;

        // Calculate total mitigation from enabled actions
        $totalSavings = 0;
        $savingsMap = [];

        foreach ($enabledActions as $action) {
            $saving = (float) ($action['estimated_impact'] ?? 0);
            $savingsMap[$action['id']] = $saving;
            $totalSavings += $saving;
        }

        $projectedLiability = max(0, $ihtLiability - $totalSavings);
        $projectedToBeneficiaries = max(0, $netEstate - $projectedLiability);
        $projectedEffectiveRate = $grossEstate > 0 ? ($projectedLiability / $grossEstate) * 100 : 0;

        return [
            'current_scenario' => [
                'iht_liability' => $this->roundToPenny($ihtLiability),
                'effective_tax_rate' => round($currentEffectiveRate, 1),
                'estate_to_beneficiaries' => $this->roundToPenny($currentToBeneficiaries),
            ],
            'projected_scenario' => [
                'iht_liability' => $this->roundToPenny($projectedLiability),
                'effective_tax_rate' => round($projectedEffectiveRate, 1),
                'estate_to_beneficiaries' => $this->roundToPenny($projectedToBeneficiaries),
                'total_mitigation_savings' => $this->roundToPenny($totalSavings),
            ],
            'is_approximate' => true,
            'frontend_calc_params' => [
                'current_iht_liability' => $ihtLiability,
                'net_estate' => $netEstate,
                'gross_estate' => $grossEstate,
                'savings_map' => $savingsMap,
            ],
        ];
    }
}
