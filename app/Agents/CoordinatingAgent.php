<?php

declare(strict_types=1);

namespace App\Agents;

use Anthropic\Client as AnthropicClient;
use App\Models\CriticalIllnessPolicy;
use App\Models\DBPension;
use App\Models\DCPension;
use App\Models\Estate\Asset;
use App\Models\Estate\Gift;
use App\Models\Estate\Liability;
use App\Models\Goal;
use App\Models\IncomeProtectionPolicy;
use App\Models\Investment\InvestmentAccount;
use App\Models\LifeEvent;
use App\Models\LifeInsurancePolicy;
use App\Models\Mortgage;
use App\Models\Property;
use App\Models\SavingsAccount;
use App\Models\BusinessInterest;
use App\Models\Chattel;
use App\Models\Estate\Trust;
use App\Models\FamilyMember;
use App\Models\User;
use App\Services\AI\AiToolDefinitions;
use App\Services\Coordination\CashFlowCoordinator;
use App\Services\Coordination\ConflictResolver;
use App\Services\Coordination\CrossModuleStrategyService;
use App\Services\Coordination\HolisticPlanner;
use App\Services\Coordination\PriorityRanker;
use App\Services\NetWorth\NetWorthService;
use App\Services\PrerequisiteGateService;
use App\Services\TaxConfigService;
use App\Traits\HasAiChat;
use App\Traits\HasAiGuardrails;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

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
        private readonly AnthropicClient $anthropicClient,
        private readonly AiToolDefinitions $toolDefinitions,
        private readonly NetWorthService $netWorthService,
        private readonly PrerequisiteGateService $prerequisiteGate,
    ) {}

    /**
     * Analyze user data and generate insights (BaseAgent requirement)
     */
    public function analyze(int $userId): array
    {
        return $this->orchestrateAnalysis($userId);
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
        $user = \App\Models\User::find($userId);
        if ($user) {
            $crossModuleStrategies = $this->crossModuleStrategyService->generateCrossModuleStrategies($allAnalysis, $user);
        }

        return [
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
        $user = \App\Models\User::find($userId);
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
            \Illuminate\Support\Facades\Log::error("{$module} analysis failed: ".$e->getMessage());

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
    public function executeTool(string $toolName, array $input, User $user): array
    {
        $isPreviewUser = $user->is_preview_user;

        // Prerequisite gate check
        $gate = $this->prerequisiteGate->canExecuteTool($toolName, $input, $user);
        if (! $gate['can_proceed']) {
            $firstAction = $gate['required_actions'][0] ?? null;

            return [
                'blocked' => true,
                'reason' => $gate['guidance'],
                'missing_data' => $gate['missing'],
                'suggested_action' => $firstAction,
                'instruction' => 'Explain to the user exactly what data is missing and why it is needed. '
                    .'List each missing item clearly. '
                    .($firstAction ? "Then use the navigate_to_page tool to take them to \"{$firstAction['route']}\" where they can add the missing information." : ''),
            ];
        }

        try {
            return match ($toolName) {
                'navigate_to_page' => $this->handleNavigation($input),
                'list_goals' => $this->handleListGoals($user),
                'list_life_events' => $this->handleListLifeEvents($user),
                'get_module_analysis' => $this->handleModuleAnalysis($input, $user),
                'create_what_if_scenario' => $this->handleCreateWhatIfScenario($input, $user),
                'get_recommendations' => $this->handleRecommendations($user),
                'get_tax_information' => $this->handleTaxInformation($input),
                'generate_financial_plan' => $this->handleFinancialPlan($user),
                'create_goal' => $this->handleCreateGoal($input, $user, $isPreviewUser),
                'create_life_event' => $this->handleCreateLifeEvent($input, $user, $isPreviewUser),
                'create_savings_account' => $this->handleCreateSavingsAccount($input, $user, $isPreviewUser),
                'create_investment_account' => $this->handleCreateInvestmentAccount($input, $user, $isPreviewUser),
                'create_pension' => $this->handleCreatePension($input, $user, $isPreviewUser),
                'create_property' => $this->handleCreateProperty($input, $user, $isPreviewUser),
                'create_mortgage' => $this->handleCreateMortgage($input, $user, $isPreviewUser),
                'create_protection_policy' => $this->handleCreateProtectionPolicy($input, $user, $isPreviewUser),
                'create_estate_asset' => $this->handleCreateEstateAsset($input, $user, $isPreviewUser),
                'create_estate_liability' => $this->handleCreateEstateLiability($input, $user, $isPreviewUser),
                'create_estate_gift' => $this->handleCreateEstateGift($input, $user, $isPreviewUser),
                'create_family_member' => $this->handleCreateFamilyMember($input, $user, $isPreviewUser),
                'create_trust' => $this->handleCreateTrust($input, $user, $isPreviewUser),
                'create_business_interest' => $this->handleCreateBusinessInterest($input, $user, $isPreviewUser),
                'create_chattel' => $this->handleCreateChattel($input, $user, $isPreviewUser),
                'update_record' => $this->handleUpdateRecord($input, $user, $isPreviewUser),
                'delete_record' => $this->handleDeleteRecord($input, $user, $isPreviewUser),
                'update_profile' => $this->handleUpdateProfile($input, $user, $isPreviewUser),
                default => ['error' => true, 'error_type' => 'unknown_tool', 'message' => "Unknown tool: {$toolName}"],
            };
        } catch (\Illuminate\Validation\ValidationException $e) {
            return ['error' => true, 'error_type' => 'validation_failed', 'message' => $e->validator->errors()->first()];
        } catch (\Illuminate\Database\QueryException $e) {
            Log::error('[CoordinatingAgent] Database error', ['tool' => $toolName, 'user_id' => $user->id, 'error' => $e->getMessage()]);

            return ['error' => true, 'error_type' => 'database_error', 'message' => 'Unable to save the record. Please try again.'];
        } catch (\Exception $e) {
            Log::error('[CoordinatingAgent] Tool execution failed', ['tool' => $toolName, 'user_id' => $user->id, 'error' => $e->getMessage()]);

            return ['error' => true, 'error_type' => 'execution_failed', 'message' => 'An unexpected error occurred. Please try again.'];
        }
    }

    // ─── Read-only tool handlers ─────────────────────────────────────

    private function handleNavigation(array $input): array
    {
        return ['action' => 'navigate', 'route_path' => $input['route_path'], 'description' => $input['description'] ?? ''];
    }

    private function handleListGoals(User $user): array
    {
        $goals = \App\Models\Goal::forUserOrJoint($user->id)
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
        $events = \App\Models\LifeEvent::forUserOrJoint($user->id)
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

    private function handleModuleAnalysis(array $input, User $user): array
    {
        $module = $input['module'];

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

        return $this->summariseToolAnalysis($module, $analysis);
    }

    private function handleCreateWhatIfScenario(array $input, User $user): array
    {
        $service = app(\App\Services\WhatIf\WhatIfScenarioService::class);

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
            'route_path' => '/planning/what-if/' . $result['scenario_id'],
        ];
    }

    private function handleRecommendations(User $user): array
    {
        $analysis = $this->orchestrateAnalysis($user->id);

        return [
            'recommendations' => $analysis['ranked_recommendations'] ?? [],
            'total' => count($analysis['ranked_recommendations'] ?? []),
            'surplus' => $analysis['available_surplus'] ?? 0,
        ];
    }

    private function handleTaxInformation(array $input): array
    {
        $topic = $input['topic'];

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
            'goal_type' => ['required', Rule::in(['emergency_fund', 'house_deposit', 'holiday', 'education', 'wedding', 'car', 'retirement_supplement', 'other'])],
            'monthly_contribution' => 'nullable|numeric|min:0|max:999999.99',
        ]);
        if ($validationError) {
            return $validationError;
        }

        return [
            'action' => 'fill_form',
            'entity_type' => 'goal',
            'route' => '/goals',
            'fields' => [
                'goal_name' => $input['name'],
                'goal_type' => $input['goal_type'],
                'target_amount' => $input['target_amount'],
                'target_date' => $input['target_date'],
                'priority' => $input['priority'],
                'monthly_contribution' => $input['monthly_contribution'] ?? null,
            ],
        ];
    }

    private function handleCreateLifeEvent(array $input, User $user, bool $isPreview): array
    {
        if ($isPreview) {
            return $this->previewBlocked('life event');
        }

        $validationError = $this->validateToolInput($input, [
            'event_type' => 'required|string|max:100',
            'event_date' => 'required|date',
            'description' => 'required|string|max:500',
            'estimated_cost' => 'nullable|numeric|min:0|max:999999999.99',
        ]);
        if ($validationError) {
            return $validationError;
        }

        return [
            'action' => 'fill_form',
            'entity_type' => 'life_event',
            'route' => '/goals?tab=events',
            'fields' => [
                'event_name' => $input['description'],
                'event_type' => $input['event_type'],
                'description' => $input['description'],
                'amount' => $input['estimated_cost'] ?? 0,
                'expected_date' => $input['event_date'],
                'certainty' => 'likely',
            ],
        ];
    }

    private function handleCreateSavingsAccount(array $input, User $user, bool $isPreview): array
    {
        if ($isPreview) {
            return $this->previewBlocked('savings account');
        }

        $validationError = $this->validateToolInput($input, [
            'account_name' => 'required|string|max:255',
            'current_balance' => 'required|numeric|min:0|max:999999999.99',
            'account_type' => ['nullable', Rule::in(['easy_access', 'notice', 'fixed_term', 'regular_saver', 'savings_account', 'current_account', 'instant_access', 'fixed', 'cash_isa', 'junior_isa', 'premium_bonds', 'nsi'])],
            'interest_rate' => 'nullable|numeric|min:0|max:25',
            'regular_contribution_amount' => 'nullable|numeric|min:0|max:999999.99',
        ]);
        if ($validationError) {
            return $validationError;
        }

        $duplicateCheck = $this->checkForDuplicate(SavingsAccount::class, $user->id, 'account_name', $input['account_name']);
        if ($duplicateCheck) {
            return $duplicateCheck;
        }

        $isIsa = $input['is_isa'] ?? false;
        $accountType = $input['account_type'] ?? 'easy_access';

        // Map AI account_type to form-compatible account_type
        $formAccountType = match ($accountType) {
            'fixed_term' => 'fixed',
            'regular_saver' => 'easy_access',
            default => $accountType,
        };

        // If ISA, set account_type to cash_isa so the form shows ISA fields
        if ($isIsa && !in_array($formAccountType, ['cash_isa', 'junior_isa'])) {
            $formAccountType = 'cash_isa';
        }

        return [
            'action' => 'fill_form',
            'entity_type' => 'savings_account',
            'route' => '/net-worth/cash',
            'fields' => [
                'institution' => $input['institution'] ?? $input['account_name'],
                'account_type' => $formAccountType,
                'current_balance' => (float) $input['current_balance'],
                'interest_rate' => isset($input['interest_rate']) ? (float) $input['interest_rate'] : null,
                'is_isa' => $isIsa,
                'is_emergency_fund' => $input['is_emergency_fund'] ?? false,
                'regular_contribution_amount' => isset($input['regular_contribution_amount']) ? (float) $input['regular_contribution_amount'] : null,
                'access_type' => match ($formAccountType) {
                    'notice' => 'notice', 'fixed' => 'fixed', default => 'immediate'
                },
            ],
            'message' => "I'll fill in the form for your \"{$input['account_name']}\" account now.",
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
            'account_type' => ['nullable', Rule::in(['stocks_shares_isa', 'lifetime_isa', 'personal_investment_account', 'onshore_bond', 'offshore_bond'])],
            'monthly_contribution_amount' => 'nullable|numeric|min:0|max:999999.99',
            'platform_fee_percent' => 'nullable|numeric|min:0|max:10',
        ]);
        if ($validationError) {
            return $validationError;
        }

        $duplicateCheck = $this->checkForDuplicate(InvestmentAccount::class, $user->id, 'account_name', $input['account_name']);
        if ($duplicateCheck) {
            return $duplicateCheck;
        }

        $accountType = $input['account_type'] ?? 'personal_investment_account';

        $account = InvestmentAccount::create([
            'user_id' => $user->id,
            'account_name' => $input['account_name'],
            'account_type' => $accountType,
            'provider' => $input['provider'] ?? null,
            'current_value' => $input['current_value'],
            'monthly_contribution_amount' => $input['monthly_contribution_amount'] ?? 0,
            'contribution_frequency' => 'monthly',
            'platform_fee_percent' => $input['platform_fee_percent'] ?? null,
            'ownership_type' => 'individual',
            'ownership_percentage' => 100,
            'country' => 'GB',
            'tax_year' => $this->taxConfig->getTaxYear(),
        ]);

        $this->invalidateModuleCache($user->id, 'investment');

        return ['created' => true, 'entity_type' => 'investment_account', 'entity_id' => $account->id, 'name' => $account->account_name, 'message' => "Investment account \"{$account->account_name}\" created with a value of £".number_format((float) $account->current_value, 2).'.'];
    }

    private function handleCreatePension(array $input, User $user, bool $isPreview): array
    {
        if ($isPreview) {
            return $this->previewBlocked('pension');
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

        $category = $input['pension_category'] ?? 'dc';

        if ($category === 'db') {
            $pension = DBPension::create([
                'user_id' => $user->id,
                'scheme_name' => $input['scheme_name'],
                'scheme_type' => $input['scheme_type'] ?? 'final_salary',
                'accrued_annual_pension' => $input['accrued_annual_pension'] ?? 0,
                'normal_retirement_age' => $input['normal_retirement_age'] ?? 67,
                'pensionable_service_years' => $input['pensionable_service_years'] ?? null,
            ]);

            $this->invalidateModuleCache($user->id, 'retirement');

            return ['created' => true, 'entity_type' => 'db_pension', 'entity_id' => $pension->id, 'name' => $pension->scheme_name, 'message' => "Defined Benefit pension \"{$pension->scheme_name}\" created".($pension->accrued_annual_pension > 0 ? ' with an accrued pension of £'.number_format((float) $pension->accrued_annual_pension, 2).' per year' : '').'.'];
        }

        $pension = DCPension::create([
            'user_id' => $user->id,
            'scheme_name' => $input['scheme_name'],
            'scheme_type' => $input['scheme_type'] ?? 'workplace',
            'provider' => $input['provider'] ?? null,
            'current_fund_value' => $input['current_fund_value'] ?? 0,
            'employee_contribution_percent' => $input['employee_contribution_percent'] ?? null,
            'employer_contribution_percent' => $input['employer_contribution_percent'] ?? null,
            'retirement_age' => $input['normal_retirement_age'] ?? 67,
        ]);

        $this->invalidateModuleCache($user->id, 'retirement');

        return ['created' => true, 'entity_type' => 'dc_pension', 'entity_id' => $pension->id, 'name' => $pension->scheme_name, 'message' => "Defined Contribution pension \"{$pension->scheme_name}\" created".($pension->current_fund_value > 0 ? ' with a fund value of £'.number_format((float) $pension->current_fund_value, 2) : '').'.'];
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
            'outstanding_mortgage' => 'nullable|numeric|min:0|max:999999999.99',
            'mortgage_rate' => 'nullable|numeric|min:0|max:25',
            'monthly_rental_income' => 'nullable|numeric|min:0|max:999999.99',
        ]);
        if ($validationError) {
            return $validationError;
        }

        $property = Property::create([
            'user_id' => $user->id,
            'property_type' => $input['property_type'] ?? 'main_residence',
            'current_value' => $input['current_value'],
            'purchase_price' => $input['purchase_price'] ?? null,
            'purchase_date' => $input['purchase_date'] ?? null,
            'address_line_1' => $input['address_line_1'] ?? null,
            'postcode' => $input['postcode'] ?? null,
            'outstanding_mortgage' => $input['outstanding_mortgage'] ?? 0,
            'monthly_rental_income' => $input['monthly_rental_income'] ?? null,
            'ownership_type' => 'individual',
            'ownership_percentage' => 100,
            'country' => 'GB',
        ]);

        $mortgageMessage = '';
        if (! empty($input['outstanding_mortgage']) && $input['outstanding_mortgage'] > 0) {
            Mortgage::create([
                'property_id' => $property->id,
                'user_id' => $user->id,
                'outstanding_balance' => $input['outstanding_mortgage'],
                'interest_rate' => $input['mortgage_rate'] ?? null,
                'lender_name' => $input['mortgage_lender'] ?? null,
                'mortgage_type' => 'repayment',
                'rate_type' => 'fixed',
                'ownership_type' => 'individual',
                'ownership_percentage' => 100,
                'country' => 'GB',
            ]);
            $mortgageMessage = ' A linked mortgage of £'.number_format((float) $input['outstanding_mortgage'], 2).' was also created.';
        }

        $this->invalidateModuleCache($user->id, 'property');

        return ['created' => true, 'entity_type' => 'property', 'entity_id' => $property->id, 'name' => $input['address_line_1'] ?? ucfirst(str_replace('_', ' ', $input['property_type'] ?? 'main_residence')), 'message' => 'Property created with a value of £'.number_format((float) $property->current_value, 2).'.'.$mortgageMessage];
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
            'rate_type' => ['nullable', Rule::in(['fixed', 'variable', 'tracker'])],
            'monthly_payment' => 'nullable|numeric|min:0|max:999999.99',
            'remaining_term_months' => 'nullable|integer|min:1|max:480',
        ]);
        if ($validationError) {
            return $validationError;
        }

        $propertyId = $this->resolvePropertyId($user, $input['property_address_hint'] ?? null);

        if (! $propertyId) {
            return ['error' => true, 'error_type' => 'missing_dependency', 'message' => 'Could not find a matching property. Please create the property first.'];
        }

        $mortgage = Mortgage::create([
            'property_id' => $propertyId,
            'user_id' => $user->id,
            'lender_name' => $input['lender_name'] ?? null,
            'outstanding_balance' => $input['outstanding_balance'],
            'interest_rate' => $input['interest_rate'] ?? null,
            'mortgage_type' => $input['mortgage_type'] ?? 'repayment',
            'rate_type' => $input['rate_type'] ?? 'fixed',
            'monthly_payment' => $input['monthly_payment'] ?? null,
            'remaining_term_months' => $input['remaining_term_months'] ?? null,
            'ownership_type' => 'individual',
            'ownership_percentage' => 100,
            'country' => 'GB',
        ]);

        $this->invalidateModuleCache($user->id, 'property');

        return ['created' => true, 'entity_type' => 'mortgage', 'entity_id' => $mortgage->id, 'name' => ($input['lender_name'] ?? 'Mortgage').' mortgage', 'message' => 'Mortgage created with an outstanding balance of £'.number_format((float) $mortgage->outstanding_balance, 2).'.'];
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

        if ($policyType === 'income_protection') {
            $policy = IncomeProtectionPolicy::create([
                'user_id' => $user->id,
                'provider' => $input['provider'] ?? null,
                'benefit_amount' => $input['benefit_amount'] ?? 0,
                'benefit_frequency' => 'monthly',
                'premium_amount' => $input['premium_amount'] ?? null,
            ]);
            $this->invalidateModuleCache($user->id, 'protection');

            return ['created' => true, 'entity_type' => 'income_protection_policy', 'entity_id' => $policy->id, 'name' => ($input['provider'] ?? 'Income protection').' policy', 'message' => 'Income protection policy created'.($policy->benefit_amount > 0 ? ' with a monthly benefit of £'.number_format((float) $policy->benefit_amount, 2) : '').'.'];
        }

        if (in_array($policyType, ['standalone_ci', 'accelerated_ci'])) {
            $ciType = $policyType === 'standalone_ci' ? 'standalone' : 'accelerated';
            $policy = CriticalIllnessPolicy::create([
                'user_id' => $user->id,
                'policy_type' => $ciType,
                'provider' => $input['provider'] ?? null,
                'sum_assured' => $input['sum_assured'] ?? 0,
                'premium_amount' => $input['premium_amount'] ?? null,
                'premium_frequency' => $input['premium_frequency'] ?? 'monthly',
                'policy_term_years' => $input['policy_term_years'] ?? null,
            ]);
            $this->invalidateModuleCache($user->id, 'protection');

            return ['created' => true, 'entity_type' => 'critical_illness_policy', 'entity_id' => $policy->id, 'name' => ($input['provider'] ?? 'Critical illness').' policy', 'message' => 'Critical illness policy created'.($policy->sum_assured > 0 ? ' for £'.number_format((float) $policy->sum_assured, 2) : '').'.'];
        }

        // Life insurance (term, whole of life, etc.)
        $policy = LifeInsurancePolicy::create([
            'user_id' => $user->id,
            'policy_type' => $policyType,
            'provider' => $input['provider'] ?? null,
            'sum_assured' => $input['sum_assured'] ?? 0,
            'premium_amount' => $input['premium_amount'] ?? null,
            'premium_frequency' => $input['premium_frequency'] ?? 'monthly',
            'policy_term_years' => $input['policy_term_years'] ?? null,
            'in_trust' => $input['in_trust'] ?? false,
        ]);
        $this->invalidateModuleCache($user->id, 'protection');

        $typeLabel = str_replace('_', ' ', $policyType);

        return ['created' => true, 'entity_type' => 'life_insurance_policy', 'entity_id' => $policy->id, 'name' => ($input['provider'] ?? 'Life insurance').' - '.$typeLabel, 'message' => 'Life insurance policy created'.($policy->sum_assured > 0 ? ' for £'.number_format((float) $policy->sum_assured, 2) : '').'.'];
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

        $asset = Asset::create([
            'user_id' => $user->id,
            'asset_name' => $input['asset_name'],
            'asset_type' => $input['asset_type'],
            'current_value' => $input['current_value'],
            'is_iht_exempt' => $input['is_iht_exempt'] ?? false,
            'exemption_reason' => $input['exemption_reason'] ?? null,
            'ownership_type' => 'individual',
            'valuation_date' => now()->toDateString(),
        ]);
        $this->invalidateModuleCache($user->id, 'estate');

        return ['created' => true, 'entity_type' => 'estate_asset', 'entity_id' => $asset->id, 'name' => $asset->asset_name, 'message' => "Estate asset \"{$asset->asset_name}\" created with a value of £".number_format((float) $asset->current_value, 2).'.'];
    }

    private function handleCreateEstateLiability(array $input, User $user, bool $isPreview): array
    {
        if ($isPreview) {
            return $this->previewBlocked('estate liability');
        }

        $validationError = $this->validateToolInput($input, [
            'liability_name' => 'required|string|max:255',
            'liability_type' => ['required', Rule::in(['loan', 'personal_loan', 'credit_card', 'mortgage', 'student_loan', 'other'])],
            'current_balance' => 'required|numeric|min:0|max:999999999.99',
            'monthly_payment' => 'nullable|numeric|min:0|max:999999.99',
            'interest_rate' => 'nullable|numeric|min:0|max:50',
        ]);
        if ($validationError) {
            return $validationError;
        }

        $liability = Liability::create([
            'user_id' => $user->id,
            'liability_name' => $input['liability_name'],
            'liability_type' => $input['liability_type'],
            'current_balance' => $input['current_balance'],
            'monthly_payment' => $input['monthly_payment'] ?? null,
            'interest_rate' => $input['interest_rate'] ?? null,
            'ownership_type' => 'individual',
            'country' => 'GB',
        ]);
        $this->invalidateModuleCache($user->id, 'estate');

        return ['created' => true, 'entity_type' => 'estate_liability', 'entity_id' => $liability->id, 'name' => $liability->liability_name, 'message' => "Estate liability \"{$liability->liability_name}\" created with a balance of £".number_format((float) $liability->current_balance, 2).'.'];
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

        $gift = Gift::create([
            'user_id' => $user->id,
            'gift_date' => substr($input['gift_date'], 0, 10),
            'recipient' => $input['recipient'],
            'gift_type' => $input['gift_type'] ?? 'pet',
            'gift_value' => $input['gift_value'],
            'status' => 'within_7_years',
            'notes' => $input['notes'] ?? null,
        ]);
        $this->invalidateModuleCache($user->id, 'estate');

        return ['created' => true, 'entity_type' => 'estate_gift', 'entity_id' => $gift->id, 'name' => "Gift to {$gift->recipient}", 'message' => 'Gift of £'.number_format((float) $gift->gift_value, 2)." to {$gift->recipient} recorded."];
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

    private function checkForDuplicate(string $modelClass, int $userId, string $nameField, string $nameValue): ?array
    {
        $allowedColumns = ['first_name', 'surname', 'name', 'email', 'asset_name', 'liability_name', 'trust_name', 'scheme_name', 'provider', 'account_name', 'policy_name', 'gift_type'];
        if (!in_array($nameField, $allowedColumns, true)) {
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
     * Summarise analysis data for tool result.
     */
    private function summariseToolAnalysis(string $module, array $analysis): array
    {
        if (isset($analysis['error'])) {
            return $analysis;
        }

        $summary = ['module' => $module];

        if (isset($analysis['data'])) {
            $data = $analysis['data'];
            $summary['metrics'] = $this->extractKeyMetrics($data);
            $summary['recommendations'] = array_slice($data['recommendations'] ?? [], 0, 5);
        } elseif (isset($analysis['summary'])) {
            $summary['metrics'] = $analysis['summary'];
            $summary['recommendations'] = array_slice($analysis['ranked_recommendations'] ?? [], 0, 5);
        } else {
            $summary['metrics'] = $analysis;
        }

        return $summary;
    }

    private function extractKeyMetrics(array $data): array
    {
        $metrics = [];
        $keyFields = [
            'total_value', 'total_cover', 'coverage_gaps', 'net_worth',
            'monthly_surplus', 'emergency_fund_months', 'pension_projection',
            'iht_liability', 'total_savings', 'total_investments',
            'retirement_income', 'target_income', 'shortfall',
            'risk_score', 'asset_allocation', 'progress_percentage',
        ];

        foreach ($keyFields as $field) {
            if (isset($data[$field])) {
                $metrics[$field] = $data[$field];
            }
        }

        return $metrics;
    }

    // ─── Additional creation tool handlers ──────────────────────────────

    private function handleCreateFamilyMember(array $input, User $user, bool $isPreview): array
    {
        if ($isPreview) {
            return $this->previewBlocked('family member');
        }

        $validationError = $this->validateToolInput($input, [
            'first_name' => 'required|string|max:255',
            'relationship' => ['required', Rule::in(['spouse', 'child', 'parent', 'sibling', 'other'])],
            'surname' => 'nullable|string|max:255',
            'date_of_birth' => 'nullable|date',
            'gender' => ['nullable', Rule::in(['male', 'female', 'other'])],
            'is_dependent' => 'nullable|boolean',
        ]);
        if ($validationError) {
            return $validationError;
        }

        return [
            'action' => 'fill_form',
            'entity_type' => 'family_member',
            'route' => '/profile',
            'fields' => [
                'first_name' => $input['first_name'],
                'last_name' => $input['surname'] ?? '',
                'relationship' => $input['relationship'],
                'date_of_birth' => $input['date_of_birth'] ?? null,
                'gender' => $input['gender'] ?? null,
                'is_dependent' => $input['is_dependent'] ?? ($input['relationship'] === 'child'),
            ],
        ];
    }

    private function handleCreateTrust(array $input, User $user, bool $isPreview): array
    {
        if ($isPreview) {
            return $this->previewBlocked('trust');
        }

        $validationError = $this->validateToolInput($input, [
            'trust_name' => 'required|string|max:255',
            'trust_type' => ['required', Rule::in(['discretionary', 'bare', 'interest_in_possession', 'life_insurance', 'loan', 'discounted_gift', 'accumulation_maintenance'])],
            'current_value' => 'nullable|numeric|min:0|max:999999999.99',
            'date_established' => 'nullable|date',
            'settlor' => 'nullable|string|max:255',
        ]);
        if ($validationError) {
            return $validationError;
        }

        $trust = Trust::create([
            'user_id' => $user->id,
            'trust_name' => $input['trust_name'],
            'trust_type' => $input['trust_type'],
            'current_value' => $input['current_value'] ?? 0,
            'date_established' => $input['date_established'] ?? null,
            'settlor' => $input['settlor'] ?? null,
        ]);

        return ['created' => true, 'entity_type' => 'trust', 'entity_id' => $trust->id, 'name' => $trust->trust_name, 'message' => "Trust \"{$trust->trust_name}\" created."];
    }

    private function handleCreateBusinessInterest(array $input, User $user, bool $isPreview): array
    {
        if ($isPreview) {
            return $this->previewBlocked('business interest');
        }

        $validationError = $this->validateToolInput($input, [
            'business_name' => 'required|string|max:255',
            'business_type' => ['required', Rule::in(['sole_trader', 'partnership', 'limited_company', 'llp'])],
            'ownership_percentage' => 'nullable|numeric|min:0|max:100',
            'estimated_value' => 'nullable|numeric|min:0|max:999999999.99',
            'annual_profit' => 'nullable|numeric|min:0|max:999999999.99',
        ]);
        if ($validationError) {
            return $validationError;
        }

        $business = BusinessInterest::create([
            'user_id' => $user->id,
            'business_name' => $input['business_name'],
            'business_type' => $input['business_type'],
            'ownership_percentage' => $input['ownership_percentage'] ?? 100,
            'estimated_value' => $input['estimated_value'] ?? 0,
            'annual_profit' => $input['annual_profit'] ?? 0,
        ]);

        return ['created' => true, 'entity_type' => 'business_interest', 'entity_id' => $business->id, 'name' => $business->business_name, 'message' => "Business interest \"{$business->business_name}\" recorded."];
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

        $chattel = Chattel::create([
            'user_id' => $user->id,
            'description' => $input['description'],
            'category' => $input['category'] ?? 'other',
            'estimated_value' => $input['estimated_value'],
            'purchase_value' => $input['purchase_value'] ?? null,
            'is_insured' => $input['is_insured'] ?? false,
        ]);

        return ['created' => true, 'entity_type' => 'chattel', 'entity_id' => $chattel->id, 'name' => $chattel->description, 'message' => "Personal valuable \"{$chattel->description}\" recorded (£" . number_format($chattel->estimated_value, 0) . ').'];
    }

    // ─── Generic update/delete handlers ─────────────────────────────────

    private function handleUpdateRecord(array $input, User $user, bool $isPreview): array
    {
        if ($isPreview) {
            return $this->previewBlocked('record');
        }

        $entityType = $input['entity_type'];
        $entityId = (int) $input['entity_id'];
        $fields = $input['fields'] ?? [];

        if (empty($fields)) {
            return ['error' => true, 'error_type' => 'validation_failed', 'message' => 'No fields provided to update.'];
        }

        $model = $this->resolveModel($entityType, $entityId, $user->id);
        if (isset($model['error'])) {
            return $model;
        }

        // Only allow updating fillable fields
        $fillable = $model->getFillable();
        $safeFields = array_intersect_key($fields, array_flip($fillable));
        // Never allow changing user_id or id
        unset($safeFields['user_id'], $safeFields['id']);

        if (empty($safeFields)) {
            return ['error' => true, 'error_type' => 'validation_failed', 'message' => 'None of the provided fields are editable.'];
        }

        $route = $this->getRouteForEntityType($entityType);

        return [
            'action' => 'fill_form',
            'mode' => 'edit',
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'route' => $route,
            'fields' => $safeFields,
            'message' => "I'll update the " . str_replace('_', ' ', $entityType) . ' for you now.',
        ];
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

        $entityType = $input['entity_type'];
        $entityId = (int) $input['entity_id'];

        $model = $this->resolveModel($entityType, $entityId, $user->id);
        if (isset($model['error'])) {
            return $model;
        }

        $name = $model->goal_name ?? $model->account_name ?? $model->trust_name ?? $model->business_name ?? $model->description ?? $model->first_name ?? "#{$entityId}";

        $model->delete();

        return ['deleted' => true, 'entity_type' => $entityType, 'entity_id' => $entityId, 'message' => ucfirst(str_replace('_', ' ', $entityType)) . " \"{$name}\" deleted."];
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
            return ['error' => true, 'error_type' => 'not_found', 'message' => ucfirst(str_replace('_', ' ', $entityType)) . " not found or does not belong to you."];
        }

        return $model;
    }

    // ─── Profile update handler ─────────────────────────────────────────

    private function handleUpdateProfile(array $input, User $user, bool $isPreview): array
    {
        if ($isPreview) {
            return $this->previewBlocked('profile');
        }

        $section = $input['section'];
        $fields = $input['fields'] ?? [];

        if (empty($fields)) {
            return ['error' => true, 'error_type' => 'validation_failed', 'message' => 'No fields provided to update.'];
        }

        $allowedFields = match ($section) {
            'personal' => ['first_name', 'surname', 'date_of_birth', 'gender', 'marital_status', 'phone', 'address_line_1', 'address_line_2', 'city', 'county', 'postcode', 'national_insurance_number'],
            'income_occupation' => ['employment_status', 'occupation', 'employer', 'industry', 'annual_employment_income', 'annual_self_employment_income', 'annual_rental_income', 'annual_dividend_income', 'annual_other_income', 'target_retirement_age'],
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

        $user->update($safeFields);

        return ['updated' => true, 'section' => $section, 'fields_updated' => array_keys($safeFields), 'message' => 'Profile (' . str_replace('_', ' ', $section) . ') updated successfully.'];
    }
}
