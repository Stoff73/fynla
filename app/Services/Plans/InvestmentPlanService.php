<?php

declare(strict_types=1);

namespace App\Services\Plans;

use App\Agents\InvestmentAgent;
use App\Agents\SavingsAgent;
use App\Models\Goal;
use App\Models\Investment\InvestmentAccount;
use App\Models\Investment\RiskProfile;
use App\Models\PlanActionFundingSelection;
use App\Models\SavingsAccount;
use App\Models\User;
use App\Services\Coordination\RecommendationPersonaliser;
use App\Services\Investment\FeeAnalyzer;
use App\Services\Investment\InvestmentActionDefinitionService;
use App\Services\Investment\Recommendation\ConflictResolutionService;
use App\Services\Investment\Recommendation\ContributionWaterfallService;
use App\Services\Investment\Recommendation\DataReadinessService;
use App\Services\Investment\Recommendation\GoalAssessmentService;
use App\Services\Investment\Recommendation\LifeEventAssessmentService;
use App\Services\Investment\Recommendation\RecommendationOutputFormatter;
use App\Services\Investment\Recommendation\SafetyCheckService;
use App\Services\Investment\Recommendation\SpouseOptimisationService;
use App\Services\Investment\Recommendation\TransferRecommendationService;
use App\Services\Investment\Recommendation\UserContextBuilder;
use App\Services\TaxConfigService;

class InvestmentPlanService extends BasePlanService
{
    /** Contribution action categories that should show a funding source. */
    private const CONTRIBUTION_CATEGORIES = [
        'ISA Allowance',
        'Emergency Fund Surplus',
        'Goal',
    ];

    /** Liquid cash account types safe to recommend as a funding source. */
    private const FUNDING_CASH_ACCOUNT_TYPES = [
        'current_account',
        'instant_access',
        'business_current',
        'business_savings',
    ];

    public function __construct(
        private readonly InvestmentAgent $investmentAgent,
        private readonly SavingsAgent $savingsAgent,
        private readonly FeeAnalyzer $feeAnalyzer,
        private readonly PlanConfigService $planConfig,
        private readonly DisposableIncomeAccessor $incomeAccessor,
        private readonly TaxConfigService $taxConfig,
        private readonly InvestmentActionDefinitionService $actionDefinitionService,
        private readonly RecommendationPersonaliser $personaliser,
        private readonly UserContextBuilder $userContextBuilder,
        private readonly DataReadinessService $dataReadinessService,
        private readonly SafetyCheckService $safetyCheckService,
        private readonly ContributionWaterfallService $waterfallService,
        private readonly TransferRecommendationService $transferService,
        private readonly SpouseOptimisationService $spouseService,
        private readonly GoalAssessmentService $goalAssessmentService,
        private readonly LifeEventAssessmentService $lifeEventService,
        private readonly ConflictResolutionService $conflictResolutionService,
        private readonly RecommendationOutputFormatter $outputFormatter
    ) {}

    public function generatePlan(int $userId, array $options = []): array
    {
        $user = User::findOrFail($userId);
        $completeness = $this->checkDataCompleteness($userId);

        $investmentAnalysis = $this->investmentAgent->analyze($userId);
        $savingsAnalysis = $this->savingsAgent->analyze($userId);

        $investmentAccounts = InvestmentAccount::forUserOrJoint($userId)
            ->with('holdings')
            ->get();

        $savingsAccounts = SavingsAccount::forUserOrJoint($userId)->get();

        $currentSituation = $this->buildCurrentSituation(
            $investmentAnalysis,
            $savingsAnalysis,
            $investmentAccounts,
            $savingsAccounts
        );

        // Get recommendations from DB-driven service (replaces agent + buildSavingsRecommendations)
        $recommendations = $this->getRecommendations($userId, [
            'investment_analysis' => $investmentAnalysis,
            'savings_analysis' => $savingsAnalysis,
            'investment_accounts' => $investmentAccounts,
        ]);

        // Get goal recommendations from DB-driven service (replaces buildGoalRecommendations)
        $goals = $this->getGoalsForPlan($userId, 'investment');
        $goalRecommendations = $this->actionDefinitionService->evaluateGoalActions($goals['linked']);

        // Merge: goals first, then agent recs
        $allRecs = array_merge($goalRecommendations, $recommendations);
        ['actions' => $actions, 'enabledActions' => $enabledActions] = $this->prepareActions($allRecs, 'investment', $options);

        $userAge = $user->date_of_birth ? (int) \Carbon\Carbon::parse($user->date_of_birth)->age : null;
        $retirementAge = $user->target_retirement_age ? (int) $user->target_retirement_age : null;
        $yearsToRetirement = ($userAge !== null && $retirementAge !== null && $retirementAge > $userAge)
            ? $retirementAge - $userAge
            : null;

        // Enrich actions with cascade parameters for per-action what-if charts
        $actions = $this->enrichActionsWithCascadeParams($user, $investmentAnalysis, $actions, $currentSituation);

        // Enrich contribution actions with funding source recommendations
        $actions = $this->enrichActionsWithFundingSource($user, $actions);

        // Re-derive enabled actions after enrichment
        $enabledActions = collect($actions)->where('enabled', true)->values()->toArray();

        $whatIf = $this->buildWhatIfData($user, $investmentAnalysis, $savingsAnalysis, $currentSituation, $enabledActions, $yearsToRetirement);
        $conclusion = $this->generateDynamicConclusion($currentSituation, $enabledActions, 'investment');
        $accountProjections = $this->buildAccountGrowthProjections($actions, $investmentAccounts, $userId, $yearsToRetirement);

        return [
            'metadata' => $this->buildPlanMetadata($user, 'investment', $completeness),
            'completeness_warning' => $this->buildCompletenessWarning($completeness),
            'executive_summary' => $this->buildExecutiveSummary($user, $investmentAnalysis, $savingsAnalysis, $investmentAccounts, $savingsAccounts, $goals, $actions),
            'personal_information' => $this->buildPersonalInformation($user),
            'current_situation' => $currentSituation,
            'actions' => $actions,
            'what_if' => $whatIf,
            'conclusion' => $conclusion,
            'account_projections' => $accountProjections,
            'linked_goals' => $goals['linked'],
            'unlinked_goals' => $goals['unlinked'],
        ];
    }

    /**
     * Get recommendations by running the full pipeline:
     *   1. DB-driven triggers (InvestmentActionDefinitionService)
     *   2. Recommendation pipeline (waterfall, transfers, spouse, conflict resolution)
     *
     * The pipeline runs in parallel with existing triggers, and ConflictResolutionService
     * merges and deduplicates all sources into a single prioritised list.
     *
     * @param  int  $userId  User ID
     * @param  array|null  $preComputedData  Optional pre-computed data with keys:
     *                                       investment_analysis, savings_analysis, investment_accounts
     */
    public function getRecommendations(int $userId, ?array $preComputedData = null): array
    {
        $investmentAnalysis = $preComputedData['investment_analysis'] ?? $this->investmentAgent->analyze($userId);
        $savingsAnalysis = $preComputedData['savings_analysis'] ?? $this->savingsAgent->analyze($userId);

        $investmentAccounts = $preComputedData['investment_accounts'] ?? InvestmentAccount::forUserOrJoint($userId)
            ->with('holdings')
            ->get();

        $savingsAccounts = SavingsAccount::forUserOrJoint($userId)->get();

        // ── Phase 1: DB-driven trigger recommendations ──
        $accountFeeAnalyses = $investmentAccounts->map(
            fn ($acct) => $this->feeAnalyzer->analyzeAccountFees($acct)
        )->filter(fn ($a) => $a['success'] ?? false)->values()->toArray();

        $triggerResult = $this->actionDefinitionService->evaluateAgentActions(
            $investmentAnalysis,
            $savingsAnalysis,
            $investmentAccounts,
            $savingsAccounts,
            $userId,
            $accountFeeAnalyses
        );

        $triggerRecs = $triggerResult['recommendations'] ?? [];

        // ── Phase 2: Pipeline recommendations ──
        $user = User::find($userId);
        $pipelineRecs = [];

        if ($user) {
            $pipelineRecs = $this->runPipeline($user, $investmentAnalysis, $savingsAnalysis, $investmentAccounts, $triggerRecs);
        }

        // If pipeline returned merged results, use those (they include trigger recs)
        $recommendations = ! empty($pipelineRecs) ? $pipelineRecs : $triggerRecs;

        // Filter out non-investment recommendations from spouse/transfer services.
        // Pension, savings allowance, and other cross-module actions belong in the
        // holistic plan only — individual module plans must stay focused.
        $recommendations = array_values(array_filter($recommendations, function (array $rec): bool {
            $type = $rec['strategy_type'] ?? $rec['scan_type'] ?? '';

            // Spouse strategies that belong to other modules
            $nonInvestmentStrategies = [
                'pension_coordination',      // Retirement module
                'non_earning_spouse_pension', // Retirement module
                'psa_optimisation',          // Savings module
                'marriage_allowance',        // Tax/general — not investment-specific
            ];

            // Transfer scans that belong to other modules
            $nonInvestmentScans = [
                'psa_breach',                // Savings module
            ];

            return ! in_array($type, array_merge($nonInvestmentStrategies, $nonInvestmentScans), true);
        }));

        // Add personalised context
        if ($user) {
            $recommendations = $this->personaliser->personaliseRecommendations($recommendations, $user);
        }

        return $recommendations;
    }

    /**
     * Run the full recommendation pipeline.
     *
     * Pipeline phases:
     *  1. Data readiness gate
     *  2. Build user context (UserContextBuilder)
     *  3. Safety checks (SafetyCheckService) — adjusts surplus
     *  4. Life event assessment (LifeEventAssessmentService) — wrapper modifiers
     *  5. Goal assessment (GoalAssessmentService) — wrapper modifiers
     *  6. Contribution waterfall (ContributionWaterfallService)
     *  7. Transfer scans (TransferRecommendationService)
     *  8. Spouse optimisation (SpouseOptimisationService)
     *  9. Conflict resolution (ConflictResolutionService) — merge all sources
     *
     * Falls back gracefully to trigger-only recommendations if any phase fails.
     *
     * @return array Merged recommendations, or empty array if pipeline cannot run
     */
    private function runPipeline(
        User $user,
        array $investmentAnalysis,
        array $savingsAnalysis,
        $investmentAccounts,
        array $triggerRecs
    ): array {
        try {
            // Phase 1: Data readiness gate
            $readiness = $this->dataReadinessService->assess($user);
            if (! ($readiness['can_proceed'] ?? true)) {
                // Pipeline cannot run — fall back to trigger-only recommendations
                return [];
            }

            // Phase 2: Build user context from pre-computed data
            $context = $this->userContextBuilder->buildFromExisting(
                $investmentAnalysis,
                $savingsAnalysis,
                $investmentAccounts,
                $user
            );

            // Phase 3: Safety checks — adjusts surplus
            $safetyResult = $this->safetyCheckService->check($context);
            $adjustedSurplus = $safetyResult['adjusted_surplus'] ?? 0;

            // Phase 4: Life event assessment
            $lifeEventModifiers = $this->lifeEventService->assess($context);

            // Phase 5: Goal assessment
            $goalModifiers = $this->goalAssessmentService->assess($context);

            // Phase 6: Contribution waterfall
            $waterfallResult = ($adjustedSurplus > 0 && ($safetyResult['can_invest'] ?? true))
                ? $this->waterfallService->allocate($context, $adjustedSurplus, $lifeEventModifiers, $goalModifiers, $safetyResult)
                : ['recommendations' => [], 'total_allocated' => 0, 'remaining_surplus' => 0, 'steps_executed' => 0, 'steps_skipped' => 0, 'decision_path' => []];

            // Phase 7: Transfer scans
            $transferResult = $this->transferService->scan($context);

            // Phase 8: Spouse optimisation
            $spouseResult = $this->spouseService->optimise($context);

            // Phase 9: Conflict resolution — merge all sources
            $merged = $this->conflictResolutionService->resolve(
                $waterfallResult['recommendations'] ?? [],
                $triggerRecs,
                $transferResult['recommendations'] ?? [],
                $spouseResult['recommendations'] ?? []
            );

            return $merged['recommendations'] ?? [];
        } catch (\Exception $e) {
            // Pipeline failure is non-fatal — fall back to trigger-only recommendations
            \Illuminate\Support\Facades\Log::warning('Investment pipeline failed, falling back to triggers: '.$e->getMessage());

            return [];
        }
    }

    public function checkDataCompleteness(int $userId): array
    {
        $missing = [];
        $user = User::find($userId);
        $checks = [
            'investment_accounts' => InvestmentAccount::where('user_id', $userId)->exists(),
            'risk_profile' => RiskProfile::where('user_id', $userId)->exists(),
            'savings_accounts' => SavingsAccount::where('user_id', $userId)->exists(),
            'income' => $user && ($user->annual_employment_income || $user->annual_self_employment_income),
        ];

        $hasHoldings = false;
        if ($checks['investment_accounts']) {
            $hasHoldings = InvestmentAccount::where('user_id', $userId)
                ->whereHas('holdings')
                ->exists();
            if (! $hasHoldings) {
                $missing[] = [
                    'field' => 'holdings',
                    'label' => 'Investment holdings',
                    'description' => 'Add holdings to your investment accounts for detailed analysis.',
                    'link' => '/net-worth/investments',
                ];
            }
        }

        if (! $checks['investment_accounts']) {
            $missing[] = [
                'field' => 'investment_accounts',
                'label' => 'Investment accounts',
                'description' => 'Add your investment accounts to receive portfolio analysis.',
                'link' => '/net-worth/investments',
            ];
        }

        if (! $checks['risk_profile']) {
            $missing[] = [
                'field' => 'risk_profile',
                'label' => 'Risk profile',
                'description' => 'Complete the risk questionnaire for personalised allocation recommendations.',
                'link' => '/net-worth/investments',
            ];
        }

        if (! $checks['savings_accounts']) {
            $missing[] = [
                'field' => 'savings_accounts',
                'label' => 'Savings accounts',
                'description' => 'Add your savings accounts for emergency fund analysis.',
                'link' => '/savings',
            ];
        }

        if (! $checks['income']) {
            $missing[] = [
                'field' => 'income',
                'label' => 'Income details',
                'description' => 'Add your income for accurate emergency fund runway calculations.',
                'link' => '/profile',
            ];
        }

        // Check retirement date for projection horizon
        $hasRetirementDate = $user && $user->target_retirement_age && $user->date_of_birth;
        if (! $hasRetirementDate) {
            $missing[] = [
                'field' => 'retirement_date',
                'label' => 'Target retirement age',
                'description' => 'Set your target retirement age to enable investment growth projections.',
                'link' => '/net-worth/retirement',
            ];
        }

        $total = count($checks) + 2; // +1 for holdings, +1 for retirement date
        $present = $total - count($missing);

        return [
            'percentage' => $total > 0 ? round(($present / $total) * 100) : 0,
            'missing' => $missing,
            'complete' => empty($missing),
        ];
    }

    /**
     * Build structured executive summary matching the retirement plan format.
     */
    private function buildExecutiveSummary(
        User $user,
        array $investmentAnalysis,
        array $savingsAnalysis,
        $investmentAccounts,
        $savingsAccounts,
        array $goals = [],
        array $actions = []
    ): array {
        $firstName = $this->getUserFirstName($user);
        $totalInvestmentValue = $investmentAccounts->sum('current_value');
        $totalSavingsValue = $savingsAccounts->sum('current_balance');
        $totalWealth = $totalInvestmentValue + $totalSavingsValue;
        $totalAccounts = $investmentAccounts->count() + $savingsAccounts->count();
        $emergencyRunway = $savingsAnalysis['emergency_fund']['runway_months'] ?? 0;

        // Introduction sentence
        $introduction = $totalAccounts > 0
            ? sprintf(
                'This plan reviews your portfolio of %s across %d %s and provides personalised recommendations to optimise your investment and savings position.',
                $this->formatCurrency($totalWealth),
                $totalAccounts,
                $totalAccounts === 1 ? 'account' : 'accounts'
            )
            : 'This plan provides personalised recommendations to help you start building your investment and savings position.';

        // Goals summary for the table
        $allGoals = array_merge($goals['linked'] ?? [], $goals['unlinked'] ?? []);
        $goalsSummary = [];
        foreach ($allGoals as $goal) {
            $goalsSummary[] = [
                'name' => $goal['name'] ?? 'Unnamed goal',
                'target' => $goal['target_amount'] ?? 0,
                'progress' => $goal['progress_percentage'] ?? 0,
                'on_track' => $goal['is_on_track'] ?? false,
            ];
        }

        // Actions summary for the table (top priority actions, max 5)
        $actionsSummary = [];
        $count = 0;
        foreach ($actions as $action) {
            if ($count >= 5) {
                break;
            }
            $actionsSummary[] = [
                'title' => $action['title'] ?? 'Recommendation',
                'priority' => $action['priority'] ?? 'medium',
            ];
            $count++;
        }
        $totalActions = count($actions);

        // Closing statement
        $hasCritical = ! empty(array_filter($actions, fn ($a) => ($a['priority'] ?? '') === 'critical'));
        $closing = $hasCritical
            ? 'The sections below highlight urgent actions and specific recommendations to strengthen your investment position.'
            : ($totalActions > 0
                ? 'The sections below provide a detailed breakdown of your current holdings, asset allocation, fees, and specific recommendations to improve your investment position.'
                : 'Your investment position looks healthy. Continue monitoring your portfolio and review this plan periodically.');

        // On track: no critical actions and emergency fund covers at least 6 months
        $onTrack = ! $hasCritical && $emergencyRunway >= 6;

        return [
            'opening' => 'Thank you for using Fynla. Here is your personalised Investment Plan based on the accounts and holdings you have provided.',
            'greeting' => "Dear {$firstName},",
            'introduction' => $introduction,
            'goals_summary' => $goalsSummary,
            'actions_summary' => $actionsSummary,
            'total_actions' => $totalActions,
            'closing' => $closing,
            'on_track' => $onTrack,
        ];
    }

    /**
     * Build personal information section matching the retirement plan format.
     */
    private function buildPersonalInformation(User $user): array
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

        $incomeData = $this->incomeAccessor->getForUser($user);

        // Risk level
        $riskProfile = RiskProfile::where('user_id', $user->id)->first();

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
            'risk_level' => $riskProfile->risk_level ?? null,
        ];
    }

    private function buildCurrentSituation(
        array $investmentAnalysis,
        array $savingsAnalysis,
        $investmentAccounts,
        $savingsAccounts
    ): array {
        $accounts = $investmentAccounts->map(function ($account) {
            return [
                'id' => $account->id,
                'name' => $account->account_name,
                'type' => $account->account_type,
                'provider' => $account->provider,
                'value' => $this->roundToPenny((float) $account->current_value),
                'holdings_count' => $account->holdings->count(),
            ];
        })->toArray();

        $savings = $savingsAccounts->map(function ($account) {
            return [
                'id' => $account->id,
                'institution' => $account->institution,
                'type' => $account->account_type,
                'balance' => $this->roundToPenny((float) $account->current_balance),
                'interest_rate' => (float) ($account->interest_rate ?? 0),
            ];
        })->toArray();

        return [
            'investment_accounts' => $accounts,
            'savings_accounts' => $savings,
            'asset_allocation' => $investmentAnalysis['asset_allocation'] ?? [],
            'fee_analysis' => $investmentAnalysis['fee_analysis'] ?? [],
            'diversification' => $investmentAnalysis['diversification_summary'] ?? null,
            'tax_wrappers' => $investmentAnalysis['tax_wrappers'] ?? [],
            'emergency_fund' => [
                'runway_months' => $savingsAnalysis['emergency_fund']['runway_months'] ?? 0,
                'category' => $savingsAnalysis['emergency_fund']['category'] ?? 'Unknown',
                'total_savings' => $savingsAnalysis['summary']['total_savings'] ?? 0,
            ],
            'isa_allowance' => [
                'used' => $savingsAnalysis['isa_allowance']['total_used'] ?? 0,
                'remaining' => $savingsAnalysis['isa_allowance']['remaining'] ?? 0,
            ],
            'total_investment_value' => $this->roundToPenny($investmentAccounts->sum('current_value')),
            'total_savings_value' => $this->roundToPenny($savingsAccounts->sum('current_balance')),
        ];
    }

    /**
     * Build what-if data using DB-driven impact types instead of str_contains().
     */
    private function buildWhatIfData(
        User $user,
        array $investmentAnalysis,
        array $savingsAnalysis,
        array $currentSituation,
        array $enabledActions,
        ?int $yearsToRetirement = null
    ): array {
        $totalInvestment = $currentSituation['total_investment_value'];
        $totalSavings = $currentSituation['total_savings_value'];
        $emergencyMonths = $currentSituation['emergency_fund']['runway_months'] ?? 0;
        $projectionYears = $yearsToRetirement;
        $growthRate = $this->planConfig->getDefaultGrowthRate();

        // Use disposable income via DistributionAccount instead of hardcoded amounts
        $monthlyDisposable = $this->incomeAccessor->getMonthlyForUser($user);
        $budget = new DistributionAccount($monthlyDisposable);

        $feeReduction = 0;
        $additionalSavings = 0;

        foreach ($enabledActions as $action) {
            $impactType = $this->actionDefinitionService->getWhatIfImpactType($action['category'] ?? '');

            match ($impactType) {
                'fee_reduction' => $feeReduction += ($action['estimated_impact'] ?? 200),
                'savings_increase' => $additionalSavings += $budget->allocate($action['id'] ?? 'savings', $monthlyDisposable * 0.2),
                'contribution' => $additionalSavings += $budget->allocate($action['id'] ?? 'contribution', $monthlyDisposable * 0.3),
                'tax_optimisation' => $feeReduction += $totalInvestment * $this->planConfig->getTaxOptimisationGain(),
                default => $additionalSavings += $budget->allocate($action['id'] ?? 'default', $monthlyDisposable * 0.1),
            };
        }

        $currentProjected = $projectionYears !== null
            ? $this->roundToPenny($this->projectValue($totalInvestment, $growthRate, $projectionYears, 0))
            : null;

        $projectedWithActions = $projectionYears !== null
            ? $this->roundToPenny($this->projectValue($totalInvestment, $growthRate, $projectionYears, $additionalSavings))
            : null;

        return [
            'current_scenario' => [
                'total_wealth' => $this->roundToPenny($totalInvestment + $totalSavings),
                'annual_fees' => $this->roundToPenny($investmentAnalysis['fee_analysis']['total_annual_fees'] ?? 0),
                'emergency_fund_months' => round($emergencyMonths, 1),
                'projected_value' => $currentProjected,
            ],
            'projected_scenario' => [
                'total_wealth' => $this->roundToPenny($totalInvestment + $totalSavings + ($additionalSavings * 12)),
                'annual_fees' => $this->roundToPenny(max(0, ($investmentAnalysis['fee_analysis']['total_annual_fees'] ?? 0) - $feeReduction)),
                'emergency_fund_months' => round($emergencyMonths + ($additionalSavings > 0 ? 2 : 0), 1),
                'projected_value' => $projectedWithActions,
                'additional_monthly_savings' => $this->roundToPenny($additionalSavings),
            ],
            'is_approximate' => true,
            'frontend_calc_params' => [
                'current_value' => $totalInvestment,
                'growth_rate' => $growthRate,
                'years' => $projectionYears,
            ],
        ];
    }

    /**
     * Enrich actions with cascade_params for per-action what-if charts.
     *
     * Each action gets a cascade_params.additional_monthly value representing
     * the monthly amount this action adds to the investment/savings pot.
     */
    private function enrichActionsWithCascadeParams(User $user, array $investmentAnalysis, array $actions, array $currentSituation): array
    {
        $totalInvestment = $currentSituation['total_investment_value'] ?? 0;
        $monthlyDisposable = $this->incomeAccessor->getMonthlyForUser($user);
        $budget = new DistributionAccount($monthlyDisposable);

        foreach ($actions as &$action) {
            $impactType = $this->actionDefinitionService->getWhatIfImpactType($action['category'] ?? '');

            $additionalMonthly = match ($impactType) {
                'fee_reduction' => $this->estimateFeeReductionMonthly($action),
                'savings_increase' => $budget->allocate($action['id'] ?? 'savings', $monthlyDisposable * 0.2),
                'contribution' => $budget->allocate($action['id'] ?? 'contribution', $monthlyDisposable * 0.3),
                'tax_optimisation' => $totalInvestment * $this->planConfig->getTaxOptimisationGain() / 12,
                default => $totalInvestment * $this->planConfig->getDefaultActionGain() / 12,
            };

            $action['cascade_params'] = [
                'additional_monthly' => $this->roundToPenny($additionalMonthly),
            ];
        }
        unset($action);

        return $actions;
    }

    /**
     * Enrich contribution actions with a recommended funding source and eligible account list.
     *
     * Only applies to contribution-type actions (ISA Allowance, Emergency Fund Surplus, Goal).
     */
    private function enrichActionsWithFundingSource(User $user, array $actions): array
    {
        // Check if any actions need funding source data
        $hasContributionAction = false;
        foreach ($actions as $action) {
            if (in_array($action['category'] ?? '', self::CONTRIBUTION_CATEGORIES, true)) {
                $hasContributionAction = true;
                break;
            }
        }

        if (! $hasContributionAction) {
            return $actions;
        }

        // Load persisted selections (real users only - preview users have none saved)
        $persistedSelections = PlanActionFundingSelection::getForUser($user->id, 'investment');

        // Build eligible accounts once for the user
        $eligibleAccounts = $this->buildEligibleFundingAccounts($user);

        foreach ($actions as &$action) {
            if (! in_array($action['category'] ?? '', self::CONTRIBUTION_CATEGORIES, true)) {
                continue;
            }

            $targetAccountId = $action['account_id'] ?? 0;
            $selectionKey = ($action['category'] ?? '').'_'.$targetAccountId;

            // Check for persisted selection
            $persisted = $persistedSelections->get($selectionKey);
            $selected = null;

            if ($persisted) {
                // Verify the persisted account still exists in eligible list
                $selected = collect($eligibleAccounts)->first(
                    fn ($a) => $a['id'] === $persisted->funding_source_id
                        && $a['type'] === $persisted->funding_source_type
                );
            }

            // Auto-recommend if no valid persisted selection
            if (! $selected) {
                $selected = $this->autoRecommendFundingAccount($eligibleAccounts);
            }

            $action['funding_source'] = [
                'selected_id' => $selected['id'] ?? null,
                'selected_type' => $selected['type'] ?? null,
                'selected_name' => $selected['name'] ?? null,
                'warning' => $selected['warning'] ?? null,
                'eligible_accounts' => $eligibleAccounts,
            ];
        }
        unset($action);

        return $actions;
    }

    /**
     * Build the list of eligible funding accounts for a user.
     */
    private function buildEligibleFundingAccounts(User $user): array
    {
        $monthlyExpenditure = $this->resolveMonthlyExpenditure($user)['amount'];
        $targetMonths = $this->planConfig->getEmergencyFundTargetMonths();
        $emergencyThreshold = $monthlyExpenditure * $targetMonths;

        $accounts = [];

        // Cash accounts (non-ISA, liquid types)
        $cashAccounts = SavingsAccount::where('user_id', $user->id)
            ->where('is_isa', false)
            ->whereIn('account_type', self::FUNDING_CASH_ACCOUNT_TYPES)
            ->orderByDesc('current_balance')
            ->get();

        foreach ($cashAccounts as $account) {
            $balance = (float) $account->current_balance;
            $additionalMonthly = (float) ($account->additional_monthly_savings ?? 0);
            $balanceAfterYear = $balance - ($additionalMonthly * 12);

            $warning = null;
            if ($balanceAfterYear < $emergencyThreshold) {
                $warning = "Withdrawing would reduce your emergency fund below {$targetMonths} months of expenditure.";
            }

            $accounts[] = [
                'id' => $account->id,
                'type' => 'savings',
                'name' => $account->account_name ?? $account->institution ?? 'Cash Account',
                'balance' => $this->roundToPenny($balance),
                'warning' => $warning,
            ];
        }

        // GIA investment accounts
        $giaAccounts = InvestmentAccount::where('user_id', $user->id)
            ->where('account_type', 'gia')
            ->orderByDesc('current_value')
            ->get();

        foreach ($giaAccounts as $account) {
            $accounts[] = [
                'id' => $account->id,
                'type' => 'investment',
                'name' => $account->account_name ?? $account->provider ?? 'General Investment Account',
                'balance' => $this->roundToPenny((float) $account->current_value),
                'warning' => 'Using this account may trigger a Capital Gains Tax event.',
            ];
        }

        return $accounts;
    }

    /**
     * Auto-recommend the best funding account: safe cash first, then cash with warning, then GIA.
     */
    private function autoRecommendFundingAccount(array $eligibleAccounts): ?array
    {
        // First: cash without warning
        foreach ($eligibleAccounts as $account) {
            if ($account['type'] === 'savings' && $account['warning'] === null) {
                return $account;
            }
        }

        // Second: cash with warning
        foreach ($eligibleAccounts as $account) {
            if ($account['type'] === 'savings') {
                return $account;
            }
        }

        // Third: GIA
        foreach ($eligibleAccounts as $account) {
            if ($account['type'] === 'investment') {
                return $account;
            }
        }

        return null;
    }

    /**
     * Build per-account growth projections comparing current fees vs reduced fees.
     * Each account projects to its latest linked goal target date, or retirement if no goal.
     */
    private function buildAccountGrowthProjections(array $actions, $investmentAccounts, int $userId, ?int $yearsToRetirement = null): array
    {
        $accountActions = collect($actions)->where('scope', 'account')->where('enabled', true);

        if ($accountActions->isEmpty()) {
            return [];
        }

        $accountIdsWithActions = $accountActions->pluck('account_id')->unique()->filter()->values();

        // Load goals linked to investment accounts for this user
        $accountGoals = Goal::where('user_id', $userId)
            ->whereNotNull('linked_investment_account_id')
            ->whereNotNull('target_date')
            ->get()
            ->groupBy('linked_investment_account_id');

        $projections = [];
        $growthRate = $this->planConfig->getDefaultGrowthRate();
        $now = \Carbon\Carbon::now();

        foreach ($accountIdsWithActions as $accountId) {
            $account = $investmentAccounts->firstWhere('id', $accountId);
            if (! $account) {
                continue;
            }

            $feeAnalysis = $this->feeAnalyzer->analyzeAccountFees($account);
            if (! ($feeAnalysis['success'] ?? false)) {
                continue;
            }

            $currentValue = (float) ($feeAnalysis['account_value'] ?? $account->current_value ?? 0);
            if ($currentValue <= 0) {
                continue;
            }

            // Determine projection years: latest goal target date, or retirement
            $years = $yearsToRetirement;
            $projectionLabel = 'to retirement';
            $goals = $accountGoals->get($accountId);

            if ($goals && $goals->isNotEmpty()) {
                $latestGoal = $goals->sortByDesc('target_date')->first();
                $goalYears = (int) ceil($now->diffInMonths(\Carbon\Carbon::parse($latestGoal->target_date)) / 12);
                if ($goalYears > 0) {
                    $years = $goalYears;
                    $projectionLabel = $latestGoal->goal_name;
                }
            }

            // Skip if no projection horizon available
            if ($years === null || $years <= 0) {
                continue;
            }

            $currentFeePercent = $feeAnalysis['total_fee_percent'] ?? 0;
            $currentFeeRate = $currentFeePercent / 100;

            $actionsForAccount = $accountActions->where('account_id', $accountId);
            $reducedFeePercent = $this->estimateReducedFeePercent($feeAnalysis, $actionsForAccount->toArray());
            $reducedFeeRate = $reducedFeePercent / 100;

            $currentFeesSeries = [];
            $reducedFeesSeries = [];

            for ($y = 0; $y <= $years; $y++) {
                $currentFeesSeries[] = $this->roundToPenny($currentValue * pow(1 + $growthRate - $currentFeeRate, $y));
                $reducedFeesSeries[] = $this->roundToPenny($currentValue * pow(1 + $growthRate - $reducedFeeRate, $y));
            }

            $projectionDifference = $reducedFeesSeries[$years] - $currentFeesSeries[$years];
            $annualFeeSaving = ($currentFeePercent - $reducedFeePercent) / 100 * $currentValue;

            $projections[] = [
                'account_id' => $account->id,
                'account_name' => $account->account_name,
                'account_type' => $account->account_type,
                'current_value' => $this->roundToPenny($currentValue),
                'current_fee_percent' => round($currentFeePercent, 2),
                'reduced_fee_percent' => round($reducedFeePercent, 2),
                'annual_fee_saving' => $this->roundToPenny($annualFeeSaving),
                'years' => $years,
                'projection_label' => $projectionLabel,
                'current_fees_series' => $currentFeesSeries,
                'reduced_fees_series' => $reducedFeesSeries,
                'projection_difference' => $this->roundToPenny($projectionDifference),
            ];
        }

        return $projections;
    }

    /**
     * Estimate what fees could be reduced to based on enabled account-level actions.
     */
    private function estimateReducedFeePercent(array $feeAnalysis, array $actions): float
    {
        $currentFeePercent = $feeAnalysis['total_fee_percent'] ?? 0;
        $totalReduction = 0;
        $accountValue = $feeAnalysis['account_value'] ?? 0;

        if ($accountValue <= 0) {
            return $currentFeePercent;
        }

        foreach ($actions as $action) {
            $category = strtolower($action['category'] ?? '');

            if (str_contains($category, 'platform')) {
                $currentPlatformPercent = (($feeAnalysis['fees']['platform_fee'] ?? 0) / $accountValue) * 100;
                $totalReduction += max(0, $currentPlatformPercent - $this->planConfig->getPlatformFeeBenchmark());
            } elseif (str_contains($category, 'high fee') || str_contains($category, 'fees')) {
                $currentOcf = $feeAnalysis['weighted_ocf'] ?? 0;
                $totalReduction += max(0, $currentOcf - $this->planConfig->getOCFBenchmark());
            }
        }

        return max(0, round($currentFeePercent - $totalReduction, 2));
    }

    /**
     * Estimate monthly equivalent of fee reduction for cascade params.
     */
    private function estimateFeeReductionMonthly(array $action): float
    {
        $estimatedImpact = $action['estimated_impact'] ?? 0;

        if ($estimatedImpact > 0) {
            return $estimatedImpact / 12;
        }

        return 0;
    }

    /**
     * Simple future value projection using the shared FV calculation.
     */
    private function projectValue(float $presentValue, float $rate, int $years, float $monthlyContribution): float
    {
        return $this->projectFutureValue($presentValue, $rate, $years, $monthlyContribution);
    }
}
