<?php

declare(strict_types=1);

namespace App\Services\Plans;

use App\Agents\EstateAgent;
use App\Constants\TaxDefaults;
use App\Models\Estate\Will;
use App\Models\LifeInsurancePolicy;
use App\Models\User;
use App\Services\Coordination\RecommendationPersonaliser;
use App\Services\Estate\EstateAssetAggregatorService;
use App\Services\Estate\IHTCalculationService;
use App\Services\Estate\IHTFormattingService;
use App\Services\TaxConfigService;

class EstatePlanService extends BasePlanService
{
    public function __construct(
        private readonly EstateAgent $estateAgent,
        private readonly IHTCalculationService $ihtCalculator,
        private readonly TaxConfigService $taxConfig,
        private readonly PlanConfigService $planConfig,
        private readonly DisposableIncomeAccessor $disposableIncome,
        private readonly EstateAssetAggregatorService $assetAggregator,
        private readonly IHTFormattingService $formattingService,
        private readonly RecommendationPersonaliser $personaliser
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
        $recommendations = array_values(array_filter($recommendations, fn ($r) => ($r['category'] ?? '') !== 'planning'));
        $recommendations = $this->enrichRecommendations($recommendations, $user, $data);
        ['actions' => $actions, 'enabledActions' => $enabledActions] = $this->prepareActions($recommendations, 'estate', $options);

        // Attach gifting detail to actions from the analysis data
        $actions = $this->attachGiftingDetailToActions($actions, $data);

        $currentSituation = $this->buildCurrentSituation($data, $user);
        $whatIf = $this->buildWhatIfData($data, $enabledActions);
        $conclusion = $this->generateDynamicConclusion($currentSituation, $enabledActions, 'estate');

        // Build structured executive summary
        $executiveSummary = $this->buildExecutiveSummary($user, $data, $actions);

        return [
            'metadata' => $this->buildPlanMetadata($user, 'estate', $completeness),
            'completeness_warning' => $this->buildCompletenessWarning($completeness),
            'executive_summary' => $executiveSummary,
            'personal_information' => $this->buildPersonalInformation($user, $data),
            'current_situation' => $currentSituation,
            'actions' => $actions,
            'what_if' => $whatIf,
            'conclusion' => $conclusion,
        ];
    }

    public function getRecommendations(int $userId, ?array $preComputedData = null): array
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
        // Add personalised context before enrichment
        $recommendations = $this->personaliser->personaliseRecommendations($recommendations, $user);

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
     * Build structured executive summary with key actions table.
     */
    private function buildExecutiveSummary(User $user, array $data, array $actions): array
    {
        $firstName = $this->getUserFirstName($user);
        $ihtLiability = (float) ($data['summary']['iht_liability'] ?? 0);

        $greeting = "Dear {$firstName},";
        $opening = 'Thank you for using Fynla. Here is your personalised Estate Plan based on your assets, liabilities, and Inheritance Tax position.';
        $introduction = 'Below you will find a summary of your Inheritance Tax position, the allowances available to you, and the specific actions you can take to reduce or eliminate your liability.';

        // Key actions summary (top 5 enabled)
        $enabledActions = collect($actions)->where('enabled', true)->values();
        $actionsSummary = $enabledActions->take(5)->map(fn ($a) => [
            'title' => $a['title'],
            'priority' => $a['priority'],
        ])->toArray();

        $closing = $ihtLiability > 0
            ? 'The sections below break down your full Inheritance Tax calculation, the assets included in your estate, and the specific strategies you can implement to reduce your liability. You can toggle each action on or off to see its projected impact.'
            : 'Our analysis shows your estate benefits from sufficient allowances to cover your current position. Review the details below.';

        return [
            'greeting' => $greeting,
            'opening' => $opening,
            'introduction' => $introduction,
            'actions_summary' => $actionsSummary,
            'total_actions' => count($actions),
            'closing' => $closing,
        ];
    }

    private function buildEmptyExecutiveSummary(): array
    {
        return [
            'greeting' => null,
            'narrative' => 'Set up your Inheritance Tax profile and add your estate assets to receive a personalised estate plan.',
            'key_metrics' => [],
        ];
    }

    private function buildCurrentSituation(array $data, User $user): array
    {
        $ihtCalc = $data['iht_calculation'] ?? [];
        $assetBreakdown = $data['asset_breakdown'] ?? [];
        $lifeCover = $data['life_cover'] ?? [];
        $charitableAnalysis = $data['charitable_analysis'] ?? [];
        $profile = $data['profile'] ?? [];

        // Determine spouse and data sharing status
        $hasLinkedSpouse = $user->spouse_id !== null;
        $spouse = $hasLinkedSpouse ? User::find($user->spouse_id) : null;
        $dataSharingEnabled = $hasLinkedSpouse && $user->hasAcceptedSpousePermission();

        // Gather assets for formatting service (same as IHTController)
        $userAssets = $this->assetAggregator->gatherUserAssets($user);
        $spouseAssets = ($spouse && $dataSharingEnabled)
            ? $this->assetAggregator->gatherUserAssets($spouse)
            : collect();

        // Format breakdowns using IHTFormattingService
        $assetsBreakdown = $this->formattingService->formatAssetsBreakdown(
            $userAssets,
            $spouseAssets,
            $dataSharingEnabled,
            $user,
            $spouse,
            $ihtCalc
        );

        $liabilitiesBreakdown = $this->formattingService->formatLiabilitiesBreakdown(
            $user,
            $spouse,
            $dataSharingEnabled
        );

        // Recalculate projected liabilities from formatting service (same as IHTController)
        $totalLiabilities = $liabilitiesBreakdown['user']['total'];
        $projectedLiabilities = $liabilitiesBreakdown['user']['projected_total'];

        if ($dataSharingEnabled && isset($liabilitiesBreakdown['spouse'])) {
            $totalLiabilities += $liabilitiesBreakdown['spouse']['total'];
            $projectedLiabilities += $liabilitiesBreakdown['spouse']['projected_total'];
        }

        $ihtCalc['total_liabilities'] = $totalLiabilities;
        $ihtCalc['projected_liabilities'] = $projectedLiabilities;
        $ihtCalc['projected_net_estate'] = ($ihtCalc['projected_gross_assets'] ?? 0) - $projectedLiabilities;

        $totalAllowances = ($ihtCalc['nrb_available'] ?? 0) + ($ihtCalc['rnrb_available'] ?? 0);
        $ihtCalc['projected_taxable_estate'] = max(0, $ihtCalc['projected_net_estate'] - $totalAllowances);
        $ihtRate = (float) ($ihtCalc['iht_rate'] ?? $this->taxConfig->getInheritanceTax()['standard_rate'] ?? 0.40);
        $ihtCalc['projected_iht_liability'] = $ihtCalc['projected_taxable_estate'] * $ihtRate;

        // Build iht_summary matching IHTController response shape
        $ihtSummary = [
            'current' => [
                'net_estate' => $ihtCalc['total_net_estate'] ?? 0,
                'gross_assets' => $ihtCalc['total_gross_assets'] ?? 0,
                'liabilities' => $ihtCalc['total_liabilities'] ?? 0,
                'nrb_available' => $ihtCalc['nrb_available'] ?? 0,
                'nrb_individual' => $ihtCalc['nrb_individual'] ?? 0,
                'nrb_transferred' => $ihtCalc['nrb_transferred'] ?? 0,
                'nrb_message' => $ihtCalc['nrb_message'] ?? '',
                'rnrb_available' => $ihtCalc['rnrb_available'] ?? 0,
                'rnrb_individual' => $ihtCalc['rnrb_individual'] ?? 0,
                'rnrb_transferred' => $ihtCalc['rnrb_transferred'] ?? 0,
                'rnrb_status' => $ihtCalc['rnrb_status'] ?? 'none',
                'rnrb_message' => $ihtCalc['rnrb_message'] ?? '',
                'total_allowances' => $ihtCalc['total_allowances'] ?? 0,
                'taxable_estate' => $ihtCalc['taxable_estate'] ?? 0,
                'iht_liability' => $ihtCalc['iht_liability'] ?? 0,
                'effective_rate' => $ihtCalc['effective_rate'] ?? 0,
            ],
            'projected' => [
                'net_estate' => $ihtCalc['projected_net_estate'] ?? 0,
                'gross_assets' => $ihtCalc['projected_gross_assets'] ?? 0,
                'liabilities' => $ihtCalc['projected_liabilities'] ?? 0,
                'taxable_estate' => $ihtCalc['projected_taxable_estate'] ?? 0,
                'iht_liability' => $ihtCalc['projected_iht_liability'] ?? 0,
                'years_to_death' => $ihtCalc['years_to_death'] ?? 0,
                'estimated_age_at_death' => $ihtCalc['estimated_age_at_death'] ?? 0,
                'cash' => $ihtCalc['projected_cash'] ?? null,
                'investments' => $ihtCalc['projected_investments'] ?? null,
                'properties' => $ihtCalc['projected_properties'] ?? null,
            ],
            'is_married' => $ihtCalc['is_married'] ?? false,
            'is_widowed' => $ihtCalc['is_widowed'] ?? false,
            'data_sharing_enabled' => $ihtCalc['data_sharing_enabled'] ?? false,
        ];

        // Rate and NRB/RNRB messages
        $ihtConfig = $this->taxConfig->getInheritanceTax();
        $ihtStandardRate = (float) ($ihtConfig['standard_rate'] ?? TaxDefaults::IHT_RATE);
        $charitableRate = (float) ($ihtConfig['reduced_rate_charity'] ?? TaxDefaults::IHT_CHARITABLE_RATE);

        $charitableStatus = $charitableAnalysis['status'] ?? 'none';
        $appliedRateType = $charitableStatus === 'qualifies' ? 'charitable' : 'standard';
        $appliedRateMessage = $charitableStatus === 'qualifies'
            ? sprintf('Reduced rate of %d%% applies as 10%% or more of the net estate is left to charity.', (int) round($charitableRate * 100))
            : sprintf('Standard Inheritance Tax rate of %d%% applies.', (int) round($ihtStandardRate * 100));

        $isMarried = $profile['has_spouse'] ?? false;
        $isWidowed = ($profile['marital_status'] ?? '') === 'widowed'
            || ($ihtCalc['transferable_nrb'] ?? 0) > 0;

        $nrb = (float) ($ihtCalc['nrb_available'] ?? 0);
        $rnrb = (float) ($ihtCalc['rnrb_available'] ?? 0);

        $nrbMessage = $isWidowed
            ? 'Includes transferred Nil Rate Band from deceased spouse.'
            : ($isMarried ? 'Individual Nil Rate Band. On second death, up to double may be available.' : 'Individual Nil Rate Band.');

        $rnrbMessage = $rnrb > 0
            ? 'Residence Nil Rate Band available as your estate includes a qualifying residential property passing to direct descendants.'
            : 'Residence Nil Rate Band is not available. This may be because your estate does not include a qualifying residential property, or it does not pass to direct descendants.';

        return [
            // Full IHT calculation (pass-through for frontend)
            'calculation' => $ihtCalc,

            // Formatted breakdowns from IHTFormattingService
            'assets_breakdown' => $assetsBreakdown,
            'liabilities_breakdown' => $liabilitiesBreakdown,

            // Formatted summary matching IHTController shape
            'iht_summary' => $ihtSummary,

            // Display flags
            'data_sharing_enabled' => $dataSharingEnabled,
            'has_linked_spouse' => $hasLinkedSpouse && $spouse !== null,

            // Messages for below the table
            'iht_rate_type' => $appliedRateType,
            'iht_rate_message' => $appliedRateMessage,
            'nrb_message' => $nrbMessage,
            'rnrb_message' => $rnrbMessage,

            // Keep existing supplementary cards (unchanged)
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
                'status' => $charitableStatus,
                'current_percentage' => round((float) ($charitableAnalysis['current_percentage'] ?? 0), 1),
                'threshold' => $this->planConfig->getCharitableGivingThreshold(),
                'shortfall' => $this->roundToPenny((float) ($charitableAnalysis['shortfall'] ?? 0)),
                'potential_saving' => $this->roundToPenny((float) ($charitableAnalysis['potential_saving'] ?? 0)),
            ],
            'linked_accounts' => $this->buildLinkedAccountsList($user),
        ];
    }

    /**
     * Build personal information section for the estate plan.
     */
    private function buildPersonalInformation(User $user, array $data): array
    {
        $fullName = trim(($user->first_name ?? '').' '.($user->surname ?? '')) ?: ($user->name ?? "\u{2014}");
        $dob = $user->date_of_birth;
        $age = $dob ? (int) $dob->diffInYears(now()) : null;

        // Spouse
        $spouseName = null;
        if (in_array($user->marital_status, ['married', 'civil_partnership']) && $user->spouse) {
            $spouse = $user->spouse;
            $spouseName = trim(($spouse->first_name ?? '').' '.($spouse->surname ?? '')) ?: $spouse->name;
        }

        // Children
        $children = $user->familyMembers()
            ->where('relationship', 'child')
            ->get()
            ->map(fn ($child) => $child->name)
            ->toArray();

        // Income
        $grossIncome = (float) ($user->annual_employment_income ?? 0)
            + (float) ($user->annual_self_employment_income ?? 0)
            + (float) ($user->annual_rental_income ?? 0)
            + (float) ($user->annual_dividend_income ?? 0)
            + (float) ($user->annual_interest_income ?? 0)
            + (float) ($user->annual_other_income ?? 0)
            + (float) ($user->annual_trust_income ?? 0);

        $incomeData = $this->disposableIncome->getForUser($user);

        // Estate-specific profile fields
        $ihtCalc = $data['iht_calculation'] ?? [];
        $profile = $data['profile'] ?? [];
        $isMarried = $profile['has_spouse'] ?? false;
        $isWidowed = ($profile['marital_status'] ?? '') === 'widowed'
            || ($ihtCalc['transferable_nrb'] ?? 0) > 0;
        $maritalStatusIht = $isMarried ? 'married' : ($isWidowed ? 'widowed' : 'single');

        return [
            'full_name' => $fullName,
            'date_of_birth' => $dob?->toDateString(),
            'age' => $age,
            'marital_status' => $user->marital_status,
            'spouse_name' => $spouseName,
            'children' => $children,
            'gross_income' => $this->roundToPenny($grossIncome),
            'net_income' => $this->roundToPenny($incomeData['net_income']),
            'annual_expenditure' => $this->roundToPenny($incomeData['annual_expenditure']),
            'disposable_income' => $this->roundToPenny($incomeData['annual']),
            'monthly_disposable' => $this->roundToPenny($incomeData['monthly']),
            'estimated_age_at_death' => $ihtCalc['estimated_age_at_death'] ?? null,
            'years_to_death' => $ihtCalc['years_to_death'] ?? null,
            'marital_status_iht' => $maritalStatusIht,
            'has_will' => Will::where('user_id', $user->id)->exists(),
        ];
    }

    /**
     * Build list of linked accounts included in the estate.
     */
    private function buildLinkedAccountsList(User $user): array
    {
        $assets = $this->assetAggregator->gatherUserAssets($user);

        $excludedTypes = ['dc_pension', 'db_pension'];

        return $assets
            ->filter(fn ($asset) => (float) $asset->current_value > 0)
            ->filter(fn ($asset) => ! in_array($asset->asset_type ?? '', $excludedTypes, true))
            ->map(fn ($asset) => [
                'name' => $asset->asset_name ?? 'Unknown Asset',
                'type' => $asset->asset_type ?? 'other',
                'value' => $this->roundToPenny((float) $asset->current_value),
                'is_exempt' => (bool) ($asset->is_iht_exempt ?? false),
            ])
            ->sortByDesc('value')
            ->values()
            ->toArray();
    }

    /**
     * Attach gifting detail from analysis data to matching actions.
     */
    private function attachGiftingDetailToActions(array $actions, array $data): array
    {
        $giftingStrategies = $data['gifting_opportunities']['strategies'] ?? [];

        // Build lookup by strategy name keywords
        $petStrategy = null;
        $annualStrategy = null;
        foreach ($giftingStrategies as $strategy) {
            $name = strtolower($strategy['strategy_name'] ?? '');
            if (str_contains($name, 'pet') || str_contains($name, 'potentially exempt')) {
                $petStrategy = $strategy;
            }
            if (str_contains($name, 'annual exemption')) {
                $annualStrategy = $strategy;
            }
        }

        foreach ($actions as &$action) {
            $category = $action['category'] ?? '';

            if ($category === 'pet_gifting' && $petStrategy) {
                $action['gift_schedule'] = $petStrategy['gift_schedule'] ?? [];
                $action['seven_year_cycles'] = (int) ($petStrategy['number_of_cycles'] ?? 0);
                $action['amount_per_cycle'] = (float) ($petStrategy['amount_per_cycle'] ?? 0);
            }

            if ($category === 'annual_gifting' && $annualStrategy) {
                $action['annual_gifting_detail'] = [
                    'annual_amount' => (float) ($annualStrategy['annual_amount'] ?? 0),
                    'years' => (int) ($annualStrategy['years'] ?? 0),
                    'total_gifted' => (float) ($annualStrategy['total_gifted'] ?? 0),
                    'iht_saved' => (float) ($annualStrategy['iht_saved'] ?? 0),
                ];
            }
        }
        unset($action);

        return $actions;
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
