<?php

declare(strict_types=1);

namespace App\Agents;

use App\Constants\GateRoutes;
use App\Constants\QuerySchemas;
use App\Constants\TaxDefaults;
use App\Constants\UpdateRecordAllowlist;
use App\Constants\ValidationLimits;
use App\Events\Eval\AgentDecision;
use App\Events\Eval\EngineCalled;
use App\Exceptions\SpouseCollisionException;
use App\Models\AiConversation;
use App\Models\AiMessage;
use App\Models\BusinessInterest;
use App\Models\Chattel;
use App\Models\CriticalIllnessPolicy;
use App\Models\DBPension;
use App\Models\DCPension;
use App\Models\Estate\Asset;
use App\Models\Estate\Gift;
use App\Models\Estate\LastingPowerOfAttorney;
use App\Models\Estate\Liability;
use App\Models\Estate\LpaAttorney;
use App\Models\Estate\Trust;
use App\Models\Estate\Will;
use App\Models\ExpenditureProfile;
use App\Models\FamilyMember;
use App\Models\Goal;
use App\Models\IncomeProtectionPolicy;
use App\Models\Investment\Holding;
use App\Models\Investment\InvestmentAccount;
use App\Models\Invoice;
use App\Models\LifeEvent;
use App\Models\LifeInsurancePolicy;
use App\Models\Mortgage;
use App\Models\Property;
use App\Models\RetirementProfile;
use App\Models\SavingsAccount;
use App\Models\StatePension;
use App\Models\TaxStrategyHouseholdInput;
use App\Models\User;
use App\Services\AI\AdviceFyn;
use App\Services\AI\AdvicePromptCacheInvalidator;
use App\Services\AI\AiToolDefinitions;
use App\Services\AI\AuditChainService;
use App\Services\AI\Memory\Episodic\FetchProvenanceCollector;
use App\Services\AI\Pointers\FetchContext;
use App\Services\AI\Pointers\FetchDispatcher;
use App\Services\AI\Pointers\PointerRegistry;
use App\Services\AI\ToolResultContract;
use App\Services\AI\ToolResultContractException;
use App\Services\Cache\CacheInvalidationService;
use App\Services\Coordination\CashFlowCoordinator;
use App\Services\Coordination\ComposedTaxPlanService;
use App\Services\Coordination\ConflictResolver;
use App\Services\Coordination\CrossModuleStrategyService;
use App\Services\Coordination\HolisticPlanner;
use App\Services\Coordination\PriorityRanker;
use App\Services\Eval\EvalBypassGate;
use App\Services\NetWorth\NetWorthService;
use App\Services\Onboarding\CaptureAccuracyGate;
use App\Services\Onboarding\HouseholdProvisioner;
use App\Services\Onboarding\SpouseLinkingService;
use App\Services\Payment\SubscriptionStatusService;
use App\Services\PrerequisiteGateService;
use App\Services\Retirement\AnnualAllowanceChecker;
use App\Services\Stores\Exceptions\StoreValidationException;
use App\Services\Stores\Exceptions\TierLimitExceededException;
use App\Services\Stores\GoalStore;
use App\Services\Stores\IngestSource;
use App\Services\Stores\InvestmentAccountStore;
use App\Services\Stores\LiabilityStore;
use App\Services\Stores\LifeEventStore;
use App\Services\Stores\MortgageStore;
use App\Services\Stores\Normalisers\InvestmentAccountNormaliser;
use App\Services\Stores\Normalisers\MortgageNormaliser;
use App\Services\Stores\Normalisers\PensionNormaliser;
use App\Services\Stores\Normalisers\PropertyNormaliser;
use App\Services\Stores\Normalisers\SavingsAccountNormaliser;
use App\Services\Stores\PensionStore;
use App\Services\Stores\PropertyStore;
use App\Services\Stores\SavingsStore;
use App\Services\Stores\TierGate;
use App\Services\Tax\IncomeDefinitionsService;
use App\Services\Tax\TaxStrategyMath;
use App\Services\TaxConfigService;
use App\Services\Tiers\TeaserGate;
use App\Services\WhatIf\WhatIfScenarioService;
use App\Traits\HasAiChat;
use App\Traits\HasAiGuardrails;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * CoordinatingAgent
 *
 * Orchestrates cross-module analysis by coordinating all module agents.
 * Resolves conflicts, ranks recommendations, and generates holistic financial plans.
 * Also serves as the single entry point for AI chat (via HasAiChat trait).
 */
class CoordinatingAgent extends BaseAgent
{
    use HasAiChat;
    use HasAiGuardrails;

    /**
     * Canonical savings account_type whitelist. Single source of truth for
     * both the create_savings_account coercion guard and its Rule::in
     * validation in handleCreateSavingsAccount() — keep them from drifting.
     *
     * @var list<string>
     */
    private const SAVINGS_ACCOUNT_TYPES = [
        'easy_access', 'notice', 'fixed', 'fixed_term',
        'regular_saver', 'cash_isa', 'junior_isa', 'current_account',
    ];

    public function __construct(
        private readonly ConflictResolver $conflictResolver,
        private readonly PriorityRanker $priorityRanker,
        private readonly HolisticPlanner $holisticPlanner,
        private readonly CashFlowCoordinator $cashFlowCoordinator,
        private readonly CrossModuleStrategyService $crossModuleStrategyService,
        private readonly ProtectionAgent $protectionAgent,
        private readonly InvestmentAgent $investmentAgent,
        private readonly SavingsAgent $savingsAgent,
        private readonly RetirementAgent $retirementAgent,
        private readonly EstateAgent $estateAgent,
        private readonly GoalsAgent $goalsAgent,
        private readonly TaxOptimisationAgent $taxOptimisationAgent,
        private readonly TaxConfigService $taxConfig,
        private readonly AiToolDefinitions $toolDefinitions,
        private readonly NetWorthService $netWorthService,
        private readonly PrerequisiteGateService $prerequisiteGate,
        private readonly ComposedTaxPlanService $composedTaxPlans,
        private readonly TeaserGate $teaserGate,
        private readonly CaptureAccuracyGate $captureAccuracyGate,
        private readonly HouseholdProvisioner $householdProvisioner,
        private readonly SubscriptionStatusService $subscriptionStatusService,
    ) {}

    /**
     * After any Fyn write (the ~10 capture handlers below all call this), clear
     * the full set of the user's caches — not just this agent's. The mobile /m
     * surface is served entirely from CacheInvalidationService-managed keys
     * (mobile_dashboard_*, mobile_module_*, mobile_level_actions_*), and the Fyn
     * capture path is its ONLY writer, so a Fyn write must invalidate them or the
     * /m dashboard + drill-downs stay stale for up to 24h. BaseAgent's version
     * only clears v1_{agent}_* keys.
     */
    public function invalidateUserCache(int $userId, array $additionalKeys = []): void
    {
        parent::invalidateUserCache($userId, $additionalKeys);
        app(CacheInvalidationService::class)->invalidateForUser($userId);
    }

    /**
     * Analyze user data and generate insights (BaseAgent requirement)
     */
    public function analyze(int $userId): array
    {
        return $this->orchestrateAnalysis($userId);
    }

    /**
     * Build the analysis dict the prompt builder needs, sized to the
     * query's classification.
     *
     * - `holistic` (HOLISTIC_HEALTH only): full orchestrate. Returns
     *   the same shape as orchestrateAnalysis() — every cross-module
     *   field populated. The eval trace fires `EngineCalled:orchestrate_analysis`.
     * - `module` (every module-scoped advice query): runs only the
     *   relevant module agents' `analyze()`. Returns a thin shape with
     *   `module_analysis` populated for the relevant modules and the
     *   cross-module fields (available_surplus, conflicts, etc.) absent.
     *   The eval trace does NOT fire orchestrate_analysis — we never
     *   called it. Fixes the "wasted holistic compute on every chat
     *   send" issue surfaced by the 9 module-scoped scenarios'
     *   `must_not_contain: orchestrate_analysis` assertion.
     * - `factual` (INCOME / GENERAL / NAVIGATION / etc.): no module
     *   analysis at all. Returns an empty thin shape. The prompt
     *   builder still renders net worth, goals, life events from the
     *   user record directly.
     */
    public function analyzeRelevantModules(
        int $userId,
        ?array $classification,
        ?array $kycResult = null,
    ): array {
        $primary = is_array($classification) ? ($classification['primary'] ?? null) : null;
        $level = AdviceFyn::engineCallLevelFor(is_string($primary) ? $primary : null);

        if ($level === 'holistic') {
            return $this->orchestrateAnalysis($userId);
        }

        if ($level === 'factual') {
            return ['module_analysis' => []];
        }

        // Module path. Map classification → modules and fan out to the
        // matching agents. MODULE_MAP exposes module names that don't
        // resolve to a dispatchable agent (`tax`, `property`, `income`);
        // those are silently skipped — the prompt builder reads goals,
        // property and income from the user record directly.
        $modules = QuerySchemas::getModulesForClassification(is_array($classification) ? $classification : []);
        $moduleAnalysis = [];
        $analysisUser = null;
        foreach ($modules as $module) {
            $analysis = match ($module) {
                'protection' => $this->protectionAgent->analyze($userId),
                'savings' => $this->savingsAgent->analyze($userId),
                'investment' => $this->investmentAgent->analyze($userId),
                'retirement' => $this->retirementAgent->analyze($userId),
                'estate' => $this->estateAgent->analyze($userId),
                'goals' => $this->goalsAgent->analyze($userId),
                default => null,
            };
            if ($analysis !== null) {
                if ($this->isQuestionScopedModule($module, $classification, $kycResult)
                    && $this->requiresQuestionScopedFallback($analysis)) {
                    $analysis = $this->questionScopedModuleAnalysis(
                        $module,
                        $analysisUser ??= User::findOrFail($userId),
                        (string) $primary,
                    );
                }
                $moduleAnalysis[$module] = $analysis;
            }
        }

        return ['module_analysis' => $moduleAnalysis];
    }

    /**
     * Chat with an override system prompt and tool allowlist.
     *
     * Used by OnboardingChatDirector during asset_capture delegation
     * (allowedTools filters the existing tool set) and grouped_extract
     * delegation (toolsListOverride replaces the tool set entirely with
     * the onboarding extraction tools).
     *
     * Overrides are applied for the duration of this call only — a
     * try/finally block guarantees they are cleared even if the delegated
     * chat() generator throws.
     *
     * @param  list<string>|null  $allowedTools
     * @param  list<array<string, mixed>>|null  $toolsListOverride
     */
    public function chatWithPromptOverride(
        User $user,
        AiConversation $conversation,
        string $message,
        ?string $currentRoute,
        ?string $systemPromptOverride,
        ?array $allowedTools,
        bool $persistUserMessage = true,
        ?array $toolsListOverride = null,
        ?string $personaOverride = null,
        ?string $providerOverride = null,
    ): \Generator {
        $this->setChatOverrides(
            systemPrompt: $systemPromptOverride,
            allowedTools: $allowedTools,
            skipUserMessagePersistence: ! $persistUserMessage,
            toolsListOverride: $toolsListOverride,
            personaOverride: $personaOverride,
            providerOverride: $providerOverride,
        );

        try {
            foreach ($this->chat($user, $conversation, $message, $currentRoute) as $event) {
                yield $event;
            }
        } finally {
            $this->clearChatOverrides();
        }
    }

    /**
     * Generate personalized recommendations (BaseAgent requirement)
     */
    public function generateRecommendations(array $analysisData): array
    {
        $userContext = $this->getUserContext($analysisData['user_id'] ?? 0);

        return $this->priorityRanker->rankRecommendations(
            $this->extractRecommendations($analysisData),
            $userContext
        );
    }

    /**
     * Build what-if scenarios (BaseAgent requirement)
     */
    public function buildScenarios(int $userId, array $parameters): array
    {
        // For coordinating agent, scenarios would involve changing multiple module inputs
        // This is a placeholder for future implementation
        return [
            'message' => 'Cross-module scenarios not yet implemented',
            'scenarios' => [],
        ];
    }

    /**
     * Orchestrate comprehensive analysis across all modules
     *
     * @param  array|null  $moduleAgents  Optional array of instantiated module agents
     * @return array Coordinated analysis results
     */
    public function orchestrateAnalysis(int $userId, ?array $moduleAgents = null): array
    {
        $orchestrateStart = microtime(true);

        // Eval trace — emit at ENTRY so the orchestrate span is bookended
        // (the existing emit below fires at exit). Doc A §5.3 spec parity.
        event(new EngineCalled(
            engine: 'orchestrate_analysis',
            params: ['user_id' => $userId, 'phase' => 'entry'],
            resultSummary: ['result_path' => 'pending'],
            durationMs: 0,
            atMicrotime: $orchestrateStart,
        ));

        // Collect analysis from all modules
        $allAnalysis = $this->collectModuleAnalysis($userId, $moduleAgents);

        // Calculate available surplus
        $availableSurplus = $this->cashFlowCoordinator->calculateAvailableSurplus($userId);
        $allAnalysis['available_surplus'] = $availableSurplus;

        // Extract recommendations from all modules
        $allRecommendations = $this->extractRecommendations($allAnalysis);

        // Identify conflicts
        $conflicts = $this->conflictResolver->identifyConflicts($allRecommendations);

        // Resolve conflicts
        $resolvedRecommendations = $this->resolveConflicts($allRecommendations, $conflicts);

        // Rank recommendations
        $userContext = $this->getUserContext($userId);
        $rankedRecommendations = $this->rankRecommendations($resolvedRecommendations, $userContext);

        // Optimize cashflow allocation
        $demands = $this->extractDemands($rankedRecommendations);
        $cashFlowAllocation = $this->cashFlowCoordinator->optimizeContributionAllocation($availableSurplus, $demands);
        $shortfallAnalysis = $this->cashFlowCoordinator->identifyCashFlowShortfalls($cashFlowAllocation);

        // Generate cross-module strategies
        $crossModuleStrategies = [];
        $user = User::find($userId);
        if ($user) {
            $crossModuleStrategies = $this->crossModuleStrategyService->generateCrossModuleStrategies($allAnalysis, $user);
        }

        $result = [
            'user_id' => $userId,
            'analysis_date' => now()->toIso8601String(),
            'module_analysis' => $allAnalysis,
            'available_surplus' => $availableSurplus,
            'conflicts' => $conflicts,
            'ranked_recommendations' => $rankedRecommendations,
            'cashflow_allocation' => $cashFlowAllocation,
            'shortfall_analysis' => $shortfallAnalysis,
            'cross_module_strategies' => $crossModuleStrategies,
            'summary' => [
                'total_recommendations' => count($rankedRecommendations),
                'conflicts_identified' => count($conflicts),
                'total_monthly_demand' => $cashFlowAllocation['total_demand'] ?? 0,
                'cashflow_surplus' => $availableSurplus,
                'has_shortfall' => $shortfallAnalysis['has_shortfall'] ?? false,
                'cross_module_strategies_count' => count($crossModuleStrategies),
            ],
        ];

        event(new EngineCalled(
            engine: 'orchestrate_analysis',
            params: ['user_id' => $userId],
            resultSummary: [
                'keys_returned' => array_keys($result),
                'result_path' => 'happy',
                'modules_with_analysis' => array_keys($allAnalysis),
            ],
            durationMs: (int) round((microtime(true) - $orchestrateStart) * 1000),
            atMicrotime: microtime(true),
        ));

        return $result;
    }

    /**
     * Generate holistic financial plan
     *
     * @param  array|null  $moduleAgents  Optional array of instantiated module agents
     * @return array Complete holistic plan
     */
    public function generateHolisticPlan(int $userId, ?array $moduleAgents = null): array
    {
        // Get orchestrated analysis
        $analysis = $this->orchestrateAnalysis($userId, $moduleAgents);

        // Generate holistic plan
        $plan = $this->holisticPlanner->createHolisticPlan($userId, $analysis['module_analysis']);

        // Add ranked recommendations to plan
        $actionPlan = $this->priorityRanker->createActionPlan($analysis['ranked_recommendations']);

        return array_merge($plan, [
            'ranked_recommendations' => $analysis['ranked_recommendations'],
            'action_plan' => $actionPlan['action_plan'],
            'action_plan_summary' => $actionPlan['summary'],
            'cashflow_allocation' => $analysis['cashflow_allocation'],
            'shortfall_analysis' => $analysis['shortfall_analysis'],
            'conflicts' => $analysis['conflicts'],
        ]);
    }

    /**
     * Resolve conflicts between recommendations
     *
     * @return array Resolved recommendations
     */
    public function resolveConflicts(array $allRecommendations, array $conflicts): array
    {
        $resolved = $allRecommendations;

        foreach ($conflicts as $conflict) {
            switch ($conflict['type']) {
                case 'protection_vs_savings_conflict':
                    $resolution = $this->conflictResolver->resolveProtectionVsSavings($allRecommendations);
                    $resolved['conflict_resolutions'][] = $resolution;
                    break;

                case 'cashflow_conflict':
                    $resolution = $this->conflictResolver->resolveContributionConflicts(
                        $allRecommendations['available_surplus'] ?? 0,
                        $conflict['demands']
                    );
                    $resolved['conflict_resolutions'][] = [
                        'type' => 'cashflow',
                        'resolution' => $resolution,
                    ];
                    break;

                case 'isa_allowance_conflict':
                    // Get ISA allowance from tax configuration
                    $isaConfig = $this->taxConfig->getISAAllowances();
                    // Fallback to 2025/26 UK ISA allowance if config unavailable
                    $isaAllowance = $isaConfig['annual_allowance'] ?? 20000;
                    $resolution = $this->conflictResolver->resolveISAAllocation($isaAllowance, $conflict['demands']);
                    $resolved['conflict_resolutions'][] = [
                        'type' => 'isa_allowance',
                        'resolution' => $resolution,
                    ];
                    break;
            }
        }

        return $resolved;
    }

    /**
     * Rank recommendations by priority
     *
     * @return array Ranked recommendations
     */
    public function rankRecommendations(array $recommendations, array $userContext): array
    {
        return $this->priorityRanker->rankRecommendations($recommendations, $userContext);
    }

    /**
     * Collect analysis from all module agents
     */
    private function collectModuleAnalysis(int $userId, ?array $moduleAgents): array
    {
        $analysis = [];

        $analysis['protection'] = $this->safeModuleAnalysis('Protection', function () use ($userId) {
            $protectionResult = $this->protectionAgent->analyze($userId);

            return $this->mapProtectionAnalysis($protectionResult);
        }, fn () => $this->getDefaultModuleAnalysis(['adequacy_score' => 0, 'coverage_gap' => 0]));

        $analysis['savings'] = $this->safeModuleAnalysis('Savings', function () use ($userId) {
            $savingsResult = $this->savingsAgent->analyze($userId);
            $savingsRecs = [];

            try {
                $savingsRecs = $this->savingsAgent->generateRecommendations($savingsResult);
            } catch (\Exception $e) {
                // Recommendations generation is non-critical
            }

            return $this->mapSavingsAnalysis($savingsResult, $savingsRecs);
        }, fn () => $this->getDefaultModuleAnalysis(['total_savings' => 0, 'emergency_fund_months' => 0]));

        $analysis['investment'] = $this->safeModuleAnalysis('Investment', function () use ($userId) {
            $investmentResult = $this->investmentAgent->analyze($userId);
            $investmentRecs = [];

            if (($investmentResult['portfolio_summary']['accounts_count'] ?? 0) > 0) {
                try {
                    $recsResult = $this->investmentAgent->generateRecommendations($investmentResult);
                    $investmentRecs = $recsResult['recommendations'] ?? [];
                } catch (\Exception $e) {
                    // Recommendations generation is non-critical
                }
            }

            return $this->mapInvestmentAnalysis($investmentResult, $investmentRecs);
        }, fn () => $this->getDefaultModuleAnalysis([
            'total_portfolio_value' => 0, 'diversification_score' => 0,
            'portfolio_health_score' => 70, 'annual_return_percent' => 0, 'risk_warnings' => [],
        ]));

        $analysis['retirement'] = $this->safeModuleAnalysis('Retirement', function () use ($userId) {
            $retirementResult = $this->retirementAgent->analyze($userId);
            $retirementData = $retirementResult['data'] ?? $retirementResult;
            $retirementRecs = [];

            if ($retirementResult['success'] ?? false) {
                try {
                    $recsResult = $this->retirementAgent->generateRecommendations($retirementData);
                    $retirementRecs = $recsResult['recommendations'] ?? [];
                } catch (\Exception $e) {
                    // Recommendations generation is non-critical
                }
            }

            return $this->mapRetirementAnalysis($retirementResult, $retirementRecs);
        }, fn () => $this->getDefaultModuleAnalysis([
            'total_pension_value' => 0, 'projected_annual_income' => 0,
            'target_income' => 0, 'income_gap' => 0,
        ]));

        $analysis['estate'] = $this->safeModuleAnalysis('Estate', function () use ($userId) {
            $estateResult = $this->estateAgent->analyze($userId);
            $estateData = $estateResult['data'] ?? [];
            $estateRecs = [];

            if ($estateResult['success'] ?? false) {
                $recsResult = $this->estateAgent->generateRecommendations($estateResult);
                $estateRecs = $recsResult['data']['recommendations'] ?? [];
            }

            return $this->mapEstateAnalysis($estateData, $estateRecs, $userId);
        }, fn () => $this->getDefaultEstateAnalysis($userId));

        $analysis['goals'] = $this->safeModuleAnalysis('Goals', function () use ($userId) {
            $goalsResult = $this->goalsAgent->analyze($userId);
            $goalsRecs = [];

            if ($goalsResult['has_goals'] ?? false) {
                $goalsRecsResult = $this->goalsAgent->generateRecommendations($goalsResult);
                $goalsRecs = $goalsRecsResult['recommendations'] ?? [];
            }

            return array_merge($goalsResult, ['recommendations' => $goalsRecs]);
        }, fn () => ['has_goals' => false, 'recommendations' => [], 'error' => 'Analysis failed']);

        $analysis['tax_optimisation'] = $this->safeModuleAnalysis('TaxOptimisation', function () use ($userId) {
            $taxResult = $this->taxOptimisationAgent->analyze($userId);
            $taxData = $taxResult['data'] ?? $taxResult;
            $taxRecs = [];

            if ($taxResult['success'] ?? false) {
                $recsResult = $this->taxOptimisationAgent->generateRecommendations($taxResult);
                $taxRecs = $recsResult['recommendations'] ?? [];
            }

            return [
                'strategies' => $taxData['strategies'] ?? [],
                'total_estimated_saving' => $taxData['total_estimated_saving'] ?? 0,
                'allowance_usage' => $taxData['allowance_usage'] ?? [],
                'recommendations' => $taxRecs,
            ];
        }, fn () => [
            'strategies' => [],
            'total_estimated_saving' => 0,
            'allowance_usage' => [],
            'recommendations' => [],
            'error' => 'Analysis failed',
        ]);

        // User context
        $user = User::find($userId);
        $analysis['user'] = [
            'age' => $user && $user->date_of_birth ? $user->date_of_birth->age : 40,
        ];

        return $analysis;
    }

    /**
     * Safely run a module analysis with error handling.
     */
    private function safeModuleAnalysis(string $module, callable $analyzer, callable $defaultProvider): array
    {
        try {
            return $analyzer();
        } catch (\Exception $e) {
            Log::error("{$module} analysis failed: ".$e->getMessage());

            return $defaultProvider();
        }
    }

    /**
     * Build a default analysis result for a failed module.
     */
    private function getDefaultModuleAnalysis(array $fields): array
    {
        return array_merge($fields, [
            'recommendations' => [],
            'full_analysis' => [],
            'error' => 'Analysis failed',
        ]);
    }

    /**
     * Extract recommendations from module analysis
     */
    private function extractRecommendations(array $allAnalysis): array
    {
        $recommendations = [
            'module_scores' => [],
        ];

        foreach ($allAnalysis as $module => $analysis) {
            if ($module === 'available_surplus' || $module === 'user') {
                continue;
            }

            // Store module scores for conflict resolution
            $recommendations['module_scores'][$module] = $analysis;

            // Extract module recommendations
            if (isset($analysis['recommendations']) && is_array($analysis['recommendations'])) {
                $recommendations[$module] = $analysis['recommendations'];
            }
        }

        $recommendations['available_surplus'] = $allAnalysis['available_surplus'] ?? 0;

        return $recommendations;
    }

    /**
     * Get user context for priority ranking
     */
    private function getUserContext(int $userId): array
    {
        // In full implementation, fetch from user profile/preferences table
        return [
            'module_priorities' => [
                'protection' => 80,
                'savings' => 75,
                'retirement' => 70,
                'tax_optimisation' => 65,
                'investment' => 60,
                'goals' => 55,
                'estate' => 50,
            ],
        ];
    }

    /**
     * Extract contribution demands from recommendations
     */
    private function extractDemands(array $recommendations): array
    {
        $demands = [];

        foreach ($recommendations as $rec) {
            if (! isset($rec['module'])) {
                continue;
            }

            $module = $rec['module'];
            $category = $this->mapModuleToCategory($module);

            // Extract monetary demand
            $amount = $rec['recommended_monthly_contribution']
                ?? $rec['recommended_monthly_premium']
                ?? 0;

            if ($amount > 0) {
                if (! isset($demands[$category])) {
                    $demands[$category] = [
                        'amount' => 0,
                        'urgency' => $rec['urgency_score'] ?? 50,
                    ];
                }
                $demands[$category]['amount'] += $amount;
                $demands[$category]['urgency'] = max($demands[$category]['urgency'], $rec['urgency_score'] ?? 50);
            }
        }

        return $demands;
    }

    /**
     * Map module name to cashflow category
     */
    private function mapModuleToCategory(string $module): string
    {
        return match ($module) {
            'protection' => 'protection',
            'savings' => 'emergency_fund',
            'investment' => 'investment',
            'retirement' => 'pension',
            'estate' => 'estate',
            'goals' => 'goals',
            'tax_optimisation' => 'tax_optimisation',
            default => $module,
        };
    }

    /**
     * Map ProtectionAgent analysis response to the flat format expected by HolisticPlanner.
     */
    private function mapProtectionAnalysis(array $protectionResult): array
    {
        // ProtectionAgent uses $this->response() wrapper
        $data = $protectionResult['data'] ?? $protectionResult;
        $adequacy = $data['adequacy_score'] ?? [];
        $gaps = $data['gaps'] ?? [];

        return [
            'adequacy_score' => $adequacy['overall_score'] ?? $adequacy['score'] ?? 0,
            'coverage_gap' => $gaps['total_gap'] ?? 0,
            'recommendations' => $data['recommendations'] ?? [],
            'full_analysis' => $data,
        ];
    }

    /**
     * Map SavingsAgent analysis response to the flat format expected by HolisticPlanner.
     *
     * Handles both legacy inline recommendations and DB-driven recommendations
     * from SavingsActionDefinitionService. The new engine outputs recommendations
     * with keys: title, description, category, priority, definition_key, estimated_impact.
     * PriorityRanker applies default scoring for savings recommendations that lack
     * module-specific fields (e.g. emergency_fund_months on individual recs).
     */
    private function mapSavingsAnalysis(array $savingsData, array $recommendations = []): array
    {
        return [
            'total_savings' => $savingsData['summary']['total_savings'] ?? 0,
            'emergency_fund_months' => $savingsData['emergency_fund']['runway_months'] ?? 0,
            'recommendations' => $recommendations,
            'full_analysis' => $savingsData,
        ];
    }

    /**
     * Map InvestmentAgent analysis response to the flat format expected by HolisticPlanner.
     */
    private function mapInvestmentAnalysis(array $investmentData, array $recommendations = []): array
    {
        $portfolioSummary = $investmentData['portfolio_summary'] ?? [];
        $returns = $investmentData['returns'] ?? [];

        return [
            'total_portfolio_value' => $portfolioSummary['total_value'] ?? 0,
            'diversification_score' => $investmentData['diversification_score'] ?? 0,
            'portfolio_health_score' => $investmentData['diversification_score'] ?? 70,
            'annual_return_percent' => $returns['total_return_percent'] ?? $returns['annualized_return'] ?? 0,
            'risk_warnings' => $investmentData['risk_metrics']['warnings'] ?? [],
            'recommendations' => $recommendations,
            'full_analysis' => $investmentData,
        ];
    }

    /**
     * Map RetirementAgent analysis response to the flat format expected by HolisticPlanner.
     */
    private function mapRetirementAnalysis(array $retirementResult, array $recommendations = []): array
    {
        // RetirementAgent uses $this->response() wrapper
        $data = $retirementResult['data'] ?? $retirementResult;
        $summary = $data['summary'] ?? [];

        return [
            'total_pension_value' => $summary['current_dc_value'] ?? 0,
            'projected_annual_income' => $summary['projected_retirement_income'] ?? 0,
            'target_income' => $summary['target_retirement_income'] ?? 0,
            'income_gap' => $summary['income_gap'] ?? 0,
            'recommendations' => $recommendations,
            'full_analysis' => $data,
        ];
    }

    /**
     * Map EstateAgent analysis response to the flat format expected by HolisticPlanner.
     */
    private function mapEstateAnalysis(array $estateData, array $recommendations, int $userId): array
    {
        $summary = $estateData['summary'] ?? [];
        $ihtCalc = $estateData['iht_calculation'] ?? [];

        // Get real cashflow data from CashFlowCoordinator
        $cashFlowData = $this->cashFlowCoordinator->getMonthlyFinancials($userId);

        return [
            'net_worth' => $summary['net_estate'] ?? 0,
            'gross_estate' => $summary['gross_estate'] ?? 0,
            'iht_liability' => $summary['iht_liability'] ?? 0,
            'effective_tax_rate' => $summary['effective_tax_rate'] ?? 0,
            'total_liabilities' => $summary['total_liabilities'] ?? 0,
            'property_value' => $ihtCalc['user_gross_assets'] ?? $summary['gross_estate'] ?? 0,
            'monthly_income' => $cashFlowData['monthly_income'],
            'monthly_expenses' => $cashFlowData['monthly_expenses'],
            'monthly_surplus' => $cashFlowData['monthly_surplus'],
            'nrb_available' => $ihtCalc['nrb_available'] ?? 0,
            'rnrb_available' => $ihtCalc['rnrb_available'] ?? 0,
            'has_spouse' => $estateData['profile']['has_spouse'] ?? false,
            'recommendations' => $recommendations,
            'full_analysis' => $estateData,
        ];
    }

    /**
     * Get default estate analysis when EstateAgent fails.
     */
    private function getDefaultEstateAnalysis(int $userId): array
    {
        $cashFlowData = $this->cashFlowCoordinator->getMonthlyFinancials($userId);

        return [
            'net_worth' => 0,
            'gross_estate' => 0,
            'iht_liability' => 0,
            'effective_tax_rate' => 0,
            'total_liabilities' => 0,
            'property_value' => 0,
            'monthly_income' => $cashFlowData['monthly_income'],
            'monthly_expenses' => $cashFlowData['monthly_expenses'],
            'monthly_surplus' => $cashFlowData['monthly_surplus'],
            'nrb_available' => 0,
            'rnrb_available' => 0,
            'has_spouse' => false,
            'recommendations' => [],
            'full_analysis' => [],
            'error' => 'Analysis failed',
        ];
    }

    // ═══════════════════════════════════════════════════════════════════
    // Tool Execution (migrated from AiToolExecutor)
    // ═══════════════════════════════════════════════════════════════════

    /**
     * Execute a tool call with prerequisite gate enforcement.
     */
    public function executeTool(
        string $toolName,
        array $input,
        User $user,
        ?int $conversationId = null,
        ?array $classification = null,
        ?array $kycResult = null,
    ): array {
        // xAI strict mode may return the string "null" instead of actual null for nullable fields
        // Also decode HTML entities (xAI sometimes encodes & as &amp; in tool arguments)
        $input = array_map(function ($v) {
            if ($v === 'null') {
                return null;
            }
            if (is_string($v)) {
                return html_entity_decode($v, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            }

            return $v;
        }, $input);

        // Strict tool schemas can serialize an absent nullable owner as the
        // numeric sentinel 0. User IDs are positive, so treat only that
        // sentinel as null before the ownership security gate. Real non-zero
        // IDs remain subject to reciprocal-spouse authorization below.
        if (($input['joint_owner_id'] ?? null) === 0 || ($input['joint_owner_id'] ?? null) === '0') {
            $input['joint_owner_id'] = null;
        }
        if (($input['trust_id'] ?? null) === 0 || ($input['trust_id'] ?? null) === '0') {
            $input['trust_id'] = null;
        }
        if ($toolName === 'capture_dependants' && $conversationId !== null) {
            $input = $this->captureAccuracyGate->restoreDependantNames(
                $input,
                $this->latestUserMessageText($conversationId) ?? '',
            );
        }

        // Bypass when the active token EXPLICITLY lists `bypass-preview-mode`
        // (eval flow) AND the X-Eval-Run-Id header is set
        // (April30Updates F-12 — defence-in-depth on the eval bypass).
        $hasEvalBypass = EvalBypassGate::isActive($user);
        $isPreviewUser = (bool) $user->is_preview_user && ! $hasEvalBypass;

        // S0.12 — append a chain row at dispatch. Replaces the [AI-AUDIT] file
        // log that used to fire after the result returned (which dropped any
        // tool that threw before the log line was reached).
        $this->appendAuditEvent([
            'user_id' => $user->id,
            'conversation_id' => $conversationId,
            'tool_name' => $toolName,
            'operation' => self::operationFor($toolName),
            'status' => 'dispatched',
            'input_summary' => self::summariseInput($toolName, $input, $isPreviewUser),
        ]);

        // Eval trace — capture every tool dispatch with name + args.
        event(new AgentDecision(
            agent: 'CoordinatingAgent',
            decisionPoint: 'tool_dispatch',
            outcome: $toolName,
            context: [
                'args' => $input,
                'user_id' => $user->id,
                'conversation_id' => $conversationId,
                'is_preview_user' => $isPreviewUser,
            ],
            atMicrotime: microtime(true),
        ));

        $verifyEditScopeError = $this->verifyEditScopeError($toolName, $input);
        if ($verifyEditScopeError !== null) {
            $this->appendAuditCompletion($user, $conversationId, $toolName, $input, $verifyEditScopeError);

            return $verifyEditScopeError;
        }

        // The primary-question matrix is the effective advice gate. Do not
        // reapply broader module readiness after question-specific KYC passes.
        $questionScopedAnalysis = $this->isQuestionScopedModuleTool(
            $toolName,
            $input,
            $classification,
            $kycResult,
        );
        $questionScopedHolistic = $this->isQuestionScopedHolisticTool(
            $toolName,
            $classification,
            $kycResult,
        );
        $nonEntitledHolisticPlan = $toolName === 'generate_financial_plan'
            && ! $this->teaserGate->isFull($user, 'holistic_plan');

        // Entitlement precedes readiness for the holistic tool, matching the
        // REST middleware. The handler still uses the audited completion path.
        $gate = ($questionScopedAnalysis || $questionScopedHolistic || $nonEntitledHolisticPlan)
            ? ['can_proceed' => true]
            : $this->prerequisiteGate->canExecuteTool($toolName, $input, $user);
        if (! $gate['can_proceed']) {
            $firstAction = $gate['required_actions'][0] ?? null;
            $pageLabel = null;
            if ($firstAction !== null) {
                $destination = GateRoutes::destinationForRoute(
                    $firstAction['route'] ?? '',
                    GateRoutes::DASHBOARD,
                );
                $pageLabel = GateRoutes::resolve($destination)['label'];
            }

            return [
                'blocked' => true,
                'reason' => $gate['guidance'],
                'missing_data' => $gate['missing'],
                'suggested_action' => $pageLabel === null ? null : ['label' => $pageLabel],
                'instruction' => 'Explain to the user exactly what data is missing and why it is needed. '
                    .'List each missing item clearly. '
                    .($pageLabel ? "Then signpost the {$pageLabel} page by its label in plain text. Do not output an internal route or call a navigation or write tool on the advice surface." : ''),
            ];
        }

        $jointOwnerError = $this->captureJointOwnerError($toolName, $input, $user);
        if ($jointOwnerError !== null) {
            $this->appendAuditCompletion($user, $conversationId, $toolName, $input, $jointOwnerError);

            return $jointOwnerError;
        }

        $trustError = $this->captureTrustError($toolName, $input, $user);
        if ($trustError !== null) {
            $this->appendAuditCompletion($user, $conversationId, $toolName, $input, $trustError);

            return $trustError;
        }

        $latestUserText = $this->latestUserMessageText($conversationId);
        $recentUserEvidence = $this->recentUserMessageEvidence($conversationId) ?? $latestUserText;
        [$accuracyEvidence, $accuracyCacheKey] = $this->captureAccuracyEvidence(
            $conversationId,
            $toolName,
            $recentUserEvidence ?? '',
            $input,
        );
        $accuracy = $this->captureAccuracyGate->inspect($toolName, $input, $accuracyEvidence);
        if (! $accuracy['allowed']) {
            if ($accuracyCacheKey !== null) {
                Cache::put($accuracyCacheKey, $accuracyEvidence, now()->addMinutes(15));
                $this->rememberCaptureAccuracyCacheKey($conversationId, $accuracyCacheKey);
            }
            $result = [
                'error' => true,
                'error_type' => 'clarification_required',
                'clarification_required' => true,
                'reason' => $accuracy['reason'],
                'missing' => $accuracy['missing'],
                'message' => $accuracy['reason'],
            ];
            $this->appendAuditCompletion($user, $conversationId, $toolName, $input, $result);

            return $result;
        }

        if ($accuracyCacheKey !== null) {
            Cache::forget($accuracyCacheKey);
            $this->forgetCaptureAccuracyCacheKey($conversationId, $accuracyCacheKey);
        }

        // CoALA pointer fetch tools — `fetch_{pointer_id}` routes through the
        // FetchDispatcher → handler path, sharing provenance + degrade-on-failure
        // with the pre-fetch path. Matched against toolPointers() so dashed
        // pointer_ids (isa-annual-allowance → fetch_isa_annual_allowance) resolve
        // correctly. No assistant AiMessage is in scope here, so provenance is
        // recorded on the next prompt rebuild rather than this row (pass null).
        if (str_starts_with($toolName, 'fetch_')) {
            $pointerResult = $this->handlePointerFetch($toolName, $user);
            if ($pointerResult !== null) {
                $this->appendAuditCompletion($user, $conversationId, $toolName, $input, $pointerResult);

                return $pointerResult;
            }
        }

        try {
            $result = match ($toolName) {
                'navigate_to_page' => $this->handleNavigation($input),
                // Handoff tools — stubbed so HasAiChat doesn't error. The
                // synthetic 'handoff' SSE event yielded downstream from this
                // result is consumed by OnboardingChatDirector::handleInlineCapture.
                'delegate_to_capture' => [
                    'action' => 'handoff',
                    'handoff_type' => 'delegate_to_capture',
                    'payload' => $input,
                ],
                'capture_complete' => [
                    'action' => 'handoff',
                    'handoff_type' => 'capture_complete',
                    'payload' => $input,
                ],
                'capture_personal_details' => $this->handleCapturePersonalDetails($input, $user),
                'capture_spouse_details' => $this->handleCaptureSpouseDetails($input, $user),
                'capture_dependants' => $this->handleCaptureDependants($input, $user),
                'capture_work_details' => $this->handleCaptureWorkDetails($input, $user),
                'list_records' => $this->handleListRecords($input, $user),
                'list_goals' => $this->handleListGoals($user),
                'list_life_events' => $this->handleListLifeEvents($user),
                'get_module_analysis' => $this->handleModuleAnalysis($input, $user, $classification, $kycResult),
                'search_conversation_index' => $this->handleSearchConversationIndex($input, $user, $conversationId),
                'create_what_if_scenario' => $this->handleCreateWhatIfScenario($input, $user),
                'get_recommendations' => $this->handleRecommendations($user),
                'get_subscription_status' => $this->handleGetSubscriptionStatus($user),
                'list_invoices' => $this->handleListInvoices($user),
                'get_current_plan' => $this->handleGetCurrentPlan($user),
                'get_tax_information' => $this->handleTaxInformation($input, $user),
                'generate_financial_plan' => $this->handleFinancialPlan($user),
                'create_goal' => $this->handleCreateGoal($input, $user, $isPreviewUser),
                'create_life_event' => $this->handleCreateLifeEvent($input, $user, $isPreviewUser),
                'create_savings_account' => $this->handleCreateSavingsAccount($input, $user, $isPreviewUser),
                'create_investment_account' => $this->handleCreateInvestmentAccount($input, $user, $isPreviewUser),
                'create_holding' => $this->handleCreateHolding($input, $user, $isPreviewUser),
                'create_pension' => $this->handleCreatePension($input, $user, $isPreviewUser),
                'create_property' => $this->handleCreateProperty($input, $user, $isPreviewUser),
                'create_mortgage' => $this->handleCreateMortgage($input, $user, $isPreviewUser),
                'create_protection_policy' => $this->handleCreateProtectionPolicy($input, $user, $isPreviewUser),
                'create_asset' => $this->handleCreateEstateAsset($input, $user, $isPreviewUser),
                'create_liability' => $this->handleCreateEstateLiability($input, $user, $isPreviewUser),
                'create_estate_gift' => $this->handleCreateEstateGift($input, $user, $isPreviewUser),
                'create_will' => $this->handleCreateWill($input, $user, $isPreviewUser),
                'update_will' => $this->handleUpdateWill($input, $user, $isPreviewUser),
                'create_power_of_attorney' => $this->handleCreatePowerOfAttorney($input, $user, $isPreviewUser),
                'update_power_of_attorney' => $this->handleUpdatePowerOfAttorney($input, $user, $isPreviewUser),
                'create_family_member' => $this->handleCreateFamilyMember($input, $user, $isPreviewUser),
                'create_trust' => $this->handleCreateTrust($input, $user, $isPreviewUser),
                'create_business_interest' => $this->handleCreateBusinessInterest($input, $user, $isPreviewUser),
                'create_chattel' => $this->handleCreateChattel($input, $user, $isPreviewUser),
                'set_expenditure' => $this->handleSetExpenditure($input, $user, $isPreviewUser),
                'update_record' => $this->handleUpdateRecord($input, $user, $isPreviewUser),
                'delete_record' => $this->handleDeleteRecord($input, $user, $isPreviewUser),
                'update_profile' => $this->handleUpdateProfile($input, $user, $isPreviewUser),
                // SaveTax campaign — sections 4-6
                'capture_salary_sacrifice' => $this->handleCaptureSalarySacrifice($input, $user, $isPreviewUser),
                'capture_spouse_work_status' => $this->handleCaptureSpouseWorkStatus($input, $user, $isPreviewUser),
                'capture_spouse_household_data' => $this->handleCaptureSpouseHouseholdData($input, $user, $isPreviewUser),
                'capture_spouse_non_working_assets' => $this->handleCaptureSpouseNonWorkingAssets($input, $user, $isPreviewUser),
                'capture_pension_history' => $this->handleCapturePensionHistory($input, $user, $isPreviewUser, $conversationId),
                'capture_charitable_giving' => $this->handleCaptureCharitableGiving($input, $user, $isPreviewUser),
                // PensionCheck campaign — retirement goals + state pension
                'capture_retirement_goals' => $this->handleCaptureRetirementGoals($input, $user, $isPreviewUser, $conversationId),
                'capture_state_pension' => $this->handleCaptureStatePension($input, $user, $isPreviewUser),
                default => ['error' => true, 'error_type' => 'unknown_tool', 'message' => "Unknown tool: {$toolName}"],
            };

            // S0.12 — chain-append the completion row. `persisted` for any
            // result without an `error` key; `failed` otherwise. Replaces the
            // [AI-AUDIT] file log entirely.
            $this->appendAuditCompletion($user, $conversationId, $toolName, $input, $result);

            return $result;
        } catch (ValidationException $e) {
            $this->appendAuditEvent([
                'user_id' => $user->id,
                'conversation_id' => $conversationId,
                'tool_name' => $toolName,
                'operation' => self::operationFor($toolName),
                'status' => 'failed',
                'result_summary' => ['error_type' => 'validation_failed', 'message' => $e->validator->errors()->first()],
            ]);

            return ['error' => true, 'error_type' => 'validation_failed', 'message' => $e->validator->errors()->first()];
        } catch (QueryException $e) {
            Log::error('[CoordinatingAgent] Database error', ['tool' => $toolName, 'user_id' => $user->id, 'error' => $e->getMessage()]);
            $this->appendAuditEvent([
                'user_id' => $user->id,
                'conversation_id' => $conversationId,
                'tool_name' => $toolName,
                'operation' => self::operationFor($toolName),
                'status' => 'failed',
                'result_summary' => ['error_type' => 'database_error', 'message' => $e->getMessage()],
            ]);

            return ['error' => true, 'error_type' => 'database_error', 'message' => 'Unable to save the record. Please try again.'];
        } catch (\Exception $e) {
            Log::error('[CoordinatingAgent] Tool execution failed', ['tool' => $toolName, 'user_id' => $user->id, 'error' => $e->getMessage()]);
            $this->appendAuditEvent([
                'user_id' => $user->id,
                'conversation_id' => $conversationId,
                'tool_name' => $toolName,
                'operation' => self::operationFor($toolName),
                'status' => 'failed',
                'result_summary' => ['error_type' => 'execution_failed', 'message' => $e->getMessage()],
            ]);

            return ['error' => true, 'error_type' => 'execution_failed', 'message' => 'An unexpected error occurred. Please try again.'];
        }
    }

    /**
     * A joint_owner_id grants the linked account visibility of the record, so
     * Fyn may attach only the user's reciprocally linked spouse. A merely
     * existing user ID is not sufficient authorization.
     */
    private function captureJointOwnerError(string $toolName, array $input, User $user): ?array
    {
        if (! in_array($toolName, [
            'create_savings_account',
            'create_investment_account',
            'create_property',
            'create_liability',
        ], true)) {
            return null;
        }

        $ownershipType = $input['ownership_type'] ?? null;
        $hasJointOwner = array_key_exists('joint_owner_id', $input)
            && $input['joint_owner_id'] !== null;
        if (! in_array($ownershipType, ['joint', 'tenants_in_common'], true)) {
            if (! $hasJointOwner) {
                return null;
            }

            return [
                'error' => true,
                'error_type' => 'clarification_required',
                'clarification_required' => true,
                'reason' => 'A joint owner can only be attached to a jointly owned record.',
                'missing' => ['ownership_type'],
                'message' => 'A joint owner can only be attached to a jointly owned record.',
            ];
        }

        $jointOwnerId = isset($input['joint_owner_id']) && is_numeric($input['joint_owner_id'])
            ? (int) $input['joint_owner_id']
            : null;
        $isReciprocalSpouse = $jointOwnerId !== null
            && (int) $user->spouse_id === $jointOwnerId
            && User::query()->whereKey($jointOwnerId)->where('spouse_id', $user->id)->exists();

        if ($isReciprocalSpouse) {
            return null;
        }

        return [
            'error' => true,
            'error_type' => 'clarification_required',
            'clarification_required' => true,
            'reason' => 'I can only attach a joint record after the other owner is securely linked to your account.',
            'missing' => ['joint_owner_id'],
            'message' => 'I can only attach a joint record after the other owner is securely linked to your account.',
        ];
    }

    /**
     * A trust_id makes the record visible in trust reporting. It must therefore
     * resolve inside the authenticated user's own household, never merely exist.
     */
    private function captureTrustError(string $toolName, array $input, User $user): ?array
    {
        if (! in_array($toolName, ['create_savings_account', 'create_property', 'create_liability'], true)) {
            return null;
        }

        $ownershipType = $input['ownership_type'] ?? null;
        $trustId = isset($input['trust_id'])
            && is_numeric($input['trust_id'])
            && (int) $input['trust_id'] > 0
            ? (int) $input['trust_id']
            : null;

        if ($ownershipType !== 'trust' && $trustId === null) {
            return null;
        }

        $authorised = $ownershipType === 'trust'
            && $trustId !== null
            && Trust::query()
                ->whereKey($trustId)
                ->where(function ($query) use ($user): void {
                    $query->where('user_id', $user->id);
                    if ($user->household_id !== null) {
                        $query->orWhere('household_id', $user->household_id);
                    }
                })
                ->exists();

        if ($authorised) {
            return null;
        }

        $recordLabel = match ($toolName) {
            'create_savings_account' => 'savings account',
            'create_liability' => 'liability',
            default => 'property',
        };
        $message = "I can only attach a trust-owned {$recordLabel} after the trust is securely linked to your household.";

        return [
            'error' => true,
            'error_type' => 'clarification_required',
            'clarification_required' => true,
            'reason' => $message,
            'missing' => ['trust_id'],
            'message' => $message,
        ];
    }

    /**
     * Retain only evidence from an unresolved accuracy clarification for this
     * conversation and tool. Successful capture clears it immediately.
     *
     * @return array{0:string,1:?string}
     */
    private function captureAccuracyEvidence(
        ?int $conversationId,
        string $toolName,
        string $currentUserEvidence,
        array $input,
    ): array {
        if ($conversationId === null) {
            return [$currentUserEvidence, null];
        }

        $identity = array_filter([
            'account_name' => $input['account_name'] ?? $input['name'] ?? null,
            'provider' => $input['provider'] ?? $input['institution'] ?? $input['platform'] ?? null,
            'address' => $input['address'] ?? $input['property_address'] ?? $input['address_line_1'] ?? null,
            'property_type' => $input['property_type'] ?? null,
            'lender' => $input['lender'] ?? $input['creditor'] ?? $input['lender_name'] ?? null,
            'liability_name' => $input['liability_name'] ?? null,
        ], static fn (mixed $value): bool => is_scalar($value) && trim((string) $value) !== '');
        if ($identity === []) {
            return [$currentUserEvidence, null];
        }

        $identityHash = hash('sha256', json_encode($identity, JSON_THROW_ON_ERROR));
        $cacheKey = "capture_accuracy_evidence:{$conversationId}:{$toolName}:{$identityHash}";
        $prior = Cache::get($cacheKey, '');
        $parts = is_string($prior) && trim($prior) !== ''
            ? preg_split('/\n+/', trim($prior))
            : [];
        $parts = is_array($parts) ? $parts : [];
        $currentParts = preg_split('/\n+/', trim($currentUserEvidence)) ?: [];
        $parts = array_slice(array_values(array_unique([...$parts, ...$currentParts])), -6);

        return [implode("\n", $parts), $cacheKey];
    }

    public function clearCaptureAccuracyEvidence(int $conversationId): void
    {
        $indexKey = $this->captureAccuracyCacheIndexKey($conversationId);
        $keys = Cache::get($indexKey, []);
        if (is_array($keys)) {
            foreach ($keys as $key) {
                if (is_string($key) && $key !== '') {
                    Cache::forget($key);
                }
            }
        }
        Cache::forget($indexKey);
    }

    private function rememberCaptureAccuracyCacheKey(?int $conversationId, string $cacheKey): void
    {
        if ($conversationId === null) {
            return;
        }

        $indexKey = $this->captureAccuracyCacheIndexKey($conversationId);
        $keys = Cache::get($indexKey, []);
        $keys = is_array($keys) ? $keys : [];
        $keys[] = $cacheKey;
        Cache::put($indexKey, array_values(array_unique($keys)), now()->addMinutes(15));
    }

    private function forgetCaptureAccuracyCacheKey(?int $conversationId, string $cacheKey): void
    {
        if ($conversationId === null) {
            return;
        }

        $indexKey = $this->captureAccuracyCacheIndexKey($conversationId);
        $keys = Cache::get($indexKey, []);
        if (! is_array($keys)) {
            return;
        }

        $remaining = array_values(array_filter(
            $keys,
            static fn (mixed $key): bool => is_string($key) && $key !== $cacheKey,
        ));
        if ($remaining === []) {
            Cache::forget($indexKey);

            return;
        }

        Cache::put($indexKey, $remaining, now()->addMinutes(15));
    }

    private function captureAccuracyCacheIndexKey(int $conversationId): string
    {
        return "capture_accuracy_evidence_keys:{$conversationId}";
    }

    /**
     * S0.12 — Wraps `AuditChainService::append` so failures inside the chain
     * (e.g. the migration not being run yet on a worker that holds an old
     * schema cache) cannot bring the chat down. The chain is forensic, not
     * load-bearing.
     */
    private function appendAuditEvent(array $event): void
    {
        try {
            app(AuditChainService::class)->append($event);
        } catch (\Throwable $e) {
            Log::warning('[CoordinatingAgent] Audit chain append failed', [
                'tool' => $event['tool_name'] ?? null,
                'status' => $event['status'] ?? null,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * S0.12 — Build and append the completion audit row. Status flips between
     * `persisted` (no error key) and `failed` (error returned in-band rather
     * than thrown).
     */
    private function appendAuditCompletion(User $user, ?int $conversationId, string $toolName, array $input, array $result): void
    {
        $hasError = isset($result['error']) && $result['error'] !== false;
        $entityId = $result['entity_id'] ?? $result['id'] ?? $result['data']['id'] ?? ($input['id'] ?? $input['entity_id'] ?? null);
        $entityType = $result['entity_type'] ?? ($input['entity_type'] ?? null);

        $this->appendAuditEvent([
            'user_id' => $user->id,
            'conversation_id' => $conversationId,
            'tool_name' => $toolName,
            'operation' => self::operationFor($toolName),
            'status' => $hasError ? 'failed' : 'persisted',
            'result_summary' => self::summariseResult($result),
            'entity_type' => is_string($entityType) ? $entityType : null,
            'entity_id' => is_numeric($entityId) ? (int) $entityId : null,
        ]);

        // April30Updates F-9 — invalidate the user's prompt caches on any
        // successful write. The next advice turn rebuilds the prompt with
        // the latest existing-records / financial-context state instead
        // of seeing up to 120s of stale data. Read-tool calls and failed
        // writes don't change DB state, so they don't need invalidation.
        if (! $hasError && self::operationFor($toolName) === 'write') {
            AdvicePromptCacheInvalidator::forUser($user->id);
        }
    }

    /**
     * Operation classification for INV-2.10.2 audit rows. Anything that
     * persists state is `write`; handoff stubs are `handoff`; everything else
     * is `read`. The `classify` operation is reserved for QueryClassifier
     * audits which run outside this method (Sprint 1 work).
     */
    private static function operationFor(string $toolName): string
    {
        if (in_array($toolName, ['delegate_to_capture', 'capture_complete'], true)) {
            return 'handoff';
        }

        if (str_starts_with($toolName, 'create_')
            || str_starts_with($toolName, 'update_')
            || str_starts_with($toolName, 'capture_')
            || in_array($toolName, ['delete_record', 'set_expenditure'], true)
        ) {
            return 'write';
        }

        return 'read';
    }

    /**
     * Summarise the tool input for the audit row. Truncates long string
     * values so a runaway prompt-injection attempt or pasted document
     * doesn't bloat the chain.
     */
    private static function summariseInput(string $toolName, array $input, bool $isPreviewUser): array
    {
        $summary = ['preview' => $isPreviewUser];

        foreach ($input as $key => $value) {
            if (is_scalar($value) || $value === null) {
                if (is_string($value) && mb_strlen($value) > 200) {
                    $summary[$key] = mb_substr($value, 0, 200).'…';
                } else {
                    $summary[$key] = $value;
                }
            } elseif (is_array($value)) {
                $summary[$key] = '['.count($value).' items]';
            } else {
                $summary[$key] = '[non-scalar]';
            }
        }

        return $summary;
    }

    /**
     * Summarise the tool result for the audit row. Keeps the canonical
     * outcome flags but drops large nested arrays.
     */
    private static function summariseResult(array $result): array
    {
        $keep = ['success', 'created', 'error', 'error_type', 'message', 'blocked', 'reason', 'action', 'requires_confirmation'];
        $summary = [];
        foreach ($keep as $key) {
            if (array_key_exists($key, $result)) {
                $summary[$key] = $result[$key];
            }
        }

        return $summary;
    }

    // ─── Read-only tool handlers ─────────────────────────────────────

    private function handleNavigation(array $input): array
    {
        return ['action' => 'navigate', 'route_path' => $input['route_path'], 'description' => $input['description'] ?? ''];
    }

    /**
     * CoALA pointer fetch — resolve a `fetch_{pointer_id}` tool call to its
     * Pointer (matched against toolPointers() so dashed ids round-trip) and run
     * it through the FetchDispatcher → handler. Returns the FetchResult->value
     * as the tool result, or null when no matching tool-pointer exists (the
     * caller then falls through to the normal unknown-tool path).
     *
     * @return array<string, mixed>|null
     */
    private function handlePointerFetch(string $toolName, User $user): ?array
    {
        try {
            $registry = app(PointerRegistry::class);
            $pointer = null;
            foreach ($registry->toolPointers() as $candidate) {
                if ('fetch_'.str_replace('-', '_', $candidate->pointerId) === $toolName) {
                    $pointer = $candidate;
                    break;
                }
            }

            if ($pointer === null) {
                return null;
            }

            $result = app(FetchDispatcher::class)->run(
                $pointer,
                new FetchContext($user, ''),
                null,
            );

            if ($result === null) {
                return ['error' => true, 'error_type' => 'fetch_failed', 'message' => 'Unable to retrieve that information right now. Please try again.'];
            }

            return ['success' => true, 'value' => $result->value];
        } catch (\Throwable $e) {
            Log::warning('[CoordinatingAgent] Pointer fetch failed', ['tool' => $toolName, 'user_id' => $user->id, 'error' => $e->getMessage()]);

            return null;
        }
    }

    // ─── Onboarding grouped-extraction handlers ──────────────────────

    /**
     * capture_personal_details — writes date_of_birth + marital_status
     * to users. Validates the DOB and enforces the age bounds used by
     * OnboardingValueInterpreter.
     */
    private function handleCapturePersonalDetails(array $input, User $user): array
    {
        $dob = trim((string) ($input['date_of_birth'] ?? ''));
        $marital = trim((string) ($input['marital_status'] ?? ''));

        Log::info('[CoordinatingAgent] handleCapturePersonalDetails called', [
            'user_id' => $user->id,
            'raw_dob' => $dob,
            'raw_marital' => $marital,
        ]);

        // FR-M10 — accept partial captures. If the LLM provided neither
        // field the tool call captured nothing, so surface the generic
        // retry. Any one field is enough: the state machine will stay on
        // base_personal and the next prompt (via buildPersonalPrompt) will
        // pre-confirm the field we have and ask for the missing one.
        if ($dob === '' && $marital === '') {
            Log::warning('[CoordinatingAgent] handleCapturePersonalDetails rejected: both fields empty', [
                'user_id' => $user->id,
            ]);

            return ['error' => true, 'message' => 'date_of_birth or marital_status is required'];
        }

        if ($dob !== '') {
            // Parse DOB — accept YYYY-MM-DD, DD/MM/YYYY, DD-MM-YYYY and
            // natural-language forms. Slashed DD/MM/YYYY is UK-ambiguous
            // with MDY, so handle it explicitly so "10/04/1985" parses as
            // the 10th of April rather than October 4. Two-digit years
            // ("19/02/82") resolve to the century that gives an age inside
            // the 18–105 bounds — exactly one century can qualify.
            $carbonDob = null;
            try {
                if (preg_match('#^(\d{1,2})[/-](\d{1,2})[/-](\d{4}|\d{2})$#', $dob, $m)) {
                    $d = (int) $m[1];
                    $mo = (int) $m[2];
                    if ($d >= 1 && $d <= 31 && $mo >= 1 && $mo <= 12) {
                        if (strlen($m[3]) === 2) {
                            foreach ([1900 + (int) $m[3], 2000 + (int) $m[3]] as $y) {
                                $candidate = Carbon::create($y, $mo, $d, 0, 0, 0);
                                $candidateAge = (int) $candidate->diffInYears(Carbon::now());
                                if (! $candidate->isFuture() && $candidateAge >= 18 && $candidateAge <= 105) {
                                    $carbonDob = $candidate;
                                    break;
                                }
                            }
                        } else {
                            $carbonDob = Carbon::create((int) $m[3], $mo, $d, 0, 0, 0);
                        }
                    }
                }
                if ($carbonDob === null) {
                    $carbonDob = Carbon::parse($dob);
                }
            } catch (\Throwable $e) {
                Log::warning('[CoordinatingAgent] DOB parse failed', [
                    'user_id' => $user->id,
                    'raw_dob' => $dob,
                    'error' => $e->getMessage(),
                ]);

                return ['error' => true, 'message' => 'Invalid date_of_birth — use YYYY-MM-DD, DD/MM/YYYY, or a natural-language date'];
            }

            $age = (int) $carbonDob->diffInYears(Carbon::now());
            if ($age < 18 || $age > 105) {
                Log::warning('[CoordinatingAgent] DOB outside age bounds', [
                    'user_id' => $user->id,
                    'parsed_dob' => $carbonDob->format('Y-m-d'),
                    'age' => $age,
                ]);

                return ['error' => true, 'message' => 'date_of_birth gives an age outside 18–105'];
            }

            $user->date_of_birth = $carbonDob->format('Y-m-d');
        }

        if ($marital !== '') {
            $allowedMarital = ['single', 'married', 'civil_partnership', 'divorced', 'widowed'];
            if (! in_array($marital, $allowedMarital, true)) {
                Log::warning('[CoordinatingAgent] marital_status not in allowed enum', [
                    'user_id' => $user->id,
                    'raw_marital' => $marital,
                ]);

                return ['error' => true, 'message' => 'Invalid marital_status'];
            }

            $user->marital_status = $marital;
        }

        $user->save();

        Log::info('[CoordinatingAgent] handleCapturePersonalDetails saved', [
            'user_id' => $user->id,
            'dob' => $user->date_of_birth?->format('Y-m-d'),
            'marital_status' => $user->marital_status,
            'captured_this_turn' => [
                'date_of_birth' => $dob !== '',
                'marital_status' => $marital !== '',
            ],
        ]);

        return [
            'onboarding_capture' => true,
            'field_group' => 'personal',
            'summary' => 'Personal details saved',
            'details' => [
                'date_of_birth' => $user->date_of_birth?->format('Y-m-d'),
                'marital_status' => $user->marital_status,
            ],
        ];
    }

    /**
     * capture_spouse_details — delegates to SpouseLinkingService to
     * create or link the spouse user account and create the reciprocal
     * FamilyMember rows. Respects the user's current marital_status
     * (civil_partnership stays civil_partnership).
     */
    private function handleCaptureSpouseDetails(array $input, User $user): array
    {
        $firstName = trim((string) ($input['first_name'] ?? ''));
        // Lowercase the email at the entry point so every downstream
        // lookup (SpouseLinkingService, account creation, FamilyMember
        // linkage) sees the canonical form. P0.8 — case-mismatched emails
        // were either rejecting the legitimate spouse's existing account or
        // creating a duplicate one on case-sensitive collations.
        $email = strtolower(trim((string) ($input['email'] ?? '')));
        $dob = trim((string) ($input['date_of_birth'] ?? ''));

        if ($firstName === '' || $email === '' || $dob === '') {
            return ['error' => true, 'message' => 'first_name, date_of_birth and email are required'];
        }

        // Validate email shape before hitting the service
        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['error' => true, 'message' => 'Invalid spouse email address'];
        }

        try {
            $dobFormatted = Carbon::parse($dob)->format('Y-m-d');
        } catch (\Throwable $e) {
            return ['error' => true, 'message' => 'Invalid spouse date_of_birth'];
        }

        $service = app(SpouseLinkingService::class);

        try {
            $result = $service->linkOrCreateSpouse($user, [
                'first_name' => $firstName,
                'last_name' => trim((string) ($input['last_name'] ?? '')),
                'date_of_birth' => $dobFormatted,
                'email' => $email,
                'annual_income' => isset($input['annual_income']) ? (float) $input['annual_income'] : null,
            ]);
        } catch (SpouseCollisionException $e) {
            // FR-M13 — distinguish the "email belongs to another household"
            // case so the director can emit a targeted terminal error
            // instead of the generic grouped_extract retry copy.
            return [
                'onboarding_capture_error' => true,
                'field_group' => 'spouse',
                'error_type' => 'spouse_collision',
                'message' => "That email's already registered with another Fynla household. Want to use a different address for your partner, or ask them to link their own account?",
            ];
        } catch (\InvalidArgumentException $e) {
            return ['error' => true, 'message' => $e->getMessage()];
        } catch (\Throwable $e) {
            Log::error('[CoordinatingAgent] handleCaptureSpouseDetails failed', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);

            return ['error' => true, 'message' => 'Could not link spouse account. Please try again.'];
        }

        return [
            'onboarding_capture' => true,
            'field_group' => 'spouse',
            'summary' => $result['created_new_user']
                ? 'Spouse account created and linked'
                : 'Spouse account linked',
            'details' => [
                'family_member_id' => $result['family_member']->id,
                'spouse_user_id' => $result['spouse_user']->id,
                'created_new_user' => $result['created_new_user'],
                'already_linked' => $result['already_linked'],
                'email_sent' => $result['email_sent'],
                'first_name' => $firstName,
                'annual_income' => isset($input['annual_income']) ? (float) $input['annual_income'] : null,
            ],
        ];
    }

    /**
     * capture_dependants — creates one FamilyMember row per dependant only
     * when every date of birth was supplied explicitly.
     */
    private function handleCaptureDependants(array $input, User $user): array
    {
        $dependants = is_array($input['dependants'] ?? null) ? $input['dependants'] : [];
        if (count($dependants) === 0) {
            return ['error' => true, 'message' => 'dependants list is empty'];
        }

        $normalised = [];
        $missing = [];
        foreach ($dependants as $index => $dep) {
            $dateOfBirth = trim((string) ($dep['date_of_birth'] ?? ''));
            try {
                $date = $dateOfBirth === '' ? null : Carbon::createFromFormat('!Y-m-d', $dateOfBirth);
            } catch (\Throwable) {
                $date = null;
            }

            if ($date === null
                || $date->format('Y-m-d') !== $dateOfBirth
                || $date->isFuture()
                || $date->age > 120) {
                $missing[] = "dependants.{$index}.date_of_birth";

                continue;
            }

            $relationship = (string) ($dep['relationship'] ?? '');
            if (! in_array($relationship, ['child', 'parent', 'other_dependent'], true)) {
                $missing[] = "dependants.{$index}.relationship";

                continue;
            }

            $firstName = trim((string) ($dep['first_name'] ?? ''));
            $normalised[] = [
                'date' => $date,
                'relationship' => $relationship,
                'first_name' => $firstName !== '' ? $firstName : ($relationship === 'child' ? 'Child' : 'Dependant'),
            ];
        }

        if ($missing !== []) {
            return [
                'onboarding_capture' => true,
                'clarification_required' => true,
                'field_group' => 'dependants',
                'summary' => 'Exact dependant details are required before anything is saved.',
                'details' => ['missing' => $missing],
            ];
        }

        $this->householdProvisioner->ensureFor($user);

        $created = [];
        foreach ($normalised as $dep) {
            $familyMember = FamilyMember::create([
                'user_id' => $user->id,
                'household_id' => $user->household_id,
                'relationship' => $dep['relationship'],
                'first_name' => $dep['first_name'],
                'name' => $dep['first_name'],
                'date_of_birth' => $dep['date']->toDateString(),
                'is_dependent' => true,
                'education_status' => $this->educationStatusForAge($dep['date']->age),
                'notes' => 'Captured via Fyn onboarding.',
            ]);

            $created[] = [
                'id' => $familyMember->id,
                'first_name' => $familyMember->first_name,
                'date_of_birth' => $familyMember->date_of_birth?->toDateString(),
                'age' => $dep['date']->age,
                'relationship' => $dep['relationship'],
            ];
        }

        if (count($created) === 0) {
            return ['error' => true, 'message' => 'No valid dependants could be saved'];
        }

        return [
            'onboarding_capture' => true,
            'field_group' => 'dependants',
            'summary' => count($created).' dependant'.(count($created) === 1 ? '' : 's').' saved',
            'details' => [
                'count' => count($created),
                'dependants' => $created,
            ],
        ];
    }

    /**
     * capture_work_details — writes employer + occupation + income to
     * users. For self-employed users, income lands on
     * annual_self_employment_income instead of annual_employment_income.
     *
     * Accepts partial payloads: whichever non-empty fields are present get
     * written. Only annual_income is required to advance — onboarding asks for
     * income alone, so a blank employer/occupation is reported but never blocks
     * the flow. Fields already populated on the user row count as present — this
     * lets multi-turn extraction accumulate without re-asking.
     */
    private function handleCaptureWorkDetails(array $input, User $user): array
    {
        $employer = trim((string) ($input['employer'] ?? ''));
        $occupation = trim((string) ($input['occupation'] ?? ''));
        $incomeRaw = $input['annual_income'] ?? null;
        $income = ($incomeRaw === null || $incomeRaw === '') ? null : (float) $incomeRaw;

        if ($income !== null && $income > 99_999_999) {
            return ['error' => true, 'message' => 'annual_income exceeds permitted range'];
        }
        if ($income !== null && $income < 0) {
            $income = null;
        }

        $incomeField = $user->employment_status === 'self_employed'
            ? 'annual_self_employment_income'
            : 'annual_employment_income';

        if ($employer !== '') {
            $user->employer = $employer;
        }
        if ($occupation !== '') {
            $user->occupation = $occupation;
        }
        if ($income !== null) {
            $user->{$incomeField} = $income;
        }

        $user->save();

        // Only income is required to advance — onboarding asks for income alone.
        // Employer and occupation are still written when the user volunteers them
        // (above), but a blank one must not block the flow.
        $missing = [];
        if ((float) ($user->{$incomeField} ?? 0) <= 0) {
            $missing[] = 'annual_income';
        }

        return [
            'onboarding_capture' => true,
            'field_group' => 'work',
            'summary' => count($missing) === 0
                ? 'Work details saved'
                : 'Partial work details saved — still need: '.implode(', ', $missing),
            'details' => [
                'employer' => $user->employer,
                'occupation' => $user->occupation,
                'annual_income' => (float) ($user->{$incomeField} ?? 0),
                'income_field' => $incomeField,
                'missing' => $missing,
            ],
        ];
    }

    /**
     * Map a dependant age to the closest education_status enum value on
     * family_members. Mirrors OnboardingChatDirector::educationStatusForAge
     * so the legacy regex path and the new LLM path are consistent.
     */
    private function educationStatusForAge(int $age): string
    {
        return match (true) {
            $age < 5 => 'pre_school',
            $age < 11 => 'primary',
            $age < 18 => 'secondary',
            $age < 25 => 'higher_education',
            default => 'not_applicable',
        };
    }

    private function handleListRecords(array $input, User $user): array
    {
        $entityType = $input['entity_type'] ?? null;
        if (! $entityType) {
            return ['error' => true, 'message' => 'entity_type is required'];
        }

        $userId = $user->id;
        $records = [];

        // Helper to build ownership fields from the record's own data
        $ownershipFields = function ($record) use ($userId) {
            $type = $record->ownership_type ?? 'individual';
            if ($type === 'individual') {
                return ['ownership_type' => 'individual'];
            }

            $userPct = (float) ($record->user_id === $userId
                ? ($record->ownership_percentage ?? 100)
                : (100 - ($record->ownership_percentage ?? 100)));

            $coOwnerName = $record->joint_owner_name
                ?? ($record->jointOwner?->first_name)
                ?? null;

            $fields = [
                'ownership_type' => $type,
                'your_share_percent' => $userPct,
                'co_owner_share_percent' => 100 - $userPct,
            ];
            if ($coOwnerName) {
                $fields['co_owner'] = $coOwnerName;
            }

            return $fields;
        };

        switch ($entityType) {
            case 'savings_account':
                $items = app(SavingsStore::class)->forUser($user);
                $records = $items->map(function ($a) use ($ownershipFields) {
                    $fields = $ownershipFields($a);
                    $total = (float) $a->current_balance;
                    if (isset($fields['your_share_percent']) && $fields['your_share_percent'] < 100) {
                        $fields['total_balance'] = $total;
                        $fields['your_share_value'] = round($total * $fields['your_share_percent'] / 100, 2);
                    }

                    return array_merge(['id' => $a->id, 'account_name' => $a->account_name, 'institution' => $a->institution, 'balance' => $total, 'type' => $a->account_type, 'interest_rate' => (float) $a->interest_rate, 'rate_valid_until' => $a->rate_valid_until?->format('Y-m-d'), 'access_type' => $a->access_type, 'notice_period_days' => $a->notice_period_days, 'maturity_date' => $a->maturity_date?->format('Y-m-d'), 'is_emergency_fund' => (bool) $a->is_emergency_fund, 'is_isa' => (bool) $a->is_isa, 'isa_type' => $a->isa_type, 'isa_subscription_amount' => $a->isa_subscription_amount ? (float) $a->isa_subscription_amount : null, 'regular_contribution' => $a->regular_contribution_amount ? (float) $a->regular_contribution_amount : null, 'contribution_frequency' => $a->contribution_frequency], $fields);
                })->toArray();
                break;
            case 'investment_account':
                $items = InvestmentAccount::forUserOrJoint($userId)->get();
                $records = $items->map(function ($a) use ($ownershipFields) {
                    $fields = $ownershipFields($a);
                    $total = (float) $a->current_value;
                    if (isset($fields['your_share_percent']) && $fields['your_share_percent'] < 100) {
                        $fields['total_value'] = $total;
                        $fields['your_share_value'] = round($total * $fields['your_share_percent'] / 100, 2);
                    }

                    return array_merge(['id' => $a->id, 'account_name' => $a->account_name, 'provider' => $a->provider, 'platform' => $a->platform, 'account_type' => $a->account_type, 'current_value' => $total, 'holdings_count' => $a->holdings()->count(), 'contributions_ytd' => $a->contributions_ytd ? (float) $a->contributions_ytd : null, 'monthly_contribution' => $a->monthly_contribution_amount ? (float) $a->monthly_contribution_amount : null, 'contribution_frequency' => $a->contribution_frequency, 'platform_fee_percent' => $a->platform_fee_percent ? (float) $a->platform_fee_percent : null, 'advisor_fee_percent' => $a->advisor_fee_percent ? (float) $a->advisor_fee_percent : null, 'isa_type' => $a->isa_type, 'isa_subscription_current_year' => $a->isa_subscription_current_year ? (float) $a->isa_subscription_current_year : null, 'include_in_retirement' => (bool) $a->include_in_retirement], $fields);
                })->toArray();
                break;
            case 'dc_pension':
                $items = app(PensionStore::class)->forUserByType(User::findOrFail($userId), 'dc');
                $records = $items->map(fn ($p) => ['id' => $p->id, 'scheme_name' => $p->scheme_name, 'pension_type' => $p->pension_type, 'provider' => $p->provider, 'current_value' => (float) $p->current_fund_value, 'employee_contribution' => (float) ($p->employee_contribution_percent ?? 0), 'employer_contribution' => (float) ($p->employer_contribution_percent ?? 0), 'employer_matching_limit' => $p->employer_matching_limit ? (float) $p->employer_matching_limit : null, 'monthly_contribution' => $p->monthly_contribution_amount ? (float) $p->monthly_contribution_amount : null, 'platform_fee_percent' => $p->platform_fee_percent ? (float) $p->platform_fee_percent : null, 'retirement_age' => $p->retirement_age, 'projected_value_at_retirement' => $p->projected_value_at_retirement ? (float) $p->projected_value_at_retirement : null, 'has_flexibly_accessed' => (bool) $p->has_flexibly_accessed])->toArray();
                break;
            case 'db_pension':
                $items = app(PensionStore::class)->forUserByType(User::findOrFail($userId), 'db');
                $records = $items->map(fn ($p) => ['id' => $p->id, 'scheme_name' => $p->scheme_name, 'scheme_type' => $p->scheme_type, 'annual_pension' => (float) ($p->accrued_annual_pension ?? 0), 'service_years' => $p->pensionable_service_years, 'pensionable_salary' => $p->pensionable_salary ? (float) $p->pensionable_salary : null, 'normal_retirement_age' => $p->normal_retirement_age, 'spouse_pension_percent' => $p->spouse_pension_percent ? (float) $p->spouse_pension_percent : null, 'lump_sum_entitlement' => $p->lump_sum_entitlement ? (float) $p->lump_sum_entitlement : null, 'inflation_protection' => $p->inflation_protection])->toArray();
                break;
            case 'property':
                $items = Property::with('mortgages')->forUserOrJoint($userId)->get();
                $records = $items->map(function ($p) use ($ownershipFields) {
                    $fields = $ownershipFields($p);
                    $total = (float) $p->current_value;
                    $mortgageTotal = (float) $p->mortgages->sum('outstanding_balance');
                    if (isset($fields['your_share_percent']) && $fields['your_share_percent'] < 100) {
                        $pct = $fields['your_share_percent'] / 100;
                        $fields['total_value'] = $total;
                        $fields['your_share_value'] = round($total * $pct, 2);
                        if ($mortgageTotal > 0) {
                            $fields['total_mortgage'] = $mortgageTotal;
                            $fields['your_mortgage_share'] = round($mortgageTotal * $pct, 2);
                        }
                    }

                    // Embed mortgage detail so the model gets it from property queries
                    $mortgages = $p->mortgages->map(fn ($m) => ['lender' => $m->lender_name, 'outstanding_balance' => (float) $m->outstanding_balance, 'interest_rate' => (float) ($m->interest_rate ?? 0), 'rate_type' => $m->rate_type, 'rate_fix_end_date' => $m->rate_fix_end_date?->format('Y-m-d'), 'monthly_payment' => (float) ($m->monthly_payment ?? 0), 'mortgage_type' => $m->mortgage_type, 'remaining_term_months' => $m->remaining_term_months])->toArray();

                    return array_merge(['id' => $p->id, 'address' => $p->address_line_1, 'property_type' => $p->property_type, 'current_value' => $total, 'outstanding_mortgage' => $mortgageTotal, 'mortgages' => $mortgages], $fields);
                })->toArray();
                break;
            case 'mortgage':
                $items = Mortgage::whereHas('property', fn ($q) => $q->forUserOrJoint($userId))->with('property')->get();
                $records = $items->map(fn ($m) => ['id' => $m->id, 'property' => $m->property->address_line_1 ?? 'Unknown', 'lender' => $m->lender_name, 'outstanding_balance' => (float) $m->outstanding_balance, 'interest_rate' => (float) ($m->interest_rate ?? 0), 'rate_type' => $m->rate_type, 'rate_fix_end_date' => $m->rate_fix_end_date?->format('Y-m-d'), 'monthly_payment' => (float) ($m->monthly_payment ?? 0), 'mortgage_type' => $m->mortgage_type, 'remaining_term_months' => $m->remaining_term_months, 'start_date' => $m->start_date?->format('Y-m-d'), 'maturity_date' => $m->maturity_date?->format('Y-m-d'), 'original_loan_amount' => (float) ($m->original_loan_amount ?? 0)])->toArray();
                break;
            case 'life_insurance':
                $items = LifeInsurancePolicy::where('user_id', $userId)->get();
                $records = $items->map(fn ($p) => ['id' => $p->id, 'provider' => $p->provider, 'type' => $p->policy_type, 'sum_assured' => (float) $p->sum_assured, 'premium' => (float) ($p->premium_amount ?? 0), 'premium_frequency' => $p->premium_frequency, 'policy_start_date' => $p->policy_start_date?->format('Y-m-d'), 'policy_end_date' => $p->policy_end_date?->format('Y-m-d'), 'policy_term_years' => $p->policy_term_years, 'in_trust' => (bool) $p->in_trust, 'is_mortgage_protection' => (bool) $p->is_mortgage_protection, 'joint_life' => (bool) $p->joint_life, 'ownership_type' => $p->ownership_type])->toArray();
                break;
            case 'critical_illness':
                $items = CriticalIllnessPolicy::where('user_id', $userId)->get();
                $records = $items->map(fn ($p) => ['id' => $p->id, 'provider' => $p->provider, 'policy_type' => $p->policy_type, 'sum_assured' => (float) $p->sum_assured, 'premium' => (float) ($p->premium_amount ?? 0), 'premium_frequency' => $p->premium_frequency, 'policy_start_date' => $p->policy_start_date?->format('Y-m-d'), 'policy_term_years' => $p->policy_term_years, 'ownership_type' => $p->ownership_type])->toArray();
                break;
            case 'income_protection':
                $items = IncomeProtectionPolicy::where('user_id', $userId)->get();
                $records = $items->map(fn ($p) => ['id' => $p->id, 'provider' => $p->provider, 'benefit_amount' => (float) $p->benefit_amount, 'benefit_frequency' => $p->benefit_frequency, 'premium' => (float) ($p->premium_amount ?? 0), 'premium_frequency' => $p->premium_frequency, 'deferred_period_weeks' => $p->deferred_period_weeks, 'policy_start_date' => $p->policy_start_date?->format('Y-m-d'), 'ownership_type' => $p->ownership_type])->toArray();
                break;
            case 'trust':
                $items = Trust::where('user_id', $userId)->get();
                $records = $items->map(fn ($t) => ['id' => $t->id, 'trust_name' => $t->trust_name, 'trust_type' => $t->trust_type, 'current_value' => (float) $t->current_value, 'initial_value' => $t->initial_value ? (float) $t->initial_value : null, 'creation_date' => $t->trust_creation_date?->format('Y-m-d'), 'settlor' => $t->settlor, 'beneficiaries' => $t->beneficiaries, 'trustees' => $t->trustees, 'purpose' => $t->purpose, 'is_relevant_property_trust' => (bool) $t->is_relevant_property_trust, 'retained_income_annual' => $t->retained_income_annual ? (float) $t->retained_income_annual : null, 'loan_amount' => $t->loan_amount ? (float) $t->loan_amount : null, 'is_active' => (bool) $t->is_active])->toArray();
                break;
            case 'business_interest':
                $items = BusinessInterest::forUserOrJoint($userId)->get();
                $records = $items->map(fn ($b) => array_merge(['id' => $b->id, 'business_name' => $b->business_name, 'business_type' => $b->business_type, 'estimated_value' => (float) $b->current_valuation, 'annual_revenue' => $b->annual_revenue ? (float) $b->annual_revenue : null, 'annual_profit' => $b->annual_profit ? (float) $b->annual_profit : null, 'annual_dividend_income' => $b->annual_dividend_income ? (float) $b->annual_dividend_income : null, 'trading_status' => $b->trading_status, 'employee_count' => $b->employee_count, 'acquisition_date' => $b->acquisition_date?->format('Y-m-d'), 'acquisition_cost' => $b->acquisition_cost ? (float) $b->acquisition_cost : null, 'bpr_eligible' => $b->bpr_eligible], $ownershipFields($b)))->toArray();
                break;
            case 'chattel':
                $items = Chattel::forUserOrJoint($userId)->get();
                $records = $items->map(fn ($c) => array_merge(['id' => $c->id, 'name' => $c->name, 'description' => $c->description, 'category' => $c->chattel_type, 'estimated_value' => (float) $c->current_value, 'purchase_price' => $c->purchase_price ? (float) $c->purchase_price : null, 'purchase_date' => $c->purchase_date?->format('Y-m-d'), 'make' => $c->make, 'model' => $c->model, 'year' => $c->year], $ownershipFields($c)))->toArray();
                break;
            case 'estate_liability':
                $items = Liability::forUserOrJoint($userId)->get();
                $records = $items->map(fn ($l) => array_merge(['id' => $l->id, 'liability_name' => $l->liability_name, 'type' => $l->liability_type, 'balance' => (float) $l->current_balance, 'interest_rate' => $l->interest_rate ? (float) $l->interest_rate : null, 'monthly_payment' => $l->monthly_payment ? (float) $l->monthly_payment : null, 'maturity_date' => $l->maturity_date?->format('Y-m-d'), 'is_priority_debt' => (bool) $l->is_priority_debt], $ownershipFields($l)))->toArray();
                break;
            case 'estate_gift':
                $items = Gift::where('user_id', $userId)->get();
                $records = $items->map(fn ($g) => ['id' => $g->id, 'recipient' => $g->recipient, 'gift_type' => $g->gift_type, 'value' => (float) $g->gift_value, 'date' => $g->gift_date?->format('Y-m-d'), 'status' => $g->status, 'taper_relief_applicable' => (bool) $g->taper_relief_applicable, 'notes' => $g->notes])->toArray();
                break;
            case 'family_member':
                $items = FamilyMember::where('user_id', $userId)->get();
                $records = $items->map(fn ($m) => ['id' => $m->id, 'name' => trim($m->first_name.' '.$m->last_name), 'relationship' => $m->relationship, 'age' => $m->date_of_birth ? now()->diffInYears($m->date_of_birth) : null, 'date_of_birth' => $m->date_of_birth?->format('Y-m-d'), 'gender' => $m->gender, 'annual_income' => $m->annual_income ? (float) $m->annual_income : null, 'is_dependent' => (bool) $m->is_dependent, 'education_status' => $m->education_status, 'receives_child_benefit' => (bool) $m->receives_child_benefit])->toArray();
                break;
            default:
                return ['error' => true, 'message' => "Unknown entity type: {$entityType}"];
        }

        return [
            'entity_type' => $entityType,
            'count' => count($records),
            'records' => $records,
        ];
    }

    private function handleListGoals(User $user): array
    {
        $goals = Goal::forUserOrJoint($user->id)
            ->orderByRaw("FIELD(status, 'active', 'paused', 'completed', 'abandoned')")
            ->orderBy('priority')
            ->get();

        if ($goals->isEmpty()) {
            return [
                'has_goals' => false,
                'count' => 0,
                'goals' => [],
                'message' => 'No goals set yet. You can create goals to track savings targets, house deposits, holidays, and more.',
            ];
        }

        return [
            'has_goals' => true,
            'count' => $goals->count(),
            'active_count' => $goals->where('status', 'active')->count(),
            'on_track_count' => $goals->filter(fn ($g) => $g->is_on_track)->count(),
            'goals' => $goals->map(fn ($g) => [
                'id' => $g->id,
                'name' => $g->goal_name,
                'type' => $g->goal_type,
                'status' => $g->status,
                'priority' => $g->priority,
                'target_amount' => round((float) $g->target_amount, 2),
                'current_amount' => round((float) $g->current_amount, 2),
                'remaining' => round(max(0, (float) $g->target_amount - (float) $g->current_amount), 2),
                'progress_percentage' => $g->progress_percentage,
                'is_on_track' => $g->is_on_track,
                'monthly_contribution' => round((float) ($g->monthly_contribution ?? 0), 2),
                'target_date' => $g->target_date?->format('Y-m-d'),
                'assigned_module' => $g->assigned_module,
            ])->toArray(),
        ];
    }

    private function handleListLifeEvents(User $user): array
    {
        $events = LifeEvent::forUserOrJoint($user->id)
            ->orderBy('expected_date')
            ->get();

        if ($events->isEmpty()) {
            return [
                'has_events' => false,
                'count' => 0,
                'events' => [],
                'message' => 'No life events recorded. You can add upcoming events like weddings, property purchases, inheritance, or career changes to see how they affect your financial plan.',
            ];
        }

        $active = $events->whereIn('status', ['expected', 'confirmed']);
        $completed = $events->where('status', 'completed');

        return [
            'has_events' => true,
            'count' => $events->count(),
            'active_count' => $active->count(),
            'completed_count' => $completed->count(),
            'events' => $events->map(fn ($e) => [
                'id' => $e->id,
                'name' => $e->event_name,
                'type' => $e->event_type,
                'display_type' => $e->display_event_type,
                'status' => $e->status,
                'impact_type' => $e->impact_type,
                'amount' => round((float) $e->amount, 2),
                'expected_date' => $e->expected_date?->format('Y-m-d'),
                'months_until' => $e->expected_date ? max(0, (int) now()->diffInMonths($e->expected_date, false)) : null,
                'certainty' => $e->certainty,
            ])->toArray(),
        ];
    }

    private function handleModuleAnalysis(
        array $input,
        User $user,
        ?array $classification = null,
        ?array $kycResult = null,
    ): array {
        $module = $input['module'];

        $analyzeStart = microtime(true);
        $analysis = match ($module) {
            'protection' => $this->protectionAgent->analyze($user->id),
            'savings' => $this->savingsAgent->analyze($user->id),
            'investment' => $this->investmentAgent->analyze($user->id),
            'retirement' => $this->retirementAgent->analyze($user->id),
            'estate' => $this->estateAgent->analyze($user->id),
            'goals' => $this->goalsAgent->analyze($user->id),
            'holistic' => $this->orchestrateAnalysis($user->id),
            default => ['error' => "Unknown module: {$module}"],
        };
        $analyzeDuration = (int) round((microtime(true) - $analyzeStart) * 1000);

        if ($this->isQuestionScopedModule($module, $classification, $kycResult)
            && $this->requiresQuestionScopedFallback($analysis)) {
            $analysis = $this->questionScopedModuleAnalysis(
                $module,
                $user,
                (string) $classification['primary'],
            );
        }

        // Eval trace — every module analyze invocation through this tool
        // gets one EngineCalled. result_path inferred from response shape:
        // success_false when the agent returns ['success' => false, ...],
        // happy otherwise.
        $resultPath = (isset($analysis['success']) && $analysis['success'] === false) ? 'success_false' : 'happy';
        event(new EngineCalled(
            engine: $module === 'holistic' ? 'orchestrate_analysis' : "{$module}_analysis",
            params: ['user_id' => $user->id, 'module' => $module],
            resultSummary: [
                'keys_returned' => array_keys($analysis),
                'result_path' => $resultPath,
            ],
            durationMs: $analyzeDuration,
            atMicrotime: microtime(true),
        ));

        if (($analysis['analysis_scope'] ?? null) === 'question_required_data') {
            return $analysis;
        }

        return $this->summariseToolAnalysis($module, $analysis);
    }

    private function isQuestionScopedModuleTool(
        string $toolName,
        array $input,
        ?array $classification,
        ?array $kycResult,
    ): bool {
        return $toolName === 'get_module_analysis'
            && isset($input['module'])
            && $this->isQuestionScopedModule((string) $input['module'], $classification, $kycResult);
    }

    private function isQuestionScopedModule(
        string $module,
        ?array $classification,
        ?array $kycResult,
    ): bool {
        $primary = $classification['primary'] ?? null;
        if (! $this->hasPassedPrimaryKyc($classification, $kycResult)) {
            return false;
        }

        $primaryModules = QuerySchemas::getModulesForClassification([
            'primary' => $primary,
            'related' => [],
        ]);

        return $module !== 'holistic' && in_array($module, $primaryModules, true);
    }

    private function isQuestionScopedHolisticTool(
        string $toolName,
        ?array $classification,
        ?array $kycResult,
    ): bool {
        return ($classification['primary'] ?? null) === QuerySchemas::HOLISTIC_HEALTH
            && $this->hasPassedPrimaryKyc($classification, $kycResult)
            && in_array($toolName, ['get_recommendations', 'generate_financial_plan'], true);
    }

    private function hasPassedPrimaryKyc(?array $classification, ?array $kycResult): bool
    {
        $primary = $classification['primary'] ?? null;

        return is_string($primary)
            && QuerySchemas::isAdviceType($primary)
            && ($kycResult['passed'] ?? false) === true;
    }

    private function requiresQuestionScopedFallback(array $analysis): bool
    {
        if (($analysis['can_proceed'] ?? null) === false
            || ($analysis['data']['can_proceed'] ?? null) === false) {
            return true;
        }

        if (($analysis['success'] ?? null) !== false) {
            return false;
        }

        $message = strtolower((string) ($analysis['message'] ?? ''));

        return str_contains($message, 'profile not found')
            || str_contains($message, 'no retirement profile')
            || str_contains($message, 'no investment accounts')
            || str_contains($message, 'no savings accounts');
    }

    /**
     * Return current, factual data for the primary question when a module's
     * broader readiness contract needs fields outside REQUIRED_DATA.
     * No financial-quality score or recommendation is manufactured here.
     *
     * @return array<string, mixed>
     */
    private function questionScopedModuleAnalysis(string $module, User $user, string $primary): array
    {
        $recordTypes = match ($module) {
            'retirement' => ['dc_pension', 'db_pension'],
            'savings' => ['savings_account'],
            'investment' => ['investment_account'],
            'protection' => ['life_insurance', 'critical_illness', 'income_protection'],
            'estate' => ['property', 'trust', 'estate_gift', 'estate_liability', 'business_interest', 'chattel'],
            default => [],
        };

        $records = [];
        foreach ($recordTypes as $recordType) {
            $listed = $this->handleListRecords(['entity_type' => $recordType], $user);
            $records[$recordType] = $listed['records'] ?? [];
        }

        if ($module === 'goals') {
            $records['goals'] = $this->handleListGoals($user)['goals'] ?? [];
        }

        $analysis = [
            'module' => $module,
            'query_type' => $primary,
            'analysis_scope' => 'question_required_data',
            'can_proceed' => true,
            'records' => $records,
        ];

        if ($module === 'retirement') {
            $statePension = $user->statePension()->first();
            $analysis['state_pension'] = $statePension ? [
                'forecast_annual' => (float) ($statePension->state_pension_forecast_annual ?? 0),
                'qualifying_years' => $statePension->ni_years_completed,
                'state_pension_age' => $statePension->state_pension_age,
            ] : null;
            $analysis['total_pension_value'] = (float) collect($records['dc_pension'])->sum('current_value');
            $analysis['projected_annual_income'] = (float) collect($records['db_pension'])->sum('annual_pension')
                + (float) ($analysis['state_pension']['forecast_annual'] ?? 0);
        }

        if ($module === 'savings') {
            $analysis['total_savings'] = (float) collect($records['savings_account'])->sum('balance');
        }

        if ($module === 'investment') {
            $analysis['total_portfolio_value'] = (float) collect($records['investment_account'])->sum('current_value');
        }

        if ($module === 'protection') {
            $analysis['full_analysis'] = [
                'total_cover' => (float) collect($records['life_insurance'])->sum('sum_assured')
                    + (float) collect($records['critical_illness'])->sum('sum_assured'),
            ];
        }

        return $analysis;
    }

    /**
     * S1.5 — Search the user's prior conversations by topic_keywords or
     * entity_types against the S1.3 conversation-index columns.
     *
     * Returns up to 10 conversations ordered by `last_message_at` desc.
     * Per-result payload is intentionally lean: id + title + summary +
     * topics + intents_stated + last_message_at. The model uses this
     * to surface "as we talked about last time" context — INV-2.11.3.
     *
     * `forUser` scope pins per-user ACL; the tool can never reach
     * another user's conversation history.
     *
     * Excludes the active conversation if `$activeConversationId` is
     * supplied — the model already has the active conversation's
     * messages in its history, so re-surfacing them via index search
     * would be noise.
     */
    private function handleSearchConversationIndex(array $input, User $user, ?int $activeConversationId = null): array
    {
        $topicKeywords = array_values(array_filter(
            (array) ($input['topic_keywords'] ?? []),
            fn ($v) => is_string($v) && $v !== ''
        ));

        $entityTypes = array_values(array_filter(
            (array) ($input['entity_types'] ?? []),
            fn ($v) => is_string($v) && $v !== ''
        ));

        $query = AiConversation::query()
            ->where('user_id', $user->id)
            ->whereNotNull('summary');

        if ($activeConversationId !== null) {
            $query->where('id', '!=', $activeConversationId);
        }

        if ($topicKeywords !== [] || $entityTypes !== []) {
            $query->where(function ($outer) use ($topicKeywords, $entityTypes): void {
                foreach ($topicKeywords as $topic) {
                    $outer->orWhereJsonContains('topics', $topic);
                }
                foreach ($entityTypes as $entityType) {
                    $outer->orWhereJsonContains('entities_mentioned', ['type' => $entityType]);
                }
            });
        }

        $rows = $query
            ->orderByDesc('last_message_at')
            ->limit(10)
            ->get(['id', 'title', 'summary', 'topics', 'intents_stated', 'entities_mentioned', 'last_message_at']);

        return [
            'count' => $rows->count(),
            'conversations' => $rows->map(fn ($row) => [
                'id' => $row->id,
                'title' => $row->title,
                'summary' => $row->summary,
                'topics' => $row->topics ?? [],
                'intents_stated' => $row->intents_stated ?? [],
                'entities_mentioned' => $row->entities_mentioned ?? [],
                'last_message_at' => $row->last_message_at?->toIso8601String(),
            ])->all(),
        ];
    }

    private function handleCreateWhatIfScenario(array $input, User $user): array
    {
        $service = app(WhatIfScenarioService::class);

        $result = $service->createScenario($user, [
            'name' => $input['name'],
            'scenario_type' => $input['scenario_type'] ?? 'custom',
            'parameters' => $input['parameters'],
            'created_via' => 'ai_chat',
            'ai_narrative' => $input['description'] ?? null,
        ]);

        return [
            'success' => true,
            'scenario_id' => $result['scenario_id'],
            'comparison' => $result,
            'action' => 'navigate',
            'route_path' => '/planning/what-if/'.$result['scenario_id'],
        ];
    }

    private function handleRecommendations(User $user): array
    {
        $analysis = $this->orchestrateAnalysis($user->id);

        $plan = $this->composedTaxPlans->forUser($user);

        // §6e — the tool path records the same strategy-id provenance and plan
        // digest as the fetch-skill (RecommendationHandler), via the shared home
        // on ComposedTaxPlanService, so every surfaced strategy is attributable
        // from ai_messages.fetch_provenance regardless of path.
        $ids = ComposedTaxPlanService::extractStrategyIds($plan);

        // Deliberately resolved at call time, NOT constructor-injected: the
        // collector is request-scoped, and this agent is captured by the
        // singleton AdviceFyn — constructor injection would freeze the first
        // request's collector (same hazard as the FetchDispatcher binding
        // comment in AppServiceProvider).
        app(FetchProvenanceCollector::class)->record([
            'pointer_id' => 'recommendations',
            'handler' => 'recommendations',
            'source_label' => 'recommendation engine',
            'source_version' => now()->toDateString(),
            'digest' => ComposedTaxPlanService::planDigest($plan),
            'strategy_ids' => implode(',', $ids['surfaced']),
            'locked_strategy_ids' => implode(',', $ids['locked']),
        ]);

        return [
            'recommendations' => $analysis['ranked_recommendations'] ?? [],
            'total' => count($analysis['ranked_recommendations'] ?? []),
            'surplus' => $analysis['available_surplus'] ?? 0,
            // Ordered, conflict-resolved tax plan with claim tiers + locked strategies —
            // the presentation contract lives in the tool description.
            'composed_tax_plan' => $plan,
        ];
    }

    private function handleGetSubscriptionStatus(User $user): array
    {
        $status = $this->subscriptionStatusService->forUser($user);

        return [
            ...$status,
            'action' => 'navigate',
            'route_path' => '/settings/subscription',
            'description' => 'View your subscription and invoices',
        ];
    }

    private function handleListInvoices(User $user): array
    {
        return Invoice::query()
            ->where('user_id', $user->id)
            ->orderByDesc('issued_at')
            ->orderByDesc('id')
            ->get()
            ->map(fn (Invoice $i) => [
                'invoice_id' => $i->id,
                'invoice_number' => $i->invoice_number,
                'issued_at' => $i->issued_at?->toIso8601String(),
                'amount' => round($i->total_amount / 100, 2),
                'currency' => $i->currency ?? 'GBP',
                'status' => $i->status,
                'plan_name' => $i->plan_name,
                'billing_cycle' => $i->billing_cycle,
                'pdf_url' => '/api/payment/invoices/'.$i->id.'/download',
            ])
            ->all();
    }

    private function handleGetCurrentPlan(User $user): array
    {
        $status = $this->subscriptionStatusService->forUser($user);
        $features = collect($status['capability_matrix'] ?? [])
            ->filter(static fn (string $access): bool => $access !== 'none')
            ->keys()
            ->values()
            ->all();

        return [
            'plan_name' => $status['tier_display_name'],
            'tier' => $status['tier'],
            'billing_cycle' => $status['billing_cycle'],
            'price_gbp' => round(((int) ($status['amount'] ?? 0)) / 100, 2),
            'features' => $features,
        ];
    }

    private function handleTaxInformation(array $input, User $user): array
    {
        $topic = $input['topic'];

        // income_definitions is per-user (not cacheable globally)
        if ($topic === 'income_definitions') {
            return Cache::remember("ai_income_defs_{$user->id}", 120, function () use ($user) {
                $incomeService = app(IncomeDefinitionsService::class);

                return $incomeService->calculate($user->id);
            });
        }

        // Cache tax config lookups for 5 minutes to save token cost on repeated queries
        return Cache::remember("ai_tax_info_{$topic}", 300, function () use ($topic) {
            return match ($topic) {
                'income_tax' => $this->taxConfig->getIncomeTax(),
                'national_insurance' => $this->taxConfig->getNationalInsurance(),
                'capital_gains' => $this->taxConfig->getCapitalGainsTax(),
                'dividend_tax' => $this->taxConfig->getDividendTax(),
                'inheritance_tax' => $this->taxConfig->getInheritanceTax(),
                'gifting_exemptions' => $this->taxConfig->getGiftingExemptions(),
                'stamp_duty' => $this->taxConfig->getStampDuty(),
                'isa_allowances' => $this->taxConfig->getISAAllowances(),
                'pension_allowances' => $this->taxConfig->getPensionAllowances(),
                'state_pension' => $this->taxConfig->get('pension.state_pension', []),
                'benefits' => $this->taxConfig->getBenefits(),
                'savings_config' => $this->taxConfig->getSavingsConfig(),
                'assumptions' => $this->taxConfig->getAssumptions(),
                'investment_bonds' => [
                    'onshore_bond_minimum' => $this->taxConfig->get('investment.waterfall.onshore_bond_minimum'),
                    'offshore_bond_minimum' => $this->taxConfig->get('investment.waterfall.offshore_bond_minimum'),
                    'tax_treatment' => 'Onshore bonds have 20% tax credit, 5% annual tax-deferred withdrawals, and top-slicing relief. Offshore bonds have gross roll-up with no tax credit, same 5% withdrawals, and time apportionment relief.',
                ],
                'venture_capital' => $this->taxConfig->get('investment.venture_capital', []),
                'protection_config' => $this->taxConfig->getProtectionConfig(),
                'retirement_config' => $this->taxConfig->getRetirementConfig(),
                'domicile' => $this->taxConfig->getDomicile(),
                default => ['error' => "Unknown tax topic: {$topic}"],
            };
        });
    }

    private function handleFinancialPlan(User $user): array
    {
        if (! $this->teaserGate->isFull($user, 'holistic_plan')) {
            return [
                'error' => 'upgrade_required',
                'message' => 'The Holistic Plan is part of Premium.',
                'required_tier' => 'premium',
            ];
        }

        $plan = $this->generateHolisticPlan($user->id);

        $summary = [];

        if (isset($plan['executive_summary'])) {
            $summary['executive_summary'] = $plan['executive_summary'];
        }

        $recommendations = $plan['ranked_recommendations'] ?? $plan['recommendations'] ?? [];
        $summary['top_recommendations'] = array_slice($recommendations, 0, 5);

        if (isset($plan['action_plan'])) {
            $summary['action_plan'] = array_slice($plan['action_plan'], 0, 5);
        }

        if (isset($plan['available_surplus'])) {
            $summary['monthly_surplus'] = $plan['available_surplus'];
        }

        if (isset($plan['cashflow_allocation'])) {
            $summary['suggested_allocation'] = $plan['cashflow_allocation'];
        }

        return $summary;
    }

    // ─── Entity creation tool handlers ───────────────────────────────

    private function handleCreateGoal(array $input, User $user, bool $isPreview): array
    {
        if ($isPreview) {
            return $this->previewBlocked('goal');
        }

        $validationError = $this->validateToolInput($input, [
            'name' => 'required|string|max:255',
            'target_amount' => 'required|numeric|min:0|max:999999999.99',
            'target_date' => 'required|date|after:today',
            'priority' => ['required', Rule::in(['critical', 'high', 'medium', 'low'])],
            'goal_type' => ['required', Rule::in(['emergency_fund', 'home_deposit', 'property_purchase', 'holiday', 'education', 'wedding', 'car_purchase', 'retirement', 'wealth_accumulation', 'debt_repayment', 'custom'])],
            'monthly_contribution' => 'nullable|numeric|min:0|max:999999.99',
        ]);
        if ($validationError) {
            return $validationError;
        }

        $payload = [
            'goal_name' => $input['name'],
            'goal_type' => $input['goal_type'],
            'target_amount' => (float) $input['target_amount'],
            'target_date' => $input['target_date'],
            'priority' => $input['priority'],
        ];

        // Custom goals require custom_goal_type_name; reuse the goal name
        // because the AI tool doesn't expose a separate slot for it.
        if ($input['goal_type'] === 'custom') {
            $payload['custom_goal_type_name'] = $input['name'];
        }

        if (isset($input['monthly_contribution']) && is_numeric($input['monthly_contribution'])) {
            $payload['monthly_contribution'] = (float) $input['monthly_contribution'];
        }
        if (isset($input['description']) && $input['description'] !== '') {
            $payload['description'] = $input['description'];
        }

        try {
            $goal = app(GoalStore::class)->create($payload, $user, IngestSource::FYN_AI);
        } catch (TierLimitExceededException $e) {
            return $this->tierLimitResult($e, $user, "You've reached your plan's limit of {$e->hardLimit} goals. To add more, upgrade your plan.");
        }

        $this->invalidateUserCache($user->id);

        return [
            'success' => true,
            'created' => true,
            'entity_type' => 'goal',
            'entity_id' => $goal->id,
            'name' => $goal->goal_name,
            'persisted_fields' => array_keys($payload),
            'message' => "I've added your \"{$goal->goal_name}\" goal.",
        ];
    }

    private function handleCreateLifeEvent(array $input, User $user, bool $isPreview): array
    {
        if ($isPreview) {
            return $this->previewBlocked('life event');
        }

        $validationError = $this->validateToolInput($input, [
            'event_name' => 'required|string|max:255',
            'event_type' => ['required', Rule::in(['inheritance', 'gift_received', 'bonus', 'redundancy_payment', 'property_sale', 'business_sale', 'pension_lump_sum', 'lottery_windfall', 'custom_income', 'large_purchase', 'home_improvement', 'wedding', 'education_fees', 'gift_given', 'medical_expense', 'custom_expense'])],
            'event_date' => 'required|date',
            'estimated_amount' => 'required|numeric|min:0|max:999999999.99',
            'certainty' => ['nullable', Rule::in(['confirmed', 'likely', 'possible', 'speculative'])],
            'description' => 'nullable|string|max:500',
        ]);
        if ($validationError) {
            return $validationError;
        }

        $payload = [
            'event_name' => $input['event_name'],
            'event_type' => $input['event_type'],
            'amount' => (float) $input['estimated_amount'],
            'expected_date' => $input['event_date'],
            'certainty' => $input['certainty'] ?? 'likely',
            'ownership_type' => 'individual',
            'ownership_percentage' => 100.00,
        ];

        if (isset($input['description']) && $input['description'] !== '') {
            $payload['description'] = $input['description'];
        }

        try {
            $event = app(LifeEventStore::class)->create($payload, $user, IngestSource::FYN_AI);
        } catch (TierLimitExceededException $e) {
            return $this->tierLimitResult($e, $user, "You've reached your plan's limit of {$e->hardLimit} life events. To add more, upgrade your plan.");
        }

        $this->invalidateUserCache($user->id);

        return [
            'success' => true,
            'created' => true,
            'entity_type' => 'life_event',
            'entity_id' => $event->id,
            'name' => $event->event_name,
            'persisted_fields' => array_keys($payload),
            'message' => "I've added your \"{$event->event_name}\" life event.",
        ];
    }

    /**
     * Free-tier "accounts-left" note appended to a successful create confirmation
     * (/m freemium 5.2). $used is the gate-accurate primary-owner count so the
     * note agrees with what the tier gate enforces. Empty on unlimited tiers.
     */
    private function tierCapNote(User $user, string $entityKey, int $used): string
    {
        $cap = app(TierGate::class)->hardLimit($user, $entityKey);

        return $cap === null ? '' : " That's {$used} of {$cap} on your plan.";
    }

    private function tierLimitResult(TierLimitExceededException $exception, User $user, string $message): array
    {
        $subscriptionStatus = $this->subscriptionStatusService->forUser($user);

        $result = [
            'error' => true,
            'error_type' => 'tier_limit_reached',
            'entity_key' => $exception->entityKey,
            'current_count' => $exception->currentCount,
            'hard_limit' => $exception->hardLimit,
            'required_tier' => 'premium',
            'message' => $subscriptionStatus['payment_enabled']
                ? $message
                : preg_replace('/ To add more, upgrade your plan\.$/', '', $message),
        ];

        if (! $subscriptionStatus['payment_enabled']) {
            return $result;
        }

        return array_merge($result, [
            'action' => 'subscription_options',
            'reason' => 'tier_limit_reached',
            'limit' => $exception->hardLimit,
            'tier' => $subscriptionStatus['tier'],
        ]);
    }

    private function handleCreateSavingsAccount(array $input, User $user, bool $isPreview): array
    {
        if ($isPreview) {
            return $this->previewBlocked('savings account');
        }

        // May18 — the model frequently invents an out-of-enum account_type
        // (most commonly the literal "savings_account" when the user just
        // says "add a savings account" with no type). Hard-rejecting that
        // made the FIRST create_savings_account call fail validation; the
        // model only sometimes self-corrected on a retry, so the record was
        // intermittently never created and the capture turn re-narrated.
        // Coerce a recognised synonym / unknown value to a sensible default
        // BEFORE validation. An untyped account defaults to current_account —
        // a bank account, not a savings product — unless the user stipulates a
        // savings type (those map below) or flags it as an ISA (then cash_isa).
        if (isset($input['account_type'])) {
            if (! in_array($input['account_type'], self::SAVINGS_ACCOUNT_TYPES, true)) {
                $synonyms = [
                    'savings_account' => 'easy_access',
                    'savings' => 'easy_access',
                    'instant_access' => 'easy_access',
                    'instant access' => 'easy_access',
                    'easy access' => 'easy_access',
                    // A bank/current account is a distinct canonical type, not a
                    // savings product (/bug 6.2). 'current_account' is whitelisted
                    // above so it passes straight through; only free-text synonyms
                    // need mapping here.
                    'bank' => 'current_account',
                    'bank account' => 'current_account',
                    'bank_account' => 'current_account',
                    'current' => 'current_account',
                    'current account' => 'current_account',
                    'cash isa' => 'cash_isa',
                    'junior isa' => 'junior_isa',
                    'fixed_rate' => 'fixed',
                    'fixed rate' => 'fixed',
                    'bond' => 'fixed',
                    'notice_account' => 'notice',
                ];
                $key = strtolower((string) $input['account_type']);
                if ($key === 'isa' || ! empty($input['is_isa'])) {
                    return [
                        'error' => true,
                        'clarification_required' => true,
                        'error_type' => 'capture_accuracy_required',
                        'missing' => ['isa_subtype'],
                        'message' => 'I need to know whether this is a Cash ISA, Stocks & Shares ISA, Lifetime ISA, or Innovative Finance ISA before I can save it.',
                    ];
                }

                $input['account_type'] = $synonyms[$key] ?? 'current_account';
            }
        }

        if (! empty($input['is_isa']) && ! in_array($input['account_type'] ?? null, ['cash_isa', 'junior_isa'], true)) {
            return [
                'error' => true,
                'clarification_required' => true,
                'error_type' => 'capture_accuracy_required',
                'missing' => ['isa_subtype'],
                'message' => 'I need to know whether this is a Cash ISA, Stocks & Shares ISA, Lifetime ISA, or Innovative Finance ISA before I can save it.',
            ];
        }

        $validationError = $this->validateToolInput($input, [
            'account_name' => 'required|string|max:255',
            'current_balance' => 'required|numeric|min:0|max:999999999.99',
            'account_type' => ['nullable', Rule::in(self::SAVINGS_ACCOUNT_TYPES)],
            'institution' => 'nullable|string|max:255',
            'interest_rate' => 'nullable|numeric|min:0|max:20',
            'is_isa' => 'nullable|boolean',
            'is_emergency_fund' => 'nullable|boolean',
            'regular_contribution_amount' => 'nullable|numeric|min:0',
            'isa_subscription_amount' => 'nullable|numeric|min:0|max:999999.99',
            'ownership_type' => ['nullable', Rule::in(['individual', 'joint', 'tenants_in_common', 'trust'])],
            'ownership_percentage' => 'nullable|numeric|min:0|max:100',
            'joint_owner_id' => 'nullable|integer|exists:users,id',
            'trust_id' => 'nullable|integer|exists:trusts,id',
        ]);
        if ($validationError) {
            return $validationError;
        }

        if (in_array($input['account_type'] ?? null, ['cash_isa', 'junior_isa'], true)
            && ($input['ownership_type'] ?? 'individual') !== 'individual') {
            return [
                'error' => true,
                'error_type' => 'validation_failed',
                'message' => 'ISAs can only be individually owned under UK tax rules.',
            ];
        }

        $duplicateCheck = $this->checkForDuplicate(SavingsAccount::class, $user->id, 'account_name', $input['account_name']);
        if ($duplicateCheck) {
            return $duplicateCheck;
        }

        $canonical = app(SavingsAccountNormaliser::class)->fromFyn($input);

        try {
            $account = app(SavingsStore::class)->create(
                $canonical,
                $user,
                IngestSource::FYN_AI
            );
        } catch (StoreValidationException $e) {
            return [
                'error' => true,
                'error_type' => 'validation_failed',
                'errors' => $e->errors,
                'message' => 'Validation failed for savings account.',
            ];
        } catch (TierLimitExceededException $e) {
            return $this->tierLimitResult($e, $user, "You've reached your plan's limit of {$e->hardLimit} savings accounts. To add more, upgrade your plan.");
        }

        $this->invalidateUserCache($user->id);

        // A current account isn't a "savings account" (CSJ: don't call a bank
        // account savings); other types keep their existing wording.
        $typeLabel = $account->account_type === 'current_account' ? 'current account' : 'savings account';

        return [
            'success' => true,
            'created' => true,
            'entity_type' => 'savings_account',
            'entity_id' => $account->id,
            'name' => $account->account_name,
            'persisted_fields' => array_keys($canonical),
            'message' => "I've added your \"{$account->account_name}\" {$typeLabel}.".$this->tierCapNote($user, SavingsStore::ENTITY_KEY, app(SavingsStore::class)->countForUser($user)),
        ];
    }

    private function handleCreateInvestmentAccount(array $input, User $user, bool $isPreview): array
    {
        if ($isPreview) {
            return $this->previewBlocked('investment account');
        }

        $validationError = $this->validateToolInput($input, [
            'account_name' => 'required|string|max:255',
            'current_value' => 'required|numeric|min:0|max:999999999.99',
            'account_type' => ['nullable', Rule::in([
                'stocks_shares_isa', 'lifetime_isa', 'innovative_finance_isa', 'personal_investment_account',
                'onshore_bond', 'offshore_bond', 'vct', 'eis',
                'private_company', 'crowdfunding', 'saye', 'csop',
                'emi', 'unapproved_options', 'rsu', 'other',
                // DB-canonical values (the HTTP form posts these directly)
                'isa', 'gia',
            ])],
            'isa_type' => ['nullable', Rule::in(['stocks_and_shares', 'lifetime', 'innovative_finance'])],
            'provider' => 'nullable|string|max:255',
            'monthly_contribution_amount' => 'nullable|numeric|min:0|max:999999.99',
            'platform_fee_percent' => 'nullable|numeric|min:0|max:10',
            'annual_dividend_income' => 'nullable|numeric|min:0|max:999999999.99',
            'isa_subscription_current_year' => 'nullable|numeric|min:0|max:'.TaxDefaults::ISA_ALLOWANCE,
            'ownership_type' => ['nullable', Rule::in(['individual', 'joint', 'tenants_in_common', 'trust'])],
            'ownership_percentage' => 'nullable|numeric|min:0|max:100',
            'joint_owner_id' => 'nullable|integer|exists:users,id',
        ]);
        if ($validationError) {
            return $validationError;
        }

        $duplicateCheck = $this->checkForDuplicate(InvestmentAccount::class, $user->id, 'account_name', $input['account_name']);
        if ($duplicateCheck) {
            return $duplicateCheck;
        }

        $accountType = $input['account_type'] ?? 'personal_investment_account';

        // AI-facing enums → DB canonical values (mirrors the existing
        // form-fill mapping so AI direct-write and HTTP form path persist
        // identical rows).
        $dbAccountType = match ($accountType) {
            'stocks_shares_isa', 'lifetime_isa', 'innovative_finance_isa' => 'isa',
            'personal_investment_account' => 'gia',
            default => $accountType,
        };

        $isaType = match ($accountType) {
            'stocks_shares_isa' => 'stocks_and_shares',
            'lifetime_isa' => 'lifetime',
            'innovative_finance_isa' => 'innovative_finance',
            'isa' => $input['isa_type'] ?? null,
            default => null,
        };

        // ISAs are individually owned per UK tax rules. Mirrors the HTTP
        // controller's hard rejection.
        $ownershipType = $input['ownership_type'] ?? 'individual';
        if ($dbAccountType === 'isa' && $ownershipType !== 'individual') {
            return [
                'error' => true,
                'error_type' => 'validation_failed',
                'message' => 'ISAs can only be individually owned under UK tax rules.',
            ];
        }

        $payload = [
            'user_id' => $user->id,
            'account_name' => $input['account_name'],
            'provider' => ! empty($input['provider']) ? $input['provider'] : $input['account_name'],
            'account_type' => $dbAccountType,
            'current_value' => (float) $input['current_value'],
            'ownership_type' => $ownershipType,
            'ownership_percentage' => isset($input['ownership_percentage'])
                ? (float) $input['ownership_percentage']
                : 100.00,
            'joint_owner_id' => $input['joint_owner_id'] ?? null,
        ];

        if ($isaType !== null) {
            $payload['isa_type'] = $isaType;
        }

        // Optional numeric / string fields — only persist when supplied.
        $optionalNumeric = [
            'monthly_contribution_amount', 'platform_fee_percent',
            'isa_subscription_current_year',
            'investment_amount', 'number_of_shares', 'price_per_share',
            'units_granted', 'exercise_price', 'market_value_at_grant',
            'current_share_price', 'units_vested', 'units_unvested',
            'cliff_percentage', 'saye_monthly_savings',
            'saye_current_savings_balance', 'scheme_duration_months',
        ];
        foreach ($optionalNumeric as $field) {
            if (isset($input[$field]) && $input[$field] !== '' && is_numeric($input[$field])) {
                $payload[$field] = (float) $input[$field];
            }
        }

        $optionalString = [
            'company_legal_name', 'company_registration_number',
            'crowdfunding_platform', 'instrument_type', 'funding_round',
            'share_class', 'tax_relief_type', 'employer_name',
            'vesting_type',
        ];
        foreach ($optionalString as $field) {
            if (isset($input[$field]) && $input[$field] !== '') {
                $payload[$field] = (string) $input[$field];
            }
        }

        $optionalDate = [
            'bond_purchase_date', 'investment_date', 'grant_date',
            'full_vest_date', 'cliff_date', 'scheme_start_date',
        ];
        foreach ($optionalDate as $field) {
            if (isset($input[$field]) && $input[$field] !== '') {
                $payload[$field] = $input[$field];
            }
        }

        if (isset($input['bond_withdrawal_taken'])) {
            $payload['bond_withdrawal_taken'] = (float) $input['bond_withdrawal_taken'];
        }
        if (isset($input['employer_is_listed'])) {
            $payload['employer_is_listed'] = (bool) $input['employer_is_listed'];
        }

        $canonical = InvestmentAccountNormaliser::fromFyn($payload, $user);

        try {
            $account = app(InvestmentAccountStore::class)->create($canonical, $user, IngestSource::FYN_AI);
        } catch (StoreValidationException $e) {
            return [
                'error' => true,
                'error_type' => 'validation_failed',
                'errors' => $e->errors,
                'message' => 'Validation failed for investment account.',
            ];
        } catch (TierLimitExceededException $e) {
            return $this->tierLimitResult($e, $user, "You've reached your plan's limit of {$e->hardLimit} investment accounts. To add more, upgrade your plan.");
        }

        // Taxable dividend income feeds the user-level figure the tax-strategy
        // engine reads (Dividend Allowance usage, composed taxable income).
        // ISA dividends are tax-free, so they never touch it. Accumulates
        // across accounts; the duplicate check above prevents double-counting
        // a re-captured account.
        if ($dbAccountType !== 'isa'
            && isset($input['annual_dividend_income']) && is_numeric($input['annual_dividend_income'])
            && (float) $input['annual_dividend_income'] > 0) {
            $user->update([
                'annual_dividend_income' => (float) ($user->annual_dividend_income ?? 0)
                    + (float) $input['annual_dividend_income'],
            ]);
        }

        $this->invalidateUserCache($user->id);

        return [
            'success' => true,
            'created' => true,
            'entity_type' => 'investment_account',
            'entity_id' => $account->id,
            'name' => $account->account_name,
            'persisted_fields' => array_keys(array_diff_key($canonical, ['user_id' => null])),
            'message' => "I've added your \"{$account->account_name}\" investment account.".$this->tierCapNote($user, InvestmentAccountStore::ENTITY_KEY, InvestmentAccount::where('user_id', $user->id)->count()),
        ];
    }

    private function handleCreateHolding(array $input, User $user, bool $isPreview): array
    {
        if ($isPreview) {
            return $this->previewBlocked('investment holding');
        }

        $validationError = $this->validateToolInput($input, [
            'account_name' => 'required|string|max:255',
            'security_name' => 'required|string|max:255',
            'ticker' => 'nullable|string|max:20',
            'asset_type' => ['required', Rule::in(['uk_equity', 'us_equity', 'international_equity', 'fund', 'etf', 'bond', 'cash', 'alternative', 'property'])],
            'allocation_percent' => 'nullable|numeric|min:0|max:100',
            'purchase_price' => 'nullable|numeric|min:0|max:999999.99',
            'current_price' => 'nullable|numeric|min:0|max:999999.99',
            'ocf_percent' => 'nullable|numeric|min:0|max:10',
        ]);
        if ($validationError) {
            return $validationError;
        }

        // Look up the investment account by name/provider for this user
        $account = InvestmentAccount::where('user_id', $user->id)
            ->where(function ($query) use ($input) {
                $query->where('provider', 'LIKE', '%'.$input['account_name'].'%')
                    ->orWhere('account_name', 'LIKE', '%'.$input['account_name'].'%');
            })
            ->orderByDesc('id')
            ->first();

        if (! $account) {
            return [
                'error' => true,
                'message' => "I couldn't find an investment account matching \"{$input['account_name']}\". Please create the account first, then I can add holdings to it.",
            ];
        }

        $allocationPct = isset($input['allocation_percent']) ? (float) $input['allocation_percent'] : null;
        $accountCurrentValue = (float) ($account->current_value ?? 0);
        $currentValue = $allocationPct !== null
            ? round(($allocationPct / 100) * $accountCurrentValue, 2)
            : 0.0;

        $payload = [
            'holdable_id' => $account->id,
            'holdable_type' => InvestmentAccount::class,
            'security_name' => $input['security_name'],
            'asset_type' => $input['asset_type'],
            'current_value' => $currentValue,
        ];

        if ($allocationPct !== null) {
            $payload['allocation_percent'] = $allocationPct;
        }
        foreach (['ticker', 'isin'] as $field) {
            if (isset($input[$field]) && $input[$field] !== '') {
                $payload[$field] = $input[$field];
            }
        }
        foreach (['purchase_price', 'current_price', 'ocf_percent'] as $field) {
            if (isset($input[$field]) && is_numeric($input[$field])) {
                $payload[$field] = (float) $input[$field];
            }
        }
        if (isset($input['purchase_date']) && $input['purchase_date'] !== '') {
            $payload['purchase_date'] = $input['purchase_date'];
        }

        $holding = DB::transaction(fn () => Holding::create($payload));

        $this->invalidateUserCache($user->id);

        return [
            'success' => true,
            'created' => true,
            'entity_type' => 'investment_holding',
            'entity_id' => $holding->id,
            'name' => $holding->security_name,
            'persisted_fields' => array_keys($payload),
            'message' => "I've added \"{$holding->security_name}\" to your {$account->provider} account.",
        ];
    }

    private function handleCreatePension(array $input, User $user, bool $isPreview): array
    {
        if ($isPreview) {
            return $this->previewBlocked('pension');
        }

        // WP-1 — the model zero-fills fields it does not know; a retirement
        // age of 0 is impossible and used to fail min:50 validation (handler
        // AND PensionStore layers), silently losing an otherwise-valid
        // pension (the 2026-07-03 "Beta Ltd Workplace Pension" incident).
        // Treat impossible zeros as not-provided.
        foreach (['normal_retirement_age', 'retirement_age', 'state_pension_age'] as $ageField) {
            if (isset($input[$ageField]) && (int) $input[$ageField] === 0) {
                unset($input[$ageField]);
            }
        }

        $validationError = $this->validateToolInput($input, [
            'pension_category' => ['required', Rule::in(['dc', 'db'])],
            'scheme_name' => 'required|string|max:255',
            'current_fund_value' => 'nullable|numeric|min:0|max:999999999.99',
            'employee_contribution_percent' => 'nullable|numeric|min:0|max:100',
            'employer_contribution_percent' => 'nullable|numeric|min:0|max:100',
            'accrued_annual_pension' => 'nullable|numeric|min:0|max:999999.99',
            'normal_retirement_age' => 'nullable|integer|min:50|max:75',
        ]);
        if ($validationError) {
            return $validationError;
        }

        $dcDuplicate = $this->checkForDuplicate(DCPension::class, $user->id, 'scheme_name', $input['scheme_name']);
        if ($dcDuplicate) {
            return $dcDuplicate;
        }
        $dbDuplicate = $this->checkForDuplicate(DBPension::class, $user->id, 'scheme_name', $input['scheme_name']);
        if ($dbDuplicate) {
            return $dbDuplicate;
        }

        $canonical = app(PensionNormaliser::class)->fromFynPension($input);
        $entityType = $canonical['type'] === 'db' ? 'db_pension' : 'dc_pension';

        try {
            $pension = $canonical['type'] === 'db'
                ? app(PensionStore::class)->createDb($canonical, $user, IngestSource::FYN_AI)
                : app(PensionStore::class)->createDc($canonical, $user, IngestSource::FYN_AI);
        } catch (StoreValidationException $e) {
            return [
                'error' => true,
                'error_type' => 'validation_failed',
                'errors' => $e->errors,
                'message' => 'Validation failed for pension.',
            ];
        } catch (TierLimitExceededException $e) {
            return $this->tierLimitResult($e, $user, "You've reached your plan's limit of {$e->hardLimit} pensions. To add more, upgrade your plan.");
        }

        $this->invalidateUserCache($user->id);

        return [
            'success' => true,
            'created' => true,
            'entity_type' => $entityType,
            'entity_id' => $pension->id,
            'name' => $pension->scheme_name,
            'persisted_fields' => array_keys(array_diff_key($canonical, ['type' => null])),
            'message' => "I've added your \"{$pension->scheme_name}\" pension.".$this->tierCapNote($user, PensionStore::ENTITY_KEY, DCPension::where('user_id', $user->id)->count() + DBPension::where('user_id', $user->id)->count()),
        ];
    }

    private function handleCreateProperty(array $input, User $user, bool $isPreview): array
    {
        if ($isPreview) {
            return $this->previewBlocked('property');
        }

        $validationError = $this->validateToolInput($input, [
            'property_type' => ['required', Rule::in(['main_residence', 'secondary_residence', 'buy_to_let'])],
            'current_value' => 'required|numeric|min:0|max:999999999.99',
            'purchase_price' => 'nullable|numeric|min:0|max:999999999.99',
            'ownership_type' => ['nullable', Rule::in(['individual', 'joint', 'tenants_in_common', 'trust'])],
            'ownership_percentage' => 'nullable|numeric|min:0|max:100',
            'joint_owner_id' => 'nullable|integer|exists:users,id',
            'trust_id' => 'nullable|integer|exists:trusts,id',
            'tenure_type' => ['nullable', Rule::in(['freehold', 'leasehold'])],
            'lease_remaining_years' => 'nullable|integer|min:0|max:999',
            'has_mortgage' => 'nullable|boolean',
            'mortgage_outstanding_balance' => 'nullable|numeric|min:0|max:999999999.99',
            'mortgage_interest_rate' => 'nullable|numeric|min:0|max:25',
            'mortgage_monthly_payment' => 'nullable|numeric|min:0|max:999999.99',
            'mortgage_type' => ['nullable', Rule::in(['repayment', 'interest_only', 'mixed'])],
            'mortgage_rate_type' => ['nullable', Rule::in(['fixed', 'variable', 'tracker', 'discount', 'mixed'])],
            'monthly_rental_income' => 'nullable|numeric|min:0|max:999999.99',
            'monthly_council_tax' => 'nullable|numeric|min:0|max:99999.99',
            'monthly_gas' => 'nullable|numeric|min:0|max:99999.99',
            'monthly_electricity' => 'nullable|numeric|min:0|max:99999.99',
            'monthly_water' => 'nullable|numeric|min:0|max:99999.99',
            'monthly_building_insurance' => 'nullable|numeric|min:0|max:99999.99',
            'monthly_contents_insurance' => 'nullable|numeric|min:0|max:99999.99',
            'monthly_service_charge' => 'nullable|numeric|min:0|max:99999.99',
            'monthly_maintenance_reserve' => 'nullable|numeric|min:0|max:99999.99',
            'other_monthly_costs' => 'nullable|numeric|min:0|max:99999.99',
            // Legacy field names from AiToolDefinitions (Anthropic path)
            'outstanding_mortgage' => 'nullable|numeric|min:0|max:999999999.99',
            'mortgage_rate' => 'nullable|numeric|min:0|max:25',
        ]);
        if ($validationError) {
            return $validationError;
        }

        // Pass 4 PR 3 — route Property create through PropertyStore via fromFyn.
        // Address/city/postcode placeholders preserve pre-PR Fyn handler behaviour
        // (the response shape below surfaces $property->address_line_1 to the user,
        // so a non-null display string is expected). DB columns are nullable; this
        // is a UX-display default, not a constraint workaround.
        $propertyType = $input['property_type'];
        $ownershipType = $input['ownership_type'] ?? 'individual';
        $ownershipPct = isset($input['ownership_percentage'])
            ? (float) $input['ownership_percentage']
            : match ($ownershipType) {
                'joint', 'tenants_in_common' => 50.0,
                default => 100.0,
            };

        // array_merge with defaults FIRST + $input SECOND so Fyn's actual values
        // override the placeholders. Equivalent to array_replace($defaults, $input).
        $toolInput = array_merge([
            'address_line_1' => ucfirst(str_replace('_', ' ', $propertyType)),
            'city' => 'Unknown',
            'postcode' => 'N/A',
            'ownership_type' => $ownershipType,
            'ownership_percentage' => $ownershipPct,
            'joint_owner_id' => $input['joint_owner_id'] ?? null,
        ], $input);

        $canonical = app(PropertyNormaliser::class)->fromFyn($toolInput);

        // Mortgage auto-create — flagged via has_mortgage OR by legacy
        // outstanding_mortgage / mortgage_outstanding_balance fields. Routed
        // through MortgageStore (Pass 5 PR 4). Both writes stay wrapped in
        // DB::transaction so a mortgage failure rolls back the property —
        // atomicity invariant carried over from the pre-PR handler. Nested
        // DB::transaction (the store opens its own) is safe under Laravel
        // savepoints.
        $mortgageBalance = $input['mortgage_outstanding_balance'] ?? $input['outstanding_mortgage'] ?? null;
        $hasMortgage = ! empty($input['has_mortgage'])
            || (is_numeric($mortgageBalance) && (float) $mortgageBalance > 0);

        try {
            $property = DB::transaction(function () use ($canonical, $user, $hasMortgage, $input, $mortgageBalance, $ownershipType, $ownershipPct) {
                $property = app(PropertyStore::class)->create(
                    $canonical,
                    $user,
                    IngestSource::FYN_AI
                );

                if ($hasMortgage) {
                    $rate = $input['mortgage_interest_rate'] ?? $input['mortgage_rate'] ?? null;
                    $mortgagePayload = [
                        'property_id' => $property->id,
                        'lender_name' => $input['mortgage_lender'] ?? 'To be completed',
                        'mortgage_type' => $input['mortgage_type'] ?? 'repayment',
                        'rate_type' => $input['mortgage_rate_type'] ?? 'fixed',
                        'outstanding_balance' => is_numeric($mortgageBalance) ? (float) $mortgageBalance : 0,
                        'monthly_payment' => 0,
                        'ownership_type' => $ownershipType,
                        'ownership_percentage' => $ownershipPct,
                        'joint_owner_id' => $property->joint_owner_id,
                    ];
                    if (is_numeric($rate)) {
                        $mortgagePayload['interest_rate'] = (float) $rate;
                    }
                    if (isset($input['mortgage_monthly_payment']) && is_numeric($input['mortgage_monthly_payment'])) {
                        $mortgagePayload['monthly_payment'] = (float) $input['mortgage_monthly_payment'];
                    }
                    if (isset($input['mortgage_start_date']) && $input['mortgage_start_date'] !== '') {
                        $mortgagePayload['start_date'] = $input['mortgage_start_date'];
                    }
                    if (isset($input['mortgage_maturity_date']) && $input['mortgage_maturity_date'] !== '') {
                        $mortgagePayload['maturity_date'] = $input['mortgage_maturity_date'];
                    }

                    // Route through MortgageStore (PR 4). Nested DB::transaction is safe
                    // (Laravel uses savepoints), and TierLimitExceededException /
                    // StoreValidationException bubble up to the outer catch blocks.
                    $mortgageCanonical = MortgageNormaliser::fromFyn($mortgagePayload, $user);
                    app(MortgageStore::class)->create($mortgageCanonical, $user, IngestSource::FYN_AI);
                }

                return $property;
            });
        } catch (StoreValidationException $e) {
            return [
                'error' => true,
                'error_type' => 'validation_failed',
                'errors' => $e->errors,
                'message' => 'Validation failed for property.',
            ];
        } catch (TierLimitExceededException $e) {
            return $this->tierLimitResult($e, $user, "You've reached your plan's property limit. To add more, upgrade your plan.");
        }

        $this->invalidateUserCache($user->id);

        return [
            'success' => true,
            'created' => true,
            'entity_type' => 'property',
            'entity_id' => $property->id,
            'name' => $property->address_line_1,
            'persisted_fields' => array_keys($canonical),
            'message' => "I've added your property at \"{$property->address_line_1}\".",
        ];
    }

    private function handleCreateMortgage(array $input, User $user, bool $isPreview): array
    {
        if ($isPreview) {
            return $this->previewBlocked('mortgage');
        }

        $validationError = $this->validateToolInput($input, [
            'outstanding_balance' => 'required|numeric|min:0|max:999999999.99',
            'interest_rate' => 'nullable|numeric|min:0|max:25',
            'mortgage_type' => ['nullable', Rule::in(['repayment', 'interest_only', 'mixed'])],
            'rate_type' => ['nullable', Rule::in(['fixed', 'variable', 'tracker', 'discount', 'mixed'])],
            'monthly_payment' => 'nullable|numeric|min:0|max:999999.99',
            'remaining_term_months' => 'nullable|integer|min:1|max:480',
            'start_date' => 'nullable|date',
            'maturity_date' => 'nullable|date',
            'lender_name' => 'nullable|string|max:255',
            'property_address_hint' => 'nullable|string|max:500',
        ]);
        if ($validationError) {
            return $validationError;
        }

        // Resolve target property: fuzzy match on hint, else fall back to
        // the user's only property if exactly one exists.
        $hint = trim((string) ($input['property_address_hint'] ?? ''));
        $propertyQuery = Property::where('user_id', $user->id);
        if ($hint !== '') {
            $like = '%'.$hint.'%';
            $propertyQuery->where(function ($q) use ($like) {
                $q->where('address_line_1', 'LIKE', $like)
                    ->orWhere('postcode', 'LIKE', $like)
                    ->orWhere('city', 'LIKE', $like);
            });
        }
        $property = $propertyQuery->orderBy('id')->first();

        if (! $property) {
            $userProperties = Property::where('user_id', $user->id)->count();

            return [
                'error' => true,
                'error_type' => $userProperties === 0 ? 'missing_property' : 'property_match_failed',
                'message' => $userProperties === 0
                    ? "I couldn't find a property to attach this mortgage to. Please add the property first."
                    : "I couldn't match \"{$hint}\" to one of your properties. Please be more specific.",
            ];
        }

        $payload = [
            'user_id' => $user->id,
            'property_id' => $property->id,
            'lender_name' => $input['lender_name'] ?? 'To be completed',
            'mortgage_type' => $input['mortgage_type'] ?? 'repayment',
            'rate_type' => $input['rate_type'] ?? 'fixed',
            'outstanding_balance' => (float) $input['outstanding_balance'],
            'ownership_type' => $property->ownership_type,
            'ownership_percentage' => (float) $property->ownership_percentage,
            'joint_owner_id' => $property->joint_owner_id,
        ];

        if (isset($input['lender_name']) && $input['lender_name'] !== '') {
            $payload['lender_name'] = $input['lender_name'];
        }
        if (isset($input['interest_rate']) && is_numeric($input['interest_rate'])) {
            $payload['interest_rate'] = (float) $input['interest_rate'];
        }
        if (isset($input['monthly_payment']) && is_numeric($input['monthly_payment'])) {
            $payload['monthly_payment'] = (float) $input['monthly_payment'];
        }
        if (isset($input['remaining_term_months']) && is_numeric($input['remaining_term_months'])) {
            $payload['remaining_term_months'] = (int) $input['remaining_term_months'];
        }
        foreach (['start_date', 'maturity_date'] as $f) {
            if (isset($input[$f]) && $input[$f] !== '') {
                $payload[$f] = $input[$f];
            }
        }

        $canonical = MortgageNormaliser::fromFyn($payload, $user);

        try {
            $mortgage = app(MortgageStore::class)->create($canonical, $user, IngestSource::FYN_AI);
        } catch (StoreValidationException $e) {
            return [
                'error' => true,
                'error_type' => 'validation_failed',
                'errors' => $e->errors,
                'message' => 'Validation failed for mortgage.',
            ];
        } catch (TierLimitExceededException $e) {
            return $this->tierLimitResult($e, $user, "You've reached your plan's limit of {$e->hardLimit} mortgages. To add more, upgrade your plan.");
        }

        $this->invalidateUserCache($user->id);

        return [
            'success' => true,
            'created' => true,
            'entity_type' => 'mortgage',
            'entity_id' => $mortgage->id,
            'name' => $mortgage->lender_name ?? 'Mortgage',
            'persisted_fields' => array_keys(array_diff_key($canonical, ['user_id' => null])),
            'message' => "I've added the mortgage on \"{$property->address_line_1}\".",
        ];
    }

    private function handleCreateProtectionPolicy(array $input, User $user, bool $isPreview): array
    {
        if ($isPreview) {
            return $this->previewBlocked('protection policy');
        }

        $validationError = $this->validateToolInput($input, [
            'policy_type' => ['required', Rule::in(['level_term', 'term', 'whole_of_life', 'decreasing_term', 'family_income_benefit', 'standalone_ci', 'accelerated_ci', 'income_protection'])],
            'sum_assured' => 'nullable|numeric|min:0|max:999999999.99',
            'benefit_amount' => 'nullable|numeric|min:0|max:999999.99',
            'premium_amount' => 'nullable|numeric|min:0|max:99999.99',
            'policy_term_years' => 'nullable|integer|min:1|max:50',
        ]);
        if ($validationError) {
            return $validationError;
        }

        $policyType = $input['policy_type'];

        // Choose the right model + entity_type per category. Each protection
        // category has its own table and bespoke field set.
        $category = match ($policyType) {
            'income_protection' => 'income_protection',
            'standalone_ci', 'accelerated_ci' => 'critical_illness',
            default => 'life',
        };

        $sumAssured = isset($input['sum_assured']) ? (float) $input['sum_assured'] : 0.0;
        $benefitAmount = isset($input['benefit_amount']) ? (float) $input['benefit_amount'] : 0.0;
        $providerLabel = $input['provider'] ?? str_replace('_', ' ', $policyType);

        // Resolve any natural-language date the LLM passed through. We do NOT
        // ask the LLM to format dates as ISO 8601 — it's an unreliable thing
        // to delegate. The handler accepts whatever phrase the user said and
        // parses it deterministically (Carbon handles "today", "yesterday",
        // "26 April 2026", "last Monday", etc.). Bad strings drop to null
        // rather than 500 the request.
        foreach (['policy_start_date', 'policy_end_date'] as $dateField) {
            if (isset($input[$dateField]) && is_string($input[$dateField]) && $input[$dateField] !== '') {
                try {
                    $input[$dateField] = Carbon::parse($input[$dateField])->toDateString();
                } catch (\Throwable $e) {
                    unset($input[$dateField]);
                }
            }
        }

        if ($category === 'life') {
            $payload = [
                'user_id' => $user->id,
                // map generic 'term' onto canonical 'level_term'
                'policy_type' => $policyType === 'term' ? 'level_term' : $policyType,
                'sum_assured' => $policyType === 'family_income_benefit' && $benefitAmount > 0
                    ? $benefitAmount
                    : $sumAssured,
                'premium_frequency' => $input['premium_frequency'] ?? 'monthly',
            ];
            foreach (['provider', 'policy_number', 'policy_start_date', 'policy_end_date'] as $f) {
                if (isset($input[$f]) && $input[$f] !== '') {
                    $payload[$f] = $input[$f];
                }
            }
            foreach (['premium_amount', 'start_value', 'decreasing_rate', 'indexation_rate'] as $f) {
                if (isset($input[$f]) && is_numeric($input[$f])) {
                    $payload[$f] = (float) $input[$f];
                }
            }
            if (isset($input['policy_term_years']) && is_numeric($input['policy_term_years'])) {
                $payload['policy_term_years'] = (int) $input['policy_term_years'];
            }
            foreach (['in_trust', 'is_mortgage_protection', 'joint_life'] as $f) {
                if (isset($input[$f])) {
                    $payload[$f] = (bool) $input[$f];
                }
            }

            // BS-17 in-turn idempotency: grok-4.3 occasionally emits
            // create_protection_policy twice for the same entity inside one
            // multi-entity message. Without this guard the second tool call
            // creates a duplicate row before the LLM has a chance to see the
            // first result. The check is scoped to a 60s window so genuine
            // separate-session adds are unaffected.
            $existing = LifeInsurancePolicy::where('user_id', $user->id)
                ->where('policy_type', $payload['policy_type'])
                ->where('sum_assured', $payload['sum_assured'])
                ->where('provider', $payload['provider'] ?? null)
                ->where('created_at', '>', now()->subMinute())
                ->first();
            if ($existing !== null) {
                $this->invalidateUserCache($user->id);

                return [
                    'success' => true,
                    'created' => false,
                    'duplicate' => true,
                    'entity_type' => 'life_insurance_policy',
                    'entity_id' => $existing->id,
                    'name' => (string) $providerLabel,
                    'persisted_fields' => [],
                    'message' => "Already added — skipped duplicate \"{$providerLabel}\" policy.",
                ];
            }

            $policy = DB::transaction(fn () => LifeInsurancePolicy::create($payload));
            $entityType = 'life_insurance_policy';
        } elseif ($category === 'critical_illness') {
            // CI table's enum uses bare values (standalone | accelerated)
            // — strip the AI tool's `_ci` suffix.
            $ciType = match ($policyType) {
                'standalone_ci' => 'standalone',
                'accelerated_ci' => 'accelerated',
                default => 'standalone',
            };
            $payload = [
                'user_id' => $user->id,
                'policy_type' => $ciType,
                'sum_assured' => $sumAssured,
                'premium_frequency' => $input['premium_frequency'] ?? 'monthly',
            ];
            foreach (['provider', 'policy_number', 'policy_start_date'] as $f) {
                if (isset($input[$f]) && $input[$f] !== '') {
                    $payload[$f] = $input[$f];
                }
            }
            if (isset($input['premium_amount']) && is_numeric($input['premium_amount'])) {
                $payload['premium_amount'] = (float) $input['premium_amount'];
            }
            if (isset($input['policy_term_years']) && is_numeric($input['policy_term_years'])) {
                $payload['policy_term_years'] = (int) $input['policy_term_years'];
            }
            if (isset($input['conditions_covered']) && is_array($input['conditions_covered'])) {
                $payload['conditions_covered'] = $input['conditions_covered'];
            }

            // BS-17 in-turn idempotency — see life-branch comment above.
            $existing = CriticalIllnessPolicy::where('user_id', $user->id)
                ->where('policy_type', $payload['policy_type'])
                ->where('sum_assured', $payload['sum_assured'])
                ->where('provider', $payload['provider'] ?? null)
                ->where('created_at', '>', now()->subMinute())
                ->first();
            if ($existing !== null) {
                $this->invalidateUserCache($user->id);

                return [
                    'success' => true,
                    'created' => false,
                    'duplicate' => true,
                    'entity_type' => 'critical_illness_policy',
                    'entity_id' => $existing->id,
                    'name' => (string) $providerLabel,
                    'persisted_fields' => [],
                    'message' => "Already added — skipped duplicate \"{$providerLabel}\" policy.",
                ];
            }

            $policy = DB::transaction(fn () => CriticalIllnessPolicy::create($payload));
            $entityType = 'critical_illness_policy';
        } else {
            $payload = [
                'user_id' => $user->id,
                'benefit_amount' => $benefitAmount > 0 ? $benefitAmount : $sumAssured,
                'premium_frequency' => $input['premium_frequency'] ?? 'monthly',
                'benefit_frequency' => $input['benefit_frequency'] ?? 'monthly',
            ];
            foreach (['provider', 'policy_number', 'occupation_class', 'policy_start_date'] as $f) {
                if (isset($input[$f]) && $input[$f] !== '') {
                    $payload[$f] = $input[$f];
                }
            }
            if (isset($input['premium_amount']) && is_numeric($input['premium_amount'])) {
                $payload['premium_amount'] = (float) $input['premium_amount'];
            }
            foreach (['deferred_period_weeks', 'benefit_period_months'] as $f) {
                if (isset($input[$f]) && is_numeric($input[$f])) {
                    $payload[$f] = (int) $input[$f];
                }
            }

            // BS-17 in-turn idempotency — see life-branch comment above.
            $existing = IncomeProtectionPolicy::where('user_id', $user->id)
                ->where('benefit_amount', $payload['benefit_amount'])
                ->where('provider', $payload['provider'] ?? null)
                ->where('created_at', '>', now()->subMinute())
                ->first();
            if ($existing !== null) {
                $this->invalidateUserCache($user->id);

                return [
                    'success' => true,
                    'created' => false,
                    'duplicate' => true,
                    'entity_type' => 'income_protection_policy',
                    'entity_id' => $existing->id,
                    'name' => (string) $providerLabel,
                    'persisted_fields' => [],
                    'message' => "Already added — skipped duplicate \"{$providerLabel}\" policy.",
                ];
            }

            $policy = DB::transaction(fn () => IncomeProtectionPolicy::create($payload));
            $entityType = 'income_protection_policy';
        }

        $this->invalidateUserCache($user->id);

        return [
            'success' => true,
            'created' => true,
            'entity_type' => $entityType,
            'entity_id' => $policy->id,
            'name' => (string) $providerLabel,
            'persisted_fields' => array_keys(array_diff_key($payload, ['user_id' => null])),
            'message' => "I've added your \"{$providerLabel}\" protection policy.",
        ];
    }

    private function handleCreateEstateAsset(array $input, User $user, bool $isPreview): array
    {
        if ($isPreview) {
            return $this->previewBlocked('estate asset');
        }

        $validationError = $this->validateToolInput($input, [
            'asset_name' => 'required|string|max:255',
            'asset_type' => ['required', Rule::in(['property', 'pension', 'investment', 'business', 'other'])],
            'current_value' => 'required|numeric|min:0|max:999999999.99',
        ]);
        if ($validationError) {
            return $validationError;
        }

        $payload = [
            'user_id' => $user->id,
            'asset_name' => $input['asset_name'],
            'asset_type' => $input['asset_type'],
            'current_value' => (float) $input['current_value'],
            'ownership_type' => 'individual',
            'valuation_date' => now()->toDateString(),
            'is_iht_exempt' => (bool) ($input['is_iht_exempt'] ?? false),
        ];

        if (isset($input['exemption_reason']) && $input['exemption_reason'] !== '') {
            $payload['exemption_reason'] = $input['exemption_reason'];
        }
        if (isset($input['liquidity']) && $input['liquidity'] !== '') {
            $payload['liquidity'] = $input['liquidity'];
        }

        $asset = DB::transaction(fn () => Asset::create($payload));

        $this->invalidateUserCache($user->id);

        return [
            'success' => true,
            'created' => true,
            'entity_type' => 'estate_asset',
            'entity_id' => $asset->id,
            'name' => $asset->asset_name,
            'persisted_fields' => array_keys(array_diff_key($payload, ['user_id' => null])),
            'message' => "I've added \"{$asset->asset_name}\" to your estate.",
        ];
    }

    private function handleCreateEstateLiability(array $input, User $user, bool $isPreview): array
    {
        if ($isPreview) {
            return $this->previewBlocked('liability');
        }

        $validationError = $this->validateToolInput($input, [
            'liability_name' => 'required|string|max:255',
            'liability_type' => ['required', Rule::in(['loan', 'personal_loan', 'credit_card', 'mortgage', 'student_loan', 'secured_loan', 'overdraft', 'hire_purchase', 'business_loan', 'other'])],
            'current_balance' => 'required|numeric|min:0|max:999999999.99',
            'monthly_payment' => 'nullable|numeric|min:0|max:999999.99',
            'interest_rate' => 'nullable|numeric|min:0|max:50',
            'ownership_type' => ['nullable', Rule::in(['individual', 'joint', 'tenants_in_common', 'trust'])],
            'ownership_percentage' => 'nullable|numeric|min:0|max:100',
            'joint_owner_id' => 'nullable|integer|exists:users,id',
            'trust_id' => 'nullable|integer|exists:trusts,id',
        ]);
        if ($validationError) {
            return $validationError;
        }

        $dbLiabilityType = match ($input['liability_type']) {
            'loan' => 'personal_loan',
            default => $input['liability_type'],
        };

        $payload = [
            'user_id' => $user->id,
            'liability_name' => $input['liability_name'],
            'liability_type' => $dbLiabilityType,
            'current_balance' => (float) $input['current_balance'],
            'ownership_type' => $input['ownership_type'] ?? 'individual',
            'ownership_percentage' => isset($input['ownership_percentage']) ? (float) $input['ownership_percentage'] : 100.0,
            'joint_owner_id' => $input['joint_owner_id'] ?? null,
            'trust_id' => $input['trust_id'] ?? null,
        ];

        if (isset($input['monthly_payment']) && is_numeric($input['monthly_payment'])) {
            $payload['monthly_payment'] = (float) $input['monthly_payment'];
        }
        if (isset($input['interest_rate']) && is_numeric($input['interest_rate'])) {
            $payload['interest_rate'] = (float) $input['interest_rate'];
        }
        foreach (['secured_against', 'mortgage_type', 'notes'] as $f) {
            if (isset($input[$f]) && $input[$f] !== '') {
                $payload[$f] = $input[$f];
            }
        }
        foreach (['maturity_date', 'fixed_until'] as $f) {
            if (isset($input[$f]) && $input[$f] !== '') {
                $payload[$f] = $input[$f];
            }
        }

        $liability = app(LiabilityStore::class)->create($payload, $user, IngestSource::FYN_AI);

        $this->invalidateUserCache($user->id);

        return [
            'success' => true,
            'created' => true,
            'entity_type' => 'estate_liability',
            'entity_id' => $liability->id,
            'name' => $liability->liability_name,
            'persisted_fields' => array_keys(array_diff_key($payload, ['user_id' => null])),
            'message' => "I've added your \"{$liability->liability_name}\" liability.",
        ];
    }

    private function handleCreateEstateGift(array $input, User $user, bool $isPreview): array
    {
        if ($isPreview) {
            return $this->previewBlocked('estate gift');
        }

        $validationError = $this->validateToolInput($input, [
            'gift_date' => 'required|date',
            'recipient' => 'required|string|max:255',
            'gift_type' => ['required', Rule::in(['pet', 'clt', 'exempt', 'small_gift', 'annual_exemption'])],
            'gift_value' => 'required|numeric|min:0|max:999999999.99',
        ]);
        if ($validationError) {
            return $validationError;
        }

        $recipient = $this->resolveFamilyNames($input['recipient'], $user) ?? $input['recipient'];

        $payload = [
            'user_id' => $user->id,
            'gift_date' => substr($input['gift_date'], 0, 10),
            'recipient' => $recipient,
            'gift_type' => $input['gift_type'] ?? 'pet',
            'gift_value' => (float) $input['gift_value'],
        ];

        if (isset($input['notes']) && $input['notes'] !== '') {
            $payload['notes'] = $input['notes'];
        }

        $gift = DB::transaction(fn () => Gift::create($payload));

        $this->invalidateUserCache($user->id);

        return [
            'success' => true,
            'created' => true,
            'entity_type' => 'estate_gift',
            'entity_id' => $gift->id,
            'name' => $recipient,
            'persisted_fields' => array_keys(array_diff_key($payload, ['user_id' => null])),
            'message' => "I've recorded your gift of £".number_format((float) $input['gift_value'])." to {$recipient}.",
        ];
    }

    // ─── Estate planning documents (Will + LPA) ──────────────────────

    private function handleCreateWill(array $input, User $user, bool $isPreview): array
    {
        if ($isPreview) {
            return $this->previewBlocked('will');
        }

        $validationError = $this->validateToolInput($input, [
            'executor_name' => 'required|string|max:255',
            'residuary_beneficiary' => 'nullable|string|max:255',
            'guardian_for_minors' => 'nullable|string|max:255',
            'specific_gifts' => 'nullable|string|max:2000',
            'spouse_primary_beneficiary' => 'nullable|boolean',
        ]);
        if ($validationError) {
            return $validationError;
        }

        // Default spouse_primary_beneficiary true for married users when not specified.
        $spousePrimary = array_key_exists('spouse_primary_beneficiary', $input)
            ? (bool) $input['spouse_primary_beneficiary']
            : in_array((string) $user->marital_status, ['married', 'civil_partnership'], true);

        $will = Will::updateOrCreate(
            ['user_id' => $user->id],
            [
                'has_will' => true,
                'executor_name' => $input['executor_name'],
                'residuary_beneficiary' => $input['residuary_beneficiary'] ?? null,
                'guardian_for_minors' => $input['guardian_for_minors'] ?? null,
                'specific_gifts' => $input['specific_gifts'] ?? null,
                'spouse_primary_beneficiary' => $spousePrimary,
                'will_last_updated' => now()->toDateString(),
            ],
        );

        $this->invalidateUserCache($user->id);

        return [
            'action' => 'record_saved',
            'entity_type' => 'will',
            'id' => $will->id,
            'message' => 'Recorded your will details.',
        ];
    }

    private function handleUpdateWill(array $input, User $user, bool $isPreview): array
    {
        if ($isPreview) {
            return $this->previewBlocked('will');
        }

        $validationError = $this->validateToolInput($input, [
            'executor_name' => 'nullable|string|max:255',
            'residuary_beneficiary' => 'nullable|string|max:255',
            'guardian_for_minors' => 'nullable|string|max:255',
            'specific_gifts' => 'nullable|string|max:2000',
            'spouse_primary_beneficiary' => 'nullable|boolean',
        ]);
        if ($validationError) {
            return $validationError;
        }

        $will = Will::where('user_id', $user->id)->first();

        if ($will === null) {
            // No existing will to update — fall through to create semantics.
            return $this->handleCreateWill($input, $user, $isPreview);
        }

        $updates = array_filter(
            [
                'executor_name' => $input['executor_name'] ?? null,
                'residuary_beneficiary' => $input['residuary_beneficiary'] ?? null,
                'guardian_for_minors' => $input['guardian_for_minors'] ?? null,
                'specific_gifts' => $input['specific_gifts'] ?? null,
                'spouse_primary_beneficiary' => array_key_exists('spouse_primary_beneficiary', $input)
                    ? (bool) $input['spouse_primary_beneficiary']
                    : null,
            ],
            fn ($v) => $v !== null,
        );

        if ($updates === []) {
            return [
                'error' => true,
                'error_type' => 'nothing_to_update',
                'message' => 'No will fields were provided for update.',
            ];
        }

        $updates['will_last_updated'] = now()->toDateString();

        $will->update($updates);

        $this->invalidateUserCache($user->id);

        return [
            'action' => 'record_saved',
            'entity_type' => 'will',
            'id' => $will->id,
            'message' => 'Updated your will details.',
        ];
    }

    private function handleCreatePowerOfAttorney(array $input, User $user, bool $isPreview): array
    {
        if ($isPreview) {
            return $this->previewBlocked('lasting power of attorney');
        }

        $validationError = $this->validateToolInput($input, [
            'lpa_type' => ['required', Rule::in(['property_financial', 'health_welfare'])],
            'primary_attorney_name' => 'required|string|max:255',
            'replacement_attorney_name' => 'nullable|string|max:255',
            'status' => ['nullable', Rule::in(['draft', 'registered'])],
            'opg_reference' => 'nullable|string|max:50',
        ]);
        if ($validationError) {
            return $validationError;
        }

        $donorName = trim(($user->first_name ?? '').' '.($user->surname ?? '')) ?: ($user->name ?? '');

        $lpa = DB::transaction(function () use ($input, $user, $donorName) {
            $lpa = LastingPowerOfAttorney::create([
                'user_id' => $user->id,
                'lpa_type' => $input['lpa_type'],
                'status' => $input['status'] ?? 'draft',
                'source' => 'created',
                'donor_full_name' => $donorName,
                'donor_date_of_birth' => $user->date_of_birth,
                'opg_reference' => $input['opg_reference'] ?? null,
                'is_registered_with_opg' => ($input['status'] ?? 'draft') === 'registered',
                'registration_date' => ($input['status'] ?? 'draft') === 'registered' ? now()->toDateString() : null,
            ]);

            LpaAttorney::create([
                'lasting_power_of_attorney_id' => $lpa->id,
                'attorney_type' => 'primary',
                'full_name' => $input['primary_attorney_name'],
                'sort_order' => 1,
            ]);

            if (! empty($input['replacement_attorney_name'])) {
                LpaAttorney::create([
                    'lasting_power_of_attorney_id' => $lpa->id,
                    'attorney_type' => 'replacement',
                    'full_name' => $input['replacement_attorney_name'],
                    'sort_order' => 2,
                ]);
            }

            return $lpa;
        });

        $this->invalidateUserCache($user->id);

        $typeLabel = $lpa->lpa_type === 'property_financial' ? 'Property & Financial Affairs' : 'Health & Welfare';

        return [
            'action' => 'record_saved',
            'entity_type' => 'lasting_power_of_attorney',
            'id' => $lpa->id,
            'message' => "Recorded your {$typeLabel} LPA.",
        ];
    }

    private function handleUpdatePowerOfAttorney(array $input, User $user, bool $isPreview): array
    {
        if ($isPreview) {
            return $this->previewBlocked('lasting power of attorney');
        }

        $validationError = $this->validateToolInput($input, [
            'lpa_id' => 'required|integer|exists:lasting_powers_of_attorney,id',
            'status' => ['nullable', Rule::in(['draft', 'registered'])],
            'opg_reference' => 'nullable|string|max:50',
            'primary_attorney_name' => 'nullable|string|max:255',
            'replacement_attorney_name' => 'nullable|string|max:255',
        ]);
        if ($validationError) {
            return $validationError;
        }

        $lpa = LastingPowerOfAttorney::where('user_id', $user->id)
            ->find($input['lpa_id']);

        if ($lpa === null) {
            return [
                'error' => true,
                'error_type' => 'not_found',
                'message' => 'No LPA with that ID found for this user.',
            ];
        }

        DB::transaction(function () use ($input, $lpa) {
            $updates = [];

            if (array_key_exists('status', $input) && $input['status'] !== null) {
                $updates['status'] = $input['status'];
                $updates['is_registered_with_opg'] = $input['status'] === 'registered';
                if ($input['status'] === 'registered' && $lpa->registration_date === null) {
                    $updates['registration_date'] = now()->toDateString();
                }
            }

            if (array_key_exists('opg_reference', $input) && $input['opg_reference'] !== null) {
                $updates['opg_reference'] = $input['opg_reference'];
            }

            if ($updates !== []) {
                $lpa->update($updates);
            }

            if (! empty($input['primary_attorney_name'])) {
                $primary = $lpa->attorneys()->where('attorney_type', 'primary')->first();
                if ($primary !== null) {
                    $primary->update(['full_name' => $input['primary_attorney_name']]);
                } else {
                    LpaAttorney::create([
                        'lasting_power_of_attorney_id' => $lpa->id,
                        'attorney_type' => 'primary',
                        'full_name' => $input['primary_attorney_name'],
                        'sort_order' => 1,
                    ]);
                }
            }

            if (! empty($input['replacement_attorney_name'])) {
                $replacement = $lpa->attorneys()->where('attorney_type', 'replacement')->first();
                if ($replacement !== null) {
                    $replacement->update(['full_name' => $input['replacement_attorney_name']]);
                } else {
                    LpaAttorney::create([
                        'lasting_power_of_attorney_id' => $lpa->id,
                        'attorney_type' => 'replacement',
                        'full_name' => $input['replacement_attorney_name'],
                        'sort_order' => 2,
                    ]);
                }
            }
        });

        $this->invalidateUserCache($user->id);

        return [
            'action' => 'record_saved',
            'entity_type' => 'lasting_power_of_attorney',
            'id' => $lpa->id,
            'message' => 'Updated your LPA.',
        ];
    }

    // ─── Tool execution helpers ──────────────────────────────────────

    private function previewBlocked(string $entityType): array
    {
        return ['blocked' => true, 'reason' => "You are in preview mode. Creating a {$entityType} is not available — please create a real account to save data."];
    }

    private function validateToolInput(array $input, array $rules): ?array
    {
        $validator = Validator::make($input, $rules);
        if ($validator->fails()) {
            return ['error' => true, 'error_type' => 'validation_failed', 'message' => $validator->errors()->first()];
        }

        return null;
    }

    /**
     * Resolve generic family/role references to actual names.
     *
     * "my wife" → "Jane Smith" or "(Wife) name to be confirmed"
     * "myself" / "me" / "I" → "John Smith"
     * "my children" → "Tom Smith, Emily Smith" or "(Children) names to be confirmed"
     * "my solicitor" → "(Solicitor) name to be confirmed" (unless a name follows)
     * "my brother" → "(Brother) name to be confirmed" (unless a name follows)
     */
    private function resolveFamilyNames(?string $text, User $user): ?string
    {
        if (! $text) {
            return null;
        }

        $userName = trim($user->first_name.' '.$user->surname);
        $spouse = $user->spouse;
        $spouseFullName = $spouse ? trim($spouse->first_name.' '.$spouse->surname) : null;

        $children = FamilyMember::where('user_id', $user->id)
            ->where('relationship', 'child')
            ->get();
        $childNames = $children->count() > 0
            ? $children->map(fn ($c) => trim($c->first_name.' '.($c->last_name ?? '')))->implode(', ')
            : null;

        // Split on comma and " and " to handle "my wife and children", "myself, solicitor"
        $parts = preg_split('/\s*,\s*|\s+and\s+/i', $text);
        $parts = array_map('trim', $parts);
        $resolved = [];

        foreach ($parts as $part) {
            $lower = strtolower($part);

            // Self references — full name
            if (in_array($lower, ['myself', 'me', 'i', 'self']) || $lower === strtolower($user->first_name)) {
                $resolved[] = $userName;

                continue;
            }

            // Spouse references — full name or placeholder
            if (preg_match('/^(my\s+)?(wife|husband|partner|spouse)$/i', $lower)) {
                $resolved[] = $spouseFullName ?? '(Spouse) name to be confirmed';

                continue;
            }

            // "my wife Jane" / "wife Sarah" — spouse role + name, keep the name
            if (preg_match('/^(my\s+)?(wife|husband|partner|spouse)\s+(.+)$/i', $part, $m)) {
                $resolved[] = trim($m[3]);

                continue;
            }

            // Children references — expand to individual names or placeholder
            if (preg_match('/^(my\s+|our\s+)?(children|kids)$/i', $lower)) {
                $resolved[] = $childNames ?? '(Children) names to be confirmed';

                continue;
            }

            // "wife and children" / "wife and kids" combo
            if (preg_match('/^(my\s+|our\s+)?(wife|husband|partner|spouse)\s+and\s+(my\s+|our\s+)?(children|kids)$/i', $lower)) {
                $spousePart = $spouseFullName ?? '(Spouse) name to be confirmed';
                $childPart = $childNames ?? '(Children) names to be confirmed';
                $resolved[] = $spousePart.', '.$childPart;

                continue;
            }

            // Role references without a proper name — add placeholder
            if (preg_match('/^(my\s+|the\s+|the\s+family\s+|our\s+)?(solicitor|executor|accountant|brother|sister|mother|father|son|daughter)$/i', $lower, $m)) {
                $role = ucfirst(strtolower($m[2]));
                $resolved[] = '('.$role.') name to be confirmed';

                continue;
            }

            // "my brother David" / "my solicitor Mr Hughes" — strip "my"/"the" prefix, keep the name + role
            if (preg_match('/^(my|the|our|the\s+family)\s+(solicitor|executor|accountant|brother|sister|mother|father|son|daughter)\s+(.+)$/i', $part, $m)) {
                $role = ucfirst(strtolower($m[2]));
                $name = trim($m[3]);
                $resolved[] = $name.' ('.$role.')';

                continue;
            }

            // Generic "my X" — strip "my" prefix, keep the rest
            if (preg_match('/^(my|the|our)\s+(.+)$/i', $part, $m)) {
                $resolved[] = trim($m[2]);

                continue;
            }

            // Already a name or specific text — keep as-is
            $resolved[] = $part;
        }

        // Clean up: remove empty entries, trim whitespace
        $result = implode(', ', array_filter(array_map('trim', $resolved)));

        return $result ?: null;
    }

    private function checkForDuplicate(string $modelClass, int $userId, string $nameField, string $nameValue): ?array
    {
        // SECURITY: $allowedColumns is a whitelist preventing SQL injection in the whereRaw below.
        // NEVER add user-supplied strings to this array — only hardcoded column names.
        $allowedColumns = ['first_name', 'surname', 'name', 'email', 'asset_name', 'liability_name', 'trust_name', 'scheme_name', 'provider', 'account_name', 'policy_name', 'gift_type'];
        if (! in_array($nameField, $allowedColumns, true)) {
            throw new \InvalidArgumentException("Invalid column name: {$nameField}");
        }

        $existing = $modelClass::where('user_id', $userId)
            ->whereRaw('LOWER('.$nameField.') = ?', [strtolower($nameValue)])
            ->first();

        if ($existing) {
            return ['warning' => true, 'message' => "A similar record '{$existing->{$nameField}}' already exists. The new record was not created to avoid duplication.", 'existing_id' => $existing->id];
        }

        return null;
    }

    private function invalidateModuleCache(int $userId, string $module): void
    {
        $this->netWorthService->invalidateCache($userId);

        $cachePatterns = [
            'savings' => ["v1_savings_{$userId}"],
            'investment' => ["v1_investment_{$userId}"],
            'retirement' => ["v1_retirement_{$userId}"],
            'property' => ["v1_property_{$userId}"],
            'protection' => ["v1_protection_{$userId}"],
            'estate' => ["v1_estate_{$userId}"],
        ];

        foreach ($cachePatterns[$module] ?? [] as $key) {
            Cache::forget($key);
            Cache::forget("{$key}_analysis");
            Cache::forget("{$key}_recommendations");
        }

        Cache::forget("v1_coordinating_{$userId}_analysis");
        Cache::forget("ai_financial_context_{$userId}");
    }

    private function resolvePropertyId(User $user, ?string $hint): ?int
    {
        $properties = Property::where('user_id', $user->id)->get();

        if ($properties->isEmpty()) {
            return null;
        }

        if ($properties->count() === 1) {
            return $properties->first()->id;
        }

        if (! $hint) {
            $main = $properties->firstWhere('property_type', 'main_residence');

            return $main?->id ?? $properties->first()->id;
        }

        $hintLower = Str::lower($hint);

        if (Str::contains($hintLower, ['main', 'home', 'primary', 'residence'])) {
            $match = $properties->firstWhere('property_type', 'main_residence');
            if ($match) {
                return $match->id;
            }
        }

        if (Str::contains($hintLower, ['buy to let', 'btl', 'rental', 'let'])) {
            $match = $properties->firstWhere('property_type', 'buy_to_let');
            if ($match) {
                return $match->id;
            }
        }

        if (Str::contains($hintLower, ['second', 'holiday'])) {
            $match = $properties->firstWhere('property_type', 'secondary_residence');
            if ($match) {
                return $match->id;
            }
        }

        foreach ($properties as $property) {
            $address = Str::lower(($property->address_line_1 ?? '').' '.($property->postcode ?? ''));
            if (Str::contains($address, $hintLower) || Str::contains($hintLower, trim($address))) {
                return $property->id;
            }
        }

        return $properties->first()->id;
    }

    /**
     * Validate raw module analysis against the per-agent contract (S1.6.b)
     * and return the verbatim payload as a tool_result. The previous
     * implementation silently dropped everything outside a 15-key whitelist;
     * the new implementation hands the LLM the structured agent output it
     * was supposed to see all along.
     *
     * On contract violation the LLM receives an explicit error tool result
     * — not a malformed shape — so it degrades by asking the user for
     * clarification rather than fabricating around missing data.
     */
    private function summariseToolAnalysis(string $module, array $analysis): array
    {
        if (isset($analysis['error'])) {
            return ['module' => $module] + $analysis;
        }

        try {
            return app(ToolResultContract::class)->validate($module, $analysis);
        } catch (ToolResultContractException $e) {
            Log::error('[CoordinatingAgent] Tool result contract violation', [
                'module' => $module,
                'context' => $e->context,
                'missing_keys' => $e->missingKeys,
                'present_keys' => $e->presentKeys,
                'message' => $e->getMessage(),
            ]);

            return [
                'module' => $module,
                'error' => 'module_analysis_contract_violation',
                'detail' => 'The module analysis returned a malformed shape and was blocked. Ask the user for clarification rather than relying on this result.',
            ];
        }
    }

    // ─── Additional creation tool handlers ──────────────────────────────

    private function handleCreateFamilyMember(array $input, User $user, bool $isPreview): array
    {
        if ($isPreview) {
            return $this->previewBlocked('family member');
        }

        $validationError = $this->validateToolInput($input, [
            'first_name' => 'required|string|max:255',
            'relationship' => ['required', Rule::in(['spouse', 'partner', 'child', 'step_child', 'parent', 'other_dependent'])],
            'surname' => 'nullable|string|max:255',
            'date_of_birth' => 'nullable|date',
            'gender' => ['nullable', Rule::in(['male', 'female', 'other', 'prefer_not_to_say'])],
            'is_dependent' => 'nullable|boolean',
            'education_status' => ['nullable', Rule::in(['pre_school', 'primary', 'secondary', 'further_education', 'higher_education', 'graduated', 'not_applicable'])],
            'receives_child_benefit' => 'nullable|boolean',
            'notes' => 'nullable|string|max:1000',
        ]);
        if ($validationError) {
            return $validationError;
        }

        // Default surname to user's surname if not provided
        $surname = $input['surname'] ?? $user->surname;

        // Map relationships to DB-compatible values (DB enum: spouse, child, parent, other_dependent)
        $relationship = $input['relationship'];
        $dbRelationship = match ($relationship) {
            'step_child' => 'child',
            'partner' => 'other_dependent',
            default => $relationship,
        };

        // Add note for mapped relationships
        $mappingNote = match ($relationship) {
            'step_child' => 'Step child',
            'partner' => 'Partner (unmarried)',
            default => null,
        };

        // Default is_dependent for children and dependents
        $isDependent = $input['is_dependent'] ?? in_array($relationship, ['child', 'step_child', 'other_dependent']);

        $payload = [
            'user_id' => $user->id,
            'relationship' => $dbRelationship,
            'first_name' => $input['first_name'],
            'last_name' => $surname,
            'is_dependent' => $isDependent,
        ];
        if (isset($input['date_of_birth']) && $input['date_of_birth'] !== '') {
            $payload['date_of_birth'] = $input['date_of_birth'];
        }
        if (isset($input['gender']) && $input['gender'] !== '') {
            $payload['gender'] = $input['gender'];
        }

        // Notes: combine the relationship-mapping note with any AI-supplied
        // notes (in that order so the mapping context comes first).
        $aiNotes = $input['notes'] ?? '';
        if ($mappingNote) {
            $payload['notes'] = trim($mappingNote.($aiNotes !== '' ? '. '.$aiNotes : ''));
        } elseif ($aiNotes !== '') {
            $payload['notes'] = $aiNotes;
        }

        // Child-specific: education_status (inferred from DOB if absent).
        if ($dbRelationship === 'child') {
            $educationStatus = $input['education_status'] ?? null;
            if (empty($educationStatus) && ! empty($input['date_of_birth'])) {
                try {
                    $age = Carbon::parse($input['date_of_birth'])->age;
                    $educationStatus = match (true) {
                        $age < 5 => 'pre_school',
                        $age < 11 => 'primary',
                        $age < 16 => 'secondary',
                        $age < 18 => 'further_education',
                        $age < 22 => 'higher_education',
                        default => 'graduated',
                    };
                } catch (\Exception $e) {
                    // Unparseable DOB — leave education_status null.
                }
            }
            if (! empty($educationStatus)) {
                $payload['education_status'] = $educationStatus;
            }
            if (isset($input['receives_child_benefit'])) {
                $payload['receives_child_benefit'] = (bool) $input['receives_child_benefit'];
            }
        }

        $member = DB::transaction(fn () => FamilyMember::create($payload));

        $this->invalidateUserCache($user->id);

        return [
            'success' => true,
            'created' => true,
            'entity_type' => 'family_member',
            'entity_id' => $member->id,
            'name' => trim($member->first_name.' '.($member->last_name ?? '')),
            'persisted_fields' => array_keys(array_diff_key($payload, ['user_id' => null])),
            'message' => "I've added {$input['first_name']} as your {$relationship}.",
        ];
    }

    private function handleCreateTrust(array $input, User $user, bool $isPreview): array
    {
        if ($isPreview) {
            return $this->previewBlocked('trust');
        }

        $validationError = $this->validateToolInput($input, [
            'trust_name' => 'required|string|max:255',
            'trust_type' => ['required', Rule::in(['discretionary', 'bare', 'interest_in_possession', 'life_insurance', 'loan', 'discounted_gift', 'accumulation_maintenance', 'mixed', 'settlor_interested'])],
            'initial_value' => 'nullable|numeric|min:0|max:999999999.99',
            'current_value' => 'nullable|numeric|min:0|max:999999999.99',
            'trust_creation_date' => 'nullable|date',
            'beneficiaries' => 'nullable|string|max:1000',
            'trustees' => 'nullable|string|max:1000',
            'purpose' => 'nullable|string|max:1000',
        ]);
        if ($validationError) {
            return $validationError;
        }

        $initialValue = isset($input['initial_value'])
            ? (float) $input['initial_value']
            : (isset($input['current_value']) ? (float) $input['current_value'] : 0);
        $currentValue = isset($input['current_value']) ? (float) $input['current_value'] : $initialValue;

        $beneficiaries = $this->resolveFamilyNames($input['beneficiaries'] ?? null, $user);
        $trustees = $this->resolveFamilyNames($input['trustees'] ?? null, $user);
        $settlor = $input['settlor'] ?? trim($user->first_name.' '.$user->surname);
        $creationDate = $input['trust_creation_date'] ?? now()->toDateString();

        // Discretionary + A&M trusts attract relevant-property regime
        // (10-yearly + exit charges). Mirrors TrustController::createTrust.
        $isRelevantProperty = in_array($input['trust_type'], ['discretionary', 'accumulation_maintenance'], true);

        $payload = [
            'user_id' => $user->id,
            'trust_name' => $input['trust_name'],
            'trust_type' => $input['trust_type'],
            'initial_value' => $initialValue,
            'current_value' => $currentValue,
            'trust_creation_date' => $creationDate,
            'settlor' => $settlor,
            'is_relevant_property_trust' => $isRelevantProperty,
        ];
        if ($beneficiaries !== null) {
            $payload['beneficiaries'] = $beneficiaries;
        }
        if ($trustees !== null) {
            $payload['trustees'] = $trustees;
        }
        if (isset($input['purpose']) && $input['purpose'] !== '') {
            $payload['purpose'] = $input['purpose'];
        }

        // FR-M15 — TrustObserver::created emits the corresponding Gift
        // (Chargeable Lifetime Transfer) when the trust persists; we don't
        // need to do anything extra here.
        $trust = DB::transaction(fn () => Trust::create($payload));

        $this->invalidateUserCache($user->id);

        $cltMessage = $initialValue > 0
            ? " I've also recorded a Chargeable Lifetime Transfer of £".number_format($initialValue).' for Inheritance Tax tracking.'
            : '';

        return [
            'success' => true,
            'created' => true,
            'entity_type' => 'trust',
            'entity_id' => $trust->id,
            'name' => $trust->trust_name,
            'persisted_fields' => array_keys(array_diff_key($payload, ['user_id' => null])),
            'message' => "I've added your \"{$trust->trust_name}\" trust.{$cltMessage}",
        ];
    }

    private function handleCreateBusinessInterest(array $input, User $user, bool $isPreview): array
    {
        if ($isPreview) {
            return $this->previewBlocked('business interest');
        }

        $validationError = $this->validateToolInput($input, [
            'business_name' => 'required|string|max:255',
            'business_type' => ['required', Rule::in(['sole_trader', 'partnership', 'limited_company', 'llp', 'other'])],
            'industry_sector' => 'nullable|string|max:255',
            'ownership_percentage' => 'nullable|numeric|min:0|max:100',
            'estimated_value' => 'nullable|numeric|min:0|max:999999999.99',
            'annual_revenue' => 'nullable|numeric|min:0|max:999999999.99',
            'annual_profit' => 'nullable|numeric|min:-999999999.99|max:999999999.99',
            'annual_dividend_income' => 'nullable|numeric|min:0|max:999999999.99',
            'employee_count' => 'nullable|integer|min:0|max:99999',
        ]);
        if ($validationError) {
            return $validationError;
        }

        $payload = [
            'user_id' => $user->id,
            'business_name' => $input['business_name'],
            'business_type' => $input['business_type'],
            'current_valuation' => isset($input['estimated_value']) ? (float) $input['estimated_value'] : 0,
            'ownership_type' => 'individual',
            'ownership_percentage' => isset($input['ownership_percentage']) ? (float) $input['ownership_percentage'] : 100.00,
            'valuation_date' => now()->toDateString(),
        ];

        foreach (['annual_revenue', 'annual_profit', 'annual_dividend_income'] as $f) {
            if (isset($input[$f]) && is_numeric($input[$f])) {
                $payload[$f] = (float) $input[$f];
            }
        }
        if (isset($input['employee_count']) && is_numeric($input['employee_count'])) {
            $payload['employee_count'] = (int) $input['employee_count'];
        }
        foreach (['description', 'notes'] as $f) {
            if (isset($input[$f]) && $input[$f] !== '') {
                $payload[$f] = $input[$f];
            }
        }

        $bi = DB::transaction(fn () => BusinessInterest::create($payload));

        $this->invalidateUserCache($user->id);

        return [
            'success' => true,
            'created' => true,
            'entity_type' => 'business_interest',
            'entity_id' => $bi->id,
            'name' => $bi->business_name,
            'persisted_fields' => array_keys(array_diff_key($payload, ['user_id' => null])),
            'message' => "I've added your \"{$bi->business_name}\" business interest.",
        ];
    }

    private function handleCreateChattel(array $input, User $user, bool $isPreview): array
    {
        if ($isPreview) {
            return $this->previewBlocked('personal valuable');
        }

        $validationError = $this->validateToolInput($input, [
            'description' => 'required|string|max:255',
            'estimated_value' => 'required|numeric|min:0|max:999999999.99',
            'category' => ['nullable', Rule::in(['jewellery', 'art', 'antiques', 'collectibles', 'vehicles', 'other'])],
            'purchase_value' => 'nullable|numeric|min:0|max:999999999.99',
            'is_insured' => 'nullable|boolean',
        ]);
        if ($validationError) {
            return $validationError;
        }

        // AI category -> canonical DB chattel_type (singular forms).
        $chattelType = match ($input['category'] ?? 'other') {
            'jewellery' => 'jewelry',
            'art' => 'art',
            'antiques' => 'antique',
            'collectibles' => 'collectible',
            'vehicles' => 'vehicle',
            default => 'other',
        };

        $payload = [
            'user_id' => $user->id,
            'chattel_type' => $chattelType,
            'name' => $input['description'],
            'current_value' => (float) $input['estimated_value'],
            'ownership_type' => 'individual',
            'ownership_percentage' => 100.00,
            'valuation_date' => now()->toDateString(),
        ];

        if (isset($input['purchase_value']) && is_numeric($input['purchase_value'])) {
            $payload['purchase_price'] = (float) $input['purchase_value'];
        }
        if (isset($input['notes']) && $input['notes'] !== '') {
            $payload['notes'] = $input['notes'];
        }

        $chattel = DB::transaction(fn () => Chattel::create($payload));

        $this->invalidateUserCache($user->id);

        return [
            'success' => true,
            'created' => true,
            'entity_type' => 'chattel',
            'entity_id' => $chattel->id,
            'name' => $chattel->name,
            'persisted_fields' => array_keys(array_diff_key($payload, ['user_id' => null])),
            'message' => "I've added your \"{$chattel->name}\".",
        ];
    }

    private function handleSetExpenditure(array $input, User $user, bool $isPreview): array
    {
        if ($isPreview) {
            return $this->previewBlocked('expenditure');
        }

        // All expenditure category fields (monthly amounts)
        $categoryFields = [
            'rent', 'utilities', 'food_groceries', 'transport_fuel', 'healthcare_medical', 'insurance',
            'mobile_phones', 'internet_tv', 'subscriptions',
            'clothing_personal_care', 'entertainment_dining', 'holidays_travel', 'pets',
            'childcare', 'school_fees', 'school_lunches', 'school_extras', 'university_fees', 'children_activities',
            'gifts_charity', 'charitable_donations', 'other_expenditure',
        ];

        $updateData = [];
        $resolvedAmounts = [];
        foreach ($categoryFields as $field) {
            $stored = $user->getAttribute($field);
            $resolvedAmounts[$field] = is_numeric($stored) ? max(0.0, (float) $stored) : 0.0;

            if (! array_key_exists($field, $input)) {
                continue;
            }
            if (! is_numeric($input[$field]) || (float) $input[$field] < 0) {
                return [
                    'error' => true,
                    'error_type' => 'validation_failed',
                    'message' => 'Expenditure amounts must be zero or more.',
                ];
            }

            $resolvedAmounts[$field] = (float) $input[$field];
            $updateData[$field] = (float) $input[$field];
        }

        if (empty($updateData)) {
            return ['error' => true, 'error_type' => 'validation_failed', 'message' => 'No expenditure amounts provided.'];
        }

        // A Fyn turn may update only one category. Recalculate from that edit
        // plus every stored category so omitted values are preserved, while an
        // explicit zero clears the named category.
        $total = array_sum($resolvedAmounts);
        $updateData['monthly_expenditure'] = $total;
        $updateData['annual_expenditure'] = $total * 12;
        $updateData['expenditure_entry_mode'] = 'category';
        DB::transaction(function () use ($user, $updateData, $total): void {
            $user->update($updateData);

            // FR-M12 — mirror the monthly total into ExpenditureProfile so the
            // dashboard and both review surfaces see the same committed total.
            ExpenditureProfile::updateOrCreate(
                ['user_id' => $user->id],
                ['total_monthly_expenditure' => $total],
            );
        });

        $formatted = collect($updateData)
            ->except(['monthly_expenditure', 'annual_expenditure', 'expenditure_entry_mode'])
            ->map(fn ($v, $k) => str_replace('_', ' ', ucfirst($k)).': £'.number_format($v, 2))
            ->values()
            ->implode(', ');

        return [
            'updated' => true,
            'action' => 'navigate',
            'route_path' => '/valuable-info?section=expenditure',
            'section' => 'expenditure',
            'fields_updated' => array_keys($updateData),
            'total_monthly' => $total,
            'total_annual' => $total * 12,
            'message' => "Expenditure updated: {$formatted}. Total: £".number_format($total, 2).'/month.',
        ];
    }

    // ─── SaveTax campaign capture handlers ──────────────────────────────

    private function handleCaptureSalarySacrifice(array $input, User $user, bool $isPreview): array
    {
        if ($isPreview) {
            return $this->previewBlocked('pension');
        }

        if (! isset($input['salary_sacrifice'])) {
            return ['error' => true, 'error_type' => 'validation_failed', 'message' => 'salary_sacrifice is required.'];
        }

        // WP-1 — the model often has no real pension id in this turn (the
        // 2026-07-03 walk sent pension_id: 0 and the whole capture failed
        // silently). A real id wins; otherwise fall back to the user's single
        // DC pension. None or ambiguous → a retryable structured error naming
        // what is needed.
        $pensionId = (int) ($input['pension_id'] ?? 0);
        if ($pensionId > 0) {
            $pension = app(PensionStore::class)->find($pensionId, 'dc', $user);
            if (! $pension) {
                return ['error' => true, 'error_type' => 'not_found', 'message' => 'Pension not found or not owned by user.'];
            }
        } else {
            $dcPensions = app(PensionStore::class)->forUserByType($user, 'dc');
            if ($dcPensions->count() === 1) {
                $pension = $dcPensions->first();
            } elseif ($dcPensions->isEmpty()) {
                return ['error' => true, 'error_type' => 'not_found', 'message' => 'No pension on file yet — create the pension first, then set salary sacrifice on it.'];
            } else {
                return ['error' => true, 'error_type' => 'ambiguous', 'message' => 'More than one pension on file — ask the user which pension the salary sacrifice applies to, then call again with that pension_id.'];
            }
        }

        $payload = ['salary_sacrifice' => (bool) $input['salary_sacrifice']];

        if (array_key_exists('employer_ni_rebate_pct', $input) && $input['employer_ni_rebate_pct'] !== null) {
            $rebate = (float) $input['employer_ni_rebate_pct'];
            $payload['employer_ni_rebate_pct'] = max(0.0, min(1.0, $rebate));
        }

        $pension->update($payload);

        return [
            'updated' => true,
            'pension_id' => $pension->id,
            'message' => 'Salary sacrifice setting updated.',
        ];
    }

    private function handleCaptureSpouseWorkStatus(array $input, User $user, bool $isPreview): array
    {
        if ($isPreview) {
            return $this->previewBlocked('household');
        }

        if (! array_key_exists('spouse_works', $input)) {
            return ['error' => true, 'error_type' => 'validation_failed', 'message' => 'spouse_works is required.'];
        }

        $works = (bool) $input['spouse_works'];

        $user->update([
            'marriage_allowance_eligible' => ! $works,
            'household_calculation_mode' => $works ? 'dual_earner' : 'single_earner_couple',
        ]);

        return [
            'updated' => true,
            'household_calculation_mode' => $user->household_calculation_mode,
            'marriage_allowance_eligible' => $user->marriage_allowance_eligible,
            'message' => $works
                ? 'Recorded that your spouse works — we\'ll capture more details next.'
                : 'Recorded that your spouse doesn\'t currently work — Marriage Allowance may apply.',
        ];
    }

    private function handleCaptureSpouseHouseholdData(array $input, User $user, bool $isPreview): array
    {
        if ($isPreview) {
            return $this->previewBlocked('household');
        }

        $allowedFields = [
            'spouse_annual_income',
            'spouse_employment_status',
            'spouse_isa_balance',
            'spouse_psa_band',
            'spouse_unrealised_gains',
            'spouse_annual_dividends',
            'spouse_pension_input_annual',
        ];
        if (array_diff(array_keys($input), $allowedFields) !== []) {
            return ['error' => true, 'error_type' => 'validation_failed', 'message' => 'One or more spouse household fields are not supported.'];
        }

        $allowed = array_intersect_key($input, array_flip($allowedFields));
        if ($allowed === []) {
            return ['error' => true, 'error_type' => 'validation_failed', 'message' => 'No spouse household details were provided.'];
        }

        $rules = [
            'spouse_employment_status' => ['sometimes', 'nullable', Rule::in(['employed', 'full_time', 'part_time', 'self_employed', 'retired', 'unemployed', 'other'])],
            'spouse_psa_band' => ['sometimes', 'nullable', Rule::in(['basic', 'higher', 'additional'])],
        ];
        foreach ([
            'spouse_annual_income', 'spouse_isa_balance', 'spouse_unrealised_gains',
            'spouse_annual_dividends', 'spouse_pension_input_annual',
        ] as $field) {
            $rules[$field] = ['sometimes', 'nullable', 'numeric', 'min:'.ValidationLimits::MIN_CURRENCY_VALUE, 'max:'.ValidationLimits::MAX_CURRENCY_VALUE];
        }
        $validator = Validator::make($allowed, $rules);
        if ($validator->fails()) {
            return [
                'error' => true,
                'error_type' => 'validation_failed',
                'errors' => $validator->errors()->toArray(),
                'message' => 'Validation failed for spouse household details.',
            ];
        }

        $existing = TaxStrategyHouseholdInput::where('user_id', $user->id)->first();
        $spouseIncome = array_key_exists('spouse_annual_income', $allowed)
            ? (float) $allowed['spouse_annual_income']
            : ($existing?->spouse_annual_income !== null ? (float) $existing->spouse_annual_income : null);
        $spouseDividends = array_key_exists('spouse_annual_dividends', $allowed)
            ? (float) $allowed['spouse_annual_dividends']
            : ($existing?->spouse_annual_dividends !== null ? (float) $existing->spouse_annual_dividends : 0.0);
        unset($allowed['spouse_psa_band']);
        if ($spouseIncome !== null) {
            $allowed['spouse_psa_band'] = app(TaxStrategyMath::class)->bandFromIncome($spouseIncome + $spouseDividends);
        }

        TaxStrategyHouseholdInput::updateOrCreate(
            ['user_id' => $user->id],
            $allowed
        );

        return [
            'onboarding_capture' => true,
            'field_group' => 'campaign_spouse_household',
            'summary' => 'Spouse household data captured.',
            'details' => $allowed,
        ];
    }

    private function handleCaptureSpouseNonWorkingAssets(array $input, User $user, bool $isPreview): array
    {
        if ($isPreview) {
            return $this->previewBlocked('household');
        }

        $allowedFields = [
            'spouse_existing_isa_balance',
            'spouse_existing_savings_balance',
            'spouse_existing_investment_balance',
            'spouse_existing_dividend_holdings_value',
            'spouse_existing_pension_balance',
        ];
        if (array_diff(array_keys($input), $allowedFields) !== []) {
            return ['error' => true, 'error_type' => 'validation_failed', 'message' => 'One or more spouse asset fields are not supported.'];
        }

        $allowed = array_intersect_key($input, array_flip($allowedFields));
        if ($allowed === []) {
            return ['error' => true, 'error_type' => 'validation_failed', 'message' => 'No spouse asset details were provided.'];
        }

        $rules = [];
        foreach ($allowedFields as $field) {
            $rules[$field] = ['sometimes', 'nullable', 'numeric', 'min:'.ValidationLimits::MIN_CURRENCY_VALUE, 'max:'.ValidationLimits::MAX_CURRENCY_VALUE];
        }
        $validator = Validator::make($allowed, $rules);
        if ($validator->fails()) {
            return [
                'error' => true,
                'error_type' => 'validation_failed',
                'errors' => $validator->errors()->toArray(),
                'message' => 'Validation failed for spouse asset details.',
            ];
        }

        TaxStrategyHouseholdInput::updateOrCreate(
            ['user_id' => $user->id],
            $allowed
        );

        return [
            'onboarding_capture' => true,
            'field_group' => 'campaign_spouse_non_working_assets',
            'summary' => 'Spouse standalone assets captured.',
            'details' => $allowed,
        ];
    }

    private function handleCapturePensionHistory(array $input, User $user, bool $isPreview, ?int $conversationId = null): array
    {
        if ($isPreview) {
            return $this->previewBlocked('pension');
        }

        $history = $input['history'] ?? null;
        if (! is_array($history) || $history === []) {
            return ['error' => true, 'error_type' => 'validation_failed', 'message' => 'history must be a non-empty array.'];
        }

        // Carry-forward disambiguation guard. The user is asked for their gross
        // pension input "in each of the last 3 tax years"; when they reply with
        // ONE lump figure (e.g. "Around £90,000") and no per-year breakdown,
        // the figure is fatally ambiguous — £90k spread across three years is
        // unused allowance to top up, but £90k PER YEAR is an annual-allowance
        // charge. Whatever split the model invents to satisfy the per-year tool
        // schema is fabricated financial data. So when the source message
        // carries a single figure with no per-year structure, write nothing and
        // ask the clarifying question; emitTerminalError voices the message
        // verbatim and keeps the user on STATE_CAMPAIGN_PENSION_HISTORY so their
        // next reply re-enters this handler with the disambiguated figures.
        $sourceMessage = $this->latestUserMessageText($conversationId);
        if ($sourceMessage !== null && $this->pensionHistoryIsAmbiguousSingleFigure($sourceMessage)) {
            return [
                'onboarding_capture_error' => true,
                'field_group' => 'campaign_pension_history',
                'error_type' => 'pension_history_ambiguous',
                'message' => 'Just to be sure I read that correctly — is that the total across all three tax years, or roughly that amount per year? It changes whether you have unused allowance to top up.',
            ];
        }

        // "In total across the three years" + a single entry → distribute the
        // total evenly across the prior-3 window. Writing the total into ONE
        // year fabricates an annual-allowance excess in that year and
        // overstates carry-forward (live-browser finding 2026-06-11: £90k
        // landed in 2024/25 alone → £120k "unused" instead of £90k).
        $distributed = false;
        if (count($history) === 1 && $sourceMessage !== null
            && preg_match('/\b(total|across|altogether|combined|in all)\b/i', $sourceMessage) === 1) {
            $taxYear = app(TaxConfigService::class)->getTaxYear();
            $priorYears = app(AnnualAllowanceChecker::class)->getPrevious3TaxYears($taxYear);
            $perYear = round(((float) (reset($history)['pension_input_amount'] ?? 0)) / 3, 2);
            $history = array_map(
                fn (string $year): array => ['tax_year' => $year, 'pension_input_amount' => $perYear],
                $priorYears
            );
            $distributed = true;
        }

        $canonical = app(PensionNormaliser::class)->fromFynInputHistory(['history' => $history]);

        try {
            $written = app(PensionStore::class)->captureInputHistory(
                $canonical['entries'],
                $user,
                IngestSource::FYN_AI,
            );
        } catch (StoreValidationException $e) {
            return ['error' => true, 'error_type' => 'validation_failed', 'message' => 'No valid history entries provided.'];
        }

        return [
            'onboarding_capture' => true,
            'field_group' => 'campaign_pension_history',
            'summary' => $distributed
                ? sprintf('Captured pension history — the total split evenly across the last three tax years (%s per year).', '£'.number_format($perYear, 2))
                : sprintf('Captured %d year(s) of pension history.', count($written)),
            'details' => $written,
        ];
    }

    /**
     * Fetch the verbatim text of the most recent user message in the
     * conversation — the in-flight turn that triggered the current tool call.
     * Used by capture handlers that must reason about the user's actual words
     * (e.g. detecting an ambiguous single figure) rather than trusting the
     * post-hoc structure the model put into the tool arguments.
     */
    private function latestUserMessageText(?int $conversationId): ?string
    {
        if ($conversationId === null) {
            return null;
        }

        $message = AiMessage::query()
            ->where('conversation_id', $conversationId)
            ->where('role', 'user')
            ->orderByDesc('id')
            ->first();

        $text = $message?->content;

        return is_string($text) && $text !== '' ? $text : null;
    }

    /**
     * Return a bounded, chronological window of the user's own words so a
     * create tool called after several clarification turns can validate every
     * required fact. CaptureAccuracyGate still scopes this text to the target
     * entity, preventing another account's ownership facts from being reused.
     */
    private function recentUserMessageEvidence(?int $conversationId): ?string
    {
        if ($conversationId === null) {
            return null;
        }

        $stateBoundary = AiMessage::query()
            ->where('conversation_id', $conversationId)
            ->where('role', 'assistant')
            ->orderByDesc('id')
            ->limit(12)
            ->get(['id', 'metadata'])
            ->first(function (AiMessage $message): bool {
                $metadata = is_array($message->metadata) ? $message->metadata : [];

                return isset($metadata['onboarding_step'])
                    && ($metadata['capture_write_failed'] ?? false) !== true
                    && ($metadata['is_resume_greeting'] ?? false) !== true;
            });
        $stateBoundaryId = $stateBoundary?->id;

        $messages = AiMessage::query()
            ->where('conversation_id', $conversationId)
            ->where('role', 'user')
            ->when($stateBoundaryId !== null, fn ($query) => $query->where('id', '>', $stateBoundaryId))
            ->orderByDesc('id')
            ->limit(6)
            ->pluck('content')
            ->reverse()
            ->filter(static fn (mixed $content): bool => is_string($content) && trim($content) !== '')
            ->values();

        if ($stateBoundaryId !== null) {
            $previousUser = AiMessage::query()
                ->where('conversation_id', $conversationId)
                ->where('role', 'user')
                ->where('id', '<', $stateBoundaryId)
                ->orderByDesc('id')
                ->first(['id', 'content']);
            $hasUnresolvedClarification = $previousUser !== null
                && AiMessage::query()
                    ->where('conversation_id', $conversationId)
                    ->where('role', 'assistant')
                    ->where('id', '>', $previousUser->id)
                    ->where('id', '<', $stateBoundaryId)
                    ->orderBy('id')
                    ->get(['content', 'metadata'])
                    ->contains(function (AiMessage $message): bool {
                        $metadata = is_array($message->metadata) ? $message->metadata : [];

                        return ($metadata['is_resume_greeting'] ?? false) !== true
                            && (! isset($metadata['onboarding_step'])
                                || ($metadata['capture_write_failed'] ?? false) === true)
                            && $this->assistantRequestsCaptureClarification((string) $message->content);
                    });

            if ($hasUnresolvedClarification && is_string($previousUser->content)) {
                $messages->prepend($previousUser->content);
            }
        }

        return $messages->isEmpty() ? null : $messages->implode("\n");
    }

    private function assistantRequestsCaptureClarification(string $content): bool
    {
        $content = trim($content);
        if ($content === '') {
            return false;
        }

        return str_contains($content, '?')
            || preg_match(
                '/\b(?:i need|could you|can you|would you|please (?:tell|confirm|provide|share)|tell me|confirm whether)\b/i',
                $content,
            ) === 1;
    }

    /**
     * Detect whether a pension-history reply gives a SINGLE monetary figure for
     * the three-year window with no per-year structure — the catastrophic
     * total-vs-per-year ambiguity. Returns false when the reply is per-year
     * shaped ("about 5,000 each year"), lists multiple figures ("30k, 25k,
     * 20k"), labels distinct tax years, or carries no monetary figure at all
     * (e.g. "zero" / "none", which capture cleanly as no contributions).
     */
    private function pensionHistoryIsAmbiguousSingleFigure(string $message): bool
    {
        $figureCount = $this->countMonetaryFigures($message);
        if ($figureCount !== 1) {
            // Zero figures ("none"/"nothing") capture as no contributions;
            // two or more figures are already per-year and unambiguous.
            return false;
        }

        $lower = mb_strtolower($message);

        // Disambiguating cues — the user has already told us whether the figure
        // is annual (per-year) or the whole-window total. Either resolves the
        // ambiguity, so capture rather than re-ask.
        $resolvingCues = [
            // per-year
            'each year', 'per year', 'a year', 'every year', 'each of', 'each tax year',
            'per annum', 'annually', '/yr', 'p.a.', 'pa ', 'each of the', 'for each',
            'first year', 'second year', 'third year', 'last year', 'year before',
            // whole-window total
            'in total', 'total of', 'altogether', 'across', 'combined', 'between them',
            'all three', 'over the three', 'over three', 'spread', 'split',
        ];
        foreach ($resolvingCues as $cue) {
            if (str_contains($lower, $cue)) {
                return false;
            }
        }

        // Explicit per-year tax-year labelling (e.g. "2024/25") means the user
        // attributed the figure to a specific year, not the whole window.
        if (preg_match('/\b20\d{2}\s*\/\s*\d{2}\b/', $message) === 1) {
            return false;
        }

        return true;
    }

    /**
     * Count distinct monetary figures in a free-text message. Handles
     * £-prefixed and bare numbers, thousands separators, and the "k"/"m"
     * shorthand (90k, 1.2m). Used to tell a single lump figure apart from a
     * per-year list.
     */
    private function countMonetaryFigures(string $message): int
    {
        $matches = [];
        // £90,000 / 90000 / 90k / 1.2m / £3,250.50 — optional £ prefix, optional
        // k|m shorthand suffix; the trailing negative lookahead stops a figure
        // running into an adjacent digit/decimal so "90000" counts once.
        preg_match_all('/£?\s?\d[\d,]*(?:\.\d+)?\s?(?:k|m)?(?![\d.])/iu', $message, $matches);

        $figures = array_filter(
            array_map('trim', $matches[0] ?? []),
            // Drop bare year-like tokens that are not really amounts on their
            // own (a 4-digit 19xx/20xx with no £, k, m, comma or decimal).
            static function (string $tok): bool {
                $hasMoneyMarker = (bool) preg_match('/[£,km.]/i', $tok);
                $isYearLike = (bool) preg_match('/^(?:19|20)\d{2}$/', preg_replace('/[^\d]/', '', $tok) ?? '');

                return $hasMoneyMarker || ! $isYearLike;
            }
        );

        return count($figures);
    }

    private function handleCaptureCharitableGiving(array $input, User $user, bool $isPreview): array
    {
        if ($isPreview) {
            return $this->previewBlocked('profile');
        }

        if (! array_key_exists('annual_donations', $input)) {
            return ['error' => true, 'error_type' => 'validation_failed', 'message' => 'annual_donations is required.'];
        }

        $amount = (float) $input['annual_donations'];
        if ($amount < 0) {
            return ['error' => true, 'error_type' => 'validation_failed', 'message' => 'annual_donations must be >= 0.'];
        }

        $user->update(['annual_charitable_donations' => $amount]);

        return [
            'onboarding_capture' => true,
            'field_group' => 'campaign_charitable_giving',
            'summary' => $amount > 0
                ? sprintf('Annual Gift Aid donations recorded as £%s.', number_format($amount, 0))
                : 'No Gift Aid donations recorded.',
            'details' => ['annual_charitable_donations' => $amount],
        ];
    }

    /**
     * capture_retirement_goals — writes target_retirement_age and/or
     * target_retirement_income to retirement_profiles.
     *
     * target_retirement_age is NOT NULL in the schema so a new record cannot be
     * created without it. When only income is supplied and no profile exists, the
     * handler returns a partial-capture response and writes nothing — the caller
     * must ask the user for their target retirement age first.
     */
    private function handleCaptureRetirementGoals(array $input, User $user, bool $isPreview, ?int $conversationId = null): array
    {
        if ($isPreview) {
            return $this->previewBlocked('retirement');
        }

        $age = isset($input['target_retirement_age']) ? (int) $input['target_retirement_age'] : null;
        $income = isset($input['target_retirement_income']) ? (float) $input['target_retirement_income'] : null;

        if ($age === null && $income === null) {
            return ['error' => true, 'error_type' => 'validation_failed', 'message' => 'Provide a target retirement age, an income, or both.'];
        }

        if ($age !== null && ($age < 55 || $age > 75)) {
            return ['error' => true, 'error_type' => 'validation_failed', 'message' => 'Target retirement age must be between 55 and 75.'];
        }

        if ($income !== null && $income < 0) {
            return ['error' => true, 'error_type' => 'validation_failed', 'message' => 'Target retirement income must be 0 or more.'];
        }

        $existing = RetirementProfile::where('user_id', $user->id)->first();

        if ($this->verifyEditScope !== null && $existing === null) {
            return ['error' => true, 'error_type' => 'not_found', 'message' => 'No retirement goals are on file to update.'];
        }

        if ($existing !== null) {
            $updates = array_filter(['target_retirement_age' => $age, 'target_retirement_income' => $income], fn ($v) => $v !== null);
            $existing->update($updates);
        } elseif ($age !== null) {
            // On the first turn only income may have been provided (the partial-retry
            // protocol kept us on this state). Merge any parked income so it is not
            // silently dropped when the LLM re-calls the tool with age only.
            if ($income === null) {
                $income = $this->retrieveParkedRetirementGoalIncome($conversationId);
            }
            $currentAge = $user->date_of_birth ? Carbon::parse($user->date_of_birth)->age : 30;
            $creates = array_filter(['target_retirement_income' => $income], fn ($v) => $v !== null);
            RetirementProfile::create([
                'user_id' => $user->id,
                'current_age' => $currentAge,
                'target_retirement_age' => $age,
                ...$creates,
            ]);
            // Clear the parked income — it has now been committed to the profile.
            $this->clearParkedRetirementGoalIncome($conversationId);
        } else {
            // Income only — cannot create a profile without target_retirement_age.
            // Park the income in the conversation so it survives the partial-retry
            // and is carried forward even if the LLM re-calls with age only.
            $this->parkRetirementGoalIncome($conversationId, $income);

            return [
                'onboarding_capture' => true,
                'field_group' => 'campaign_retirement_goals',
                'summary' => 'Income target noted — still need the retirement age to save the goal.',
                'details' => ['missing' => ['target_retirement_age']],
            ];
        }

        // Mirror the age onto users.target_retirement_age. The retirement store is
        // retirement_profiles, but the /retirement page (RetirementProjectionService),
        // the "When you want to retire" data-requirement, and ModuleAvailabilityProvider
        // all read users.target_retirement_age — leaving it null showed the default
        // age (67) and kept the checklist item outstanding despite the goal being set.
        if ($age !== null) {
            $user->target_retirement_age = $age;
            $user->save();
        }

        // Bust the user's caches so the synthesis, /retirement page and dashboards
        // recompute against the new goal. The retirement analysis is remembered
        // under retirement_analysis_{id}; its model-level tag flush silently no-ops
        // on the file cache driver (no tag support), so the mid-walk profile=null
        // analysis would otherwise persist and the synthesis would voice no plan.
        $this->invalidateUserCache($user->id);

        $parts = [];
        if ($age !== null) {
            $parts[] = sprintf('retirement age %d', $age);
        }
        if ($income !== null) {
            $parts[] = sprintf('income £%s per year', number_format($income, 0));
        }

        return [
            'onboarding_capture' => true,
            'field_group' => 'campaign_retirement_goals',
            'summary' => 'Retirement goals recorded: '.implode(', ', $parts).'.',
            'details' => array_filter(['target_retirement_age' => $age, 'target_retirement_income' => $income], fn ($v) => $v !== null),
        ];
    }

    /**
     * Stash the income figure in `ai_conversations.onboarding_parked_facts` so
     * it survives a partial-retry turn and can be merged into the next call.
     */
    private function parkRetirementGoalIncome(?int $conversationId, ?float $income): void
    {
        if ($conversationId === null || $income === null) {
            return;
        }

        $conv = AiConversation::find($conversationId);
        if ($conv === null) {
            return;
        }

        $parked = is_array($conv->onboarding_parked_facts) ? $conv->onboarding_parked_facts : [];
        $parked['retirement_goals'] = ['target_retirement_income' => $income];
        $conv->update(['onboarding_parked_facts' => $parked]);
    }

    /** Retrieve the parked income figure (null when nothing was stashed). */
    private function retrieveParkedRetirementGoalIncome(?int $conversationId): ?float
    {
        if ($conversationId === null) {
            return null;
        }

        $conv = AiConversation::find($conversationId);
        $parked = is_array($conv?->onboarding_parked_facts) ? $conv->onboarding_parked_facts : [];

        $income = $parked['retirement_goals']['target_retirement_income'] ?? null;

        return $income !== null ? (float) $income : null;
    }

    /** Remove the parked retirement-goals bucket after the income has been committed. */
    private function clearParkedRetirementGoalIncome(?int $conversationId): void
    {
        if ($conversationId === null) {
            return;
        }

        $conv = AiConversation::find($conversationId);
        if ($conv === null) {
            return;
        }

        $parked = is_array($conv->onboarding_parked_facts) ? $conv->onboarding_parked_facts : [];
        unset($parked['retirement_goals']);
        $conv->update(['onboarding_parked_facts' => $parked === [] ? null : $parked]);
    }

    /**
     * capture_state_pension — writes state_pension_forecast_annual,
     * ni_years_completed, and/or state_pension_age to state_pensions.
     * All columns are nullable (or have DB defaults) so updateOrCreate is safe
     * with any subset of the three parameters.
     */
    private function handleCaptureStatePension(array $input, User $user, bool $isPreview): array
    {
        if ($isPreview) {
            return $this->previewBlocked('retirement');
        }

        $forecastAnnual = isset($input['forecast_annual']) ? (float) $input['forecast_annual'] : null;
        $niYears = isset($input['ni_years_completed']) ? (int) $input['ni_years_completed'] : null;
        $spAge = isset($input['state_pension_age']) ? (int) $input['state_pension_age'] : null;

        if ($forecastAnnual === null && $niYears === null && $spAge === null) {
            return ['error' => true, 'error_type' => 'validation_failed', 'message' => 'Provide at least one State Pension field.'];
        }

        if ($forecastAnnual !== null && ($forecastAnnual < 0 || $forecastAnnual > ValidationLimits::MAX_CURRENCY_VALUE)) {
            return ['error' => true, 'error_type' => 'validation_failed', 'message' => 'State Pension forecast must be 0 or more.'];
        }

        if ($niYears !== null && ($niYears < 0 || $niYears > 60)) {
            return ['error' => true, 'error_type' => 'validation_failed', 'message' => 'National Insurance qualifying years must be between 0 and 60.'];
        }

        if ($spAge !== null && ($spAge < 55 || $spAge > 75)) {
            return ['error' => true, 'error_type' => 'validation_failed', 'message' => 'State Pension age must be between 55 and 75.'];
        }

        $existing = StatePension::where('user_id', $user->id)->first();
        if ($this->verifyEditScope !== null && $existing === null) {
            return ['error' => true, 'error_type' => 'not_found', 'message' => 'No State Pension details are on file to update.'];
        }

        // Guard against the "not sure" garbage call. When the user says they don't
        // know their forecast, the model can still call this tool with all-zero
        // values, OR invent a state_pension_age from an unrelated pension's normal
        // retirement age (user 185: forecast 0 / ni 0 / age 60 lifted from an NHS
        // scheme). A State Pension row is only justified by a real forecast amount
        // or qualifying-years count — state_pension_age alone NEVER justifies one
        // (it is the field the model most easily hallucinates). When neither the
        // forecast nor the qualifying years is a positive figure, treat the call as
        // no-capture: write nothing and let advance_on_answered_question move on.
        // The tool schema also tells the model not to infer the age or call the
        // tool when the user is unsure.
        $hasPositiveSignal = ($forecastAnnual !== null && $forecastAnnual > 0)
            || ($niYears !== null && $niYears > 0);

        if (! $hasPositiveSignal && $existing === null) {
            return [
                'onboarding_capture' => false,
                'summary' => 'No State Pension figures recorded — the user did not give a forecast, qualifying years, or age.',
            ];
        }

        $updates = array_filter([
            'state_pension_forecast_annual' => $forecastAnnual,
            'ni_years_completed' => $niYears,
            'state_pension_age' => $spAge,
        ], fn ($v) => $v !== null);

        if ($existing !== null) {
            $existing->update($updates);
        } else {
            StatePension::create(['user_id' => $user->id, ...$updates]);
        }

        // Bust the user's caches so the retirement analysis (and the "No State
        // Pension Forecast" recommendation that depends on this row) recompute —
        // the file cache driver on csjones has no tag support, so the model-level
        // tag flush silently no-ops.
        $this->invalidateUserCache($user->id);

        $parts = [];
        if ($forecastAnnual !== null) {
            $parts[] = sprintf('forecast £%s per year', number_format($forecastAnnual, 0));
        }
        if ($niYears !== null) {
            $parts[] = sprintf('%d National Insurance qualifying years', $niYears);
        }
        if ($spAge !== null) {
            $parts[] = sprintf('State Pension age %d', $spAge);
        }

        return [
            'onboarding_capture' => true,
            'field_group' => 'campaign_state_pension',
            'summary' => 'State Pension details recorded: '.implode(', ', $parts).'.',
            'details' => $updates,
        ];
    }

    // ─── Generic update/delete handlers ─────────────────────────────────

    private function handleUpdateRecord(array $input, User $user, bool $isPreview): array
    {
        if ($isPreview) {
            return $this->previewBlocked('record');
        }

        $entityType = (string) ($input['entity_type'] ?? '');
        $entityId = (int) ($input['entity_id'] ?? 0);
        $fields = $input['fields'] ?? [];

        if (empty($fields)) {
            return ['error' => true, 'error_type' => 'validation_failed', 'message' => 'No fields provided to update.'];
        }

        // Map AI tool field names to actual model field names. Aliasing happens
        // BEFORE the allowlist check so the LLM may use either the schema
        // name or any legacy alias and still pass.
        $fieldAliases = match ($entityType) {
            'business_interest' => ['estimated_value' => 'current_valuation', 'value' => 'current_valuation'],
            'chattel' => ['estimated_value' => 'current_value', 'category' => 'chattel_type', 'value' => 'current_value', 'chattel_name' => 'name'],
            'dc_pension' => ['current_value' => 'current_fund_value', 'pot_value' => 'current_fund_value', 'monthly_contribution' => 'monthly_contribution_amount', 'employer_contribution' => 'employer_contribution_percent'],
            'mortgage' => ['current_balance' => 'outstanding_balance', 'term_years' => 'remaining_term_months'],
            'life_insurance' => ['life_policy_type' => 'policy_type', 'monthly_premium' => 'premium_amount', 'end_date' => 'policy_end_date'],
            'critical_illness' => ['monthly_premium' => 'premium_amount'],
            'income_protection' => ['monthly_premium' => 'premium_amount', 'monthly_benefit' => 'benefit_amount', 'deferred_period' => 'deferred_period_weeks'],
            'savings_account' => ['balance' => 'current_balance', 'provider' => 'institution'],
            'investment_account' => ['total_value' => 'current_value'],
            'db_pension' => ['annual_pension_amount' => 'accrued_annual_pension'],
            'estate_asset' => ['value' => 'current_value'],
            'estate_liability' => ['outstanding_amount' => 'current_balance'],
            'estate_gift' => ['value' => 'gift_value', 'gift_description' => 'gift_type', 'recipient_name' => 'recipient'],
            'trust' => ['value' => 'current_value'],
            'family_member' => ['surname' => 'last_name'],
            default => [],
        };
        foreach ($fieldAliases as $aiName => $dbName) {
            if (array_key_exists($aiName, $fields) && ! array_key_exists($dbName, $fields)) {
                $fields[$dbName] = $fields[$aiName];
                unset($fields[$aiName]);
            }
        }

        // Per-entity allowlist enforcement (INV-2.7.3). Replaces the prior
        // `unset($safeFields['user_id'], $safeFields['id'])` blocklist with
        // a positive list — every other column (identity FKs, audit
        // timestamps, ownership-changing fields like Trust.settlor or
        // FamilyMember.relationship) is rejected explicitly.
        $allowed = UpdateRecordAllowlist::allowedFields($entityType);
        if (empty($allowed)) {
            return [
                'error' => true,
                'error_type' => 'unsupported_entity_type',
                'entity_type' => $entityType,
                'message' => "The entity type '{$entityType}' cannot be updated via this tool.",
            ];
        }

        $disallowed = array_diff(array_keys($fields), $allowed);
        if (! empty($disallowed)) {
            return [
                'error' => true,
                'error_type' => 'fields_not_allowed',
                'entity_type' => $entityType,
                'disallowed_fields' => array_values($disallowed),
                'allowed_fields' => $allowed,
            ];
        }

        // Pension mutations route through PensionStore (canonical write path
        // for SP1 Pass 3). Holdings + ancillary pension models continue to
        // route through resolveModel until their own stores land.
        if ($entityType === 'dc_pension' || $entityType === 'db_pension') {
            try {
                $store = app(PensionStore::class);
                $pension = $entityType === 'dc_pension'
                    ? $store->updateDc($entityId, $fields, $user, IngestSource::FYN_AI)
                    : $store->updateDb($entityId, $fields, $user, IngestSource::FYN_AI);
            } catch (ModelNotFoundException $e) {
                return ['error' => true, 'error_type' => 'not_found', 'message' => 'Record not found or unauthorized.'];
            } catch (StoreValidationException $e) {
                return [
                    'error' => true,
                    'error_type' => 'validation_failed',
                    'errors' => $e->errors,
                    'message' => 'Validation failed for pension update.',
                ];
            }

            return [
                'success' => true,
                'entity_type' => $entityType,
                'entity_id' => $pension->id,
                'fields_updated' => array_keys($fields),
                'message' => 'Updated '.str_replace('_', ' ', $entityType).' successfully.',
            ];
        }

        if (in_array($entityType, ['savings_account', 'investment_account', 'estate_liability'], true)) {
            try {
                $record = match ($entityType) {
                    'savings_account' => app(SavingsStore::class)->update($entityId, $fields, $user, IngestSource::FYN_AI),
                    'investment_account' => app(InvestmentAccountStore::class)->update($entityId, $fields, $user, IngestSource::FYN_AI),
                    'estate_liability' => app(LiabilityStore::class)->update($entityId, $fields, $user, IngestSource::FYN_AI),
                };
            } catch (ModelNotFoundException $e) {
                return ['error' => true, 'error_type' => 'not_found', 'message' => 'Record not found or unauthorized.'];
            } catch (StoreValidationException $e) {
                return [
                    'error' => true,
                    'error_type' => 'validation_failed',
                    'errors' => $e->errors,
                    'message' => 'Validation failed for account update.',
                ];
            }

            return [
                'success' => true,
                'entity_type' => $entityType,
                'entity_id' => $record->id,
                'fields_updated' => array_keys($fields),
                'message' => 'Updated '.str_replace('_', ' ', $entityType).' successfully.',
            ];
        }

        $model = $this->resolveModel($entityType, $entityId, $user->id);
        if (is_array($model) && isset($model['error'])) {
            return $model;
        }

        return DB::transaction(function () use ($model, $fields, $entityType) {
            $model->fill($fields);
            $model->save();

            return [
                'success' => true,
                'entity_type' => $entityType,
                'entity_id' => $model->id,
                'fields_updated' => array_keys($fields),
                'message' => 'Updated '.str_replace('_', ' ', $entityType).' successfully.',
            ];
        });
    }

    /**
     * Map entity types to their frontend page routes.
     */
    private function getRouteForEntityType(string $entityType): string
    {
        return match ($entityType) {
            'savings_account' => '/net-worth/cash',
            'investment_account' => '/net-worth/investments',
            'dc_pension', 'db_pension' => '/net-worth/retirement',
            'property', 'mortgage' => '/net-worth/property',
            'life_insurance', 'critical_illness', 'income_protection', 'protection_policy' => '/protection',
            'goal' => '/goals',
            'life_event' => '/goals?tab=events',
            'family_member' => '/profile',
            'trust' => '/trusts',
            'business_interest' => '/net-worth/business',
            'chattel' => '/net-worth/chattels',
            'estate_asset', 'estate_gift' => '/estate',
            'estate_liability' => '/net-worth/liabilities',
            default => '/dashboard',
        };
    }

    private function handleDeleteRecord(array $input, User $user, bool $isPreview): array
    {
        if ($isPreview) {
            return $this->previewBlocked('record');
        }

        $entityType = (string) ($input['entity_type'] ?? '');
        $entityId = (int) ($input['entity_id'] ?? 0);

        // Two-phase confirmation (Rubric-A D5 Level 3): the first call
        // never deletes — it returns a deterministic SHA-256 token bound
        // to (user_id, entity_type, entity_id, today's date). The LLM
        // must echo that exact token on the second call to proceed.
        // The same-day salt means tokens cannot be replayed across days.
        $expectedToken = hash(
            'sha256',
            $user->id.'|'.$entityType.'|'.$entityId.'|'.now()->format('Y-m-d')
        );

        $providedToken = (string) ($input['confirmation_token'] ?? '');

        if (! hash_equals($expectedToken, $providedToken)) {
            return [
                'requires_confirmation' => true,
                'confirmation_token' => $expectedToken,
                'entity_type' => $entityType,
                'entity_id' => $entityId,
                'preview_message' => 'This will permanently delete '
                    .str_replace('_', ' ', $entityType)." #{$entityId}. "
                    .'Confirm with the user before re-calling delete_record with this confirmation_token.',
            ];
        }

        // Pension deletions route through PensionStore (canonical write path
        // for SP1 Pass 3). Same pattern as update — fetch via the store for
        // the name, then call the typed delete entry point.
        if ($entityType === 'dc_pension' || $entityType === 'db_pension') {
            $store = app(PensionStore::class);
            $pension = $store->find($entityId, $entityType === 'dc_pension' ? 'dc' : 'db', $user);
            if ($pension === null) {
                return ['error' => true, 'error_type' => 'not_found', 'message' => 'Record not found or unauthorized.'];
            }

            $name = $pension->scheme_name ?? "#{$entityId}";

            try {
                $entityType === 'dc_pension'
                    ? $store->deleteDc($entityId, $user, 'user_requested')
                    : $store->deleteDb($entityId, $user, 'user_requested');
            } catch (ModelNotFoundException $e) {
                return ['error' => true, 'error_type' => 'not_found', 'message' => 'Record not found or unauthorized.'];
            }

            return [
                'success' => true,
                'deleted' => true,
                'entity_type' => $entityType,
                'entity_id' => $entityId,
                'message' => ucfirst(str_replace('_', ' ', $entityType))." \"{$name}\" deleted.",
            ];
        }

        if ($entityType === 'estate_liability') {
            $liability = app(LiabilityStore::class)->find($entityId, $user);
            if ($liability === null || $liability->user_id !== $user->id) {
                return ['error' => true, 'error_type' => 'not_found', 'message' => 'Record not found or unauthorized.'];
            }

            $name = $liability->liability_name ?? "#{$entityId}";
            app(LiabilityStore::class)->delete($entityId, $user, IngestSource::FYN_AI);

            return [
                'success' => true,
                'deleted' => true,
                'entity_type' => $entityType,
                'entity_id' => $entityId,
                'message' => "Estate liability \"{$name}\" deleted.",
            ];
        }

        $model = $this->resolveModel($entityType, $entityId, $user->id);
        if (is_array($model) && isset($model['error'])) {
            return $model;
        }

        $name = $model->goal_name
            ?? $model->account_name
            ?? $model->trust_name
            ?? $model->business_name
            ?? $model->description
            ?? $model->first_name
            ?? "#{$entityId}";

        return DB::transaction(function () use ($model, $entityType, $entityId, $name) {
            $model->delete();

            return [
                'success' => true,
                'deleted' => true,
                'entity_type' => $entityType,
                'entity_id' => $entityId,
                'message' => ucfirst(str_replace('_', ' ', $entityType))." \"{$name}\" deleted.",
            ];
        });
    }

    /**
     * Resolve a model instance by entity type and ID, ensuring it belongs to the user.
     */
    private function resolveModel(string $entityType, int $entityId, int $userId): mixed
    {
        $modelClass = match ($entityType) {
            'goal' => Goal::class,
            'life_event' => LifeEvent::class,
            'savings_account' => SavingsAccount::class,
            'investment_account' => InvestmentAccount::class,
            'dc_pension' => DCPension::class,
            'db_pension' => DBPension::class,
            'property' => Property::class,
            'mortgage' => Mortgage::class,
            'life_insurance' => LifeInsurancePolicy::class,
            'critical_illness' => CriticalIllnessPolicy::class,
            'income_protection' => IncomeProtectionPolicy::class,
            'estate_asset' => Asset::class,
            'estate_liability' => Liability::class,
            'estate_gift' => Gift::class,
            'family_member' => FamilyMember::class,
            'trust' => Trust::class,
            'business_interest' => BusinessInterest::class,
            'chattel' => Chattel::class,
            default => null,
        };

        if (! $modelClass) {
            return ['error' => true, 'error_type' => 'invalid_entity', 'message' => "Unknown entity type: {$entityType}"];
        }

        $model = $modelClass::where('id', $entityId)->where('user_id', $userId)->first();

        if (! $model) {
            return ['error' => true, 'error_type' => 'not_found', 'message' => ucfirst(str_replace('_', ' ', $entityType)).' not found or does not belong to you.'];
        }

        return $model;
    }

    // ─── Profile update handler ─────────────────────────────────────────

    private function handleUpdateProfile(array $input, User $user, bool $isPreview): array
    {
        if ($isPreview) {
            return $this->previewBlocked('profile');
        }

        $section = (string) ($input['section'] ?? '');
        $fields = $input['fields'] ?? [];

        if (! is_array($fields) || empty($fields)) {
            return ['error' => true, 'error_type' => 'validation_failed', 'message' => 'No fields provided to update.'];
        }

        // Save Tax spouse review data belongs to the household-input row, not
        // the authenticated user's profile. Require that reviewed row to exist:
        // an edit must never create a new spouse record or overwrite the user's
        // own income when the model says "my spouse earns ...".
        if ($section === 'spouse_household') {
            $household = TaxStrategyHouseholdInput::where('user_id', $user->id)->first();
            if ($household === null) {
                return ['error' => true, 'error_type' => 'not_found', 'message' => 'No spouse household details are on file to update.'];
            }

            $allowedFields = [
                'spouse_works',
                'spouse_annual_income', 'spouse_employment_status', 'spouse_isa_balance',
                'spouse_psa_band', 'spouse_unrealised_gains', 'spouse_annual_dividends',
                'spouse_pension_input_annual', 'spouse_existing_isa_balance',
                'spouse_existing_savings_balance', 'spouse_existing_investment_balance',
                'spouse_existing_dividend_holdings_value', 'spouse_existing_pension_balance',
            ];
            $disallowed = array_diff(array_keys($fields), $allowedFields);
            if ($disallowed !== []) {
                return [
                    'error' => true,
                    'error_type' => 'validation_failed',
                    'message' => 'One or more spouse fields cannot be updated.',
                ];
            }
            if ($fields === []) {
                return ['error' => true, 'error_type' => 'validation_failed', 'message' => 'None of the provided spouse fields can be updated.'];
            }

            $moneyFields = [
                'spouse_annual_income', 'spouse_isa_balance', 'spouse_unrealised_gains',
                'spouse_annual_dividends', 'spouse_pension_input_annual',
                'spouse_existing_isa_balance', 'spouse_existing_savings_balance',
                'spouse_existing_investment_balance', 'spouse_existing_dividend_holdings_value',
                'spouse_existing_pension_balance',
            ];
            $rules = [
                'spouse_works' => ['sometimes', 'boolean'],
                'spouse_employment_status' => ['sometimes', 'nullable', Rule::in(['employed', 'full_time', 'part_time', 'self_employed', 'retired', 'unemployed', 'other'])],
                'spouse_psa_band' => ['sometimes', 'nullable', Rule::in(['basic', 'higher', 'additional'])],
            ];
            foreach ($moneyFields as $field) {
                $rules[$field] = ['sometimes', 'nullable', 'numeric', 'min:'.ValidationLimits::MIN_CURRENCY_VALUE, 'max:'.ValidationLimits::MAX_CURRENCY_VALUE];
            }
            $validator = Validator::make($fields, $rules);
            if ($validator->fails()) {
                return [
                    'error' => true,
                    'error_type' => 'validation_failed',
                    'errors' => $validator->errors()->toArray(),
                    'message' => 'Validation failed for spouse household details.',
                ];
            }

            $safeFields = $fields;
            $works = array_key_exists('spouse_works', $safeFields) ? (bool) $safeFields['spouse_works'] : null;
            unset($safeFields['spouse_works']);
            if (array_key_exists('spouse_annual_income', $safeFields)) {
                $safeFields['spouse_psa_band'] = app(TaxStrategyMath::class)
                    ->bandFromIncome((float) $safeFields['spouse_annual_income']);
            }

            DB::transaction(function () use ($user, $household, $safeFields, $works): void {
                if ($works !== null) {
                    $user->update([
                        'household_calculation_mode' => $works ? 'dual_earner' : 'single_earner_couple',
                        'marriage_allowance_eligible' => ! $works,
                    ]);
                    $safeFields = array_merge($safeFields, array_fill_keys($works ? [
                        'spouse_existing_isa_balance', 'spouse_existing_savings_balance',
                        'spouse_existing_investment_balance', 'spouse_existing_dividend_holdings_value',
                        'spouse_existing_pension_balance',
                    ] : [
                        'spouse_annual_income', 'spouse_employment_status', 'spouse_isa_balance',
                        'spouse_psa_band', 'spouse_unrealised_gains', 'spouse_annual_dividends',
                        'spouse_pension_input_annual',
                    ], null));
                }
                if ($safeFields !== []) {
                    $household->update($safeFields);
                }
            });

            return [
                'updated' => true,
                'section' => $section,
                'fields_updated' => array_keys($fields),
                'message' => 'Spouse household details updated successfully.',
            ];
        }

        // The campaign review page shows a single monthly total. Preserve that
        // representation when it is edited and keep ExpenditureProfile in sync.
        // Category-shaped updates still use the canonical category handler.
        if ($section === 'expenditure') {
            $hasMonthlyTotal = array_key_exists('monthly_expenditure', $fields);
            $hasAnnualTotal = array_key_exists('annual_expenditure', $fields);
            $categoryFields = array_diff(array_keys($fields), ['monthly_expenditure', 'annual_expenditure']);
            if (($hasMonthlyTotal || $hasAnnualTotal) && $categoryFields !== []) {
                return ['error' => true, 'error_type' => 'validation_failed', 'message' => 'Provide either a total expenditure amount or category amounts, not both.'];
            }
            if ($hasMonthlyTotal && $hasAnnualTotal) {
                if (! is_numeric($fields['monthly_expenditure']) || ! is_numeric($fields['annual_expenditure'])
                    || abs(((float) $fields['monthly_expenditure'] * 12) - (float) $fields['annual_expenditure']) > 0.01) {
                    return ['error' => true, 'error_type' => 'validation_failed', 'message' => 'Monthly and annual expenditure totals do not agree.'];
                }
            }
            if (! $hasMonthlyTotal && ! $hasAnnualTotal) {
                return $this->handleSetExpenditure($fields, $user, $isPreview);
            }

            $sourceAmount = $hasMonthlyTotal
                ? $fields['monthly_expenditure']
                : $fields['annual_expenditure'];
            if (! is_numeric($sourceAmount) || (float) $sourceAmount < ValidationLimits::MIN_CURRENCY_VALUE
                || (float) $sourceAmount > ValidationLimits::MAX_CURRENCY_VALUE) {
                return ['error' => true, 'error_type' => 'validation_failed', 'message' => 'Monthly expenditure must be zero or more.'];
            }
            $monthly = $hasMonthlyTotal
                ? (float) $sourceAmount
                : ((float) $sourceAmount / 12);
            DB::transaction(function () use ($user, $monthly): void {
                $user->update([
                    'monthly_expenditure' => $monthly,
                    'annual_expenditure' => $monthly * 12,
                    'expenditure_entry_mode' => 'simple',
                ]);
                ExpenditureProfile::updateOrCreate(
                    ['user_id' => $user->id],
                    ['total_monthly_expenditure' => $monthly],
                );
            });

            return [
                'updated' => true,
                'section' => $section,
                'fields_updated' => ['monthly_expenditure', 'annual_expenditure', 'expenditure_entry_mode'],
                'message' => 'Monthly expenditure updated successfully.',
            ];
        }

        $allowedFields = match ($section) {
            // NI number excluded — sensitive PII should not be AI-writable
            'personal' => ['first_name', 'surname', 'date_of_birth', 'gender', 'marital_status', 'phone', 'address_line_1', 'address_line_2', 'city', 'county', 'postcode'],
            'income_occupation' => ['employment_status', 'occupation', 'employer', 'industry', 'annual_employment_income', 'annual_self_employment_income', 'annual_dividend_income', 'annual_other_income', 'target_retirement_age'],
            'expenditure' => ['monthly_expenditure', 'annual_expenditure', 'expenditure_entry_mode'],
            'domicile' => ['country_of_birth', 'uk_arrival_date', 'domicile_status'],
            default => [],
        };

        if (empty($allowedFields)) {
            return ['error' => true, 'error_type' => 'validation_failed', 'message' => "Unknown profile section: {$section}"];
        }

        $safeFields = array_intersect_key($fields, array_flip($allowedFields));
        if (empty($safeFields)) {
            return ['error' => true, 'error_type' => 'validation_failed', 'message' => 'None of the provided fields are valid for this profile section.'];
        }

        if ($section === 'income_occupation') {
            $disallowed = array_diff(array_keys($fields), $allowedFields);
            if ($disallowed !== []) {
                return ['error' => true, 'error_type' => 'validation_failed', 'message' => 'One or more income fields cannot be updated.'];
            }
            $rules = [
                'employment_status' => ['sometimes', Rule::in(['employed', 'full_time', 'part_time', 'self_employed', 'retired', 'unemployed', 'other'])],
                'occupation' => ['sometimes', 'nullable', 'string', 'max:255'],
                'employer' => ['sometimes', 'nullable', 'string', 'max:255'],
                'industry' => ['sometimes', 'nullable', 'string', 'max:255'],
                'target_retirement_age' => ['sometimes', 'nullable', 'integer', 'min:'.ValidationLimits::MIN_RETIREMENT_AGE, 'max:'.ValidationLimits::MAX_RETIREMENT_AGE],
            ];
            foreach (['annual_employment_income', 'annual_self_employment_income', 'annual_dividend_income', 'annual_other_income'] as $field) {
                $rules[$field] = ['sometimes', 'nullable', 'numeric', 'min:'.ValidationLimits::MIN_CURRENCY_VALUE, 'max:'.ValidationLimits::MAX_CURRENCY_VALUE];
            }
            $validator = Validator::make($safeFields, $rules);
            if ($validator->fails()) {
                return [
                    'error' => true,
                    'error_type' => 'validation_failed',
                    'errors' => $validator->errors()->toArray(),
                    'message' => 'Validation failed for income details.',
                ];
            }

            $newStatus = $safeFields['employment_status'] ?? null;
            $oldStatus = (string) ($user->employment_status ?? '');
            $oldIsSelfEmployed = $oldStatus === 'self_employed';
            $newIsSelfEmployed = $newStatus === 'self_employed';
            $newIsEmployed = in_array($newStatus, ['employed', 'full_time', 'part_time'], true);
            if ($newStatus !== null && $newIsSelfEmployed !== $oldIsSelfEmployed) {
                $replacementField = $newIsSelfEmployed
                    ? 'annual_self_employment_income'
                    : ($newIsEmployed ? 'annual_employment_income' : null);
                if ($replacementField !== null && ! array_key_exists($replacementField, $safeFields)) {
                    return [
                        'error' => true,
                        'error_type' => 'validation_failed',
                        'message' => 'Include the replacement annual income when changing employment type.',
                    ];
                }
            }

            if ($newIsSelfEmployed && array_key_exists('annual_self_employment_income', $safeFields)) {
                $safeFields['annual_employment_income'] = null;
            } elseif ($newIsEmployed && array_key_exists('annual_employment_income', $safeFields)) {
                $safeFields['annual_self_employment_income'] = null;
            } elseif (in_array($newStatus, ['retired', 'unemployed'], true)) {
                $safeFields['annual_employment_income'] = null;
                $safeFields['annual_self_employment_income'] = null;
            }
        }

        $user->update($safeFields);

        return ['updated' => true, 'section' => $section, 'fields_updated' => array_keys($safeFields), 'message' => 'Profile ('.str_replace('_', ' ', $section).') updated successfully.'];
    }
}
