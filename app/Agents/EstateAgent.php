<?php

declare(strict_types=1);

namespace App\Agents;

use App\Constants\EstateDefaults;
use App\Constants\TaxDefaults;
use App\Models\Estate\Will;
use App\Models\LifeInsurancePolicy;
use App\Models\User;
use App\Services\Estate\ComprehensiveEstatePlanService;
use App\Services\Estate\EstateAssetAggregatorService;
use App\Services\Estate\GiftingStrategyOptimizer;
use App\Services\Estate\IHTCalculationService;
use App\Services\Estate\PersonalizedTrustStrategyService;
use App\Services\Estate\WillAnalysisService;
use App\Services\TaxConfigService;
use Illuminate\Support\Facades\Cache;

/**
 * EstateAgent orchestrates estate planning analysis and recommendations.
 *
 * Coordinates between IHT calculations, gifting strategies, trust recommendations,
 * and comprehensive estate planning services.
 */
class EstateAgent extends BaseAgent
{
    public function __construct(
        private readonly IHTCalculationService $ihtCalculator,
        private readonly EstateAssetAggregatorService $assetAggregator,
        private readonly ComprehensiveEstatePlanService $estatePlanService,
        private readonly GiftingStrategyOptimizer $giftingOptimizer,
        private readonly PersonalizedTrustStrategyService $trustStrategyService,
        private readonly WillAnalysisService $willAnalysisService,
        private readonly TaxConfigService $taxConfig
    ) {}

    /**
     * Analyze user's estate planning situation.
     */
    public function analyze(int $userId): array
    {
        $cacheKey = "estate_analysis_{$userId}";
        $cacheTags = ['estate', 'user_'.$userId];

        return $this->remember($cacheKey, function () use ($userId) {
            $user = User::with([
                'ihtProfile',
                'assets',
                'properties',
                'liabilities',
                'mortgages',
                'spouse',
                'familyMembers',
                'trusts',
                'gifts',
            ])->findOrFail($userId);

            // Load life insurance policies written in trust (for IHT mitigation)
            $lifePoliciesInTrust = LifeInsurancePolicy::where('user_id', $userId)
                ->where('in_trust', true)
                ->get();

            // Load non-trust life policies for trust placement recommendations
            $lifePoliciesNotInTrust = LifeInsurancePolicy::where('user_id', $userId)
                ->where(function ($q) {
                    $q->where('in_trust', false)->orWhereNull('in_trust');
                })
                ->get();

            $spouseLifeCoverInTrust = 0;
            if ($user->spouse) {
                $spouseLifeCoverInTrust = LifeInsurancePolicy::where('user_id', $user->spouse->id)
                    ->where('in_trust', true)
                    ->sum('sum_assured');
            }

            // Aggregate all estate assets into summary
            $assetSummary = $this->buildAssetSummary($user);

            // Calculate IHT
            $ihtCalculation = null;
            $ihtLiability = 0;
            $effectiveTaxRate = 0;

            try {
                $ihtCalculation = $this->ihtCalculator->calculate($user);
                $ihtLiability = $ihtCalculation['iht_liability'] ?? 0;
                $effectiveTaxRate = $ihtCalculation['effective_rate'] ?? 0;
            } catch (\Exception $e) {
                // Continue without IHT calculation
            }

            // Get trust recommendations
            $trustRecommendations = [];
            if ($user->ihtProfile) {
                try {
                    $assets = $this->assetAggregator->gatherUserAssets($user);
                    $trustRecommendations = $this->trustStrategyService->generatePersonalizedTrustStrategy(
                        $assets,
                        $ihtLiability,
                        $user->ihtProfile,
                        $user
                    );
                } catch (\Throwable $e) {
                    // Continue without trust recommendations
                }
            }

            // Get gifting opportunities
            $giftingOpportunities = [];
            try {
                $currentAge = $user->date_of_birth
                    ? (int) $user->date_of_birth->diffInYears(now())
                    : EstateDefaults::DEFAULT_CURRENT_AGE;
                $yearsUntilDeath = max(1, EstateDefaults::DEFAULT_LIFE_EXPECTANCY - $currentAge);
                $nrb = $ihtCalculation['nrb_available'] ?? $this->taxConfig->getInheritanceTax()['nil_rate_band'];
                $rnrb = $ihtCalculation['rnrb_available'] ?? 0;

                $giftingOpportunities = $this->giftingOptimizer->calculateOptimalGiftingStrategy(
                    $assetSummary['net_estate'] ?? 0,
                    $ihtLiability,
                    $yearsUntilDeath,
                    $user,
                    $nrb,
                    $rnrb
                );
            } catch (\Throwable $e) {
                // Continue without gifting opportunities
            }

            // Check will for trust-triggering wishes
            $trustWishTriggers = [];
            try {
                $will = Will::where('user_id', $userId)->with('bequests')->first();
                if ($will) {
                    $trustWishTriggers = $this->willAnalysisService->detectTrustTriggeringWishes($will);
                }
            } catch (\Throwable $e) {
                // Continue without wish triggers
            }

            // Analyze charitable bequests
            $charitableAnalysis = [];
            try {
                $netEstate = $assetSummary['net_estate'] ?? 0;
                $charitableAnalysis = $this->willAnalysisService->analyzeCharitableBequests($user, $netEstate);
            } catch (\Throwable $e) {
                // Continue without charitable analysis
            }

            // Calculate current age and life expectancy context
            $currentAge = $user->date_of_birth ?
                (int) $user->date_of_birth->diffInYears(now()) : EstateDefaults::DEFAULT_CURRENT_AGE;

            return $this->response(
                true,
                'Estate analysis completed successfully.',
                [
                    'summary' => [
                        'gross_estate' => $assetSummary['gross_estate'] ?? 0,
                        'net_estate' => $assetSummary['net_estate'] ?? 0,
                        'total_liabilities' => $assetSummary['total_liabilities'] ?? 0,
                        'iht_liability' => $ihtLiability,
                        'effective_tax_rate' => round($effectiveTaxRate, 2),
                    ],
                    'asset_breakdown' => $assetSummary['breakdown'] ?? [],
                    'iht_calculation' => $ihtCalculation,
                    'trust_recommendations' => $trustRecommendations,
                    'gifting_opportunities' => $giftingOpportunities,
                    'trust_wish_triggers' => $trustWishTriggers,
                    'charitable_analysis' => $charitableAnalysis,
                    'life_cover' => [
                        'user_cover_in_trust' => (float) $lifePoliciesInTrust->sum('sum_assured'),
                        'spouse_cover_in_trust' => (float) $spouseLifeCoverInTrust,
                        'total_cover_in_trust' => (float) $lifePoliciesInTrust->sum('sum_assured') + $spouseLifeCoverInTrust,
                        'total_cover_not_in_trust' => (float) $lifePoliciesNotInTrust->sum('sum_assured'),
                        'policy_count' => $lifePoliciesInTrust->count(),
                        'policies_not_in_trust_count' => $lifePoliciesNotInTrust->count(),
                    ],
                    'profile' => [
                        'current_age' => $currentAge,
                        'marital_status' => $user->marital_status,
                        'has_dependents' => ($user->familyMembers()->where('relationship', 'child')->count() > 0),
                        'has_spouse' => $user->spouse !== null,
                        'has_iht_profile' => $user->ihtProfile !== null,
                    ],
                ]
            );
        }, null, $cacheTags);
    }

    /**
     * Generate personalized recommendations based on 7-step IHT mitigation decision tree.
     *
     * Priority order (cost-efficient, CLTs as last resort):
     * 1. Charitable Bequest Check (Rate Reduction)
     * 2. Liquidity & Affordability Assessment
     * 3. Check Existing Life Cover
     * 4. Annual Gifting Strategy (First Resort)
     * 5. Life Cover Strategy (Second Resort)
     * 6. PET Gifting Strategy (Third Resort)
     * 7. CLT into Trust (Last Resort ONLY)
     */
    public function generateRecommendations(array $analysisData): array
    {
        if (! isset($analysisData['data'])) {
            return $this->response(
                false,
                'Analysis data is incomplete. Please run analysis first.',
                []
            );
        }

        $recommendations = [];
        $data = $analysisData['data'];
        $ihtLiability = $data['summary']['iht_liability'] ?? 0;
        $netEstate = $data['summary']['net_estate'] ?? 0;
        $currentAge = $data['profile']['current_age'] ?? 50;
        $charitableAnalysis = $data['charitable_analysis'] ?? [];
        $trustWishTriggers = $data['trust_wish_triggers'] ?? [];

        // Only generate mitigation recommendations if there's an IHT liability
        if ($ihtLiability > 0) {
            $remainingLiability = $ihtLiability;

            // STEP 1: Charitable Bequest Check (Rate Reduction)
            $step1Result = $this->step1CharitableBequestCheck($charitableAnalysis, $ihtLiability);
            if ($step1Result) {
                $recommendations[] = $step1Result;
            }

            // STEP 2: Liquidity & Affordability Assessment
            $liquidityData = $this->step2LiquidityAssessment($data);
            if ($liquidityData['recommendation']) {
                $recommendations[] = $liquidityData['recommendation'];
            }

            // STEP 3: Check Existing Life Cover
            $lifeCoverData = $this->step3ExistingLifeCover($data);
            if ($lifeCoverData['usable_cover'] > 0) {
                $remainingLiability = max(0, $remainingLiability - $lifeCoverData['usable_cover']);
            }
            if ($lifeCoverData['recommendation']) {
                $recommendations[] = $lifeCoverData['recommendation'];
            }
            if ($lifeCoverData['trust_placement_recommendation'] ?? null) {
                $recommendations[] = $lifeCoverData['trust_placement_recommendation'];
            }

            // STEP 4: Annual Gifting Strategy (First Resort)
            if ($remainingLiability > 0) {
                $annualGiftingResult = $this->step4AnnualGiftingStrategy($currentAge, $remainingLiability);
                if ($annualGiftingResult['recommendation']) {
                    $recommendations[] = $annualGiftingResult['recommendation'];
                }
                $remainingLiability = max(0, $remainingLiability - $annualGiftingResult['potential_savings']);
            }

            // STEP 5: Life Cover Strategy (Second Resort) - Only if age <= 50
            if ($remainingLiability > 0 && $currentAge <= 50) {
                $lifeCoverStrategyResult = $this->step5LifeCoverStrategy($remainingLiability, $liquidityData);
                if ($lifeCoverStrategyResult['recommendation']) {
                    $recommendations[] = $lifeCoverStrategyResult['recommendation'];
                }
                $remainingLiability = max(0, $remainingLiability - $lifeCoverStrategyResult['cover_amount']);
            }

            // STEP 6: PET Gifting Strategy (Third Resort)
            if ($remainingLiability > 0) {
                $petResult = $this->step6PETGiftingStrategy($currentAge, $remainingLiability);
                if ($petResult['recommendation']) {
                    $recommendations[] = $petResult['recommendation'];
                }
                $remainingLiability = max(0, $remainingLiability - $petResult['potential_savings']);
            }

            // STEP 7: CLT into Trust (Last Resort ONLY)
            if ($remainingLiability > 0) {
                $cltResult = $this->step7CLTIntoTrust($remainingLiability);
                if ($cltResult['recommendation']) {
                    $recommendations[] = $cltResult['recommendation'];
                }
            }
        }

        // Trust wish triggers from will analysis
        if (! empty($trustWishTriggers)) {
            $recommendations[] = [
                'category' => 'will_trust_setup',
                'priority' => 'medium',
                'step' => 0,
                'title' => 'Will Wishes Require Trust Structures',
                'description' => count($trustWishTriggers).' wishes in your will may require trust arrangements',
                'actions' => array_map(fn ($t) => $t['recommendation'], array_slice($trustWishTriggers, 0, 3)),
                'details' => $trustWishTriggers,
            ];
        }

        // Recommend completing missing setup items
        $profile = $data['profile'] ?? [];
        if (! ($profile['has_iht_profile'] ?? false)) {
            $recommendations[] = [
                'category' => 'planning',
                'priority' => 'high',
                'step' => 0,
                'title' => 'Complete Your Inheritance Tax Profile',
                'description' => 'Setting up your Inheritance Tax profile allows us to calculate your allowances, spouse exemptions, and projected liability accurately.',
                'actions' => [
                    'Complete your Inheritance Tax profile',
                    'Review beneficiary designations',
                    'Consider writing or updating your will',
                ],
            ];
        }

        return $this->response(
            true,
            'Recommendations generated successfully.',
            [
                'recommendations' => $recommendations,
                'mitigation_steps_applied' => count(array_filter($recommendations, fn ($r) => ($r['step'] ?? 0) > 0)),
            ]
        );
    }

    /**
     * Step 1: Charitable Bequest Check - Rate Reduction from 40% to 36%
     */
    private function step1CharitableBequestCheck(array $charitableAnalysis, float $ihtLiability): ?array
    {
        if (empty($charitableAnalysis)) {
            return null;
        }

        $status = $charitableAnalysis['status'] ?? 'below';
        $shortfall = $charitableAnalysis['shortfall'] ?? 0;
        $potentialSaving = $charitableAnalysis['potential_saving'] ?? 0;
        $currentSaving = $charitableAnalysis['current_saving'] ?? 0;

        if ($status === 'below' && $potentialSaving > 0) {
            return [
                'category' => 'charitable_bequest',
                'priority' => 'high',
                'step' => 1,
                'title' => 'Charitable Bequest Opportunity',
                'description' => "Increase charitable giving by {$this->formatCurrency($shortfall)} to qualify for the reduced 36% IHT rate and save {$this->formatCurrency($potentialSaving)}.",
                'actions' => [
                    "Add {$this->formatCurrency($shortfall)} in charitable bequests to your will",
                    'Consider leaving to registered UK charities',
                    'This reduces your IHT rate from 40% to 36%',
                ],
                'potential_saving' => $potentialSaving,
            ];
        }

        if ($status !== 'below' && $currentSaving > 0) {
            return [
                'category' => 'charitable_bequest',
                'priority' => 'low',
                'step' => 1,
                'title' => 'Charitable Rate Applied',
                'description' => "Your charitable giving qualifies for the reduced 36% IHT rate, saving {$this->formatCurrency($currentSaving)}.",
                'actions' => ['Your current charitable bequests are sufficient for the reduced rate'],
                'current_saving' => $currentSaving,
            ];
        }

        return null;
    }

    /**
     * Step 2: Liquidity & Affordability Assessment
     */
    private function step2LiquidityAssessment(array $data): array
    {
        $assetBreakdown = $data['asset_breakdown'] ?? [];
        $liquidAssets = $assetBreakdown['liquid'] ?? 0;
        $ihtLiability = $data['summary']['iht_liability'] ?? 0;

        $liquidityRatio = $ihtLiability > 0 ? $liquidAssets / $ihtLiability : 1;
        $hasLiquidityIssue = $liquidityRatio < 0.5;

        $recommendation = null;
        if ($hasLiquidityIssue && $ihtLiability > 0) {
            $shortfall = $ihtLiability - $liquidAssets;
            $recommendation = [
                'category' => 'liquidity',
                'priority' => 'high',
                'step' => 2,
                'title' => 'Liquidity Risk Identified',
                'description' => "Your liquid assets of {$this->formatCurrency($liquidAssets)} may not cover the IHT liability of {$this->formatCurrency($ihtLiability)}.",
                'actions' => [
                    'Consider life insurance written in trust to provide liquidity',
                    'Review property holdings for potential downsizing',
                    'Build up liquid savings over time',
                ],
                'shortfall' => $shortfall,
            ];
        }

        return [
            'liquid_assets' => $liquidAssets,
            'liquidity_ratio' => $liquidityRatio,
            'has_issue' => $hasLiquidityIssue,
            'recommendation' => $recommendation,
        ];
    }

    /**
     * Step 3: Check Existing Life Cover
     */
    private function step3ExistingLifeCover(array $data): array
    {
        $lifeCover = $data['life_cover'] ?? [];
        $existingCover = (float) ($lifeCover['total_cover_in_trust'] ?? 0);
        $liabilities = $data['summary']['total_liabilities'] ?? 0;

        $usableCover = max(0, $existingCover - $liabilities);

        $recommendation = null;
        if ($usableCover > 0) {
            $recommendation = [
                'category' => 'life_cover',
                'priority' => 'low',
                'step' => 3,
                'title' => 'Existing Life Cover Available',
                'description' => "You have {$this->formatCurrency($usableCover)} in life cover that can offset IHT.",
                'actions' => ['Ensure life policies are written in trust to bypass estate'],
                'usable_cover' => $usableCover,
            ];
        }

        $trustPlacementRecommendation = null;
        $notInTrustCount = $lifeCover['policies_not_in_trust_count'] ?? 0;
        if ($notInTrustCount > 0) {
            $trustPlacementRecommendation = [
                'category' => 'trust_planning',
                'priority' => 'medium',
                'step' => 3,
                'title' => 'Place Life Policies in Trust',
                'description' => sprintf(
                    'You have %d life insurance %s totalling %s not written in trust. Policies in trust bypass the estate for Inheritance Tax purposes.',
                    $notInTrustCount,
                    $notInTrustCount === 1 ? 'policy' : 'policies',
                    $this->formatCurrency($lifeCover['total_cover_not_in_trust'] ?? 0)
                ),
                'actions' => ['Contact your insurance provider to place existing policies in trust'],
            ];
        }

        return [
            'existing_cover' => $existingCover,
            'usable_cover' => $usableCover,
            'recommendation' => $recommendation,
            'trust_placement_recommendation' => $trustPlacementRecommendation,
        ];
    }

    /**
     * Step 4: Annual Gifting Strategy (First Resort)
     * Immediately exempt gifts - no 7-year wait, no tax risk
     */
    private function step4AnnualGiftingStrategy(int $currentAge, float $remainingLiability): array
    {
        $ihtConfig = $this->taxConfig->getInheritanceTax();
        $annualExemption = $ihtConfig['annual_exemption'] ?? TaxDefaults::ANNUAL_GIFT_EXEMPTION;

        // Estimate years to life expectancy
        $yearsToLifeExpectancy = max(1, EstateDefaults::DEFAULT_LIFE_EXPECTANCY - $currentAge);

        // Annual exemption potential (including carry forward from unused previous year)
        $annualGiftingCapacity = $annualExemption * $yearsToLifeExpectancy;

        // IHT saved at 40% rate
        $potentialSavings = min($annualGiftingCapacity * 0.40, $remainingLiability);

        $coversLiability = $potentialSavings >= $remainingLiability;

        $recommendation = [
            'category' => 'annual_gifting',
            'priority' => $coversLiability ? 'high' : 'medium',
            'step' => 4,
            'title' => 'Annual Gifting Strategy',
            'description' => $coversLiability
                ? "Using your annual gift exemption of {$this->formatCurrency($annualExemption)}/year could fully offset your IHT liability over {$yearsToLifeExpectancy} years."
                : "Annual gifting of {$this->formatCurrency($annualExemption)}/year could save {$this->formatCurrency($potentialSavings)} in IHT.",
            'actions' => [
                "Use your annual {$this->formatCurrency($annualExemption)} gift exemption each year",
                'Consider gifts out of normal income (fully exempt if regular and affordable)',
                'Small gifts of £250 per recipient are also exempt',
                'Wedding gifts up to £5,000 (parents) or £2,500 (grandparents)',
            ],
            'potential_saving' => $potentialSavings,
            'covers_liability' => $coversLiability,
        ];

        return [
            'recommendation' => $recommendation,
            'potential_savings' => $potentialSavings,
            'covers_liability' => $coversLiability,
        ];
    }

    /**
     * Step 5: Life Cover Strategy (Second Resort)
     * Only recommended if age <= 50 (premiums become prohibitive after 50)
     */
    private function step5LifeCoverStrategy(float $remainingLiability, array $liquidityData): array
    {
        // Estimate whole of life premium (simplified calculation)
        $estimatedAnnualPremium = $remainingLiability * 0.02; // ~2% of cover per year

        $recommendation = [
            'category' => 'new_life_cover',
            'priority' => 'medium',
            'step' => 5,
            'title' => 'Whole of Life Cover Strategy',
            'description' => "A whole of life policy for {$this->formatCurrency($remainingLiability)} could cover the remaining IHT liability.",
            'actions' => [
                "Consider whole of life cover for {$this->formatCurrency($remainingLiability)}",
                'Estimated annual premium: '.$this->formatCurrency($estimatedAnnualPremium),
                'CRITICAL: Policy must be written in trust to bypass your estate',
                'Get quotes from multiple providers',
            ],
            'estimated_premium' => $estimatedAnnualPremium,
            'cover_amount' => $remainingLiability,
        ];

        return [
            'recommendation' => $recommendation,
            'cover_amount' => $remainingLiability,
        ];
    }

    /**
     * Step 6: PET Gifting Strategy (Third Resort)
     * Potentially Exempt Transfers - exempt if donor survives 7 years
     */
    private function step6PETGiftingStrategy(int $currentAge, float $remainingLiability): array
    {
        $ihtConfig = $this->taxConfig->getInheritanceTax();
        $nrb = $ihtConfig['nil_rate_band'] ?? TaxDefaults::NRB;

        // Calculate years to life expectancy
        $yearsToLifeExpectancy = max(1, EstateDefaults::DEFAULT_LIFE_EXPECTANCY - $currentAge);

        // Calculate 7-year cycles available
        $sevenYearCycles = floor($yearsToLifeExpectancy / 7);

        // Each cycle can gift up to NRB tax-efficiently
        $petCapacity = $sevenYearCycles * $nrb;
        $potentialSavings = min($petCapacity * 0.40, $remainingLiability);

        $recommendation = null;
        if ($sevenYearCycles >= 1) {
            $recommendation = [
                'category' => 'pet_gifting',
                'priority' => 'medium',
                'step' => 6,
                'title' => 'Potentially Exempt Transfer (PET) Strategy',
                'description' => "With {$sevenYearCycles} seven-year cycles available, PETs up to {$this->formatCurrency($petCapacity)} could become fully exempt.",
                'actions' => [
                    'Make larger gifts (PETs) that become exempt after 7 years',
                    "Each 7-year cycle can shelter up to {$this->formatCurrency($nrb)} (the NRB)",
                    'Taper relief applies if death occurs within 7 years of a PET',
                    'Consider timing gifts to maximise 7-year survival probability',
                ],
                'potential_saving' => $potentialSavings,
                'seven_year_cycles' => $sevenYearCycles,
            ];
        }

        return [
            'recommendation' => $recommendation,
            'potential_savings' => $potentialSavings,
        ];
    }

    /**
     * Step 7: CLT into Trust (Last Resort ONLY)
     * Only recommended if Steps 4-6 do NOT fully cover the liability
     */
    private function step7CLTIntoTrust(float $remainingLiability): array
    {
        $ihtConfig = $this->taxConfig->getInheritanceTax();
        $nrb = $ihtConfig['nil_rate_band'] ?? TaxDefaults::NRB;
        $cltRate = $ihtConfig['clt_rate'] ?? TaxDefaults::CLT_RATE;

        // Calculate immediate charge if CLT exceeds NRB
        $excessOverNRB = max(0, $remainingLiability - $nrb);
        $immediateCharge = $excessOverNRB * $cltRate;

        $recommendation = [
            'category' => 'clt_trust',
            'priority' => 'low',
            'step' => 7,
            'title' => 'Chargeable Lifetime Transfer (CLT) - Last Resort',
            'description' => 'A CLT into trust can remove assets from your estate, but comes with immediate tax charges.',
            'actions' => [
                "CLT of {$this->formatCurrency($remainingLiability)} would incur immediate {$this->formatCurrency($immediateCharge)} charge (20% on amount over NRB)",
                'Additional 20% charge if death within 7 years (40% total)',
                'Trust subject to periodic charges (max 6% every 10 years)',
                'Exit charges apply when assets leave the trust',
                'Seek professional advice before proceeding',
            ],
            'immediate_charge' => $immediateCharge,
            'amount' => $remainingLiability,
            'warning' => 'CLTs are complex and should only be considered after exhausting simpler strategies.',
        ];

        return [
            'recommendation' => $recommendation,
        ];
    }

    /**
     * Build what-if scenarios for estate planning.
     */
    public function buildScenarios(int $userId, array $parameters): array
    {
        $user = User::with([
            'ihtProfile',
            'assets',
            'properties',
            'liabilities',
            'spouse',
        ])->findOrFail($userId);

        $scenarios = [];
        $scenarioTypes = $parameters['scenario_types'] ?? ['current', 'optimized', 'gifting'];

        foreach ($scenarioTypes as $scenarioType) {
            $scenarios[$scenarioType] = match ($scenarioType) {
                'current' => $this->buildCurrentScenario($user),
                'optimized' => $this->buildOptimizedScenario($user, $parameters),
                'gifting' => $this->buildGiftingScenario($user, $parameters),
                'property_downsizing' => $this->buildDownsizingScenario($user, $parameters),
                'trust_creation' => $this->buildTrustScenario($user, $parameters),
                default => null,
            };
        }

        return $this->response(
            true,
            'Scenarios built successfully.',
            [
                'scenarios' => array_filter($scenarios),
            ]
        );
    }

    /**
     * Build asset summary array from gathered assets and liabilities.
     */
    private function buildAssetSummary(User $user): array
    {
        $assets = $this->assetAggregator->gatherUserAssets($user);
        $grossEstate = $assets->sum('current_value');
        $totalLiabilities = $this->assetAggregator->calculateUserLiabilities($user);
        $netEstate = $grossEstate - $totalLiabilities;

        // Classify by liquidity
        $liquidTypes = ['cash', 'savings', 'investment'];
        $semiLiquidTypes = ['pension', 'dc_pension', 'db_pension'];
        $liquid = $assets->filter(fn ($a) => in_array($a->asset_type ?? '', $liquidTypes))->sum('current_value');
        $semiLiquid = $assets->filter(fn ($a) => in_array($a->asset_type ?? '', $semiLiquidTypes))->sum('current_value');
        $illiquid = $grossEstate - $liquid - $semiLiquid;

        return [
            'gross_estate' => $grossEstate,
            'net_estate' => $netEstate,
            'total_liabilities' => $totalLiabilities,
            'breakdown' => [
                'liquid' => $liquid,
                'semi_liquid' => $semiLiquid,
                'illiquid' => max(0, $illiquid),
            ],
        ];
    }

    /**
     * Build current state scenario.
     */
    private function buildCurrentScenario(User $user): array
    {
        $assetSummary = $this->buildAssetSummary($user);

        $ihtLiability = 0;
        try {
            $result = $this->ihtCalculator->calculate($user);
            $ihtLiability = $result['iht_liability'] ?? 0;
        } catch (\Exception $e) {
            // Continue with zero
        }

        return [
            'name' => 'Current Estate Position',
            'gross_estate' => $assetSummary['gross_estate'] ?? 0,
            'net_estate' => $assetSummary['net_estate'] ?? 0,
            'iht_liability' => $ihtLiability,
            'to_beneficiaries' => ($assetSummary['net_estate'] ?? 0) - $ihtLiability,
        ];
    }

    /**
     * Build optimized scenario with all strategies applied.
     */
    private function buildOptimizedScenario(User $user, array $parameters): array
    {
        $current = $this->buildCurrentScenario($user);

        // Estimate savings from various strategies
        $giftingSavings = min($current['iht_liability'] * 0.15, 50000);
        $trustSavings = min($current['iht_liability'] * 0.1, 40000);

        $optimizedIHT = max(0, $current['iht_liability'] - $giftingSavings - $trustSavings);

        return [
            'name' => 'Optimized Estate Plan',
            'gross_estate' => $current['gross_estate'],
            'net_estate' => $current['net_estate'],
            'iht_liability' => $optimizedIHT,
            'to_beneficiaries' => $current['net_estate'] - $optimizedIHT,
            'estimated_savings' => $current['iht_liability'] - $optimizedIHT,
            'strategies_applied' => ['gifting', 'trusts', 'allowance_optimization'],
        ];
    }

    /**
     * Build gifting strategy scenario.
     */
    private function buildGiftingScenario(User $user, array $parameters): array
    {
        $current = $this->buildCurrentScenario($user);
        $yearsOfGifting = $parameters['gifting_years'] ?? 7;
        $annualGiftAmount = $parameters['annual_gift'] ?? 3000;

        $totalGifted = $annualGiftAmount * $yearsOfGifting;
        $ihtSaved = $totalGifted * 0.4; // 40% IHT rate

        return [
            'name' => "Gifting Strategy ({$yearsOfGifting} years)",
            'gross_estate' => $current['gross_estate'] - $totalGifted,
            'net_estate' => $current['net_estate'] - $totalGifted,
            'iht_liability' => max(0, $current['iht_liability'] - $ihtSaved),
            'to_beneficiaries' => $current['net_estate'] - max(0, $current['iht_liability'] - $ihtSaved),
            'total_gifted' => $totalGifted,
            'estimated_iht_saved' => $ihtSaved,
        ];
    }

    /**
     * Build property downsizing scenario.
     */
    private function buildDownsizingScenario(User $user, array $parameters): array
    {
        $current = $this->buildCurrentScenario($user);
        $equityRelease = $parameters['equity_release'] ?? 200000;

        return [
            'name' => 'Property Downsizing',
            'gross_estate' => $current['gross_estate'] - $equityRelease,
            'net_estate' => $current['net_estate'] - $equityRelease,
            'iht_liability' => max(0, $current['iht_liability'] - ($equityRelease * 0.4)),
            'to_beneficiaries' => $current['net_estate'] - $equityRelease - max(0, $current['iht_liability'] - ($equityRelease * 0.4)),
            'cash_released' => $equityRelease,
        ];
    }

    /**
     * Build trust creation scenario.
     */
    private function buildTrustScenario(User $user, array $parameters): array
    {
        $current = $this->buildCurrentScenario($user);
        $trustValue = $parameters['trust_value'] ?? 325000;

        // Discretionary trust within NRB
        $ihtReduction = min($trustValue * 0.4, $current['iht_liability']);

        return [
            'name' => 'Trust Creation Strategy',
            'gross_estate' => $current['gross_estate'],
            'net_estate' => $current['net_estate'],
            'iht_liability' => max(0, $current['iht_liability'] - $ihtReduction),
            'to_beneficiaries' => $current['net_estate'] - max(0, $current['iht_liability'] - $ihtReduction),
            'trust_value' => $trustValue,
            'estimated_iht_saved' => $ihtReduction,
        ];
    }

    /**
     * Invalidate cache for user's estate analysis.
     *
     * Uses the standardised cache invalidation from BaseAgent.
     *
     * @param  int  $userId  User ID
     */
    public function invalidateCache(int $userId): void
    {
        $this->invalidateUserCache($userId, [
            "estate_analysis_{$userId}",
        ]);
    }
}
