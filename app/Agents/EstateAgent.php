<?php

declare(strict_types=1);

namespace App\Agents;

use App\Events\Eval\EngineCalled;
use App\Models\Estate\Will;
use App\Models\Goal;
use App\Models\User;
use App\Services\Coordination\RecommendationPersonaliser;
use App\Services\Estate\EstateAssetAggregatorService;
use App\Services\Estate\EstateDataReadinessService;
use App\Services\Estate\FailedGiftTaxCalculator;
use App\Services\Estate\FutureValueCalculator;
use App\Services\Estate\GiftingStrategyOptimizer;
use App\Services\Estate\IHTCalculationService;
use App\Services\Estate\LifeCoverCalculator;
use App\Services\Estate\PersonalizedTrustStrategyService;
use App\Services\Estate\WillAnalysisService;
use App\Services\Protection\LifeCoverReach;
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
        private readonly GiftingStrategyOptimizer $giftingOptimizer,
        private readonly PersonalizedTrustStrategyService $trustStrategyService,
        private readonly WillAnalysisService $willAnalysisService,
        private readonly TaxConfigService $taxConfig,
        private readonly RecommendationPersonaliser $personaliser,
        private readonly EstateDataReadinessService $readinessService,
        private readonly LifeCoverCalculator $lifeCoverCalculator,
        private readonly LifeCoverReach $lifeCoverReach,
        // W-0198 — the one home for how long this person expects to live.
        private readonly FutureValueCalculator $futureValue
    ) {}

    /**
     * Analyze user's estate planning situation.
     */
    public function analyze(int $userId): array
    {
        $analyzeStart = microtime(true);
        $result = (function () use ($userId): array {
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

            // Derived, never hand-built. This read `"estate_analysis_{$userId}"`
            // while `invalidateUserCache()` forgot `getUserCacheKey()`'s
            // `v1_estateagent_{id}_analysis` — a key-name mismatch, so every
            // invalidation cleared a key nothing had ever written and the stale
            // analysis survived for the full time-to-live. Both halves read as
            // correct in isolation, which is why reviewing the invalidation
            // *logic* never found it (W-0381).
            $cacheKey = $this->getUserCacheKey($userId, 'analysis');
            $cacheTags = ['estate', 'user_'.$userId];

            return $this->remember($cacheKey, function () use ($user, $userId) {

                // Which policies cover THIS user's life — their own, plus any
                // joint-life policy their linked spouse recorded. This read
                // `where('user_id', …)`, so it stopped at the account that typed the
                // policy in: the other life assured had her estate plan assessed,
                // itemised and gap-listed as though the £500,000 joint-life policy
                // insuring her did not exist (W-0342). `:119` below had already been
                // routed to the same reader in W-0186 and this one had not, so her
                // household cover-in-trust figure was right while everything derived
                // from this collection was blind.
                $allLifePolicies = $this->lifeCoverReach->policiesCovering($user);

                // Deliberately the user's OWN policies, not the ones covering them:
                // this figure drives "place this policy in trust", an action only the
                // policy's owner can take. Filtered from the set above rather than
                // re-queried, and ownership comes from the same reader so there is one
                // answer to "is this mine" (Rule 20).
                $lifePoliciesNotInTrust = $allLifePolicies->filter(
                    fn ($p) => $this->lifeCoverReach->isOwnedBy($p, $user) && ! $p->in_trust
                );

                // The household's in-trust cover AND the policies behind it, from one
                // pass over one set. This summed the user's cover and the spouse's,
                // then printed the count of the user's OWN policies beside it — so a
                // spouse with no policy of her own read "Cover in Trust £500,000 ·
                // Total Policies 0" (W-0186). A total and its count now cannot
                // disagree, because they come from the same place.
                $householdCoverInTrust = $this->lifeCoverReach->householdCoverInTrust($user);
                $spouseLifeCoverInTrust = $householdCoverInTrust['spouse_amount'];

                // Aggregate all estate assets into summary
                $assetSummary = $this->buildAssetSummary($user);

                // Calculate IHT (include spouse data when married and linked)
                $ihtCalculation = null;
                $ihtLiability = 0;
                $effectiveTaxRate = 0;

                try {
                    // W-0350 — reciprocal only.
                    //
                    // NOTE, not fixed here: `$dataSharingEnabled` is derived from the
                    // link's existence, while `IHTController` derives the same flag
                    // from `hasAcceptedSpousePermission()`. Two mechanisms, one
                    // question — so Fyn can quote a different estate figure from the
                    // one on screen. Aligning them changes the pooled figure for the
                    // 8 of 12 reciprocal couples with no permission row, which is a
                    // visible tax change and CSJ's call, not a side effect of this fix.
                    $spouse = $user->reciprocalLiveSpouse();
                    $dataSharingEnabled = $user->sharesFinancialDataWithSpouse();
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
                // No date of birth, no gifting timeline: the age is never assumed
                // (item 9, 2026-10-07; the old default was 50).
                if ($user->date_of_birth) {
                    try {
                        $currentAge = (int) $user->date_of_birth->diffInYears(now());
                        // W-0198. Was `override ?? 85` — it could not see the figure the
                        // user typed in the retirement module at all, so a household that
                        // set one there was projected to 85 here and to their own number
                        // in retirement.
                        $lifeExpectancy = $this->futureValue->getLifeExpectancy($user)['death_age'];
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
                //
                // W-0451 / W-0452. This used to hand `WillAnalysisService` the
                // INDIVIDUAL's net estate (`$assetSummary['net_estate']`) with the
                // HOUSEHOLD's available nil rate band — one person's assets against
                // two people's allowance — and let it strike its own baseline and
                // total the logged-in user's own bequests. So the charitable
                // position on `/plans/estate` was computed on a different estate
                // from the one the Inheritance Tax calculation had already settled,
                // and from a different person's will. `/plans/estate` printed 4.2%
                // where `/estate` printed 0.8% for the same household.
                //
                // The whole position now comes from the calculation: the baseline,
                // the threshold, the survivor's donated amount, the shortfall and
                // the two Inheritance Tax bills. Nothing is re-derived here.
                //
                // Deliberately skipped when the calculation failed above. There is
                // no charitable position to describe for an estate we could not
                // compute, and producing one from a second source is exactly the
                // parallel mechanism this change removes.
                $charitableAnalysis = [];
                if ($ihtCalculation !== null) {
                    try {
                        $charitableAnalysis = $this->willAnalysisService->analyzeCharitableBequests($ihtCalculation);
                    } catch (\Throwable $e) {
                        report($e);
                        // Continue without charitable analysis
                    }
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
                    (int) $user->date_of_birth->diffInYears(now()) : null;

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
                    'user_id' => $user->id,
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

                // S1.6.b — structured gap list for the LLM.
                $missingForQualityAdvice = $this->findMissingForQualityAdvice(
                    $user,
                    $assetSummary,
                    $allLifePolicies,
                    $will ?? null,
                );

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
                            // W-0466 F3 — carried alongside the liability so every
                            // consumer of THIS summary gets the caveat with the
                            // figure rather than one without the other. `/m` Insights
                            // and the `/m` estate screen both read it, and
                            // both printed an unqualified number for a
                            // business-owning household.
                            'unmodelled_relief_caveat' => $ihtCalculation['unmodelled_relief_caveat'] ?? null,
                        ],
                        'asset_breakdown' => $assetSummary['breakdown'] ?? [],
                        'iht_calculation' => $ihtCalculation,
                        'trust_recommendations' => $trustRecommendations,
                        'gifting_opportunities' => $giftingOpportunities,
                        'trust_wish_triggers' => $trustWishTriggers,
                        'charitable_analysis' => $charitableAnalysis,
                        'will_review_status' => $willReviewStatus,
                        'life_cover' => [
                            'user_cover_in_trust' => $householdCoverInTrust['user_amount'],
                            'spouse_cover_in_trust' => $householdCoverInTrust['spouse_amount'],
                            'total_cover_in_trust' => $householdCoverInTrust['total'],
                            'total_cover_not_in_trust' => (float) $lifePoliciesNotInTrust->sum('sum_assured'),
                            // Counts the policies behind `total_cover_in_trust` — the
                            // household's, because that figure is the household's.
                            'policy_count' => $householdCoverInTrust['count'],
                            // Deliberately individual: this figure drives "place this
                            // policy in trust", which only the policy's owner can do.
                            'policies_not_in_trust_count' => $lifePoliciesNotInTrust->count(),
                            'policy_assessment' => $policyAssessment,
                        ],
                        'pension_amendment' => $pensionAmendment,
                        'goal_liquidity' => $goalLiquidity,
                        'profile' => [
                            'current_age' => $currentAge,
                            // W-0198 — same resolver as every other consumer.
                            'life_expectancy' => $this->futureValue->getLifeExpectancy($user)['death_age'],
                            'marital_status' => $user->marital_status,
                            'has_dependents' => ($user->familyMembers()->where('relationship', 'child')->count() > 0),
                            'has_spouse' => $user->spouse !== null,
                        ],
                        'user_context' => $userContext,
                        'missing_for_quality_advice' => $missingForQualityAdvice,
                    ]
                );
            }, null, $cacheTags);
        })();

        $resultPath = match (true) {
            isset($result['success']) && $result['success'] === false => 'success_false',
            isset($result['data']['can_proceed']) && $result['data']['can_proceed'] === false => 'readiness_blocked',
            default => 'happy',
        };
        event(new EngineCalled(
            engine: 'estate_analysis',
            params: ['user_id' => $userId],
            resultSummary: ['result_path' => $resultPath, 'keys_returned' => array_keys($result)],
            durationMs: (int) round((microtime(true) - $analyzeStart) * 1000),
            atMicrotime: microtime(true),
        ));

        return $result;
    }

    /**
     * Per-agent contract gap field (S1.6.b).
     *
     * @return list<array{field: string, why: string, severity: 'blocking'|'soft'}>
     */
    private function findMissingForQualityAdvice(
        User $user,
        array $assetSummary,
        $lifePolicies,
        $will,
    ): array {
        $gaps = [];

        if (($assetSummary['gross_estate'] ?? 0) <= 0) {
            $gaps[] = [
                'field' => 'estate_assets',
                'why' => 'No estate assets recorded — Inheritance Tax liability cannot be calculated.',
                'severity' => 'blocking',
            ];
        }

        if ($user->marital_status === 'married' && $user->spouse === null) {
            $gaps[] = [
                'field' => 'spouse_link',
                'why' => 'Spouse exemption and the transferable Nil Rate Band depend on the spouse profile being linked.',
                'severity' => 'soft',
            ];
        }

        if ($will === null || ! ($will->has_will ?? false)) {
            $gaps[] = [
                'field' => 'will',
                'why' => 'Without a recorded Will, executor, beneficiaries, and bequest structure cannot be assessed.',
                'severity' => 'soft',
            ];
        }

        if ($lifePolicies->isEmpty() && ($assetSummary['gross_estate'] ?? 0) > $this->taxConfig->getInheritanceTax()['nil_rate_band']) {
            $gaps[] = [
                'field' => 'life_cover',
                'why' => 'Estate exceeds the Nil Rate Band but no life cover is recorded — a written-in-trust policy is a common Inheritance Tax mitigation route.',
                'severity' => 'soft',
            ];
        }

        return $gaps;
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
        $start = microtime(true);
        $result = (function () use ($analysisData): array {
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
            $currentAge = $data['profile']['current_age'] ?? null;
            $charitableAnalysis = $data['charitable_analysis'] ?? [];
            $trustWishTriggers = $data['trust_wish_triggers'] ?? [];
            $ihtCalc = $data['iht_calculation'] ?? [];

            // Build estate context from analysis data for granular traces
            $ctx = $this->buildEstateContext($data);

            // Only generate mitigation recommendations if there's an IHT liability.
            // Each step shows its own effect against today's tax; cover in trust
            // PAYS the tax rather than reducing it, so it never shortens a later
            // step (item 9, 2026-10-07: the old chain let new life cover zero the
            // "remaining liability" and hide the gift steps).
            if ($ihtLiability > 0) {
                // STEP 1: Charitable Bequest Check (Rate Reduction)
                $step1Result = $this->step1CharitableBequestCheck($charitableAnalysis, $ihtLiability, $ctx);
                if ($step1Result) {
                    $recommendations[] = $step1Result;
                }

                // STEP 2: Paying the tax (liquidity)
                $liquidityData = $this->step2LiquidityAssessment($data, $ctx);
                if ($liquidityData['recommendation']) {
                    $recommendations[] = $liquidityData['recommendation'];
                }

                // STEP 3: Existing life cover in trust
                $lifeCoverData = $this->step3ExistingLifeCover($data, $ctx);
                if ($lifeCoverData['recommendation']) {
                    $recommendations[] = $lifeCoverData['recommendation'];
                }

                // STEP 4: Annual gifts (per year)
                $annualGiftingResult = $this->step4AnnualGiftingStrategy($ctx);
                if ($annualGiftingResult['recommendation']) {
                    $recommendations[] = $annualGiftingResult['recommendation'];
                }

                // STEP 5: Life cover in trust for the tax not already covered
                $taxNotCovered = max(0.0, $ihtLiability - $lifeCoverData['existing_cover']);
                if ($taxNotCovered > 0) {
                    $lifeCoverStrategyResult = $this->step5LifeCoverStrategy($taxNotCovered, $liquidityData, $ctx);
                    $recommendations[] = $lifeCoverStrategyResult['recommendation'];
                }

                // STEP 6: Larger gifts within the nil rate band gifts have not used
                $petResult = $this->step6PETGiftingStrategy($ihtLiability, $ctx);
                if ($petResult['recommendation']) {
                    $recommendations[] = $petResult['recommendation'];
                }

                // STEP 7: Gifts into trust, only while tax would remain after step 6
                if ($ihtLiability - $petResult['potential_savings'] > 0) {
                    $cltResult = $this->step7CLTIntoTrust($ctx);
                    $recommendations[] = $cltResult['recommendation'];
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

            // No "will review every 3-5 years" card: no source sets a period
            // (item 9, 2026-10-07; Rule 23). Marriage revoking a will (Wills Act
            // 1837 s18) needs a marriage date the app does not record.

            // Recommend completing missing data only when we lack essentials for a meaningful calculation
            $hasDob = $currentAge !== null;
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
        })();

        event(new EngineCalled(
            engine: 'estate_recommendation',
            params: [],
            resultSummary: [
                'result_path' => (isset($result['success']) && $result['success'] === false) ? 'success_false' : 'happy',
                'keys_returned' => array_keys($result),
            ],
            durationMs: (int) round((microtime(true) - $start) * 1000),
            atMicrotime: microtime(true),
        ));

        return $result;
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
        $standardRate = (float) $ihtConfig['standard_rate'];
        // W-0451. This read the configuration array with its own `?? 0.36` — one
        // more copy of the duplication `TaxConfigService::getCharitableReducedRate()`
        // is the single home for, sitting in the very method whose sentences this
        // item rewrites. Routed rather than left standing beside a corrected figure.
        $reducedRate = $this->taxConfig->getCharitableReducedRate();
        $standardRatePercent = round($standardRate * 100);
        $reducedRatePercent = round($reducedRate * 100);
        // W-0451. The Schedule 1A threshold was written out as a literal "10%"
        // five times in this method while the two rates beside it were
        // interpolated from configuration — the half-fixed shape W-0432 warns
        // about, in one of the sites `TaxConfigService`'s own docblock names.
        // Move the configured threshold to 12% and this method said 10% in five
        // sentences whose arithmetic had already used 12%.
        $thresholdPercent = round($this->taxConfig->getCharitableThresholdPercent() * 100);

        $status = $charitableAnalysis['status'] ?? 'below';
        $shortfall = $charitableAnalysis['shortfall'] ?? 0;
        $potentialSaving = $charitableAnalysis['potential_saving'] ?? 0;
        $currentSaving = $charitableAnalysis['current_saving'] ?? 0;
        $charitableTotal = $charitableAnalysis['charitable_total'] ?? 0;
        $baseline = $charitableAnalysis['baseline'] ?? 0;
        $threshold = $charitableAnalysis['threshold'] ?? 0;
        $taxableEstate = (float) ($charitableAnalysis['taxable_estate'] ?? 0);

        // W-0452. This divided the charitable total by the baseline to obtain a
        // percentage the household calculation had already published — a third
        // site computing a figure `/estate` and `/plans/estate` were computing
        // two different ways. It reads the one answer now.
        $currentPercentage = (float) ($charitableAnalysis['charitable_percent'] ?? 0);

        // W-0451 C1 — WHOSE POSITION THIS IS.
        //
        // Every sentence below said `$ctx['first_name']` — whoever is logged in.
        // The figures beside them are the SURVIVOR's, because Schedule 1A tests
        // the estate of one deceased person and this service models to the second
        // death. When the reader is not the survivor, the sentences reported the
        // survivor's charitable position under the reader's name and told the
        // reader to add a legacy to their OWN will.
        //
        // **That instruction cannot work.** A legacy in the first-to-die's will
        // raises the pooled section 23(1) exemption and leaves the rate-test
        // amount untouched, so the rate stays at the standard rate, the estate is
        // smaller by the whole gift, and the identical instruction is issued
        // again on the next run.
        //
        // The name comes from the same resolution that chose the will.
        $rateTestName = $charitableAnalysis['rate_test_member_first_name'] ?? $ctx['first_name'];
        $rateTestIsReader = (bool) ($charitableAnalysis['rate_test_is_requesting_user'] ?? true);

        // Said once, and only when it is needed — the reader is looking at
        // someone else's will and is owed the reason. Matches the disclosure
        // `/estate`'s charitable card already carries (`IHTPlanning.vue:246`).
        $secondDeathNote = $rateTestIsReader
            ? ''
            : ' The '.$thresholdPercent.'% test looks only at the will operating on the second death, which is '.$rateTestName.'\'s.';

        $trace[] = [
            'question' => 'Do '.$rateTestName.'\'s charitable bequests reach the '.$thresholdPercent.'% threshold for the reduced Inheritance Tax rate?',
            'data_field' => 'Charitable bequest percentage of baseline',
            'data_value' => round($currentPercentage, 1).'% (£'.number_format($charitableTotal, 0).' of £'.number_format($baseline, 0).' baseline)',
            'threshold' => $thresholdPercent.'% of baseline (£'.number_format($threshold, 0).')',
            'passed' => $status !== 'below',
            'explanation' => ($status !== 'below'
                ? $rateTestName.'\'s charitable giving of £'.number_format($charitableTotal, 0).' meets or exceeds the '.$thresholdPercent.'% threshold of £'.number_format($threshold, 0).', qualifying for the reduced '.$reducedRatePercent.'% rate.'
                : $rateTestName.'\'s charitable giving of £'.number_format($charitableTotal, 0).' is '.round($currentPercentage, 1).'% of the £'.number_format($baseline, 0).' baseline (net estate minus Nil Rate Band). The '.$thresholdPercent.'% threshold is £'.number_format($threshold, 0).'.')
                .$secondDeathNote,
        ];

        if ($status === 'below' && $potentialSaving > 0) {
            // W-0451 — THE SENTENCE THAT CONTRADICTED ITSELF.
            //
            // It struck both bills on the SAME taxable estate and then quoted a
            // saving computed somewhere else on a different base:
            //
            //   "On the taxable estate of £858,780: at 40% = £343,512,
            //    at 36% = £309,161 — saving £19,580."
            //
            // £343,512 − £309,161 = £34,351. A £14,771 error — 43% — on a
            // decision trace whose entire purpose is that a reader can check it.
            //
            // Both bills, both bases and the saving now come from the one
            // definition in `IHTCalculationService::assessTaxPosition()`, so the
            // subtraction the reader performs IS the subtraction the application
            // performed. And the second bill names its own base: increasing the
            // gift lowers the rate AND removes the gift from the estate, so 36%
            // is charged on a smaller estate than 40% was. A sentence that
            // printed one base for two bills could not be made to add up.
            $taxableEstateIfQualifying = (float) ($charitableAnalysis['taxable_estate_if_qualifying'] ?? 0);
            $currentTax = (float) ($charitableAnalysis['tax_at_standard_rate'] ?? 0);
            $reducedTax = (float) ($charitableAnalysis['tax_at_reduced_rate'] ?? 0);

            $trace[] = [
                'question' => 'How much additional charitable giving is needed and what would it save?',
                'data_field' => 'Shortfall to '.$thresholdPercent.'% threshold',
                'data_value' => '£'.number_format($shortfall, 0),
                'threshold' => '£0 (no shortfall)',
                'passed' => false,
                'explanation' => 'If '.$rateTestName.' increases charitable bequests by £'.number_format($shortfall, 0)
                    .' (to reach £'.number_format($threshold, 0).'), the Inheritance Tax rate drops from '.$standardRatePercent.'% to '.$reducedRatePercent.'%'
                    .' and the additional £'.number_format($shortfall, 0).' leaves the estate as an exempt gift.'
                    .' As the will stands: '.$standardRatePercent.'% of the taxable estate of £'.number_format($taxableEstate, 0).' = £'.number_format($currentTax, 0).'.'
                    .' With the larger gift: '.$reducedRatePercent.'% of £'.number_format($taxableEstateIfQualifying, 0).' = £'.number_format($reducedTax, 0).'.'
                    .' Saving £'.number_format($potentialSaving, 0).'.'
                    .$secondDeathNote,
            ];

            return [
                'category' => 'charitable_bequest',
                'priority' => 'high',
                'step' => 1,
                'title' => 'Charitable Bequest Opportunity',
                // W-0451 C1. The description and the action both named the reader.
                // The action is the one that mattered: "Add £X to YOUR will" is not
                // merely mis-addressed when the reader is not the survivor, it is an
                // instruction that cannot produce the outcome the sentence promises.
                'description' => "Increase charitable giving in {$rateTestName}'s will by {$this->formatCurrency($shortfall)} to qualify for the reduced {$reducedRatePercent}% Inheritance Tax rate and save {$this->formatCurrency($potentialSaving)}.",
                'actions' => [
                    "Add {$this->formatCurrency($shortfall)} in charitable bequests to {$rateTestName}'s will",
                    'Consider leaving to registered UK charities',
                    "This reduces the Inheritance Tax rate from {$standardRatePercent}% to {$reducedRatePercent}%",
                ],
                // The extra the will would leave to charity: the card and the plan's
                // funding source read it (item 9; they read 0 or the saving before).
                'shortfall' => $shortfall,
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
                // W-0451. This named a saving and a taxable estate and left the
                // reader no way to get from one to the other. An estate already
                // qualifying has no shortfall, so both bills sit on the same
                // chargeable estate and the difference IS the rate differential
                // on it — which is what "the reduced rate is worth" means for
                // someone who already has it. Printed, so it subtracts.
                'explanation' => $rateTestName.'\'s charitable giving of £'.number_format($charitableTotal, 0)
                    .' qualifies for the reduced '.$reducedRatePercent.'% rate.'
                    .' On the taxable estate of £'.number_format($taxableEstate, 0)
                    .': at '.$standardRatePercent.'% = £'.number_format((float) ($charitableAnalysis['tax_at_standard_rate'] ?? 0), 0)
                    .', at '.$reducedRatePercent.'% = £'.number_format((float) ($charitableAnalysis['tax_at_reduced_rate'] ?? 0), 0)
                    .' — saving £'.number_format($currentSaving, 0).'.'
                    .$secondDeathNote,
            ];

            return [
                'category' => 'charitable_bequest',
                'priority' => 'low',
                'step' => 1,
                'title' => 'Charitable Rate Applied',
                'description' => "{$rateTestName}'s charitable giving qualifies for the reduced {$reducedRatePercent}% Inheritance Tax rate, saving {$this->formatCurrency($currentSaving)}.",
                'actions' => ['Your current charitable bequests are sufficient for the reduced rate'],
                'current_saving' => $currentSaving,
                'decision_trace' => $trace,
            ];
        }

        return null;
    }

    /**
     * Step 2: Paying the tax. The tax is due six months after the end of the
     * month of death (IHTA 1984 s226(1)); tax on land and buildings can be paid
     * in ten yearly instalments (s227(1), (2)). Fires when the estate's liquid
     * assets are less than the tax: the shortfall is what would have to be
     * sold, borrowed or paid by instalments. (Was: under 50% of the tax, a
     * threshold with no source; item 9, 2026-10-07.)
     */
    private function step2LiquidityAssessment(array $data, array $ctx): array
    {
        $trace = $this->buildEstateContextTrace($ctx);

        $assetBreakdown = $data['asset_breakdown'] ?? [];
        $liquidAssets = (float) ($assetBreakdown['liquid'] ?? 0);
        $semiLiquidAssets = (float) ($assetBreakdown['semi_liquid'] ?? 0);
        $illiquidAssets = (float) ($assetBreakdown['illiquid'] ?? 0);
        $ihtLiability = (float) ($data['summary']['iht_liability'] ?? 0);

        $shortfall = max(0.0, $ihtLiability - $liquidAssets);
        $hasLiquidityIssue = $shortfall > 0;

        $liquidAssetNames = $this->filterAssetNamesByType($ctx, ['cash', 'savings']);
        $liquidDetail = ! empty($liquidAssetNames) ? implode(', ', $liquidAssetNames) : 'No cash or savings recorded';

        $trace[] = [
            'question' => 'Do '.$ctx['first_name'].'\'s cash and savings cover the Inheritance Tax?',
            'data_field' => 'Liquidity breakdown',
            'data_value' => 'Cash and savings: £'.number_format($liquidAssets, 0)
                .' | Investments: £'.number_format($semiLiquidAssets, 0)
                .' | Property, pensions and other: £'.number_format($illiquidAssets, 0),
            'threshold' => '£'.number_format($ihtLiability, 0).' Inheritance Tax',
            'passed' => ! $hasLiquidityIssue,
            'explanation' => ! $hasLiquidityIssue
                ? 'Cash and savings of £'.number_format($liquidAssets, 0).' ('.$liquidDetail.') cover the £'.number_format($ihtLiability, 0).' of Inheritance Tax.'
                : 'Cash and savings of £'.number_format($liquidAssets, 0).' ('.$liquidDetail.') leave £'.number_format($shortfall, 0).' of the £'.number_format($ihtLiability, 0).' Inheritance Tax to find. The tax is due six months after the end of the month of death (Inheritance Tax Act 1984 s226); tax on land and buildings can be paid in ten yearly instalments (s227).',
        ];

        $recommendation = null;
        if ($hasLiquidityIssue) {
            $recommendation = [
                'category' => 'liquidity',
                'priority' => 'high',
                'step' => 2,
                'title' => 'Paying the Inheritance Tax',
                'description' => "{$ctx['first_name']}'s cash and savings of {$this->formatCurrency($liquidAssets)} are {$this->formatCurrency($shortfall)} short of the {$this->formatCurrency($ihtLiability)} Inheritance Tax.",
                'actions' => [
                    'Life cover written in trust pays out outside the estate, in time to pay the tax',
                    'Tax on land and buildings can be paid in ten yearly instalments',
                ],
                'shortfall' => $shortfall,
                'decision_trace' => $trace,
            ];
        }

        return [
            'liquid_assets' => $liquidAssets,
            'shortfall' => $shortfall,
            'has_issue' => $hasLiquidityIssue,
            'recommendation' => $recommendation,
        ];
    }

    /**
     * Step 3: Life cover already in trust. Its payout is outside the estate
     * (HMRC IHTM20012) and can pay the tax in full. (Was: cover minus every
     * debt, with no source, and a third "place policies in trust" card; the
     * trust placement card is Protection's alone, item 9 D2.)
     */
    private function step3ExistingLifeCover(array $data, array $ctx): array
    {
        $trace = $this->buildEstateContextTrace($ctx);

        $lifeCover = $data['life_cover'] ?? [];
        $existingCover = (float) ($lifeCover['total_cover_in_trust'] ?? 0);
        $ihtLiability = (float) ($data['summary']['iht_liability'] ?? 0);

        $policiesInTrust = array_filter($ctx['itemised_policies'], fn ($p) => $p['in_trust']);
        $policyDetail = ! empty($policiesInTrust)
            ? implode(', ', array_map(fn ($p) => $p['provider'].' (£'.number_format($p['sum_assured'], 0).')', $policiesInTrust))
            : 'None';

        $trace[] = [
            'question' => 'Does '.$ctx['first_name'].' have life cover written in trust?',
            'data_field' => 'Life cover in trust',
            'data_value' => '£'.number_format($existingCover, 0).' ('.$policyDetail.')',
            'threshold' => '£'.number_format($ihtLiability, 0).' Inheritance Tax',
            'passed' => $existingCover >= $ihtLiability,
            'explanation' => $existingCover > 0
                ? '£'.number_format($existingCover, 0).' of life cover in trust pays out outside the estate and can be used to pay the Inheritance Tax.'
                : 'No life cover is written in trust.',
        ];

        $recommendation = null;
        if ($existingCover > 0) {
            $recommendation = [
                'category' => 'life_cover',
                'priority' => 'low',
                'step' => 3,
                'title' => 'Life Cover in Trust',
                'description' => "{$ctx['first_name']} has {$this->formatCurrency($existingCover)} of life cover in trust, which pays out outside the estate and can pay the Inheritance Tax.",
                'actions' => ['Keep the trustees and the people the trust is for up to date'],
                'usable_cover' => $existingCover,
                'decision_trace' => $trace,
            ];
        }

        return [
            'existing_cover' => $existingCover,
            'recommendation' => $recommendation,
        ];
    }

    /**
     * Step 4: Annual gifts, per year. The annual exemption (IHTA 1984 s19, with
     * last year's unused amount carried forward once, s19(2)), small gifts (s20)
     * and wedding gifts (s22), all from the configuration. Shown per year: the
     * tax each year's gifts save at the estate's rate. (Was: £3,000 × years to
     * life expectancy, with the limits typed into the sentences.)
     */
    private function step4AnnualGiftingStrategy(array $ctx): array
    {
        $trace = $this->buildEstateContextTrace($ctx);

        $exemptions = $this->taxConfig->getGiftingExemptions();
        $annualExemption = (float) $exemptions['annual_exemption'];
        $smallGifts = (float) $exemptions['small_gifts_limit'];
        $wedding = $exemptions['wedding_gifts'];
        $rate = $this->estateRate($ctx);
        $annualSaving = $annualExemption * $rate;

        $trace[] = [
            'question' => 'What do '.$ctx['first_name'].'\'s yearly exempt gifts save?',
            'data_field' => 'Annual exemption',
            'data_value' => '£'.number_format($annualExemption, 0).' a year at '.round($rate * 100).'% = £'.number_format($annualSaving, 0).' of tax a year',
            'threshold' => 'Exempt when made (Inheritance Tax Act 1984 s19)',
            'passed' => false,
            'explanation' => 'Each year\'s £'.number_format($annualExemption, 0).' of gifts leaves the estate at once, with no seven-year wait, saving £'.number_format($annualSaving, 0).' of Inheritance Tax a year while the estate is above its allowances.',
        ];

        return [
            'recommendation' => [
                'category' => 'annual_gifting',
                'priority' => 'medium',
                'step' => 4,
                'title' => 'Annual Gifts',
                'description' => "Gifts of {$this->formatCurrency($annualExemption)} a year leave {$ctx['first_name']}'s estate at once and save {$this->formatCurrency($annualSaving)} of Inheritance Tax for each year they are made.",
                'actions' => [
                    "Use the {$this->formatCurrency($annualExemption)} annual exemption each tax year; last year's, if unused, can be added once",
                    'Regular gifts out of income that leave your usual standard of living are exempt too',
                    "Small gifts of up to {$this->formatCurrency($smallGifts)} to each person are exempt",
                    "Wedding gifts are exempt up to {$this->formatCurrency((float) $wedding['parent_to_child'])} from a parent, {$this->formatCurrency((float) $wedding['grandparent_to_grandchild'])} from a grandparent and {$this->formatCurrency((float) $wedding['other'])} from anyone else",
                ],
                'annual_exemption' => $annualExemption,
                'annual_saving' => $annualSaving,
                'decision_trace' => $trace,
            ],
        ];
    }

    /**
     * Step 5: Life cover in trust for the tax that cover already in trust does
     * not meet. No age limit and no premium: what cover costs is an insurer's
     * quote (W-0141). (Was: only at age 50 or under, with no source.)
     */
    private function step5LifeCoverStrategy(float $taxNotCovered, array $liquidityData, array $ctx): array
    {
        $trace = $this->buildEstateContextTrace($ctx);

        $trace[] = [
            'question' => 'How much Inheritance Tax is not already met by life cover in trust?',
            'data_field' => 'Tax not covered',
            'data_value' => '£'.number_format($taxNotCovered, 0),
            'threshold' => '£0',
            'passed' => false,
            'explanation' => 'Whole of life cover of £'.number_format($taxNotCovered, 0).' written in trust would pay out outside the estate in time to pay the tax. What it costs depends on the insurer\'s underwriting.',
        ];

        return [
            'recommendation' => [
                'category' => 'new_life_cover',
                'priority' => 'medium',
                'step' => 5,
                'title' => 'Life Cover for the Tax',
                'description' => "Whole of life cover of {$this->formatCurrency($taxNotCovered)} written in trust would pay {$ctx['first_name']}'s Inheritance Tax without selling anything.",
                'actions' => [
                    "Get quotes for whole of life cover of {$this->formatCurrency($taxNotCovered)}",
                    'Write the policy in trust, so its payout stays outside the estate',
                ],
                'cover_amount' => $taxNotCovered,
                'decision_trace' => $trace,
            ],
        ];
    }

    /**
     * Step 6: Larger gifts within the nil rate band that gifts of the last seven
     * years have not used. A gift up to that band carries no tax of its own even
     * if death comes within seven years (HMRC IHTM14512, "chargeable in its own
     * right" only above the band; CSJ 2026-10-07), and once survived by seven
     * years it has left the estate (IHTA 1984 s3A, s7). The band used comes from
     * the one gift engine (`FailedGiftTaxCalculator` via the Inheritance Tax
     * calculation). (Was: one band per seven years × life expectancy.)
     */
    private function step6PETGiftingStrategy(float $ihtLiability, array $ctx): array
    {
        $trace = $this->buildEstateContextTrace($ctx);

        $nrb = (float) $this->taxConfig->getInheritanceTax()['nil_rate_band'];
        // The band this user's own gifts of the last seven years use, from the
        // one gift engine (gifts within the band never appear in `failed_gifts`,
        // only in these totals).
        $member = isset($ctx['user_id']) ? User::find($ctx['user_id']) : null;
        $bandUsed = $member
            ? (float) app(FailedGiftTaxCalculator::class)->forMember($member, $nrb)['total_nrb_used']
            : 0.0;
        $bandLeft = max(0.0, $nrb - $bandUsed);
        $rate = $this->estateRate($ctx);
        $potentialSavings = min($bandLeft * $rate, $ihtLiability);

        $trace[] = [
            'question' => 'How much of '.$ctx['first_name'].'\'s nil rate band have gifts of the last seven years left?',
            'data_field' => 'Nil rate band left for gifts',
            'data_value' => '£'.number_format($nrb, 0).' − £'.number_format($bandUsed, 0).' used by gifts = £'.number_format($bandLeft, 0),
            'threshold' => '£0',
            'passed' => $bandLeft <= 0,
            'explanation' => $bandLeft > 0
                ? 'Gifts of up to £'.number_format($bandLeft, 0).' carry no tax of their own even if '.$ctx['first_name'].' dies within seven years. Once survived by seven years they have left the estate, saving up to £'.number_format($potentialSavings, 0).' of Inheritance Tax.'
                : 'Gifts of the last seven years have used the nil rate band; a further gift would carry tax of its own if '.$ctx['first_name'].' died within seven years.',
        ];

        $recommendation = null;
        if ($bandLeft > 0) {
            $recommendation = [
                'category' => 'pet_gifting',
                'priority' => 'medium',
                'step' => 6,
                'title' => 'Larger Gifts',
                'description' => "Gifts of up to {$this->formatCurrency($bandLeft)} to people carry no tax of their own even within seven years, and once {$ctx['first_name']} survives seven years they save up to {$this->formatCurrency($potentialSavings)} of Inheritance Tax.",
                'actions' => [
                    "Gifts to people of up to {$this->formatCurrency($bandLeft)} stay within the nil rate band",
                    'They leave the estate once you survive seven years from the date of each gift',
                    'Only give what you will not need: a gift you keep using is not a gift for Inheritance Tax',
                ],
                'potential_saving' => $potentialSavings,
                'band_left' => $bandLeft,
                // The gift the plan's what-if takes out of the estate (item 9).
                'impact_parameters' => ['gift' => $bandLeft],
                'decision_trace' => $trace,
            ];
        }

        return [
            'recommendation' => $recommendation,
            'potential_savings' => $potentialSavings,
        ];
    }

    /**
     * Step 7: Gifts into trust, last, only while tax would remain after step 6.
     * No amount: the gift is the user's choice. Taxed now at the lifetime rate
     * on the part above the nil rate band available, more if death follows
     * within seven years, and up to the periodic maximum every ten years (IHTA
     * 1984 s64, s66(1)), all from the configuration. (Was: the remaining TAX
     * used as the size of the gift.)
     */
    private function step7CLTIntoTrust(array $ctx): array
    {
        $trace = $this->buildEstateContextTrace($ctx);

        $lifetimeRate = $this->taxConfig->getCLTLifetimeRate();
        $periodicMax = (float) $this->taxConfig->getTrustCharges()['periodic']['max_rate'];
        $standardRate = (float) $this->taxConfig->getInheritanceTax()['standard_rate'];

        $trace[] = [
            'question' => 'What does a gift into a trust cost?',
            'data_field' => 'Charges on a gift into trust',
            'data_value' => round($lifetimeRate * 100).'% now above the nil rate band; up to '.round($periodicMax * 100).'% every ten years',
            'threshold' => 'Inheritance Tax Act 1984 s64, s66',
            'passed' => false,
            'explanation' => 'A gift into a discretionary trust is taxed at '.round($lifetimeRate * 100).'% now on the part above the nil rate band available, up to '.round($standardRate * 100).'% in all if '.$ctx['first_name'].' dies within seven years, and the trust pays up to '.round($periodicMax * 100).'% every ten years.',
        ];

        return [
            'recommendation' => [
                'category' => 'clt_trust',
                'priority' => 'low',
                'step' => 7,
                'title' => 'Gifts into a Trust',
                'description' => 'A gift into a trust leaves '.$ctx['first_name'].'\'s estate while trustees keep control, but is taxed at '.round($lifetimeRate * 100).'% now on the part above the nil rate band.',
                'actions' => [
                    'Up to '.round($standardRate * 100).'% in all if death follows within seven years',
                    'The trust pays up to '.round($periodicMax * 100).'% every ten years, and when assets leave it',
                    'A trust needs a solicitor to set up',
                ],
                'decision_trace' => $trace,
            ],
        ];
    }

    /**
     * The rate the estate's tax is charged at: 36% when the charity test is met,
     * else the standard rate, as the Inheritance Tax calculation found it.
     */
    private function estateRate(array $ctx): float
    {
        return (float) ($ctx['iht_rate'] ?? $this->taxConfig->getInheritanceTax()['standard_rate']);
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
        $currentAge = $profile['current_age'] ?? null;

        // Build profile description (no age when none is recorded, never a default)
        $profileDesc = $firstName.' '.$surname.($currentAge !== null ? ', age '.$currentAge : '');
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
        $ihtRate = (float) ($ihtCalc['iht_rate'] ?? $this->taxConfig->getInheritanceTax()['standard_rate']);
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
            'iht_rate' => $ihtRate,
            'user_id' => $userCtx['user_id'] ?? null,
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
     * Required by BaseAgent. Estate builds no what-if scenarios: nothing called
     * this, and its builders took released equity out of the estate and used
     * set-up estimates (item 9, removed 2026-10-07). The Estate plan page
     * models each step with its own figure.
     */
    public function buildScenarios(int $userId, array $parameters): array
    {
        return $this->response(false, 'Estate scenarios are not built here; the Estate plan page models each step.', []);
    }

    /**
     * Build asset summary array from gathered assets and liabilities.
     *
     * W-0397. This summed EVERY gathered asset, including the ones flagged
     * `is_iht_exempt`, and it was the only mechanism in the application that
     * did. `IHTCalculationService` rejects them (`:167-168`) and so does
     * `NetWorthAnalyzer`, so one user was shown two different own-estate figures
     * in one session: the mobile dashboard said £1,489,500 where the will
     * planning screen and the /m estate screen both said £989,500 — his
     * £500,000 of defined contribution pensions, exactly.
     *
     * Filtering here rather than at each reader, because the figure is not
     * merely displayed. It feeds the gifting strategy (`:183`), and an estate
     * inflated by exempt assets raises every threshold struck against it. That
     * is W-0154's third defect in a second place.
     *
     * **It no longer feeds the charitable 10% test.** This docblock said it did,
     * and until W-0452 that was true and was the defect: the charitable baseline
     * was struck on this INDIVIDUAL figure while the threshold it was compared
     * against came from the household calculation. The charitable position now
     * comes whole from `IHTCalculationService::calculate()` (`:231`). Corrected
     * here rather than left standing, because a stale docblock is the next
     * reader's premise.
     *
     * There is no consumer for whom the unfiltered figure was right. The two
     * that read it as `net_worth` (`DashboardAggregator:407`,
     * `CoordinatingAgent:839`) now agree with `NetWorthAnalyzer`, the module
     * that actually answers that question and which already excluded them.
     *
     * `gross_estate` and the liquidity breakdown are filtered on the same set,
     * because a breakdown that does not reconcile to its own total is how this
     * class of defect hides. Only `illiquid` moves: cash, savings and
     * investments are never flagged exempt.
     */
    private function buildAssetSummary(User $user): array
    {
        $assets = $this->assetAggregator->gatherUserAssets($user)
            ->reject(fn ($asset) => $asset->is_iht_exempt ?? false);

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
     * Invalidate cache for user's estate analysis.
     *
     * Uses the standardised cache invalidation from BaseAgent.
     *
     * @param  int  $userId  User ID
     */
    public function invalidateCache(int $userId): void
    {
        // The canonical key comes from `invalidateUserCache()`. The legacy
        // `estate_analysis_{id}` string is kept only to drain entries written by
        // the pre-W-0381 code, which would otherwise outlive the deploy by a full
        // time-to-live; it is written by nothing now.
        $this->invalidateUserCache($userId, [
            "estate_analysis_{$userId}",
        ]);
    }
}
