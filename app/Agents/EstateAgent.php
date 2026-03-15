<?php

declare(strict_types=1);

namespace App\Agents;

use App\Constants\TaxDefaults;
use App\Models\Estate\Will;
use App\Models\LifeInsurancePolicy;
use App\Models\User;
use App\Services\Coordination\RecommendationPersonaliser;
use App\Services\Estate\ComprehensiveEstatePlanService;
use App\Services\Estate\EstateAssetAggregatorService;
use App\Services\Estate\EstateDataReadinessService;
use App\Services\Estate\GiftingStrategyOptimizer;
use App\Services\Estate\IHTCalculationService;
use App\Services\Estate\LifeCoverCalculator;
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
    /**
     * Fallback current age when user date of birth is unknown.
     */
    private const DEFAULT_CURRENT_AGE = 50;

    /**
     * Fallback life expectancy for planning calculations.
     */
    private const DEFAULT_LIFE_EXPECTANCY = 85;

    public function __construct(
        private readonly IHTCalculationService $ihtCalculator,
        private readonly EstateAssetAggregatorService $assetAggregator,
        private readonly ComprehensiveEstatePlanService $estatePlanService,
        private readonly GiftingStrategyOptimizer $giftingOptimizer,
        private readonly PersonalizedTrustStrategyService $trustStrategyService,
        private readonly WillAnalysisService $willAnalysisService,
        private readonly TaxConfigService $taxConfig,
        private readonly RecommendationPersonaliser $personaliser,
        private readonly EstateDataReadinessService $readinessService,
        private readonly LifeCoverCalculator $lifeCoverCalculator
    ) {}

    /**
     * Analyze user's estate planning situation.
     */
    public function analyze(int $userId): array
    {
        // Data readiness gate — return early if blocking checks fail
        $gateUser = User::find($userId);
        if ($gateUser) {
            $readiness = $this->readinessService->assess($gateUser);
            if (! $readiness['can_proceed']) {
                return $this->response(true, 'Readiness check incomplete', [
                    'can_proceed' => false,
                    'readiness_checks' => $readiness,
                    'summary' => null,
                    'asset_breakdown' => null,
                    'iht_calculation' => null,
                    'trust_recommendations' => null,
                    'gifting_opportunities' => null,
                    'trust_wish_triggers' => null,
                    'charitable_analysis' => null,
                    'will_review_status' => null,
                    'life_cover' => null,
                    'pension_amendment' => null,
                    'profile' => null,
                ]);
            }
        }

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

            // Calculate IHT (include spouse data when married and linked)
            $ihtCalculation = null;
            $ihtLiability = 0;
            $effectiveTaxRate = 0;

            try {
                $spouse = $user->spouse;
                $dataSharingEnabled = $spouse !== null;
                $ihtCalculation = $this->ihtCalculator->calculate($user, $spouse, $dataSharingEnabled);
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
                    : self::DEFAULT_CURRENT_AGE;
                $lifeExpectancy = $user->life_expectancy_override ?? self::DEFAULT_LIFE_EXPECTANCY;
                $yearsUntilDeath = max(1, $lifeExpectancy - $currentAge);
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

            // Will review status
            $willReviewStatus = null;
            if (isset($will) && $will) {
                $lastReviewed = $will->last_reviewed_date ?? $will->will_last_updated;
                $willReviewStatus = [
                    'has_will' => (bool) $will->has_will,
                    'last_reviewed_date' => $lastReviewed?->format('Y-m-d'),
                    'is_stale' => $lastReviewed ? $lastReviewed->lt(now()->subYears(3)) : true,
                ];
            }

            // Calculate current age and life expectancy context
            $currentAge = $user->date_of_birth ?
                (int) $user->date_of_birth->diffInYears(now()) : self::DEFAULT_CURRENT_AGE;

            // Assess existing life insurance policies for IHT planning suitability
            $allPolicies = LifeInsurancePolicy::where('user_id', $userId)->get();
            $policyAssessment = [];
            if ($allPolicies->isNotEmpty()) {
                try {
                    $policyAssessment = $this->lifeCoverCalculator->assessExistingPolicies($allPolicies, $user);
                } catch (\Throwable $e) {
                    // Continue without policy assessment
                }
            }

            // Extract pension amendment from IHT calculation (already computed)
            $pensionAmendment = $ihtCalculation['pension_amendment'] ?? ['amendment_warning' => false];

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
                    'will_review_status' => $willReviewStatus,
                    'life_cover' => [
                        'user_cover_in_trust' => (float) $lifePoliciesInTrust->sum('sum_assured'),
                        'spouse_cover_in_trust' => (float) $spouseLifeCoverInTrust,
                        'total_cover_in_trust' => (float) $lifePoliciesInTrust->sum('sum_assured') + $spouseLifeCoverInTrust,
                        'total_cover_not_in_trust' => (float) $lifePoliciesNotInTrust->sum('sum_assured'),
                        'policy_count' => $lifePoliciesInTrust->count(),
                        'policies_not_in_trust_count' => $lifePoliciesNotInTrust->count(),
                        'policy_assessment' => $policyAssessment,
                    ],
                    'pension_amendment' => $pensionAmendment,
                    'profile' => [
                        'current_age' => $currentAge,
                        'life_expectancy' => $user->life_expectancy_override ?? self::DEFAULT_LIFE_EXPECTANCY,
                        'marital_status' => $user->marital_status,
                        'has_dependents' => ($user->familyMembers()->where('relationship', 'child')->count() > 0),
                        'has_spouse' => $user->spouse !== null,
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
        $lifeExpectancy = $data['profile']['life_expectancy'] ?? self::DEFAULT_LIFE_EXPECTANCY;
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
                $annualGiftingResult = $this->step4AnnualGiftingStrategy($currentAge, $remainingLiability, $lifeExpectancy);
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
                $petResult = $this->step6PETGiftingStrategy($currentAge, $remainingLiability, $lifeExpectancy);
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
            $triggerCount = count($trustWishTriggers);
            $trustWishTrace = [];
            $trustWishTrace[] = [
                'question' => 'Do any wishes in your will require trust structures to implement?',
                'data_field' => 'Trust-triggering wishes identified',
                'data_value' => (string) $triggerCount.' '.($triggerCount === 1 ? 'wish' : 'wishes'),
                'threshold' => '0 wishes',
                'passed' => false,
                'explanation' => $triggerCount.' '.($triggerCount === 1 ? 'wish' : 'wishes').' in your will may require formal trust arrangements to ensure they are carried out as intended.',
            ];

            $recommendations[] = [
                'category' => 'will_trust_setup',
                'priority' => 'medium',
                'step' => 0,
                'title' => 'Will Wishes Require Trust Structures',
                'description' => $triggerCount.' wishes in your will may require trust arrangements',
                'actions' => array_map(fn ($t) => $t['recommendation'], array_slice($trustWishTriggers, 0, 3)),
                'details' => $trustWishTriggers,
                'decision_trace' => $trustWishTrace,
            ];
        }

        // Stale will warning
        $willReviewStatus = $data['will_review_status'] ?? null;
        if ($willReviewStatus && $willReviewStatus['has_will']) {
            $isStale = $willReviewStatus['is_stale'] ?? false;
            $lastReviewed = $willReviewStatus['last_reviewed_date'] ?? 'Not recorded';

            $staleWillTrace = [];
            $staleWillTrace[] = [
                'question' => 'Has your will been reviewed within the last 3 years?',
                'data_field' => 'Last will review date',
                'data_value' => $lastReviewed,
                'threshold' => 'Within the last 3 years',
                'passed' => ! $isStale,
                'explanation' => ! $isStale
                    ? 'Your will has been reviewed recently and is up to date.'
                    : 'Your will has not been reviewed in over 3 years. It is recommended to review your will every 3-5 years or after significant life events.',
            ];

            if ($isStale) {
                $recommendations[] = [
                    'category' => 'will_review',
                    'priority' => 'medium',
                    'step' => 0,
                    'title' => 'Will Review Recommended',
                    'description' => 'Your will has not been reviewed recently. It is recommended to review your will every 3-5 years or after significant life events.',
                    'actions' => [
                        'Schedule a review with your solicitor',
                        'Check that your executor details are still correct',
                        'Ensure your beneficiaries reflect your current wishes',
                    ],
                    'last_reviewed_date' => $lastReviewed,
                    'decision_trace' => $staleWillTrace,
                ];
            }
        }

        // Recommend completing missing data only when we lack essentials for a meaningful calculation
        $grossEstate = (float) ($data['summary']['gross_estate'] ?? 0);
        $hasDob = ($data['profile']['current_age'] ?? self::DEFAULT_CURRENT_AGE) !== self::DEFAULT_CURRENT_AGE;
        if ($grossEstate <= 0 || ! $hasDob) {
            $missingDataTrace = [];

            $missingDataTrace[] = [
                'question' => 'Is your date of birth recorded for life expectancy calculations?',
                'data_field' => 'Date of birth',
                'data_value' => $hasDob ? 'Recorded' : 'Not recorded',
                'threshold' => 'Must be recorded',
                'passed' => $hasDob,
                'explanation' => $hasDob
                    ? 'Your date of birth is recorded, enabling accurate life expectancy and gifting strategy calculations.'
                    : 'Without your date of birth, we cannot calculate life expectancy or determine optimal gifting timelines.',
            ];

            $missingDataTrace[] = [
                'question' => 'Do you have at least one asset recorded in your estate?',
                'data_field' => 'Gross estate value',
                'data_value' => '£'.number_format($grossEstate, 0),
                'threshold' => 'Greater than £0',
                'passed' => $grossEstate > 0,
                'explanation' => $grossEstate > 0
                    ? 'Your estate assets are recorded, enabling Inheritance Tax calculations.'
                    : 'No assets have been recorded. We need at least one asset to calculate your Inheritance Tax position.',
            ];

            $recommendations[] = [
                'category' => 'planning',
                'priority' => 'high',
                'step' => 0,
                'title' => 'Add Your Estate Data',
                'description' => 'We need your date of birth and at least one asset (property, savings, or investment) to calculate your Inheritance Tax position accurately.',
                'actions' => array_filter([
                    ! $hasDob ? 'Add your date of birth in your profile' : null,
                    $grossEstate <= 0 ? 'Add your assets (properties, savings, investments)' : null,
                    'Consider writing or updating your will',
                ]),
                'decision_trace' => $missingDataTrace,
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
        $trace = [];

        if (empty($charitableAnalysis)) {
            return null;
        }

        $status = $charitableAnalysis['status'] ?? 'below';
        $shortfall = $charitableAnalysis['shortfall'] ?? 0;
        $potentialSaving = $charitableAnalysis['potential_saving'] ?? 0;
        $currentSaving = $charitableAnalysis['current_saving'] ?? 0;
        $currentPercentage = $charitableAnalysis['current_percentage'] ?? 0;

        $trace[] = [
            'question' => 'Do your charitable bequests reach the 10% threshold for the reduced Inheritance Tax rate?',
            'data_field' => 'Charitable bequest percentage',
            'data_value' => round($currentPercentage, 1).'%',
            'threshold' => '10% of net estate',
            'passed' => $status !== 'below',
            'explanation' => $status !== 'below'
                ? 'Your charitable giving meets or exceeds the 10% threshold, qualifying for the reduced 36% rate.'
                : 'Your charitable giving is below the 10% threshold needed for the reduced 36% Inheritance Tax rate.',
        ];

        if ($status === 'below' && $potentialSaving > 0) {
            $trace[] = [
                'question' => 'How much additional charitable giving is needed to qualify?',
                'data_field' => 'Shortfall to 10% threshold',
                'data_value' => '£'.number_format($shortfall, 0),
                'threshold' => '£0 (no shortfall)',
                'passed' => false,
                'explanation' => 'Increasing charitable bequests by £'.number_format($shortfall, 0).' would save £'.number_format($potentialSaving, 0).' in Inheritance Tax.',
            ];

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
                'decision_trace' => $trace,
            ];
        }

        if ($status !== 'below' && $currentSaving > 0) {
            $trace[] = [
                'question' => 'How much Inheritance Tax is saved by the reduced charitable rate?',
                'data_field' => 'Current saving from charitable rate',
                'data_value' => '£'.number_format($currentSaving, 0),
                'threshold' => '£0',
                'passed' => true,
                'explanation' => 'The reduced 36% rate is already saving you £'.number_format($currentSaving, 0).' in Inheritance Tax.',
            ];

            return [
                'category' => 'charitable_bequest',
                'priority' => 'low',
                'step' => 1,
                'title' => 'Charitable Rate Applied',
                'description' => "Your charitable giving qualifies for the reduced 36% IHT rate, saving {$this->formatCurrency($currentSaving)}.",
                'actions' => ['Your current charitable bequests are sufficient for the reduced rate'],
                'current_saving' => $currentSaving,
                'decision_trace' => $trace,
            ];
        }

        return null;
    }

    /**
     * Step 2: Liquidity & Affordability Assessment
     */
    private function step2LiquidityAssessment(array $data): array
    {
        $trace = [];

        $assetBreakdown = $data['asset_breakdown'] ?? [];
        $liquidAssets = $assetBreakdown['liquid'] ?? 0;
        $ihtLiability = $data['summary']['iht_liability'] ?? 0;

        $liquidityRatio = $ihtLiability > 0 ? $liquidAssets / $ihtLiability : 1;
        $hasLiquidityIssue = $liquidityRatio < 0.5;

        $trace[] = [
            'question' => 'Do your liquid assets cover at least 50% of your Inheritance Tax liability?',
            'data_field' => 'Liquidity ratio',
            'data_value' => round($liquidityRatio * 100, 1).'%',
            'threshold' => '50% (liquid assets to Inheritance Tax liability)',
            'passed' => ! $hasLiquidityIssue,
            'explanation' => ! $hasLiquidityIssue
                ? 'Your liquid assets of £'.number_format($liquidAssets, 0).' provide adequate coverage for your Inheritance Tax liability.'
                : 'Your liquid assets of £'.number_format($liquidAssets, 0).' cover only '.round($liquidityRatio * 100, 1).'% of your £'.number_format($ihtLiability, 0).' Inheritance Tax liability.',
        ];

        $recommendation = null;
        if ($hasLiquidityIssue && $ihtLiability > 0) {
            $shortfall = $ihtLiability - $liquidAssets;

            $trace[] = [
                'question' => 'What is the liquidity shortfall?',
                'data_field' => 'Liquidity shortfall',
                'data_value' => '£'.number_format($shortfall, 0),
                'threshold' => '£0 (no shortfall)',
                'passed' => false,
                'explanation' => 'Your beneficiaries may need to sell assets to pay the £'.number_format($shortfall, 0).' shortfall unless alternative liquidity sources are arranged.',
            ];

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
                'decision_trace' => $trace,
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
        $trace = [];

        $lifeCover = $data['life_cover'] ?? [];
        $existingCover = (float) ($lifeCover['total_cover_in_trust'] ?? 0);
        $liabilities = $data['summary']['total_liabilities'] ?? 0;
        $ihtLiability = $data['summary']['iht_liability'] ?? 0;

        $usableCover = max(0, $existingCover - $liabilities);

        $trace[] = [
            'question' => 'Do you have life insurance policies written in trust?',
            'data_field' => 'Total life cover in trust',
            'data_value' => '£'.number_format($existingCover, 0),
            'threshold' => '£0 (any cover in trust is beneficial)',
            'passed' => $existingCover > 0,
            'explanation' => $existingCover > 0
                ? 'You have £'.number_format($existingCover, 0).' of life cover written in trust, which bypasses your estate for Inheritance Tax purposes.'
                : 'You have no life insurance policies written in trust. Policies in trust can provide liquidity to pay Inheritance Tax without adding to your estate.',
        ];

        $trace[] = [
            'question' => 'After deducting liabilities, is there usable cover to offset Inheritance Tax?',
            'data_field' => 'Usable cover after liabilities',
            'data_value' => '£'.number_format($usableCover, 0),
            'threshold' => '£'.number_format($ihtLiability, 0).' (Inheritance Tax liability)',
            'passed' => $usableCover >= $ihtLiability,
            'explanation' => $usableCover > 0
                ? '£'.number_format($usableCover, 0).' of life cover is available to offset your Inheritance Tax liability.'
                : 'No usable cover remains after accounting for liabilities.',
        ];

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
                'decision_trace' => $trace,
            ];
        }

        $trustPlacementTrace = [];
        $notInTrustCount = $lifeCover['policies_not_in_trust_count'] ?? 0;
        $notInTrustValue = (float) ($lifeCover['total_cover_not_in_trust'] ?? 0);

        $trustPlacementTrace[] = [
            'question' => 'Do you have life insurance policies not written in trust?',
            'data_field' => 'Policies not in trust',
            'data_value' => (string) $notInTrustCount.' '.($notInTrustCount === 1 ? 'policy' : 'policies').' (£'.number_format($notInTrustValue, 0).')',
            'threshold' => '0 policies (all should be in trust)',
            'passed' => $notInTrustCount === 0,
            'explanation' => $notInTrustCount > 0
                ? $notInTrustCount.' '.($notInTrustCount === 1 ? 'policy' : 'policies').' totalling £'.number_format($notInTrustValue, 0).' could be placed in trust to bypass your estate.'
                : 'All your life insurance policies are written in trust.',
        ];

        $trustPlacementRecommendation = null;
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
                    $this->formatCurrency($notInTrustValue)
                ),
                'actions' => ['Contact your insurance provider to place existing policies in trust'],
                'decision_trace' => $trustPlacementTrace,
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
    private function step4AnnualGiftingStrategy(int $currentAge, float $remainingLiability, int $lifeExpectancy = self::DEFAULT_LIFE_EXPECTANCY): array
    {
        $trace = [];

        $ihtConfig = $this->taxConfig->getInheritanceTax();
        $annualExemption = $ihtConfig['annual_exemption'] ?? TaxDefaults::ANNUAL_GIFT_EXEMPTION;

        // Estimate years to life expectancy
        $yearsToLifeExpectancy = max(1, $lifeExpectancy - $currentAge);

        $trace[] = [
            'question' => 'How many years of annual gift exemptions are available based on life expectancy?',
            'data_field' => 'Years to life expectancy',
            'data_value' => (string) $yearsToLifeExpectancy.' years',
            'threshold' => '1 year (minimum for strategy to be worthwhile)',
            'passed' => $yearsToLifeExpectancy >= 1,
            'explanation' => 'At age '.$currentAge.' with a life expectancy of '.$lifeExpectancy.', you have approximately '.$yearsToLifeExpectancy.' years of annual exemptions available.',
        ];

        // Annual exemption potential (including carry forward from unused previous year)
        $annualGiftingCapacity = $annualExemption * $yearsToLifeExpectancy;

        // IHT saved at 40% rate
        $potentialSavings = min($annualGiftingCapacity * 0.40, $remainingLiability);

        $coversLiability = $potentialSavings >= $remainingLiability;

        $trace[] = [
            'question' => 'Can annual gifting fully offset the remaining Inheritance Tax liability?',
            'data_field' => 'Potential Inheritance Tax saving from annual gifting',
            'data_value' => '£'.number_format($potentialSavings, 0),
            'threshold' => '£'.number_format($remainingLiability, 0).' (remaining liability)',
            'passed' => $coversLiability,
            'explanation' => $coversLiability
                ? 'Annual gifting of £'.number_format($annualExemption, 0).'/year over '.$yearsToLifeExpectancy.' years could fully offset the remaining liability.'
                : 'Annual gifting can reduce the liability by £'.number_format($potentialSavings, 0).', but £'.number_format($remainingLiability - $potentialSavings, 0).' would remain.',
        ];

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
            'decision_trace' => $trace,
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
        $trace = [];

        // Estimate whole of life premium (simplified calculation)
        $estimatedAnnualPremium = $remainingLiability * 0.02; // ~2% of cover per year

        $trace[] = [
            'question' => 'Is there a remaining Inheritance Tax liability that life cover could address?',
            'data_field' => 'Remaining liability after prior strategies',
            'data_value' => '£'.number_format($remainingLiability, 0),
            'threshold' => '£0 (no remaining liability)',
            'passed' => $remainingLiability <= 0,
            'explanation' => 'A whole of life policy for £'.number_format($remainingLiability, 0).' could cover the remaining Inheritance Tax liability, providing funds outside of the estate.',
        ];

        $hasLiquidityIssue = $liquidityData['has_issue'] ?? false;

        $trace[] = [
            'question' => 'Is there a liquidity concern that makes life cover more urgent?',
            'data_field' => 'Liquidity issue identified',
            'data_value' => $hasLiquidityIssue ? 'Yes' : 'No',
            'threshold' => 'No liquidity issue',
            'passed' => ! $hasLiquidityIssue,
            'explanation' => $hasLiquidityIssue
                ? 'A liquidity shortfall has been identified. Life cover written in trust would provide immediate funds to pay the Inheritance Tax bill without requiring asset sales.'
                : 'No liquidity issue identified, but life cover still provides certainty of funds for Inheritance Tax payment.',
        ];

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
            'decision_trace' => $trace,
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
    private function step6PETGiftingStrategy(int $currentAge, float $remainingLiability, int $lifeExpectancy = self::DEFAULT_LIFE_EXPECTANCY): array
    {
        $trace = [];

        $ihtConfig = $this->taxConfig->getInheritanceTax();
        $nrb = $ihtConfig['nil_rate_band'] ?? TaxDefaults::NRB;

        // Calculate years to life expectancy
        $yearsToLifeExpectancy = max(1, $lifeExpectancy - $currentAge);

        // Calculate 7-year cycles available
        $sevenYearCycles = floor($yearsToLifeExpectancy / 7);

        $trace[] = [
            'question' => 'How many seven-year cycles are available based on your life expectancy?',
            'data_field' => 'Seven-year cycles available',
            'data_value' => (string) $sevenYearCycles.' '.($sevenYearCycles === 1.0 ? 'cycle' : 'cycles'),
            'threshold' => '1 cycle (minimum for Potentially Exempt Transfer strategy)',
            'passed' => $sevenYearCycles >= 1,
            'explanation' => $sevenYearCycles >= 1
                ? 'With '.$yearsToLifeExpectancy.' years to life expectancy, you have '.$sevenYearCycles.' complete seven-year '.($sevenYearCycles === 1.0 ? 'cycle' : 'cycles').' for Potentially Exempt Transfers.'
                : 'With only '.$yearsToLifeExpectancy.' years to life expectancy, there is insufficient time for a Potentially Exempt Transfer to become fully exempt.',
        ];

        // Each cycle can gift up to NRB tax-efficiently
        $petCapacity = $sevenYearCycles * $nrb;
        $potentialSavings = min($petCapacity * 0.40, $remainingLiability);

        if ($sevenYearCycles >= 1) {
            $trace[] = [
                'question' => 'Can Potentially Exempt Transfers cover the remaining Inheritance Tax liability?',
                'data_field' => 'Potential Inheritance Tax saving from Potentially Exempt Transfers',
                'data_value' => '£'.number_format($potentialSavings, 0),
                'threshold' => '£'.number_format($remainingLiability, 0).' (remaining liability)',
                'passed' => $potentialSavings >= $remainingLiability,
                'explanation' => 'Each seven-year cycle can shelter up to £'.number_format($nrb, 0).' (the Nil Rate Band). Total capacity: £'.number_format($petCapacity, 0).'.',
            ];
        }

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
                'decision_trace' => $trace,
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
        $trace = [];

        $ihtConfig = $this->taxConfig->getInheritanceTax();
        $nrb = $ihtConfig['nil_rate_band'] ?? TaxDefaults::NRB;
        $cltRate = $ihtConfig['clt_rate'] ?? TaxDefaults::CLT_RATE;

        $trace[] = [
            'question' => 'Is there still a remaining Inheritance Tax liability after all prior strategies?',
            'data_field' => 'Remaining liability',
            'data_value' => '£'.number_format($remainingLiability, 0),
            'threshold' => '£0 (no remaining liability)',
            'passed' => $remainingLiability <= 0,
            'explanation' => 'Steps 1 to 6 have been unable to fully offset the Inheritance Tax liability. £'.number_format($remainingLiability, 0).' remains, making a Chargeable Lifetime Transfer a last-resort option.',
        ];

        // Calculate immediate charge if CLT exceeds NRB
        $excessOverNRB = max(0, $remainingLiability - $nrb);
        $immediateCharge = $excessOverNRB * $cltRate;

        $trace[] = [
            'question' => 'Does the transfer amount exceed the Nil Rate Band, triggering an immediate charge?',
            'data_field' => 'Amount exceeding Nil Rate Band',
            'data_value' => '£'.number_format($excessOverNRB, 0),
            'threshold' => '£'.number_format($nrb, 0).' (Nil Rate Band)',
            'passed' => $excessOverNRB <= 0,
            'explanation' => $excessOverNRB > 0
                ? 'A Chargeable Lifetime Transfer of £'.number_format($remainingLiability, 0).' exceeds the Nil Rate Band by £'.number_format($excessOverNRB, 0).', incurring an immediate charge of £'.number_format($immediateCharge, 0).' at '.round($cltRate * 100).'%.'
                : 'The transfer amount is within the Nil Rate Band, so no immediate charge would apply.',
        ];

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
            'decision_trace' => $trace,
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

        // Classify by liquidity (aligned with AssetLiquidityAnalyzer reclassification)
        $liquidTypes = ['cash', 'savings'];
        $semiLiquidTypes = ['investment'];
        $illiquidTypes = ['pension', 'dc_pension', 'db_pension'];
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
            $spouse = $user->spouse;
            $dataSharingEnabled = $spouse !== null;
            $result = $this->ihtCalculator->calculate($user, $spouse, $dataSharingEnabled);
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
