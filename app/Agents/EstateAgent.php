<?php

declare(strict_types=1);

namespace App\Agents;

use App\Constants\TaxDefaults;
use App\Models\Estate\Will;
use App\Models\Goal;
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
        // Load user once with all needed relationships (avoids duplicate query)
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
        ])->find($userId);

        if (! $user) {
            return $this->response(false, 'User not found', []);
        }

        // Data readiness gate — return early if blocking checks fail
        $readiness = $this->readinessService->assess($user);
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
                'goal_liquidity' => null,
                'profile' => null,
            ]);
        }

        $cacheKey = "estate_analysis_{$userId}";
        $cacheTags = ['estate', 'user_'.$userId];

        return $this->remember($cacheKey, function () use ($user, $userId) {

            // Load all life insurance policies in a single query and filter in-memory
            $allLifePolicies = LifeInsurancePolicy::where('user_id', $userId)->get();
            $lifePoliciesInTrust = $allLifePolicies->where('in_trust', true);
            $lifePoliciesNotInTrust = $allLifePolicies->filter(fn ($p) => ! $p->in_trust);

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
                report($e);
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
                    report($e);
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
                report($e);
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
                report($e);
                // Continue without wish triggers
            }

            // Analyze charitable bequests
            $charitableAnalysis = [];
            try {
                $netEstate = $assetSummary['net_estate'] ?? 0;
                $charitableAnalysis = $this->willAnalysisService->analyzeCharitableBequests($user, $netEstate);
            } catch (\Throwable $e) {
                report($e);
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
            $policyAssessment = [];
            if ($allLifePolicies->isNotEmpty()) {
                try {
                    $policyAssessment = $this->lifeCoverCalculator->assessExistingPolicies($allLifePolicies, $user);
                } catch (\Throwable $e) {
                    report($e);
                    // Continue without policy assessment
                }
            }

            // Extract pension amendment from IHT calculation (already computed)
            $pensionAmendment = $ihtCalculation['pension_amendment'] ?? ['amendment_warning' => false];

            // Build itemised asset list for granular decision traces
            $gatheredAssets = $this->assetAggregator->gatherUserAssets($user);
            $itemisedAssets = $gatheredAssets->map(fn ($a) => [
                'name' => $a->asset_name ?? 'Unknown',
                'type' => $a->asset_type ?? 'unknown',
                'value' => round((float) ($a->current_value ?? 0), 2),
                'full_value' => round((float) ($a->full_value ?? $a->current_value ?? 0), 2),
                'ownership_type' => $a->ownership_type ?? 'individual',
                'is_iht_exempt' => $a->is_iht_exempt ?? false,
            ])->values()->toArray();

            // Build itemised life policy list
            $itemisedPolicies = $allLifePolicies->map(fn ($p) => [
                'provider' => $p->provider ?? 'Unknown provider',
                'policy_type' => $p->policy_type ?? 'life',
                'sum_assured' => (float) ($p->sum_assured ?? 0),
                'in_trust' => (bool) ($p->in_trust ?? false),
            ])->values()->toArray();

            // Build gift summary
            $giftSummary = $user->gifts->map(fn ($g) => [
                'recipient' => $g->recipient ?? 'Unknown',
                'gift_type' => $g->gift_type ?? 'unknown',
                'gift_value' => (float) ($g->gift_value ?? 0),
                'gift_date' => $g->gift_date?->format('Y-m-d'),
            ])->values()->toArray();

            // Build trust summary
            $trustSummary = $user->trusts->map(fn ($t) => [
                'trust_name' => $t->trust_name ?? 'Unnamed trust',
                'trust_type' => $t->trust_type ?? 'unknown',
                'current_value' => (float) ($t->current_value ?? 0),
            ])->values()->toArray();

            // Goal liquidity risk — outstanding goal funding that may compete with estate liquidity
            $activeGoals = Goal::forUserOrJoint($userId)->where('status', 'active')->get();
            $goalLiquidity = [
                'total_outstanding' => round($activeGoals->sum(fn ($g) => max(0, (float) $g->target_amount - (float) $g->current_amount)), 2),
                'goals' => $activeGoals->map(fn ($g) => [
                    'name' => $g->goal_name,
                    'outstanding' => round(max(0, (float) $g->target_amount - (float) $g->current_amount), 2),
                ])->filter(fn ($g) => $g['outstanding'] > 0)->values()->toArray(),
            ];

            // Build user context for recommendation traces
            $userContext = [
                'first_name' => $user->first_name ?? 'User',
                'surname' => $user->surname ?? '',
                'date_of_birth' => $user->date_of_birth?->format('Y-m-d'),
                'marital_status' => $user->marital_status ?? 'unknown',
                'spouse_first_name' => $user->spouse?->first_name,
                'spouse_surname' => $user->spouse?->surname,
                'itemised_assets' => $itemisedAssets,
                'itemised_policies' => $itemisedPolicies,
                'gift_summary' => $giftSummary,
                'trust_summary' => $trustSummary,
                'has_will' => isset($will) && $will && $will->has_will,
                'will_executor' => isset($will) ? ($will->executor_name ?? null) : null,
            ];

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
                    'goal_liquidity' => $goalLiquidity,
                    'profile' => [
                        'current_age' => $currentAge,
                        'life_expectancy' => $user->life_expectancy_override ?? self::DEFAULT_LIFE_EXPECTANCY,
                        'marital_status' => $user->marital_status,
                        'has_dependents' => ($user->familyMembers()->where('relationship', 'child')->count() > 0),
                        'has_spouse' => $user->spouse !== null,
                    ],
                    'user_context' => $userContext,
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
        $grossEstate = $data['summary']['gross_estate'] ?? 0;
        $totalLiabilities = $data['summary']['total_liabilities'] ?? 0;
        $currentAge = $data['profile']['current_age'] ?? 50;
        $lifeExpectancy = $data['profile']['life_expectancy'] ?? self::DEFAULT_LIFE_EXPECTANCY;
        $charitableAnalysis = $data['charitable_analysis'] ?? [];
        $trustWishTriggers = $data['trust_wish_triggers'] ?? [];
        $ihtCalc = $data['iht_calculation'] ?? [];

        // Build estate context from analysis data for granular traces
        $ctx = $this->buildEstateContext($data);

        // Only generate mitigation recommendations if there's an IHT liability
        if ($ihtLiability > 0) {
            $remainingLiability = $ihtLiability;

            // STEP 1: Charitable Bequest Check (Rate Reduction)
            $step1Result = $this->step1CharitableBequestCheck($charitableAnalysis, $ihtLiability, $ctx);
            if ($step1Result) {
                $recommendations[] = $step1Result;
            }

            // STEP 2: Liquidity & Affordability Assessment
            $liquidityData = $this->step2LiquidityAssessment($data, $ctx);
            if ($liquidityData['recommendation']) {
                $recommendations[] = $liquidityData['recommendation'];
            }

            // STEP 3: Check Existing Life Cover
            $lifeCoverData = $this->step3ExistingLifeCover($data, $ctx);
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
                $annualGiftingResult = $this->step4AnnualGiftingStrategy($currentAge, $remainingLiability, $lifeExpectancy, $ctx);
                if ($annualGiftingResult['recommendation']) {
                    $recommendations[] = $annualGiftingResult['recommendation'];
                }
                $remainingLiability = max(0, $remainingLiability - $annualGiftingResult['potential_savings']);
            }

            // STEP 5: Life Cover Strategy (Second Resort) - Only if age <= 50
            if ($remainingLiability > 0 && $currentAge <= 50) {
                $lifeCoverStrategyResult = $this->step5LifeCoverStrategy($remainingLiability, $liquidityData, $ctx);
                if ($lifeCoverStrategyResult['recommendation']) {
                    $recommendations[] = $lifeCoverStrategyResult['recommendation'];
                }
                $remainingLiability = max(0, $remainingLiability - $lifeCoverStrategyResult['cover_amount']);
            }

            // STEP 6: PET Gifting Strategy (Third Resort)
            if ($remainingLiability > 0) {
                $petResult = $this->step6PETGiftingStrategy($currentAge, $remainingLiability, $lifeExpectancy, $ctx);
                if ($petResult['recommendation']) {
                    $recommendations[] = $petResult['recommendation'];
                }
                $remainingLiability = max(0, $remainingLiability - $petResult['potential_savings']);
            }

            // STEP 7: CLT into Trust (Last Resort ONLY)
            if ($remainingLiability > 0) {
                $cltResult = $this->step7CLTIntoTrust($remainingLiability, $ctx);
                if ($cltResult['recommendation']) {
                    $recommendations[] = $cltResult['recommendation'];
                }
            }
        }

        // Trust wish triggers from will analysis
        if (! empty($trustWishTriggers)) {
            $triggerCount = count($trustWishTriggers);
            $trustWishTrace = $this->buildEstateContextTrace($ctx);
            $trustWishTrace[] = [
                'question' => 'Do any wishes in '.$ctx['first_name'].'\'s will require trust structures to implement?',
                'data_field' => 'Trust-triggering wishes identified',
                'data_value' => (string) $triggerCount.' '.($triggerCount === 1 ? 'wish' : 'wishes'),
                'threshold' => '0 wishes',
                'passed' => false,
                'explanation' => $triggerCount.' '.($triggerCount === 1 ? 'wish' : 'wishes')
                    .' in '.$ctx['first_name'].'\'s will may require formal trust arrangements to ensure they are carried out as intended.'
                    .($ctx['will_executor'] ? ' Current executor: '.$ctx['will_executor'].'.' : ''),
            ];

            // Add trust context if existing trusts are recorded
            if (! empty($ctx['trust_summary_text'])) {
                $trustWishTrace[] = [
                    'question' => 'Are there existing trust structures that could accommodate these wishes?',
                    'data_field' => 'Existing trusts',
                    'data_value' => $ctx['trust_summary_text'],
                    'threshold' => 'Review existing trusts before creating new ones',
                    'passed' => true,
                    'explanation' => 'Existing trust structures should be reviewed to determine if they can accommodate the wishes before establishing new trusts.',
                ];
            }

            $recommendations[] = [
                'category' => 'will_trust_setup',
                'priority' => 'medium',
                'step' => 0,
                'title' => 'Will Wishes Require Trust Structures',
                'description' => $triggerCount.' wishes in '.$ctx['first_name'].'\'s will may require trust arrangements',
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

            $staleWillTrace = $this->buildEstateContextTrace($ctx);
            $staleWillTrace[] = [
                'question' => 'Has '.$ctx['first_name'].'\'s will been reviewed within the last 3 years?',
                'data_field' => 'Last will review date',
                'data_value' => $lastReviewed,
                'threshold' => 'Within the last 3 years',
                'passed' => ! $isStale,
                'explanation' => ! $isStale
                    ? $ctx['first_name'].'\'s will has been reviewed recently and is up to date.'
                    : $ctx['first_name'].'\'s will has not been reviewed in over 3 years.'
                        .($ctx['will_executor'] ? ' Executor: '.$ctx['will_executor'].'.' : '')
                        .' It is recommended to review your will every 3-5 years or after significant life events.',
            ];

            if ($isStale) {
                $recommendations[] = [
                    'category' => 'will_review',
                    'priority' => 'medium',
                    'step' => 0,
                    'title' => 'Will Review Recommended',
                    'description' => $ctx['first_name'].'\'s will has not been reviewed recently. It is recommended to review your will every 3-5 years or after significant life events.',
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
        $hasDob = ($data['profile']['current_age'] ?? self::DEFAULT_CURRENT_AGE) !== self::DEFAULT_CURRENT_AGE;
        if ($grossEstate <= 0 || ! $hasDob) {
            $missingDataTrace = [];

            $missingDataTrace[] = [
                'question' => 'Is '.$ctx['first_name'].'\'s date of birth recorded for life expectancy calculations?',
                'data_field' => 'Date of birth',
                'data_value' => $hasDob ? 'Recorded (age '.$currentAge.')' : 'Not recorded',
                'threshold' => 'Must be recorded',
                'passed' => $hasDob,
                'explanation' => $hasDob
                    ? $ctx['first_name'].'\'s date of birth is recorded (age '.$currentAge.'), enabling accurate life expectancy and gifting strategy calculations.'
                    : 'Without '.$ctx['first_name'].'\'s date of birth, we cannot calculate life expectancy or determine optimal gifting timelines.',
            ];

            $missingDataTrace[] = [
                'question' => 'Does '.$ctx['first_name'].' have at least one asset recorded in their estate?',
                'data_field' => 'Gross estate value',
                'data_value' => '£'.number_format($grossEstate, 0),
                'threshold' => 'Greater than £0',
                'passed' => $grossEstate > 0,
                'explanation' => $grossEstate > 0
                    ? $ctx['first_name'].'\'s estate assets total £'.number_format($grossEstate, 0).', enabling Inheritance Tax calculations.'
                    : 'No assets have been recorded for '.$ctx['first_name'].'. We need at least one asset (property, savings, or investment) to calculate the Inheritance Tax position.',
            ];

            $recommendations[] = [
                'category' => 'planning',
                'priority' => 'high',
                'step' => 0,
                'title' => 'Add Your Estate Data',
                'description' => 'We need '.$ctx['first_name'].'\'s date of birth and at least one asset (property, savings, or investment) to calculate the Inheritance Tax position accurately.',
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
     * Step 1: Charitable Bequest Check - Rate Reduction (standard to reduced charitable rate)
     */
    private function step1CharitableBequestCheck(array $charitableAnalysis, float $ihtLiability, array $ctx): ?array
    {
        if (empty($charitableAnalysis)) {
            return null;
        }

        $trace = $this->buildEstateContextTrace($ctx);

        $ihtConfig = $this->taxConfig->getInheritanceTax();
        $standardRate = (float) ($ihtConfig['standard_rate'] ?? TaxDefaults::IHT_RATE);
        $reducedRate = (float) ($ihtConfig['reduced_rate_charity'] ?? 0.36);
        $standardRatePercent = round($standardRate * 100);
        $reducedRatePercent = round($reducedRate * 100);

        $status = $charitableAnalysis['status'] ?? 'below';
        $shortfall = $charitableAnalysis['shortfall'] ?? 0;
        $potentialSaving = $charitableAnalysis['potential_saving'] ?? 0;
        $currentSaving = $charitableAnalysis['current_saving'] ?? 0;
        $charitableTotal = $charitableAnalysis['charitable_total'] ?? 0;
        $baseline = $charitableAnalysis['baseline'] ?? 0;
        $threshold = $charitableAnalysis['threshold'] ?? 0;

        // Calculate actual percentage of baseline
        $currentPercentage = $baseline > 0 ? ($charitableTotal / $baseline) * 100 : 0;

        $trace[] = [
            'question' => 'Do '.$ctx['first_name'].'\'s charitable bequests reach the 10% threshold for the reduced Inheritance Tax rate?',
            'data_field' => 'Charitable bequest percentage of baseline',
            'data_value' => round($currentPercentage, 1).'% (£'.number_format($charitableTotal, 0).' of £'.number_format($baseline, 0).' baseline)',
            'threshold' => '10% of baseline (£'.number_format($threshold, 0).')',
            'passed' => $status !== 'below',
            'explanation' => $status !== 'below'
                ? $ctx['first_name'].'\'s charitable giving of £'.number_format($charitableTotal, 0).' meets or exceeds the 10% threshold of £'.number_format($threshold, 0).', qualifying for the reduced '.$reducedRatePercent.'% rate.'
                : $ctx['first_name'].'\'s charitable giving of £'.number_format($charitableTotal, 0).' is '.round($currentPercentage, 1).'% of the £'.number_format($baseline, 0).' baseline (net estate minus Nil Rate Band). The 10% threshold is £'.number_format($threshold, 0).'.',
        ];

        if ($status === 'below' && $potentialSaving > 0) {
            // Show the IHT rate reduction calculation
            $taxableEstate = $ctx['taxable_estate'];
            $currentTax = $taxableEstate * $standardRate;
            $reducedTax = $taxableEstate * $reducedRate;

            $trace[] = [
                'question' => 'How much additional charitable giving is needed and what would it save?',
                'data_field' => 'Shortfall to 10% threshold',
                'data_value' => '£'.number_format($shortfall, 0),
                'threshold' => '£0 (no shortfall)',
                'passed' => false,
                'explanation' => 'If '.$ctx['first_name'].' increases charitable bequests by £'.number_format($shortfall, 0)
                    .' (to reach £'.number_format($threshold, 0).'), the Inheritance Tax rate drops from '.$standardRatePercent.'% to '.$reducedRatePercent.'%.'
                    .' On the taxable estate of £'.number_format($taxableEstate, 0)
                    .': at '.$standardRatePercent.'% = £'.number_format($currentTax, 0)
                    .', at '.$reducedRatePercent.'% = £'.number_format($reducedTax, 0)
                    .' — saving £'.number_format($potentialSaving, 0).'.',
            ];

            return [
                'category' => 'charitable_bequest',
                'priority' => 'high',
                'step' => 1,
                'title' => 'Charitable Bequest Opportunity',
                'description' => "Increase charitable giving by {$this->formatCurrency($shortfall)} to qualify for the reduced {$reducedRatePercent}% Inheritance Tax rate and save {$this->formatCurrency($potentialSaving)}.",
                'actions' => [
                    "Add {$this->formatCurrency($shortfall)} in charitable bequests to {$ctx['first_name']}'s will",
                    'Consider leaving to registered UK charities',
                    "This reduces the Inheritance Tax rate from {$standardRatePercent}% to {$reducedRatePercent}%",
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
                'explanation' => $ctx['first_name'].'\'s charitable giving of £'.number_format($charitableTotal, 0)
                    .' qualifies for the reduced '.$reducedRatePercent.'% rate, saving £'.number_format($currentSaving, 0).' on the taxable estate of £'.number_format($ctx['taxable_estate'], 0).'.',
            ];

            return [
                'category' => 'charitable_bequest',
                'priority' => 'low',
                'step' => 1,
                'title' => 'Charitable Rate Applied',
                'description' => "{$ctx['first_name']}'s charitable giving qualifies for the reduced {$reducedRatePercent}% Inheritance Tax rate, saving {$this->formatCurrency($currentSaving)}.",
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
    private function step2LiquidityAssessment(array $data, array $ctx): array
    {
        $trace = $this->buildEstateContextTrace($ctx);

        $assetBreakdown = $data['asset_breakdown'] ?? [];
        $liquidAssets = $assetBreakdown['liquid'] ?? 0;
        $semiLiquidAssets = $assetBreakdown['semi_liquid'] ?? 0;
        $illiquidAssets = $assetBreakdown['illiquid'] ?? 0;
        $ihtLiability = $data['summary']['iht_liability'] ?? 0;

        $liquidityRatio = $ihtLiability > 0 ? $liquidAssets / $ihtLiability : 1;
        $hasLiquidityIssue = $liquidityRatio < 0.5;

        // Build itemised liquid assets list from context
        $liquidAssetNames = $this->filterAssetNamesByType($ctx, ['cash', 'savings']);
        $semiLiquidAssetNames = $this->filterAssetNamesByType($ctx, ['investment']);
        $illiquidAssetNames = $this->filterAssetNamesByType($ctx, ['property', 'pension', 'dc_pension', 'db_pension', 'business', 'chattel']);

        $liquidDetail = ! empty($liquidAssetNames)
            ? implode(', ', $liquidAssetNames)
            : 'No liquid assets recorded';

        $trace[] = [
            'question' => 'Do '.$ctx['first_name'].'\'s liquid assets cover at least 50% of the Inheritance Tax liability?',
            'data_field' => 'Liquidity breakdown',
            'data_value' => 'Liquid: £'.number_format($liquidAssets, 0)
                .' | Semi-liquid (investments): £'.number_format($semiLiquidAssets, 0)
                .' | Illiquid (property, pensions, other): £'.number_format($illiquidAssets, 0),
            'threshold' => '50% of £'.number_format($ihtLiability, 0).' Inheritance Tax liability (£'.number_format($ihtLiability * 0.5, 0).')',
            'passed' => ! $hasLiquidityIssue,
            'explanation' => ! $hasLiquidityIssue
                ? $ctx['first_name'].'\'s liquid assets of £'.number_format($liquidAssets, 0).' ('.$liquidDetail.') provide adequate coverage ('.round($liquidityRatio * 100, 1).'%) for the Inheritance Tax liability.'
                : $ctx['first_name'].'\'s liquid assets of £'.number_format($liquidAssets, 0).' ('.$liquidDetail.') cover only '.round($liquidityRatio * 100, 1).'% of the £'.number_format($ihtLiability, 0).' Inheritance Tax liability. Beneficiaries may need to sell illiquid assets.',
        ];

        $recommendation = null;
        if ($hasLiquidityIssue && $ihtLiability > 0) {
            $shortfall = $ihtLiability - $liquidAssets;

            $trace[] = [
                'question' => 'What is the liquidity shortfall and which assets might need to be sold?',
                'data_field' => 'Liquidity shortfall',
                'data_value' => '£'.number_format($shortfall, 0),
                'threshold' => '£0 (no shortfall)',
                'passed' => false,
                'explanation' => $ctx['first_name'].'\'s beneficiaries may need to sell assets to pay the £'.number_format($shortfall, 0).' shortfall.'
                    .(! empty($illiquidAssetNames) ? ' Illiquid assets that may need to be sold: '.implode(', ', $illiquidAssetNames).'.' : '')
                    .(! empty($semiLiquidAssetNames) ? ' Semi-liquid investments that could be liquidated: '.implode(', ', $semiLiquidAssetNames).'.' : ''),
            ];

            $recommendation = [
                'category' => 'liquidity',
                'priority' => 'high',
                'step' => 2,
                'title' => 'Liquidity Risk Identified',
                'description' => "{$ctx['first_name']}'s liquid assets of {$this->formatCurrency($liquidAssets)} may not cover the Inheritance Tax liability of {$this->formatCurrency($ihtLiability)}.",
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
    private function step3ExistingLifeCover(array $data, array $ctx): array
    {
        $trace = $this->buildEstateContextTrace($ctx);

        $lifeCover = $data['life_cover'] ?? [];
        $existingCover = (float) ($lifeCover['total_cover_in_trust'] ?? 0);
        $userCoverInTrust = (float) ($lifeCover['user_cover_in_trust'] ?? 0);
        $spouseCoverInTrust = (float) ($lifeCover['spouse_cover_in_trust'] ?? 0);
        $liabilities = $data['summary']['total_liabilities'] ?? 0;
        $ihtLiability = $data['summary']['iht_liability'] ?? 0;

        $usableCover = max(0, $existingCover - $liabilities);

        // Build itemised policy list for trace
        $policiesInTrust = array_filter($ctx['itemised_policies'], fn ($p) => $p['in_trust']);
        $policyDetail = ! empty($policiesInTrust)
            ? implode(', ', array_map(fn ($p) => $p['provider'].' (£'.number_format($p['sum_assured'], 0).')', $policiesInTrust))
            : 'None';

        $coverBreakdown = $ctx['has_spouse']
            ? $ctx['first_name'].': £'.number_format($userCoverInTrust, 0).', '.$ctx['spouse_first_name'].': £'.number_format($spouseCoverInTrust, 0)
            : '£'.number_format($existingCover, 0);

        $trace[] = [
            'question' => 'Does '.$ctx['first_name'].' have life insurance policies written in trust?',
            'data_field' => 'Life cover in trust',
            'data_value' => '£'.number_format($existingCover, 0).' total ('.$coverBreakdown.')',
            'threshold' => '£0 (any cover in trust is beneficial)',
            'passed' => $existingCover > 0,
            'explanation' => $existingCover > 0
                ? $ctx['first_name'].' has £'.number_format($existingCover, 0).' of life cover written in trust ('.$policyDetail.'), which bypasses the estate for Inheritance Tax purposes.'
                : $ctx['first_name'].' has no life insurance policies written in trust. Policies in trust can provide liquidity to pay Inheritance Tax without adding to the estate.',
        ];

        $trace[] = [
            'question' => 'After deducting liabilities, is there usable cover to offset Inheritance Tax?',
            'data_field' => 'Usable cover calculation',
            'data_value' => '£'.number_format($existingCover, 0).' cover − £'.number_format($liabilities, 0).' liabilities = £'.number_format($usableCover, 0),
            'threshold' => '£'.number_format($ihtLiability, 0).' (Inheritance Tax liability)',
            'passed' => $usableCover >= $ihtLiability,
            'explanation' => $usableCover > 0
                ? '£'.number_format($usableCover, 0).' of life cover is available to offset '.$ctx['first_name'].'\'s £'.number_format($ihtLiability, 0).' Inheritance Tax liability.'
                : 'No usable cover remains after accounting for £'.number_format($liabilities, 0).' in liabilities.',
        ];

        $recommendation = null;
        if ($usableCover > 0) {
            $recommendation = [
                'category' => 'life_cover',
                'priority' => 'low',
                'step' => 3,
                'title' => 'Existing Life Cover Available',
                'description' => "{$ctx['first_name']} has {$this->formatCurrency($usableCover)} in life cover that can offset Inheritance Tax.",
                'actions' => ['Ensure life policies are written in trust to bypass estate'],
                'usable_cover' => $usableCover,
                'decision_trace' => $trace,
            ];
        }

        // Trust placement for policies NOT in trust
        $trustPlacementTrace = $this->buildEstateContextTrace($ctx);
        $notInTrustCount = $lifeCover['policies_not_in_trust_count'] ?? 0;
        $notInTrustValue = (float) ($lifeCover['total_cover_not_in_trust'] ?? 0);

        // Build detail of policies not in trust
        $policiesNotInTrust = array_filter($ctx['itemised_policies'], fn ($p) => ! $p['in_trust']);
        $notInTrustDetail = ! empty($policiesNotInTrust)
            ? implode(', ', array_map(fn ($p) => $p['provider'].' '.ucfirst($p['policy_type']).' (£'.number_format($p['sum_assured'], 0).')', $policiesNotInTrust))
            : 'None';

        $trustPlacementTrace[] = [
            'question' => 'Does '.$ctx['first_name'].' have life insurance policies not written in trust?',
            'data_field' => 'Policies not in trust',
            'data_value' => $notInTrustCount.' '.($notInTrustCount === 1 ? 'policy' : 'policies').' totalling £'.number_format($notInTrustValue, 0),
            'threshold' => '0 policies (all should be in trust)',
            'passed' => $notInTrustCount === 0,
            'explanation' => $notInTrustCount > 0
                ? $notInTrustDetail.' — totalling £'.number_format($notInTrustValue, 0).' could be placed in trust to bypass '.$ctx['first_name'].'\'s estate.'
                : 'All of '.$ctx['first_name'].'\'s life insurance policies are written in trust.',
        ];

        $trustPlacementRecommendation = null;
        if ($notInTrustCount > 0) {
            $trustPlacementRecommendation = [
                'category' => 'trust_planning',
                'priority' => 'medium',
                'step' => 3,
                'title' => 'Place Life Policies in Trust',
                'description' => sprintf(
                    '%s has %d life insurance %s totalling %s not written in trust. Policies in trust bypass the estate for Inheritance Tax purposes.',
                    $ctx['first_name'],
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
    private function step4AnnualGiftingStrategy(int $currentAge, float $remainingLiability, int $lifeExpectancy, array $ctx): array
    {
        $trace = $this->buildEstateContextTrace($ctx);

        $ihtConfig = $this->taxConfig->getInheritanceTax();
        $annualExemption = $ihtConfig['annual_exemption'] ?? TaxDefaults::ANNUAL_GIFT_EXEMPTION;

        // Estimate years to life expectancy
        $yearsToLifeExpectancy = max(1, $lifeExpectancy - $currentAge);

        // Show existing gift history context
        if (! empty($ctx['gift_history_text'])) {
            $trace[] = [
                'question' => 'What is '.$ctx['first_name'].'\'s existing gift history?',
                'data_field' => 'Recorded gifts',
                'data_value' => $ctx['gift_history_text'],
                'threshold' => 'Informational — existing gifts reduce available Nil Rate Band',
                'passed' => true,
                'explanation' => 'Gifts made within the last 7 years may reduce the available Nil Rate Band. Gifts older than 7 years are fully exempt from Inheritance Tax.',
            ];
        }

        $trace[] = [
            'question' => 'How many years of annual gift exemptions are available for '.$ctx['first_name'].'?',
            'data_field' => 'Years to life expectancy',
            'data_value' => (string) $yearsToLifeExpectancy.' years (age '.$currentAge.', life expectancy '.$lifeExpectancy.')',
            'threshold' => '1 year (minimum for strategy to be worthwhile)',
            'passed' => $yearsToLifeExpectancy >= 1,
            'explanation' => $ctx['first_name'].' is '.$currentAge.' years old with a life expectancy of '.$lifeExpectancy.', giving approximately '.$yearsToLifeExpectancy.' years of annual exemptions available.',
        ];

        // Annual exemption potential (including carry forward from unused previous year)
        $annualGiftingCapacity = $annualExemption * $yearsToLifeExpectancy;

        // IHT saved at standard rate
        $ihtConfig = $this->taxConfig->getInheritanceTax();
        $ihtRate = (float) ($ihtConfig['standard_rate'] ?? TaxDefaults::IHT_RATE);
        $ihtRatePercent = round($ihtRate * 100);
        $potentialSavings = min($annualGiftingCapacity * $ihtRate, $remainingLiability);

        $coversLiability = $potentialSavings >= $remainingLiability;

        $trace[] = [
            'question' => 'Can annual gifting fully offset '.$ctx['first_name'].'\'s remaining Inheritance Tax liability?',
            'data_field' => 'Annual gifting calculation',
            'data_value' => '£'.number_format($annualExemption, 0).'/year × '.$yearsToLifeExpectancy.' years = £'.number_format($annualGiftingCapacity, 0).' total gifted, saving £'.number_format($potentialSavings, 0).' at '.$ihtRatePercent.'%',
            'threshold' => '£'.number_format($remainingLiability, 0).' (remaining liability after prior steps)',
            'passed' => $coversLiability,
            'explanation' => $coversLiability
                ? $ctx['first_name'].' gifting £'.number_format($annualExemption, 0).'/year over '.$yearsToLifeExpectancy.' years removes £'.number_format($annualGiftingCapacity, 0).' from the estate, saving £'.number_format($potentialSavings, 0).' in Inheritance Tax — fully offsetting the remaining liability.'
                : $ctx['first_name'].' gifting £'.number_format($annualExemption, 0).'/year over '.$yearsToLifeExpectancy.' years removes £'.number_format($annualGiftingCapacity, 0).' from the estate, saving £'.number_format($potentialSavings, 0).' in Inheritance Tax. However, £'.number_format($remainingLiability - $potentialSavings, 0).' of liability would remain.',
        ];

        $recommendation = [
            'category' => 'annual_gifting',
            'priority' => $coversLiability ? 'high' : 'medium',
            'step' => 4,
            'title' => 'Annual Gifting Strategy',
            'description' => $coversLiability
                ? "Using {$ctx['first_name']}'s annual gift exemption of {$this->formatCurrency($annualExemption)}/year could fully offset the Inheritance Tax liability over {$yearsToLifeExpectancy} years."
                : "Annual gifting of {$this->formatCurrency($annualExemption)}/year could save {$this->formatCurrency($potentialSavings)} in Inheritance Tax for {$ctx['first_name']}.",
            'actions' => [
                "Use the annual {$this->formatCurrency($annualExemption)} gift exemption each year",
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
    private function step5LifeCoverStrategy(float $remainingLiability, array $liquidityData, array $ctx): array
    {
        $trace = $this->buildEstateContextTrace($ctx);

        // Estimate whole of life premium (simplified calculation)
        $estimatedAnnualPremium = $remainingLiability * 0.02; // ~2% of cover per year

        $trace[] = [
            'question' => 'Is there a remaining Inheritance Tax liability that life cover could address for '.$ctx['first_name'].'?',
            'data_field' => 'Remaining liability after steps 1-4',
            'data_value' => '£'.number_format($remainingLiability, 0),
            'threshold' => '£0 (no remaining liability)',
            'passed' => $remainingLiability <= 0,
            'explanation' => 'A whole of life policy for £'.number_format($remainingLiability, 0).' written in trust could cover '.$ctx['first_name'].'\'s remaining Inheritance Tax liability, providing funds outside of the estate. At age '.$ctx['current_age'].', premiums are still affordable (estimated £'.number_format($estimatedAnnualPremium, 0).'/year at approximately 2% of cover).',
        ];

        $hasLiquidityIssue = $liquidityData['has_issue'] ?? false;
        $liquidAssets = $liquidityData['liquid_assets'] ?? 0;

        $trace[] = [
            'question' => 'Is there a liquidity concern that makes life cover more urgent?',
            'data_field' => 'Liquidity position',
            'data_value' => $hasLiquidityIssue
                ? 'Yes — liquid assets of £'.number_format($liquidAssets, 0).' are insufficient'
                : 'No — liquid assets of £'.number_format($liquidAssets, 0).' are adequate',
            'threshold' => 'No liquidity issue',
            'passed' => ! $hasLiquidityIssue,
            'explanation' => $hasLiquidityIssue
                ? 'A liquidity shortfall has been identified for '.$ctx['first_name'].'\'s estate. Life cover written in trust would provide immediate funds to pay the Inheritance Tax bill without requiring asset sales.'
                : 'No liquidity issue identified, but life cover still provides certainty of funds for '.$ctx['first_name'].'\'s Inheritance Tax payment.',
        ];

        $recommendation = [
            'category' => 'new_life_cover',
            'priority' => 'medium',
            'step' => 5,
            'title' => 'Whole of Life Cover Strategy',
            'description' => "A whole of life policy for {$this->formatCurrency($remainingLiability)} could cover {$ctx['first_name']}'s remaining Inheritance Tax liability.",
            'actions' => [
                "Consider whole of life cover for {$this->formatCurrency($remainingLiability)}",
                'Estimated annual premium: '.$this->formatCurrency($estimatedAnnualPremium).' (approximately 2% of cover at age '.$ctx['current_age'].')',
                'CRITICAL: Policy must be written in trust to bypass the estate',
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
    private function step6PETGiftingStrategy(int $currentAge, float $remainingLiability, int $lifeExpectancy, array $ctx): array
    {
        $trace = $this->buildEstateContextTrace($ctx);

        $ihtConfig = $this->taxConfig->getInheritanceTax();
        $nrb = $ihtConfig['nil_rate_band'] ?? TaxDefaults::NRB;

        // Calculate years to life expectancy
        $yearsToLifeExpectancy = max(1, $lifeExpectancy - $currentAge);

        // Calculate 7-year cycles available
        $sevenYearCycles = floor($yearsToLifeExpectancy / 7);

        $trace[] = [
            'question' => 'How many seven-year cycles are available for '.$ctx['first_name'].' based on life expectancy?',
            'data_field' => 'Seven-year cycles',
            'data_value' => $sevenYearCycles.' '.($sevenYearCycles === 1.0 ? 'cycle' : 'cycles').' ('.$yearsToLifeExpectancy.' years ÷ 7 = '.$sevenYearCycles.')',
            'threshold' => '1 cycle (minimum for Potentially Exempt Transfer strategy)',
            'passed' => $sevenYearCycles >= 1,
            'explanation' => $sevenYearCycles >= 1
                ? $ctx['first_name'].' at age '.$currentAge.' with life expectancy of '.$lifeExpectancy.' has '.$yearsToLifeExpectancy.' years remaining, providing '.$sevenYearCycles.' complete seven-year '.($sevenYearCycles === 1.0 ? 'cycle' : 'cycles').' for Potentially Exempt Transfers.'
                : $ctx['first_name'].' at age '.$currentAge.' has only '.$yearsToLifeExpectancy.' years to life expectancy — insufficient time for a Potentially Exempt Transfer to become fully exempt (requires 7 years).',
        ];

        // Each cycle can gift up to NRB tax-efficiently
        $petCapacity = $sevenYearCycles * $nrb;
        $ihtConfig = $this->taxConfig->getInheritanceTax();
        $ihtRate = (float) ($ihtConfig['standard_rate'] ?? TaxDefaults::IHT_RATE);
        $ihtRatePercent = round($ihtRate * 100);
        $potentialSavings = min($petCapacity * $ihtRate, $remainingLiability);

        if ($sevenYearCycles >= 1) {
            $coversLiability = $potentialSavings >= $remainingLiability;

            $trace[] = [
                'question' => 'Can Potentially Exempt Transfers cover '.$ctx['first_name'].'\'s remaining Inheritance Tax liability?',
                'data_field' => 'Potentially Exempt Transfer calculation',
                'data_value' => $sevenYearCycles.' cycles × £'.number_format($nrb, 0).' Nil Rate Band = £'.number_format($petCapacity, 0).' capacity, saving £'.number_format($potentialSavings, 0).' at '.$ihtRatePercent.'%',
                'threshold' => '£'.number_format($remainingLiability, 0).' (remaining liability after steps 1-5)',
                'passed' => $coversLiability,
                'explanation' => $coversLiability
                    ? 'Potentially Exempt Transfers totalling £'.number_format($petCapacity, 0).' over '.$sevenYearCycles.' cycles would save £'.number_format($potentialSavings, 0).' — fully covering the remaining liability.'
                    : 'Potentially Exempt Transfers totalling £'.number_format($petCapacity, 0).' would save £'.number_format($potentialSavings, 0).', but £'.number_format($remainingLiability - $potentialSavings, 0).' of liability would remain.',
            ];
        }

        $recommendation = null;
        if ($sevenYearCycles >= 1) {
            $recommendation = [
                'category' => 'pet_gifting',
                'priority' => 'medium',
                'step' => 6,
                'title' => 'Potentially Exempt Transfer Strategy',
                'description' => "With {$sevenYearCycles} seven-year cycles available, {$ctx['first_name']} could make Potentially Exempt Transfers up to {$this->formatCurrency($petCapacity)} that become fully exempt.",
                'actions' => [
                    'Make larger gifts (Potentially Exempt Transfers) that become exempt after 7 years',
                    "Each 7-year cycle can shelter up to {$this->formatCurrency($nrb)} (the Nil Rate Band)",
                    'Taper relief applies if death occurs within 7 years of a Potentially Exempt Transfer',
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
    private function step7CLTIntoTrust(float $remainingLiability, array $ctx): array
    {
        $trace = $this->buildEstateContextTrace($ctx);

        $ihtConfig = $this->taxConfig->getInheritanceTax();
        $nrb = $ihtConfig['nil_rate_band'] ?? TaxDefaults::NRB;
        $cltRate = $ihtConfig['clt_rate'] ?? TaxDefaults::CLT_RATE;

        $trace[] = [
            'question' => 'Is there still a remaining Inheritance Tax liability for '.$ctx['first_name'].' after all prior strategies?',
            'data_field' => 'Remaining liability after steps 1-6',
            'data_value' => '£'.number_format($remainingLiability, 0),
            'threshold' => '£0 (no remaining liability)',
            'passed' => $remainingLiability <= 0,
            'explanation' => 'Steps 1 to 6 (charitable bequests, liquidity review, existing life cover, annual gifting, new life cover, Potentially Exempt Transfers) have been unable to fully offset '.$ctx['first_name'].'\'s Inheritance Tax liability. £'.number_format($remainingLiability, 0).' remains, making a Chargeable Lifetime Transfer a last-resort option.',
        ];

        // Calculate immediate charge if CLT exceeds NRB
        $excessOverNRB = max(0, $remainingLiability - $nrb);
        $immediateCharge = $excessOverNRB * $cltRate;

        // Show existing trust context if relevant
        if (! empty($ctx['trust_summary_text'])) {
            $trace[] = [
                'question' => 'Does '.$ctx['first_name'].' have existing trust structures?',
                'data_field' => 'Existing trusts',
                'data_value' => $ctx['trust_summary_text'],
                'threshold' => 'Informational — existing trusts may affect Nil Rate Band availability',
                'passed' => true,
                'explanation' => 'Existing trust structures should be considered when planning a new Chargeable Lifetime Transfer, as they may affect the available Nil Rate Band.',
            ];
        }

        $trace[] = [
            'question' => 'Does the transfer amount exceed the Nil Rate Band, triggering an immediate charge?',
            'data_field' => 'Chargeable Lifetime Transfer calculation',
            'data_value' => '£'.number_format($remainingLiability, 0).' transfer − £'.number_format($nrb, 0).' Nil Rate Band = £'.number_format($excessOverNRB, 0).' excess × '.round($cltRate * 100).'% = £'.number_format($immediateCharge, 0).' immediate charge',
            'threshold' => '£'.number_format($nrb, 0).' (Nil Rate Band — no charge if within this amount)',
            'passed' => $excessOverNRB <= 0,
            'explanation' => $excessOverNRB > 0
                ? 'A Chargeable Lifetime Transfer of £'.number_format($remainingLiability, 0).' for '.$ctx['first_name'].' exceeds the Nil Rate Band by £'.number_format($excessOverNRB, 0).', incurring an immediate charge of £'.number_format($immediateCharge, 0).' at '.round($cltRate * 100).'%. If '.$ctx['first_name'].' dies within 7 years, an additional charge applies (up to '.round(($ihtConfig['standard_rate'] ?? TaxDefaults::IHT_RATE) * 100).'% total).'
                : 'The transfer amount is within the Nil Rate Band, so no immediate charge would apply for '.$ctx['first_name'].'.',
        ];

        $standardRatePercent = round(($ihtConfig['standard_rate'] ?? TaxDefaults::IHT_RATE) * 100);
        $cltRatePercent = round($cltRate * 100);

        $recommendation = [
            'category' => 'clt_trust',
            'priority' => 'low',
            'step' => 7,
            'title' => 'Chargeable Lifetime Transfer — Last Resort',
            'description' => 'A Chargeable Lifetime Transfer into trust can remove assets from '.$ctx['first_name'].'\'s estate, but comes with immediate tax charges.',
            'actions' => [
                "Chargeable Lifetime Transfer of {$this->formatCurrency($remainingLiability)} would incur immediate {$this->formatCurrency($immediateCharge)} charge ({$cltRatePercent}% on amount over Nil Rate Band)",
                "Additional {$cltRatePercent}% charge if death within 7 years ({$standardRatePercent}% total)",
                'Trust subject to periodic charges (max 6% every 10 years)',
                'Exit charges apply when assets leave the trust',
                'Seek professional advice before proceeding',
            ],
            'immediate_charge' => $immediateCharge,
            'amount' => $remainingLiability,
            'warning' => 'Chargeable Lifetime Transfers are complex and should only be considered after exhausting simpler strategies.',
            'decision_trace' => $trace,
        ];

        return [
            'recommendation' => $recommendation,
        ];
    }

    /**
     * Build estate context array from analysis data for use in granular decision traces.
     *
     * Extracts user profile, IHT calculation details, asset itemisation, gift history,
     * trust summary, will status, and life policy details into a flat context array
     * consumed by all step methods.
     */
    private function buildEstateContext(array $data): array
    {
        $userCtx = $data['user_context'] ?? [];
        $ihtCalc = $data['iht_calculation'] ?? [];
        $summary = $data['summary'] ?? [];
        $profile = $data['profile'] ?? [];

        $firstName = $userCtx['first_name'] ?? 'User';
        $surname = $userCtx['surname'] ?? '';
        $spouseFirstName = $userCtx['spouse_first_name'] ?? null;
        $spouseSurname = $userCtx['spouse_surname'] ?? null;
        $maritalStatus = $profile['marital_status'] ?? $userCtx['marital_status'] ?? 'unknown';
        $hasSpouse = $profile['has_spouse'] ?? ($spouseFirstName !== null);
        $currentAge = $profile['current_age'] ?? self::DEFAULT_CURRENT_AGE;

        // Build profile description
        $profileDesc = $firstName.' '.$surname.', age '.$currentAge;
        if ($hasSpouse && $spouseFirstName) {
            $profileDesc .= ', '.($maritalStatus === 'married' ? 'married' : $maritalStatus).' to '.$spouseFirstName.' '.$spouseSurname;
        } elseif ($maritalStatus && $maritalStatus !== 'unknown') {
            $profileDesc .= ', '.$maritalStatus;
        }

        // IHT calculation figures
        $grossEstate = (float) ($summary['gross_estate'] ?? 0);
        $netEstate = (float) ($summary['net_estate'] ?? 0);
        $totalLiabilities = (float) ($summary['total_liabilities'] ?? 0);
        $ihtLiability = (float) ($summary['iht_liability'] ?? 0);
        $userGrossAssets = (float) ($ihtCalc['user_gross_assets'] ?? $grossEstate);
        $spouseGrossAssets = (float) ($ihtCalc['spouse_gross_assets'] ?? 0);
        $userLiabilities = (float) ($ihtCalc['user_total_liabilities'] ?? $totalLiabilities);
        $spouseLiabilities = (float) ($ihtCalc['spouse_total_liabilities'] ?? 0);
        $nrbAvailable = (float) ($ihtCalc['nrb_available'] ?? 0);
        $nrbIndividual = (float) ($ihtCalc['nrb_individual'] ?? 0);
        $rnrbAvailable = (float) ($ihtCalc['rnrb_available'] ?? 0);
        $rnrbIndividual = (float) ($ihtCalc['rnrb_individual'] ?? 0);
        $totalAllowances = (float) ($ihtCalc['total_allowances'] ?? 0);
        $taxableEstate = (float) ($ihtCalc['taxable_estate'] ?? 0);
        $ihtRate = (float) ($ihtCalc['iht_rate'] ?? ($this->taxConfig->getInheritanceTax()['standard_rate'] ?? TaxDefaults::IHT_RATE));
        $ihtRatePercent = (int) ($ihtRate * 100);

        // Build estate composition description
        $estateDesc = 'Gross estate £'.number_format($grossEstate, 0);
        if ($hasSpouse && $spouseGrossAssets > 0) {
            $estateDesc .= ' ('.$firstName.': £'.number_format($userGrossAssets, 0).', '.$spouseFirstName.': £'.number_format($spouseGrossAssets, 0).')';
        }

        // Build allowances description
        $allowancesDesc = 'Nil Rate Band £'.number_format($nrbAvailable, 0);
        if ($hasSpouse && $nrbIndividual > 0) {
            $allowancesDesc .= ' (£'.number_format($nrbIndividual, 0).' each)';
        }
        if ($rnrbAvailable > 0) {
            $allowancesDesc .= ', Residence Nil Rate Band £'.number_format($rnrbAvailable, 0);
            if ($hasSpouse && $rnrbIndividual > 0) {
                $allowancesDesc .= ' (£'.number_format($rnrbIndividual, 0).' each)';
            }
        }
        $allowancesDesc .= '. Total allowances: £'.number_format($totalAllowances, 0);

        // Build IHT calculation description
        $ihtCalcDesc = 'Net estate £'.number_format($netEstate, 0)
            .' − allowances £'.number_format($totalAllowances, 0)
            .' = taxable estate £'.number_format($taxableEstate, 0)
            .' × '.$ihtRatePercent.'% = £'.number_format($ihtLiability, 0).' Inheritance Tax';

        // Build itemised asset breakdown by type
        $itemisedAssets = $userCtx['itemised_assets'] ?? [];
        $assetsByType = [];
        foreach ($itemisedAssets as $asset) {
            $type = $asset['type'] ?? 'other';
            if (! isset($assetsByType[$type])) {
                $assetsByType[$type] = [];
            }
            $assetsByType[$type][] = $asset;
        }

        // Build asset composition text
        $assetLines = [];
        $typeLabels = [
            'property' => 'Properties',
            'investment' => 'Investments',
            'cash' => 'Cash & savings',
            'savings' => 'Savings',
            'dc_pension' => 'Defined Contribution pensions',
            'db_pension' => 'Defined Benefit pensions',
            'business' => 'Business interests',
            'chattel' => 'Personal property',
        ];
        foreach ($assetsByType as $type => $assets) {
            $label = $typeLabels[$type] ?? ucfirst($type);
            $items = array_map(fn ($a) => $a['name'].' (£'.number_format($a['value'], 0).')', $assets);
            $typeTotal = array_sum(array_column($assets, 'value'));
            $assetLines[] = $label.': '.implode(', ', $items).' — total £'.number_format($typeTotal, 0);
        }
        $assetComposition = ! empty($assetLines) ? implode('. ', $assetLines) : 'No assets recorded';

        // Gift history text
        $giftSummary = $userCtx['gift_summary'] ?? [];
        $giftHistoryText = '';
        if (! empty($giftSummary)) {
            $giftItems = array_map(
                fn ($g) => '£'.number_format($g['gift_value'], 0).' to '.$g['recipient'].' ('.$g['gift_type'].', '.$g['gift_date'].')',
                $giftSummary
            );
            $totalGifts = array_sum(array_column($giftSummary, 'gift_value'));
            $giftHistoryText = count($giftSummary).' gifts totalling £'.number_format($totalGifts, 0).': '.implode('; ', $giftItems);
        }

        // Trust summary text
        $trustSummary = $userCtx['trust_summary'] ?? [];
        $trustSummaryText = '';
        if (! empty($trustSummary)) {
            $trustItems = array_map(
                fn ($t) => $t['trust_name'].' ('.ucfirst(str_replace('_', ' ', $t['trust_type'])).', £'.number_format($t['current_value'], 0).')',
                $trustSummary
            );
            $trustCount = count($trustSummary);
            $trustSummaryText = $trustCount.' '.($trustCount === 1 ? 'trust' : 'trusts').': '.implode('; ', $trustItems);
        }

        // Life policy details
        $itemisedPolicies = $userCtx['itemised_policies'] ?? [];

        return [
            'first_name' => $firstName,
            'surname' => $surname,
            'full_name' => trim($firstName.' '.$surname),
            'spouse_first_name' => $spouseFirstName,
            'spouse_surname' => $spouseSurname,
            'has_spouse' => $hasSpouse,
            'marital_status' => $maritalStatus,
            'current_age' => $currentAge,
            'profile_desc' => $profileDesc,
            'estate_desc' => $estateDesc,
            'allowances_desc' => $allowancesDesc,
            'iht_calc_desc' => $ihtCalcDesc,
            'asset_composition' => $assetComposition,
            'gross_estate' => $grossEstate,
            'net_estate' => $netEstate,
            'total_liabilities' => $totalLiabilities,
            'iht_liability' => $ihtLiability,
            'taxable_estate' => $taxableEstate,
            'total_allowances' => $totalAllowances,
            'nrb_available' => $nrbAvailable,
            'rnrb_available' => $rnrbAvailable,
            'iht_rate_percent' => $ihtRatePercent,
            'itemised_assets' => $itemisedAssets,
            'itemised_policies' => $itemisedPolicies,
            'gift_history_text' => $giftHistoryText,
            'trust_summary_text' => $trustSummaryText,
            'has_will' => $userCtx['has_will'] ?? false,
            'will_executor' => $userCtx['will_executor'] ?? null,
        ];
    }

    /**
     * Build the standard estate context preamble trace entries.
     *
     * Every recommendation's decision_trace starts with these entries so the reader
     * sees the full picture: who, what they own, liabilities, allowances, and the
     * resulting Inheritance Tax calculation — before the step-specific logic.
     *
     * @return array<int, array> Trace entries for user profile, estate composition, and IHT calculation
     */
    private function buildEstateContextTrace(array $ctx): array
    {
        $trace = [];

        // 1. User profile
        $trace[] = [
            'question' => 'Who is this analysis for?',
            'data_field' => 'User profile',
            'data_value' => $ctx['profile_desc'],
            'threshold' => 'Informational',
            'passed' => true,
            'explanation' => 'Estate planning analysis for '.$ctx['profile_desc'].'.',
        ];

        // 2. Estate composition with itemised assets
        $trace[] = [
            'question' => 'What is the composition of '.$ctx['first_name'].'\'s estate?',
            'data_field' => 'Estate composition',
            'data_value' => $ctx['estate_desc'],
            'threshold' => 'Informational',
            'passed' => true,
            'explanation' => $ctx['asset_composition'],
        ];

        // 3. Liabilities
        if ($ctx['total_liabilities'] > 0) {
            $trace[] = [
                'question' => 'What liabilities reduce '.$ctx['first_name'].'\'s estate?',
                'data_field' => 'Total liabilities',
                'data_value' => '£'.number_format($ctx['total_liabilities'], 0),
                'threshold' => 'Informational',
                'passed' => true,
                'explanation' => 'Total liabilities of £'.number_format($ctx['total_liabilities'], 0).' reduce the gross estate of £'.number_format($ctx['gross_estate'], 0).' to a net estate of £'.number_format($ctx['net_estate'], 0).'.',
            ];
        }

        // 4. IHT calculation
        $trace[] = [
            'question' => 'What is the Inheritance Tax calculation?',
            'data_field' => 'Inheritance Tax computation',
            'data_value' => $ctx['iht_calc_desc'],
            'threshold' => $ctx['allowances_desc'],
            'passed' => $ctx['iht_liability'] <= 0,
            'explanation' => $ctx['iht_liability'] > 0
                ? $ctx['first_name'].'\'s estate of £'.number_format($ctx['net_estate'], 0).' exceeds the combined allowances of £'.number_format($ctx['total_allowances'], 0).', resulting in £'.number_format($ctx['iht_liability'], 0).' Inheritance Tax at '.$ctx['iht_rate_percent'].'%.'
                : $ctx['first_name'].'\'s estate of £'.number_format($ctx['net_estate'], 0).' is within the combined allowances of £'.number_format($ctx['total_allowances'], 0).'. No Inheritance Tax is due.',
        ];

        return $trace;
    }

    /**
     * Filter itemised asset names by asset type from context.
     *
     * @param  array  $ctx  Estate context
     * @param  array  $types  Asset types to include (e.g. ['cash', 'savings'])
     * @return array<int, string> Array of "Name (£Value)" strings
     */
    private function filterAssetNamesByType(array $ctx, array $types): array
    {
        $assets = $ctx['itemised_assets'] ?? [];

        return array_values(array_map(
            fn ($a) => $a['name'].' (£'.number_format($a['value'], 0).')',
            array_filter($assets, fn ($a) => in_array($a['type'] ?? '', $types))
        ));
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
        $ihtRate = (float) ($this->taxConfig->getInheritanceTax()['standard_rate'] ?? TaxDefaults::IHT_RATE);
        $ihtSaved = $totalGifted * $ihtRate;

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
        $equityRelease = $parameters['equity_release'] ?? $this->taxConfig->get('estate.onboarding_estimates.property', 200000);

        $ihtRate = (float) ($this->taxConfig->getInheritanceTax()['standard_rate'] ?? TaxDefaults::IHT_RATE);

        return [
            'name' => 'Property Downsizing',
            'gross_estate' => $current['gross_estate'] - $equityRelease,
            'net_estate' => $current['net_estate'] - $equityRelease,
            'iht_liability' => max(0, $current['iht_liability'] - ($equityRelease * $ihtRate)),
            'to_beneficiaries' => $current['net_estate'] - $equityRelease - max(0, $current['iht_liability'] - ($equityRelease * $ihtRate)),
            'cash_released' => $equityRelease,
        ];
    }

    /**
     * Build trust creation scenario.
     */
    private function buildTrustScenario(User $user, array $parameters): array
    {
        $current = $this->buildCurrentScenario($user);
        $trustValue = $parameters['trust_value'] ?? ($this->taxConfig->getInheritanceTax()['nil_rate_band'] ?? TaxDefaults::NRB);

        // Discretionary trust within NRB
        $ihtRate = (float) ($this->taxConfig->getInheritanceTax()['standard_rate'] ?? TaxDefaults::IHT_RATE);
        $ihtReduction = min($trustValue * $ihtRate, $current['iht_liability']);

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
