<?php

declare(strict_types=1);

namespace App\Agents;

use App\Constants\TaxDefaults;
use App\Events\Eval\EngineCalled;
use App\Events\Eval\GateChecked;
use App\Models\Goal;
use App\Models\RetirementProfile;
use App\Models\User;
use App\Services\Estate\FutureValueCalculator;
use App\Services\Investment\FeeAnalyzer;
use App\Services\Investment\MonteCarloSimulator;
use App\Services\Investment\PortfolioAnalyzer;
use App\Services\Investment\SimpleAssetAllocationOptimizer;
use App\Services\Investment\TaxEfficiencyCalculator;
use App\Services\Plans\PlanConfigService;
use App\Services\Retirement\AnnualAllowanceChecker;
use App\Services\Retirement\DecumulationPlanner;
use App\Services\Retirement\PensionContributionOptimizer;
use App\Services\Retirement\PensionPortfolioAnalyzer;
use App\Services\Retirement\PensionProjector;
use App\Services\Retirement\RetirementActionDefinitionService;
use App\Services\Retirement\RetirementDataReadinessService;
use App\Services\Retirement\StatePensionAgeResolver;
use App\Services\Risk\RiskPreferenceService;
use App\Services\Stores\PensionStore;
use App\Services\TaxConfigService;
use Illuminate\Support\Collection;

/**
 * Retirement Agent
 *
 * Orchestrates retirement planning analysis including pension projections,
 * contribution optimization, and decumulation planning.
 */
class RetirementAgent extends BaseAgent
{
    protected int $cacheTtl = 3600;

    private ?Collection $dcPensions = null;

    public function __construct(
        private readonly PensionProjector $projector,
        private readonly AnnualAllowanceChecker $allowanceChecker,
        private readonly PensionContributionOptimizer $optimizer,
        private readonly DecumulationPlanner $planner,
        private readonly PensionPortfolioAnalyzer $pensionPortfolioAnalyzer,
        private readonly TaxConfigService $taxConfig,
        private readonly RetirementActionDefinitionService $actionDefinitionService,
        private readonly RiskPreferenceService $riskPreferenceService,
        private readonly RetirementDataReadinessService $readinessService,
        // W-0516 — the one home for State Pension age.
        private readonly StatePensionAgeResolver $statePensionAge,
        // Portfolio optimization services (shared with Investment module)
        private readonly PortfolioAnalyzer $portfolioAnalyzer,
        private readonly MonteCarloSimulator $monteCarloSimulator,
        private readonly SimpleAssetAllocationOptimizer $allocationOptimizer,
        private readonly FeeAnalyzer $feeAnalyzer,
        private readonly TaxEfficiencyCalculator $taxCalculator,
        // W-0198 — the one home for how long this person expects to live.
        private readonly FutureValueCalculator $futureValue,
        private readonly ?PlanConfigService $planConfig = null
    ) {
        if ($this->planConfig) {
            $this->cacheTtl = $this->planConfig->getRetirementCacheTTL();
        }
    }

    /**
     * Analyze user's retirement position.
     */
    public function analyze(int $userId): array
    {
        $analyzeStart = microtime(true);
        $result = (function () use ($userId): array {
            // Data readiness gate — return early if blocking checks fail
            $gateUser = User::find($userId);
            if ($gateUser) {
                $readiness = $this->readinessService->assess($gateUser);
                if (! $readiness['can_proceed']) {
                    return $this->response(true, 'Readiness check incomplete', [
                        'can_proceed' => false,
                        'readiness_checks' => $readiness,
                        // A blocking readiness check stops the ANALYSIS. It does not
                        // stop the pensions from existing, and a card reading this
                        // response must still be able to say what the household holds
                        // (W-0244). Same facts, same shape, same home as the
                        // no-target branch below.
                        'summary' => $this->provisionSummary($gateUser),
                        'income_projection' => null,
                        'breakdown' => null,
                        'annual_allowance' => null,
                        'profile' => null,
                        'decumulation' => null,
                        'post_retirement_goals' => [],
                    ]);
                }
            }

            $cacheKey = "retirement_analysis_{$userId}";
            $cacheTags = ['retirement', 'user_'.$userId];

            return $this->remember($cacheKey, function () use ($userId) {
                // Get all retirement data (single query with eager loading)
                $user = User::with(['retirementProfile', 'dcPensions', 'dbPensions', 'statePension'])
                    ->find($userId);

                $profile = $user?->retirementProfile;
                $dcPensions = $user?->dcPensions ?? collect();
                $dbPensions = $user?->dbPensions ?? collect();
                $statePension = $user?->statePension;

                $hasProfile = $profile !== null;
                event(new GateChecked(
                    gate: 'profile_gate',
                    module: 'retirement',
                    passed: $hasProfile,
                    context: ['profile_table' => 'retirement_profiles', 'user_id' => $user?->id ?? 0],
                    atMicrotime: microtime(true),
                ));

                // Project total retirement income. Every figure this returns is
                // derived from the pension records themselves — each DC scheme
                // carries its own retirement age, and `getUserAge` falls back to
                // `users.date_of_birth` — so it is a fact about what the user
                // HOLDS and does not depend on a retirement profile existing.
                $incomeProjection = $this->projector->projectTotalRetirementIncome($userId);

                if (! $hasProfile) {
                    return $this->factsWithoutTarget(
                        $user,
                        $dcPensions,
                        $dbPensions,
                        $statePension,
                        $incomeProjection,
                        $userId,
                    );
                }

                $targetIncome = (float) $profile->target_retirement_income;
                $statePensionAge = $this->statePensionAge->forUser($user);
                $retirementAge = $profile->target_retirement_age;

                // Income at retirement: only include state pension if retiring at or after SPA
                $incomeAtRetirement = ($incomeProjection['dc_annual_income'] ?? 0)
                    + ($incomeProjection['db_annual_income'] ?? 0);
                $retiresBeforeSPA = $retirementAge < $statePensionAge;
                $statePensionIncome = $incomeProjection['state_pension_income'] ?? 0;

                if (! $retiresBeforeSPA) {
                    $incomeAtRetirement += $statePensionIncome;
                }

                $incomeGap = max(0, $targetIncome - $incomeAtRetirement);

                // Income after SPA (when state pension kicks in)
                $incomeAfterSPA = $incomeAtRetirement + ($retiresBeforeSPA ? $statePensionIncome : 0);
                $incomeGapAfterSPA = max(0, $targetIncome - $incomeAfterSPA);

                // Check annual allowance
                $taxYear = $this->taxConfig->getTaxYear();
                $allowance = $this->allowanceChecker->checkAnnualAllowance($userId, $taxYear);

                // Calculate years to retirement
                $yearsToRetirement = max(0, $retirementAge - $profile->current_age);

                // Summary metrics
                $currentDcValue = (float) $dcPensions->sum('current_fund_value');
                $summary = [
                    'years_to_retirement' => $yearsToRetirement,
                    'target_retirement_age' => $retirementAge,
                    'projected_retirement_income' => $incomeAtRetirement,
                    'target_retirement_income' => $targetIncome,
                    'income_gap' => $incomeGap,
                    'retires_before_spa' => $retiresBeforeSPA,
                    'state_pension_age' => $statePensionAge,
                    'state_pension_income' => $statePensionIncome,
                    'income_after_spa' => $retiresBeforeSPA ? $incomeAfterSPA : null,
                    'income_gap_after_spa' => $retiresBeforeSPA ? $incomeGapAfterSPA : null,
                    'current_dc_value' => $currentDcValue,
                    'total_dc_value' => $incomeProjection['dc_total_value'],
                    'total_pensions_count' => $dcPensions->count() + $dbPensions->count() + ($statePension ? 1 : 0),
                    'has_retirement_target' => true,
                    'guaranteed_annual_income' => $this->guaranteedAnnualIncome($incomeProjection),
                ];

                // Detailed breakdown
                $breakdown = [
                    'dc_pensions' => $this->formatDCPensions($dcPensions, $incomeProjection),
                    'db_pensions' => $this->formatDBPensions($dbPensions),
                    'state_pension' => $this->formatStatePension($statePension, $incomeProjection),
                ];

                // Decumulation analysis for users within transition period of retirement or already retired
                $decumulation = null;
                $accumulationToDecumulationYears = (int) $this->taxConfig->get('retirement.accumulation_to_decumulation_years', 10);
                if ($yearsToRetirement <= $accumulationToDecumulationYears && $currentDcValue > 0) {
                    $decumulationUser = User::with('protectionProfile')->find($userId);
                    // W-0198. Was `override ?? profile ?? 85`. The precedence lives in
                    // one place now, so retirement, decumulation and the estate answer
                    // the same for the same person.
                    $lifeExpectancy = $decumulationUser
                        ? $this->futureValue->getLifeExpectancy($decumulationUser)['death_age']
                        : self::DEFAULT_LIFE_EXPECTANCY;
                    $yearsInRetirement = max(1, $lifeExpectancy - $retirementAge);
                    // W-0198. Same defect as the controller: presence of an optional
                    // field is not the same question as whether there is a spouse.
                    $hasSpouse = $decumulationUser
                        ? $this->futureValue->hasSpouse($decumulationUser)
                        : false;

                    // Wire care costs from RetirementProfile into decumulation planning
                    $careCostAnnual = (float) ($profile->care_cost_annual ?? 0);
                    $careStartAge = (int) ($profile->care_start_age ?? 0);
                    $careStartsAfterYear = ($careCostAnnual > 0 && $careStartAge > $retirementAge)
                        ? max(0, $careStartAge - $retirementAge)
                        : 0;

                    $decumulation = [
                        'withdrawal_rates' => $this->planner->calculateSustainableWithdrawalRate(
                            $currentDcValue,
                            $yearsInRetirement,
                            0.05,
                            0.025,
                            $careCostAnnual,
                            $careStartsAfterYear
                        ),
                        'annuity_vs_drawdown' => $this->planner->compareAnnuityVsDrawdown(
                            $currentDcValue,
                            $profile->current_age,
                            $hasSpouse,
                            $decumulationUser
                        ),
                        'pcls_strategy' => $this->planner->calculatePCLSStrategy($currentDcValue),
                        'income_phasing' => $this->planner->modelIncomePhasing(
                            $dcPensions,
                            $retirementAge
                        ),
                        'care_costs_modelled' => $careCostAnnual > 0,
                        'care_cost_annual' => round($careCostAnnual, 2),
                        'care_start_age' => $careStartAge > 0 ? $careStartAge : null,
                        'enhanced_annuity' => $this->planner->assessEnhancedAnnuityEligibility($decumulationUser),
                    ];
                }

                // Post-retirement goal detection
                $postRetirementGoals = [];
                $currentAge = $user->date_of_birth ? (int) now()->diffInYears($user->date_of_birth) : null;

                if ($currentAge) {
                    $yearsToRetirementForGoals = max(0, $retirementAge - $currentAge);
                    $retirementDate = now()->addYears($yearsToRetirementForGoals);

                    $activeGoals = Goal::forUserOrJoint($userId)->where('status', 'active')->get();
                    foreach ($activeGoals as $goal) {
                        if ($goal->target_date && $goal->target_date->gt($retirementDate)) {
                            $postRetirementGoals[] = [
                                'name' => $goal->goal_name,
                                'target_amount' => round((float) $goal->target_amount, 2),
                                'outstanding' => round(max(0, (float) $goal->target_amount - (float) $goal->current_amount), 2),
                                'monthly_contribution' => round((float) ($goal->monthly_contribution ?? 0), 2),
                                'annual_cost' => round((float) ($goal->monthly_contribution ?? 0) * 12, 2),
                                'target_date' => $goal->target_date->format('Y-m-d'),
                            ];
                        }
                    }
                }

                // S1.6.b — structured gap list for the LLM.
                $missingForQualityAdvice = $this->findMissingForQualityAdvice(
                    $user,
                    $profile,
                    $statePension,
                    $dcPensions,
                    $dbPensions,
                );

                return $this->response(true, 'Retirement analysis completed', [
                    'summary' => $summary,
                    'income_projection' => $incomeProjection,
                    'breakdown' => $breakdown,
                    'annual_allowance' => $allowance,
                    'profile' => $profile,
                    'decumulation' => $decumulation,
                    'post_retirement_goals' => $postRetirementGoals,
                    'missing_for_quality_advice' => $missingForQualityAdvice,
                ]);
            }, null, $cacheTags);
        })();

        $resultPath = match (true) {
            isset($result['success']) && $result['success'] === false => 'success_false',
            isset($result['data']['can_proceed']) && $result['data']['can_proceed'] === false => 'readiness_blocked',
            // Facts answered, target absent. Distinct from 'happy' so the eval trace
            // can still tell the two apart now that both return success: true.
            ($result['data']['summary']['has_retirement_target'] ?? null) === false => 'no_retirement_target',
            default => 'happy',
        };
        event(new EngineCalled(
            engine: 'retirement_analysis',
            params: ['user_id' => $userId],
            resultSummary: ['result_path' => $resultPath, 'keys_returned' => array_keys($result)],
            durationMs: (int) round((microtime(true) - $analyzeStart) * 1000),
            atMicrotime: microtime(true),
        ));

        return $result;
    }

    /**
     * What the user HOLDS, for a user who has not told us what they are AIMING AT.
     *
     * These are two different facts and they used to share one answer: without a
     * `retirement_profiles` row this method's caller returned `success: false` with
     * an empty data array — no pot, no schemes, no State Pension, not even a count.
     * A household holding £500,000 of Defined Contribution pensions, an NHS final
     * salary scheme paying £35,000 a year and a State Pension forecast was told it
     * had not started, and every consumer asking "does this user have retirement
     * provision" was told no (W-0244).
     *
     * **The dividing line, and it is the whole point of the shape:** everything
     * derived from the pension RECORDS is present and real — the pot, the schemes,
     * the guaranteed income, the counts, the annual allowance position. Everything
     * derived from the TARGET is null — the projection against a goal, the income
     * gap, years to retirement, the decumulation plan. `summary.has_retirement_target`
     * is the flag consumers should branch on. `success` is not that flag and must
     * never be used as one again.
     *
     * The key set is deliberately identical to the happy path's, so a consumer
     * reading `summary.income_gap` gets `null` rather than a missing key, and
     * `ToolResultContract::REQUIRED_KEYS['retirement']` is satisfied without a
     * special case.
     *
     * @param  array<string, mixed>  $incomeProjection
     * @return array<string, mixed>
     */
    private function factsWithoutTarget(
        ?User $user,
        Collection $dcPensions,
        Collection $dbPensions,
        $statePension,
        array $incomeProjection,
        int $userId,
    ): array {
        return $this->response(true, 'Retirement provision found; no retirement target set yet', [
            'summary' => $this->provisionSummary($user, $dcPensions, $dbPensions, $statePension, $incomeProjection),
            'income_projection' => $incomeProjection,
            'breakdown' => [
                'dc_pensions' => $this->formatDCPensions($dcPensions, $incomeProjection),
                'db_pensions' => $this->formatDBPensions($dbPensions),
                'state_pension' => $this->formatStatePension($statePension, $incomeProjection),
            ],
            'annual_allowance' => $this->allowanceChecker->checkAnnualAllowance(
                $userId,
                $this->taxConfig->getTaxYear(),
            ),
            // The absent target, stated as the absence it is.
            'profile' => null,
            'decumulation' => null,
            'post_retirement_goals' => [],
            'missing_for_quality_advice' => $this->findMissingForQualityAdvice(
                $user,
                null,
                $statePension,
                $dcPensions,
                $dbPensions,
            ),
        ]);
    }

    /**
     * The record-derived half of the summary: what this household holds, with every
     * target-derived figure explicitly null.
     *
     * **One home, read by both branches that answer without a usable target** — the
     * readiness-gated branch and the no-profile branch — so the two cannot drift into
     * telling a user different things about the same pensions (Rule 20). The key set
     * matches the happy path's, so a consumer reading `summary.income_gap` gets null
     * rather than a missing key.
     *
     * Callers that already hold the loaded collections pass them in; the readiness
     * gate does not, so they are loaded here.
     *
     * @param  array<string, mixed>|null  $incomeProjection
     * @return array<string, mixed>
     */
    private function provisionSummary(
        ?User $user,
        ?Collection $dcPensions = null,
        ?Collection $dbPensions = null,
        $statePension = null,
        ?array $incomeProjection = null,
    ): array {
        $dcPensions ??= $user?->dcPensions ?? collect();
        $dbPensions ??= $user?->dbPensions ?? collect();
        $statePension ??= $user?->statePension;
        $incomeProjection ??= $user !== null
            ? $this->projector->projectTotalRetirementIncome($user->id)
            : [];

        return [
            // Target-derived — null, not zero. Zero is a figure; these are absent.
            'years_to_retirement' => null,
            'target_retirement_age' => null,
            'projected_retirement_income' => null,
            'target_retirement_income' => null,
            'income_gap' => null,
            'retires_before_spa' => null,
            'income_after_spa' => null,
            'income_gap_after_spa' => null,
            // Record-derived — facts about what this household holds. W-0516 closed
            // the literal this comment used to defend: both branches now resolve from
            // the cohort schedule, so they still cannot drift and neither hardcodes an
            // age that changes with the user's year of birth.
            'state_pension_age' => $this->statePensionAge->forUser($user),
            'state_pension_income' => $incomeProjection['state_pension_income'] ?? 0,
            'current_dc_value' => (float) $dcPensions->sum('current_fund_value'),
            'total_dc_value' => $incomeProjection['dc_total_value'] ?? 0,
            'total_pensions_count' => $dcPensions->count() + $dbPensions->count() + ($statePension ? 1 : 0),
            // Reflects whether a target EXISTS, not whether it could be projected
            // against. A readiness-blocked household may well have stated a target;
            // saying otherwise would be a second way of confusing the two facts.
            'has_retirement_target' => $user?->retirementProfile !== null,
            'guaranteed_annual_income' => $this->guaranteedAnnualIncome($incomeProjection),
        ];
    }

    /**
     * Retirement income already secured by schemes that pay an income rather than
     * hold a balance — Defined Benefit plus State Pension.
     *
     * One home, read by both branches of `analyze()` and by every card that needs
     * it, so a household whose whole provision is a final salary scheme cannot be
     * shown a balance of zero on one surface and its real income on another
     * (Rule 20).
     *
     * **Basis caveat** (`app/Services/CLAUDE.md`): the Defined Benefit component is
     * nominal at retirement and the State Pension component is in today's money.
     * Every existing consumer of `PensionProjector` already sums them this way —
     * this consolidates that behaviour, it does not introduce the mixing and does
     * not fix it. Reconciling the two bases is W-0245's territory.
     *
     * @param  array<string, mixed>  $incomeProjection
     */
    private function guaranteedAnnualIncome(array $incomeProjection): float
    {
        return round(
            (float) ($incomeProjection['db_annual_income'] ?? 0)
            + (float) ($incomeProjection['state_pension_income'] ?? 0),
            2
        );
    }

    /**
     * Per-agent contract gap field (S1.6.b).
     *
     * @return list<array{field: string, why: string, severity: 'blocking'|'soft'}>
     */
    private function findMissingForQualityAdvice(
        ?User $user,
        ?RetirementProfile $profile,
        $statePension,
        Collection $dcPensions,
        Collection $dbPensions,
    ): array {
        $gaps = [];

        // The absent profile is itself the gap, and naming it is what lets the
        // caller ask for a target instead of telling a household with £500,000 of
        // pensions that it has no retirement provision (W-0244).
        if ($profile === null) {
            $gaps[] = [
                'field' => 'retirement_profile',
                'why' => 'No target retirement age or income has been set, so nothing can be projected against a goal. What the user already holds is known and unaffected.',
                'severity' => 'blocking',
            ];
        }

        if ((float) ($profile->target_retirement_income ?? 0) <= 0) {
            $gaps[] = [
                'field' => 'target_retirement_income',
                'why' => 'Income gap and contribution recommendations are computed against a target — without it the projection has nothing to aim at.',
                'severity' => 'blocking',
            ];
        }

        if ($dcPensions->isEmpty() && $dbPensions->isEmpty() && $statePension === null) {
            $gaps[] = [
                'field' => 'pension_records',
                'why' => 'No DC, DB, or State Pension recorded — there is nothing to project retirement income from.',
                'severity' => 'blocking',
            ];
        }

        if ($statePension === null) {
            $gaps[] = [
                'field' => 'state_pension',
                'why' => 'A State Pension forecast (NI years + state pension age) materially changes the projected income.',
                'severity' => 'soft',
            ];
        }

        if ($user?->marital_status === 'married' && $profile?->spouse_life_expectancy === null) {
            $gaps[] = [
                'field' => 'spouse_life_expectancy',
                'why' => 'Joint-life decumulation planning depends on the spouse life expectancy.',
                'severity' => 'soft',
            ];
        }

        return $gaps;
    }

    /**
     * Generate retirement recommendations via database-driven action definitions.
     */
    public function generateRecommendations(array $analysisData): array
    {
        $start = microtime(true);
        $result = $this->actionDefinitionService->evaluateAgentActions($analysisData);

        event(new EngineCalled(
            engine: 'retirement_recommendation',
            params: [],
            resultSummary: ['result_path' => 'happy', 'keys_returned' => is_array($result) ? array_keys($result) : []],
            durationMs: (int) round((microtime(true) - $start) * 1000),
            atMicrotime: microtime(true),
        ));

        return $result;
    }

    /**
     * Build what-if retirement scenarios.
     */
    public function buildScenarios(int $userId, array $parameters): array
    {
        $profile = RetirementProfile::where('user_id', $userId)->first();
        $dcPensions = $this->getDCPensions($userId);

        if (! $profile) {
            return $this->response(false, 'No retirement profile found', []);
        }

        $scenarios = [];

        // Scenario 1: Current trajectory
        $scenarios['current'] = $this->buildCurrentScenario($userId, $profile);

        // Scenario 2: Increased contributions (support both parameter names)
        $additionalContribution = $parameters['increased_contribution'] ?? $parameters['additional_contribution'] ?? null;
        if ($additionalContribution) {
            $scenarios['increased_contribution'] = $this->buildIncreasedContributionScenario(
                $userId,
                $profile,
                $dcPensions,
                (float) $additionalContribution
            );
        }

        // Scenario 3: Later retirement age
        if (isset($parameters['later_retirement_age'])) {
            $scenarios['later_retirement'] = $this->buildLaterRetirementScenario(
                $userId,
                $profile,
                (int) $parameters['later_retirement_age']
            );
        }

        // Scenario 4: Lower target income
        if (isset($parameters['lower_target_income'])) {
            $scenarios['lower_target'] = $this->buildLowerTargetScenario(
                $profile,
                (float) $parameters['lower_target_income']
            );
        }

        return $this->response(true, 'Scenarios generated', [
            'scenarios' => $scenarios,
            'comparison' => $this->compareScenarios($scenarios),
        ]);
    }

    /**
     * Build current trajectory scenario.
     */
    private function buildCurrentScenario(int $userId, RetirementProfile $profile): array
    {
        $incomeProjection = $this->projector->projectTotalRetirementIncome($userId);
        $targetIncome = (float) $profile->target_retirement_income;
        $projectedIncome = $incomeProjection['total_projected_income'];

        return [
            'name' => 'Current Trajectory',
            'description' => 'Based on your current contributions and retirement age',
            'retirement_age' => $profile->target_retirement_age,
            'projected_income' => $projectedIncome,
            'target_income' => $targetIncome,
            'income_gap' => $targetIncome - $projectedIncome,
        ];
    }

    /**
     * Build increased contribution scenario.
     */
    private function buildIncreasedContributionScenario(
        int $userId,
        RetirementProfile $profile,
        $dcPensions,
        float $additionalMonthlyContribution
    ): array {
        // Simulate increased contributions
        $yearsToRetirement = max(0, $profile->target_retirement_age - $profile->current_age);
        $additionalAnnualContribution = $additionalMonthlyContribution * 12;
        $growthRate = $this->planConfig?->getDefaultGrowthRate() ?? $this->getUserGrowthRate($userId);
        $withdrawalRate = $this->planConfig?->getWithdrawalRate() ?? TaxDefaults::SAFE_WITHDRAWAL_RATE;

        $additionalValue = 0.0;
        if ($yearsToRetirement > 0 && $growthRate > 0) {
            $additionalValue = $additionalAnnualContribution * ((pow(1 + $growthRate, $yearsToRetirement) - 1) / $growthRate);
        }

        $currentProjection = $this->projector->projectTotalRetirementIncome($userId);
        $newDCValue = $currentProjection['dc_total_value'] + $additionalValue;
        $newDCIncome = $newDCValue * $withdrawalRate;
        $newTotalIncome = $newDCIncome + $currentProjection['db_annual_income'] + $currentProjection['state_pension_income'];

        $targetIncome = (float) $profile->target_retirement_income;

        return [
            'name' => 'Increased Contributions',
            'description' => sprintf('Adding £%s per month to pension contributions', number_format($additionalMonthlyContribution, 2)),
            'retirement_age' => $profile->target_retirement_age,
            'additional_monthly_contribution' => $additionalMonthlyContribution,
            'additional_pot_value' => round($additionalValue, 2),
            'projected_income' => $newTotalIncome,
            'target_income' => $targetIncome,
            'income_gap' => $targetIncome - $newTotalIncome,
        ];
    }

    /**
     * Build later retirement scenario.
     */
    private function buildLaterRetirementScenario(int $userId, RetirementProfile $profile, int $newRetirementAge): array
    {
        $additionalYears = $newRetirementAge - $profile->target_retirement_age;

        // Simulate additional years of contributions and growth
        $dcPensions = $this->getDCPensions($userId);
        $currentMonthlyContributions = $dcPensions->sum('monthly_contribution_amount');
        $additionalContributions = ($currentMonthlyContributions * 12) * $additionalYears;

        $currentProjection = $this->projector->projectTotalRetirementIncome($userId);

        // Rough calculation: additional years of growth on current pot plus new contributions
        $growthRate = $this->planConfig?->getDefaultGrowthRate() ?? $this->getUserGrowthRate($userId);
        $withdrawalRate = $this->planConfig?->getWithdrawalRate() ?? TaxDefaults::SAFE_WITHDRAWAL_RATE;
        $additionalGrowth = $currentProjection['dc_total_value'] * (pow(1 + $growthRate, $additionalYears) - 1);
        $additionalFromContributions = $additionalContributions * (1 + $growthRate * ($additionalYears / 2)); // Simplified

        $newDCValue = $currentProjection['dc_total_value'] + $additionalGrowth + $additionalFromContributions;
        $newDCIncome = $newDCValue * $withdrawalRate;
        $newTotalIncome = $newDCIncome + $currentProjection['db_annual_income'] + $currentProjection['state_pension_income'];

        $targetIncome = (float) $profile->target_retirement_income;

        return [
            'name' => 'Later Retirement',
            'description' => sprintf('Retiring at age %d instead of %d', $newRetirementAge, $profile->target_retirement_age),
            'retirement_age' => $newRetirementAge,
            'additional_years' => $additionalYears,
            'projected_income' => $newTotalIncome,
            'target_income' => $targetIncome,
            'income_gap' => $targetIncome - $newTotalIncome,
        ];
    }

    /**
     * Build lower target income scenario.
     */
    private function buildLowerTargetScenario(RetirementProfile $profile, float $newTargetIncome): array
    {
        $userId = $profile->user_id;
        $currentProjection = $this->projector->projectTotalRetirementIncome($userId);
        $projectedIncome = $currentProjection['total_projected_income'];

        return [
            'name' => 'Adjusted Lifestyle',
            'description' => sprintf('Reducing target retirement income to £%s', number_format($newTargetIncome, 2)),
            'retirement_age' => $profile->target_retirement_age,
            'projected_income' => $projectedIncome,
            'target_income' => $newTargetIncome,
            'savings_required' => (float) $profile->target_retirement_income - $newTargetIncome,
            'income_gap' => $newTargetIncome - $projectedIncome,
        ];
    }

    /**
     * Compare scenarios side by side.
     */
    private function compareScenarios(array $scenarios): array
    {
        $comparison = [
            'best_scenario' => null,
            'smallest_gap' => PHP_FLOAT_MAX,
        ];

        foreach ($scenarios as $key => $scenario) {
            $gap = $scenario['income_gap'] ?? PHP_FLOAT_MAX;
            if ($gap < $comparison['smallest_gap']) {
                $comparison['smallest_gap'] = $gap;
                $comparison['best_scenario'] = $key;
            }
        }

        return $comparison;
    }

    /**
     * Get the user's risk-based growth rate, falling back to medium risk config rate.
     */
    private function getUserGrowthRate(int $userId): float
    {
        $riskLevel = $this->riskPreferenceService->getMainRiskLevel($userId);

        if ($riskLevel) {
            try {
                $params = $this->riskPreferenceService->getReturnParameters($riskLevel);

                return $params['expected_return_typical'] / 100; // Convert percentage to decimal
            } catch (\Exception $e) {
                // Fall through to config-based fallback
            }
        }

        return (float) $this->taxConfig->get('assumptions.growth_by_risk.medium', TaxDefaults::DEFAULT_GROWTH_RATE);
    }

    /**
     * Get DC pensions for a user, cached for the lifetime of this agent instance.
     */
    private function getDCPensions(int $userId): Collection
    {
        if ($this->dcPensions === null) {
            $this->dcPensions = app(PensionStore::class)->forUserByType(User::findOrFail($userId), 'dc');
        }

        return $this->dcPensions;
    }

    /**
     * Format DC pensions for output.
     */
    private function formatDCPensions($dcPensions, array $incomeProjection): array
    {
        $formatted = [];

        foreach ($dcPensions as $pension) {
            $formatted[] = [
                'id' => $pension->id,
                'scheme_name' => $pension->scheme_name,
                'scheme_type' => $pension->scheme_type,
                'provider' => $pension->provider,
                'current_value' => (float) $pension->current_fund_value,
                'monthly_contribution' => (float) $pension->monthly_contribution_amount,
                'projected_value' => (float) ($pension->projected_value_at_retirement ?? 0),
            ];
        }

        return $formatted;
    }

    /**
     * Format DB pensions for output.
     */
    private function formatDBPensions($dbPensions): array
    {
        $formatted = [];

        foreach ($dbPensions as $pension) {
            $formatted[] = [
                'id' => $pension->id,
                'scheme_name' => $pension->scheme_name,
                'scheme_type' => $pension->scheme_type,
                'accrued_annual_pension' => (float) $pension->accrued_annual_pension,
                'normal_retirement_age' => $pension->normal_retirement_age,
            ];
        }

        return $formatted;
    }

    /**
     * Format State Pension for output.
     */
    private function formatStatePension($statePension, array $incomeProjection): ?array
    {
        if (! $statePension) {
            return null;
        }

        return [
            'ni_years_completed' => $statePension->ni_years_completed,
            'ni_years_required' => $statePension->ni_years_required,
            'forecast_annual' => $incomeProjection['state_pension_income'],
            'state_pension_age' => $statePension->state_pension_age,
        ];
    }

    /**
     * Analyze DC pension portfolio holdings (portfolio optimization)
     *
     * Delegates to PensionPortfolioAnalyzer service for:
     * - Risk metrics (Alpha, Beta, Sharpe Ratio, Volatility, Max Drawdown, VaR)
     * - Asset allocation analysis
     * - Diversification scoring
     * - Fee analysis
     */
    public function analyzeDCPensionPortfolio(int $userId, ?int $dcPensionId = null): array
    {
        $cacheKey = $dcPensionId
            ? "dc_pension_{$dcPensionId}_portfolio"
            : "dc_pensions_portfolio_{$userId}";
        $cacheTags = ['retirement', 'user_'.$userId];

        return $this->remember($cacheKey, function () use ($userId, $dcPensionId) {
            return $this->pensionPortfolioAnalyzer->analyze($userId, $dcPensionId);
        }, null, $cacheTags);
    }
}
