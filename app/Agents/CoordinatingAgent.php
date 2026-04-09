<?php

declare(strict_types=1);

namespace App\Agents;

use App\Models\BusinessInterest;
use App\Models\Chattel;
use App\Models\CriticalIllnessPolicy;
use App\Models\DBPension;
use App\Models\DCPension;
use App\Models\Estate\Asset;
use App\Models\Estate\Gift;
use App\Models\Estate\Liability;
use App\Models\Estate\Trust;
use App\Models\FamilyMember;
use App\Models\Goal;
use App\Models\IncomeProtectionPolicy;
use App\Models\Investment\InvestmentAccount;
use App\Models\LifeEvent;
use App\Models\LifeInsurancePolicy;
use App\Models\Mortgage;
use App\Models\Property;
use App\Models\SavingsAccount;
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
            $result = match ($toolName) {
                'navigate_to_page' => $this->handleNavigation($input),
                'list_records' => $this->handleListRecords($input, $user),
                'list_goals' => $this->handleListGoals($user),
                'list_life_events' => $this->handleListLifeEvents($user),
                'get_module_analysis' => $this->handleModuleAnalysis($input, $user),
                'create_what_if_scenario' => $this->handleCreateWhatIfScenario($input, $user),
                'get_recommendations' => $this->handleRecommendations($user),
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
                'create_family_member' => $this->handleCreateFamilyMember($input, $user, $isPreviewUser),
                'create_trust' => $this->handleCreateTrust($input, $user, $isPreviewUser),
                'create_business_interest' => $this->handleCreateBusinessInterest($input, $user, $isPreviewUser),
                'create_chattel' => $this->handleCreateChattel($input, $user, $isPreviewUser),
                'set_expenditure' => $this->handleSetExpenditure($input, $user, $isPreviewUser),
                'update_record' => $this->handleUpdateRecord($input, $user, $isPreviewUser),
                'delete_record' => $this->handleDeleteRecord($input, $user, $isPreviewUser),
                'update_profile' => $this->handleUpdateProfile($input, $user, $isPreviewUser),
                default => ['error' => true, 'error_type' => 'unknown_tool', 'message' => "Unknown tool: {$toolName}"],
            };

            // Audit log for write operations (create, update, delete, profile changes)
            if (str_starts_with($toolName, 'create_') || in_array($toolName, ['update_record', 'delete_record', 'update_profile'])) {
                $entityId = $result['id'] ?? $result['data']['id'] ?? ($input['id'] ?? null);
                Log::channel('single')->info('[AI-AUDIT] Tool executed', [
                    'user_id' => $user->id,
                    'tool' => $toolName,
                    'entity_id' => $entityId,
                    'success' => ! isset($result['error']),
                    'preview' => $isPreviewUser,
                ]);
            }

            return $result;
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
                $items = \App\Models\SavingsAccount::where('user_id', $userId)->orWhere('joint_owner_id', $userId)->get();
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
                $items = \App\Models\Investment\InvestmentAccount::where('user_id', $userId)->orWhere('joint_owner_id', $userId)->get();
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
                $items = \App\Models\DCPension::where('user_id', $userId)->get();
                $records = $items->map(fn ($p) => ['id' => $p->id, 'scheme_name' => $p->scheme_name, 'pension_type' => $p->pension_type, 'provider' => $p->provider, 'current_value' => (float) $p->current_fund_value, 'employee_contribution' => (float) ($p->employee_contribution_percent ?? 0), 'employer_contribution' => (float) ($p->employer_contribution_percent ?? 0), 'employer_matching_limit' => $p->employer_matching_limit ? (float) $p->employer_matching_limit : null, 'monthly_contribution' => $p->monthly_contribution_amount ? (float) $p->monthly_contribution_amount : null, 'platform_fee_percent' => $p->platform_fee_percent ? (float) $p->platform_fee_percent : null, 'retirement_age' => $p->retirement_age, 'projected_value_at_retirement' => $p->projected_value_at_retirement ? (float) $p->projected_value_at_retirement : null, 'has_flexibly_accessed' => (bool) $p->has_flexibly_accessed])->toArray();
                break;
            case 'db_pension':
                $items = \App\Models\DBPension::where('user_id', $userId)->get();
                $records = $items->map(fn ($p) => ['id' => $p->id, 'scheme_name' => $p->scheme_name, 'scheme_type' => $p->scheme_type, 'annual_pension' => (float) ($p->accrued_annual_pension ?? 0), 'service_years' => $p->pensionable_service_years, 'pensionable_salary' => $p->pensionable_salary ? (float) $p->pensionable_salary : null, 'normal_retirement_age' => $p->normal_retirement_age, 'spouse_pension_percent' => $p->spouse_pension_percent ? (float) $p->spouse_pension_percent : null, 'lump_sum_entitlement' => $p->lump_sum_entitlement ? (float) $p->lump_sum_entitlement : null, 'inflation_protection' => $p->inflation_protection])->toArray();
                break;
            case 'property':
                $items = \App\Models\Property::with('mortgages')->where('user_id', $userId)->orWhere('joint_owner_id', $userId)->get();
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
                $items = \App\Models\Mortgage::whereHas('property', fn ($q) => $q->where('user_id', $userId)->orWhere('joint_owner_id', $userId))->with('property')->get();
                $records = $items->map(fn ($m) => ['id' => $m->id, 'property' => $m->property->address_line_1 ?? 'Unknown', 'lender' => $m->lender_name, 'outstanding_balance' => (float) $m->outstanding_balance, 'interest_rate' => (float) ($m->interest_rate ?? 0), 'rate_type' => $m->rate_type, 'rate_fix_end_date' => $m->rate_fix_end_date?->format('Y-m-d'), 'monthly_payment' => (float) ($m->monthly_payment ?? 0), 'mortgage_type' => $m->mortgage_type, 'remaining_term_months' => $m->remaining_term_months, 'start_date' => $m->start_date?->format('Y-m-d'), 'maturity_date' => $m->maturity_date?->format('Y-m-d'), 'original_loan_amount' => (float) ($m->original_loan_amount ?? 0)])->toArray();
                break;
            case 'life_insurance':
                $items = \App\Models\LifeInsurancePolicy::where('user_id', $userId)->get();
                $records = $items->map(fn ($p) => ['id' => $p->id, 'provider' => $p->provider, 'type' => $p->policy_type, 'sum_assured' => (float) $p->sum_assured, 'premium' => (float) ($p->premium_amount ?? 0), 'premium_frequency' => $p->premium_frequency, 'policy_start_date' => $p->policy_start_date?->format('Y-m-d'), 'policy_end_date' => $p->policy_end_date?->format('Y-m-d'), 'policy_term_years' => $p->policy_term_years, 'in_trust' => (bool) $p->in_trust, 'is_mortgage_protection' => (bool) $p->is_mortgage_protection, 'joint_life' => (bool) $p->joint_life, 'ownership_type' => $p->ownership_type])->toArray();
                break;
            case 'critical_illness':
                $items = \App\Models\CriticalIllnessPolicy::where('user_id', $userId)->get();
                $records = $items->map(fn ($p) => ['id' => $p->id, 'provider' => $p->provider, 'policy_type' => $p->policy_type, 'sum_assured' => (float) $p->sum_assured, 'premium' => (float) ($p->premium_amount ?? 0), 'premium_frequency' => $p->premium_frequency, 'policy_start_date' => $p->policy_start_date?->format('Y-m-d'), 'policy_term_years' => $p->policy_term_years, 'ownership_type' => $p->ownership_type])->toArray();
                break;
            case 'income_protection':
                $items = \App\Models\IncomeProtectionPolicy::where('user_id', $userId)->get();
                $records = $items->map(fn ($p) => ['id' => $p->id, 'provider' => $p->provider, 'benefit_amount' => (float) $p->benefit_amount, 'benefit_frequency' => $p->benefit_frequency, 'premium' => (float) ($p->premium_amount ?? 0), 'premium_frequency' => $p->premium_frequency, 'deferred_period_weeks' => $p->deferred_period_weeks, 'policy_start_date' => $p->policy_start_date?->format('Y-m-d'), 'ownership_type' => $p->ownership_type])->toArray();
                break;
            case 'trust':
                $items = \App\Models\Estate\Trust::where('user_id', $userId)->get();
                $records = $items->map(fn ($t) => ['id' => $t->id, 'trust_name' => $t->trust_name, 'trust_type' => $t->trust_type, 'current_value' => (float) $t->current_value, 'initial_value' => $t->initial_value ? (float) $t->initial_value : null, 'creation_date' => $t->trust_creation_date?->format('Y-m-d'), 'settlor' => $t->settlor, 'beneficiaries' => $t->beneficiaries, 'trustees' => $t->trustees, 'purpose' => $t->purpose, 'is_relevant_property_trust' => (bool) $t->is_relevant_property_trust, 'retained_income_annual' => $t->retained_income_annual ? (float) $t->retained_income_annual : null, 'loan_amount' => $t->loan_amount ? (float) $t->loan_amount : null, 'is_active' => (bool) $t->is_active])->toArray();
                break;
            case 'business_interest':
                $items = \App\Models\BusinessInterest::where('user_id', $userId)->orWhere('joint_owner_id', $userId)->get();
                $records = $items->map(fn ($b) => array_merge(['id' => $b->id, 'business_name' => $b->business_name, 'business_type' => $b->business_type, 'estimated_value' => (float) $b->current_valuation, 'annual_revenue' => $b->annual_revenue ? (float) $b->annual_revenue : null, 'annual_profit' => $b->annual_profit ? (float) $b->annual_profit : null, 'annual_dividend_income' => $b->annual_dividend_income ? (float) $b->annual_dividend_income : null, 'trading_status' => $b->trading_status, 'employee_count' => $b->employee_count, 'acquisition_date' => $b->acquisition_date?->format('Y-m-d'), 'acquisition_cost' => $b->acquisition_cost ? (float) $b->acquisition_cost : null, 'bpr_eligible' => $b->bpr_eligible], $ownershipFields($b)))->toArray();
                break;
            case 'chattel':
                $items = \App\Models\Chattel::where('user_id', $userId)->orWhere('joint_owner_id', $userId)->get();
                $records = $items->map(fn ($c) => array_merge(['id' => $c->id, 'name' => $c->name, 'description' => $c->description, 'category' => $c->chattel_type, 'estimated_value' => (float) $c->current_value, 'purchase_price' => $c->purchase_price ? (float) $c->purchase_price : null, 'purchase_date' => $c->purchase_date?->format('Y-m-d'), 'make' => $c->make, 'model' => $c->model, 'year' => $c->year], $ownershipFields($c)))->toArray();
                break;
            case 'estate_liability':
                $items = \App\Models\Estate\Liability::where('user_id', $userId)->orWhere('joint_owner_id', $userId)->get();
                $records = $items->map(fn ($l) => array_merge(['id' => $l->id, 'liability_name' => $l->liability_name, 'type' => $l->liability_type, 'balance' => (float) $l->current_balance, 'interest_rate' => $l->interest_rate ? (float) $l->interest_rate : null, 'monthly_payment' => $l->monthly_payment ? (float) $l->monthly_payment : null, 'maturity_date' => $l->maturity_date?->format('Y-m-d'), 'is_priority_debt' => (bool) $l->is_priority_debt], $ownershipFields($l)))->toArray();
                break;
            case 'estate_gift':
                $items = \App\Models\Estate\Gift::where('user_id', $userId)->get();
                $records = $items->map(fn ($g) => ['id' => $g->id, 'recipient' => $g->recipient, 'gift_type' => $g->gift_type, 'value' => (float) $g->gift_value, 'date' => $g->gift_date?->format('Y-m-d'), 'status' => $g->status, 'taper_relief_applicable' => (bool) $g->taper_relief_applicable, 'notes' => $g->notes])->toArray();
                break;
            case 'family_member':
                $items = \App\Models\FamilyMember::where('user_id', $userId)->get();
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
            'route_path' => '/planning/what-if/'.$result['scenario_id'],
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

    private function handleTaxInformation(array $input, User $user): array
    {
        $topic = $input['topic'];

        // income_definitions is per-user (not cacheable globally)
        if ($topic === 'income_definitions') {
            return Cache::remember("ai_income_defs_{$user->id}", 120, function () use ($user) {
                $incomeService = app(\App\Services\Tax\IncomeDefinitionsService::class);

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

        $fields = [
            'goal_name' => $input['name'],
            'goal_type' => $input['goal_type'],
            'target_amount' => (float) $input['target_amount'],
            'target_date' => $input['target_date'],
            'priority' => $input['priority'],
        ];

        // Custom goals need the custom_goal_type_name field — use the goal name
        if ($input['goal_type'] === 'custom') {
            $fields['custom_goal_type_name'] = $input['name'];
        }

        if (isset($input['monthly_contribution'])) {
            $fields['monthly_contribution'] = (float) $input['monthly_contribution'];
        }

        return [
            'action' => 'fill_form',
            'entity_type' => 'goal',
            'route' => '/goals',
            'fields' => $fields,
            'message' => "I'll fill in the form for your \"{$input['name']}\" goal now.",
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

        $fields = [
            'event_name' => $input['event_name'],
            'event_type' => $input['event_type'],
            'amount' => (float) $input['estimated_amount'],
            'expected_date' => $input['event_date'],
            'certainty' => $input['certainty'] ?? 'likely',
        ];

        if (isset($input['description'])) {
            $fields['description'] = $input['description'];
        }

        return [
            'action' => 'fill_form',
            'entity_type' => 'life_event',
            'route' => '/goals?tab=events',
            'fields' => $fields,
            'message' => "I'll fill in the form for your \"{$input['event_name']}\" life event now.",
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
        if ($isIsa && ! in_array($formAccountType, ['cash_isa', 'junior_isa'])) {
            $formAccountType = 'cash_isa';
        }

        return [
            'action' => 'fill_form',
            'entity_type' => 'savings_account',
            'route' => '/net-worth/cash',
            'fields' => [
                'institution' => ! empty($input['institution']) ? $input['institution'] : (! empty($input['provider']) ? $input['provider'] : $input['account_name']),
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
            'account_type' => ['nullable', Rule::in([
                'stocks_shares_isa', 'lifetime_isa', 'personal_investment_account',
                'onshore_bond', 'offshore_bond', 'vct', 'eis',
                'private_company', 'crowdfunding', 'saye', 'csop',
                'emi', 'unapproved_options', 'rsu', 'other',
            ])],
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

        // Map AI account_type values to form select values
        $formAccountType = match ($accountType) {
            'stocks_shares_isa', 'lifetime_isa' => 'isa',
            'personal_investment_account' => 'gia',
            default => $accountType, // vct, eis, private_company, crowdfunding, saye, csop, emi, unapproved_options, rsu, other pass through directly
        };

        // Form uses 'provider' as the main name field — map account_name to it
        $provider = $input['provider'] ?? $input['account_name'];

        $fields = [
            'account_type' => $formAccountType,
            'provider' => $provider,
            'current_value' => (float) $input['current_value'],
            'monthly_contribution_amount' => isset($input['monthly_contribution_amount']) ? (float) $input['monthly_contribution_amount'] : 0,
            'platform_fee_percent' => isset($input['platform_fee_percent']) ? (float) $input['platform_fee_percent'] : null,
        ];

        // Bond-specific fields
        if (in_array($accountType, ['onshore_bond', 'offshore_bond'])) {
            $bondFields = ['bond_purchase_date', 'bond_withdrawal_taken'];
            foreach ($bondFields as $field) {
                if (isset($input[$field])) {
                    $fields[$field] = is_numeric($input[$field]) ? (float) $input[$field] : $input[$field];
                }
            }
        }

        // Private company / Crowdfunding fields
        if (in_array($formAccountType, ['private_company', 'crowdfunding', 'vct', 'eis'])) {
            $privateStringFields = [
                'company_legal_name', 'company_registration_number', 'crowdfunding_platform',
                'investment_date', 'instrument_type', 'funding_round', 'share_class', 'tax_relief_type',
            ];
            $privateNumericFields = [
                'investment_amount', 'number_of_shares', 'price_per_share',
            ];
            foreach ($privateStringFields as $field) {
                if (isset($input[$field]) && $input[$field] !== '') {
                    $fields[$field] = (string) $input[$field];
                }
            }
            foreach ($privateNumericFields as $field) {
                if (isset($input[$field]) && $input[$field] !== '') {
                    $fields[$field] = is_numeric($input[$field]) ? (float) $input[$field] : $input[$field];
                }
            }
        }

        // Employee share scheme fields
        if (in_array($formAccountType, ['saye', 'csop', 'emi', 'unapproved_options', 'rsu'])) {
            $schemeFields = [
                'employer_name', 'employer_is_listed', 'grant_date', 'units_granted',
                'exercise_price', 'market_value_at_grant', 'current_share_price',
                'units_vested', 'units_unvested', 'vesting_type', 'full_vest_date',
                'cliff_date', 'cliff_percentage',
            ];
            foreach ($schemeFields as $field) {
                if (isset($input[$field]) && $input[$field] !== '') {
                    if (is_bool($input[$field])) {
                        $fields[$field] = $input[$field];
                    } elseif (is_numeric($input[$field])) {
                        $fields[$field] = (float) $input[$field];
                    } else {
                        $fields[$field] = $input[$field];
                    }
                }
            }

            // SAYE-specific fields
            if ($formAccountType === 'saye') {
                $sayeFields = ['saye_monthly_savings', 'saye_current_savings_balance', 'scheme_start_date', 'scheme_duration_months'];
                foreach ($sayeFields as $field) {
                    if (isset($input[$field]) && $input[$field] !== '') {
                        $fields[$field] = is_numeric($input[$field]) ? (float) $input[$field] : $input[$field];
                    }
                }
            }
        }

        // Inline holdings — pass through for holdable account types (ISA, GIA, bonds, VCT, EIS)
        $holdableTypes = ['isa', 'gia', 'onshore_bond', 'offshore_bond', 'vct', 'eis'];
        if (in_array($formAccountType, $holdableTypes) && ! empty($input['holdings']) && is_array($input['holdings'])) {
            $holdings = [];
            foreach ($input['holdings'] as $holding) {
                $h = [
                    'security_name' => $holding['security_name'] ?? '',
                    'asset_type' => $holding['asset_type'] ?? '',
                    'allocation_percent' => isset($holding['allocation_percent']) ? (float) $holding['allocation_percent'] : 0,
                ];
                if (isset($holding['cost_basis']) && $holding['cost_basis'] !== null) {
                    $h['cost_basis'] = (float) $holding['cost_basis'];
                }
                $holdings[] = $h;
            }
            $fields['holdings'] = $holdings;
        }

        return [
            'action' => 'fill_form',
            'entity_type' => 'investment_account',
            'route' => '/net-worth/investments',
            'fields' => $fields,
            'message' => "I'll fill in the form for your \"{$provider}\" investment account now.",
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
        $account = \App\Models\Investment\InvestmentAccount::where('user_id', $user->id)
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

        $fields = [
            'investment_account_id' => $account->id,
            'security_name' => $input['security_name'],
            'asset_type' => $input['asset_type'],
        ];

        if (isset($input['ticker'])) {
            $fields['ticker'] = $input['ticker'];
        }
        if (isset($input['allocation_percent'])) {
            $fields['allocation_percent'] = (float) $input['allocation_percent'];
        }
        if (isset($input['purchase_price'])) {
            $fields['purchase_price'] = (float) $input['purchase_price'];
        }
        if (isset($input['current_price'])) {
            $fields['current_price'] = (float) $input['current_price'];
        }
        if (isset($input['ocf_percent'])) {
            $fields['ocf_percent'] = (float) $input['ocf_percent'];
        }

        return [
            'action' => 'fill_form',
            'entity_type' => 'investment_holding',
            'route' => '/net-worth/investments',
            'fields' => $fields,
            'message' => "I'll add \"{$input['security_name']}\" to your {$account->provider} account now.",
        ];
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
        $entityType = $category === 'db' ? 'db_pension' : 'dc_pension';

        $schemeName = ! empty($input['scheme_name']) ? $input['scheme_name'] : ($input['provider'] ?? $input['scheme_name']);

        $fields = [
            'scheme_name' => $schemeName,
        ];

        if ($category === 'db') {
            $fields['employer_name'] = $input['scheme_name'] ?? $schemeName;
            $fields['scheme_type'] = $input['scheme_type'] ?? 'final_salary';
            // scheme_status is REQUIRED by DB form validation — default to 'Active'
            $fields['scheme_status'] = $input['scheme_status'] ?? 'Active';
            $fields['annual_income'] = isset($input['accrued_annual_pension']) ? (float) $input['accrued_annual_pension'] : 0;
            $fields['service_years'] = isset($input['pensionable_service_years']) ? (int) $input['pensionable_service_years'] : null;
            $fields['final_salary'] = isset($input['final_salary']) ? (float) $input['final_salary'] : null;
            $fields['accrual_rate'] = isset($input['accrual_rate']) ? (int) $input['accrual_rate'] : null;
            $fields['normal_retirement_age'] = isset($input['normal_retirement_age']) ? (int) $input['normal_retirement_age'] : null;
        } else {
            // Map AI scheme_type to form pension_type select values
            $formPensionType = match ($input['scheme_type'] ?? 'workplace') {
                'workplace', 'occupational' => 'occupational',
                'sipp', 'self_invested' => 'sipp',
                'personal', 'personal_pension' => 'personal',
                'stakeholder' => 'stakeholder',
                default => 'occupational',
            };
            $fields['pension_type'] = $formPensionType;
            $fields['provider'] = ! empty($input['provider']) ? $input['provider'] : $schemeName;
            $fields['current_fund_value'] = isset($input['current_fund_value']) ? (float) $input['current_fund_value'] : 0;
            $fields['employee_contribution_percent'] = isset($input['employee_contribution_percent']) ? (float) $input['employee_contribution_percent'] : null;
            $fields['employer_contribution_percent'] = isset($input['employer_contribution_percent']) ? (float) $input['employer_contribution_percent'] : null;
            $fields['monthly_contribution_amount'] = isset($input['monthly_contribution_amount']) ? (float) $input['monthly_contribution_amount'] : null;
            $fields['annual_salary'] = isset($input['annual_salary']) ? (float) $input['annual_salary'] : null;
            $fields['retirement_age'] = isset($input['retirement_age']) ? (int) $input['retirement_age'] : null;
        }

        // Strip nulls and empty strings
        $fields = array_filter($fields, fn ($v) => $v !== null && $v !== '');

        return [
            'action' => 'fill_form',
            'entity_type' => $entityType,
            'route' => '/net-worth/retirement',
            'fields' => $fields,
            'message' => "I'll fill in the form for your \"{$input['scheme_name']}\" pension now.",
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

        $propertyType = $input['property_type'] ?? 'main_residence';
        $addressLabel = $input['address_line_1'] ?? ucfirst(str_replace('_', ' ', $propertyType));

        // Frontend validateForm() requires: property_type, address_line_1, city, postcode, current_value,
        // ownership_type, ownership_percentage. Set sensible defaults for any the AI didn't provide.
        $address = $input['address_line_1'] ?? $addressLabel;
        $city = $input['city'] ?? 'Unknown';
        $postcode = $input['postcode'] ?? 'N/A';
        $ownershipType = $input['ownership_type'] ?? 'individual';
        $ownershipPct = isset($input['ownership_percentage']) ? (float) $input['ownership_percentage'] : null;
        // Auto-set ownership percentage from type if not explicitly provided
        if ($ownershipPct === null) {
            $ownershipPct = match ($ownershipType) {
                'individual' => 100.0,
                'joint' => 50.0,
                'tenants_in_common' => 50.0,
                'trust' => 0.0,
                default => 100.0,
            };
        }

        // Build property form fields — pass through all provided data
        $fields = [
            'property_type' => $propertyType,
            'current_value' => (float) $input['current_value'],
            // Address — defaults ensure form validation passes
            'address_line_1' => $address,
            'address_line_2' => $input['address_line_2'] ?? null,
            'city' => $city,
            'county' => $input['county'] ?? null,
            'postcode' => $postcode,
            // Purchase
            'purchase_price' => isset($input['purchase_price']) ? (float) $input['purchase_price'] : null,
            'purchase_date' => $input['purchase_date'] ?? null,
            'valuation_date' => $input['valuation_date'] ?? null,
            // Ownership — defaults set above to ensure form validation passes
            'ownership_type' => $ownershipType,
            'ownership_percentage' => $ownershipPct,
            'joint_owner_name' => $input['joint_owner_name'] ?? null,
            // Tenure
            'tenure_type' => $input['tenure_type'] ?? null,
            'lease_remaining_years' => isset($input['lease_remaining_years']) ? (int) $input['lease_remaining_years'] : null,
            'lease_expiry_date' => $input['lease_expiry_date'] ?? null,
            // Monthly costs
            'monthly_council_tax' => isset($input['monthly_council_tax']) ? (float) $input['monthly_council_tax'] : null,
            'monthly_gas' => isset($input['monthly_gas']) ? (float) $input['monthly_gas'] : null,
            'monthly_electricity' => isset($input['monthly_electricity']) ? (float) $input['monthly_electricity'] : null,
            'monthly_water' => isset($input['monthly_water']) ? (float) $input['monthly_water'] : null,
            'monthly_building_insurance' => isset($input['monthly_building_insurance']) ? (float) $input['monthly_building_insurance'] : null,
            'monthly_contents_insurance' => isset($input['monthly_contents_insurance']) ? (float) $input['monthly_contents_insurance'] : null,
            'monthly_service_charge' => isset($input['monthly_service_charge']) ? (float) $input['monthly_service_charge'] : null,
            'monthly_maintenance_reserve' => isset($input['monthly_maintenance_reserve']) ? (float) $input['monthly_maintenance_reserve'] : null,
            'other_monthly_costs' => isset($input['other_monthly_costs']) ? (float) $input['other_monthly_costs'] : null,
            // BTL rental
            'monthly_rental_income' => isset($input['monthly_rental_income']) ? (float) $input['monthly_rental_income'] : null,
            'tenant_name' => $input['tenant_name'] ?? null,
            'managing_agent_name' => $input['managing_agent_name'] ?? null,
        ];

        // Add mortgage fields if provided (xAI: has_mortgage flag, Anthropic: outstanding_mortgage legacy)
        $hasMortgage = ! empty($input['has_mortgage'])
            || (! empty($input['mortgage_outstanding_balance']) && $input['mortgage_outstanding_balance'] > 0)
            || (! empty($input['outstanding_mortgage']) && $input['outstanding_mortgage'] > 0);

        if ($hasMortgage) {
            $fields['has_mortgage'] = true;
            // xAI uses mortgage_outstanding_balance, Anthropic uses outstanding_mortgage
            $balance = $input['mortgage_outstanding_balance'] ?? $input['outstanding_mortgage'] ?? null;
            $fields['mortgage_outstanding_balance'] = isset($balance) ? (float) $balance : null;
            // xAI uses mortgage_interest_rate, Anthropic uses mortgage_rate
            $rate = $input['mortgage_interest_rate'] ?? $input['mortgage_rate'] ?? null;
            $fields['mortgage_interest_rate'] = isset($rate) ? (float) $rate : null;
            // AI param is 'mortgage_lender', form field is 'mortgage_lender_name'
            $fields['mortgage_lender_name'] = $input['mortgage_lender'] ?? null;
            $fields['mortgage_type'] = $input['mortgage_type'] ?? null;
            $fields['mortgage_rate_type'] = $input['mortgage_rate_type'] ?? null;
            $fields['mortgage_monthly_payment'] = isset($input['mortgage_monthly_payment']) ? (float) $input['mortgage_monthly_payment'] : null;
            $fields['mortgage_start_date'] = $input['mortgage_start_date'] ?? null;
            $fields['mortgage_maturity_date'] = $input['mortgage_maturity_date'] ?? null;
        }

        // Strip nulls and empty strings — only send fields with actual values
        $fields = array_filter($fields, fn ($v) => $v !== null && $v !== '');

        return [
            'action' => 'fill_form',
            'entity_type' => 'property',
            'route' => '/net-worth/property',
            'fields' => $fields,
            'message' => "I'll fill in the form for your property at \"{$addressLabel}\" now.",
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
        ]);
        if ($validationError) {
            return $validationError;
        }

        $lenderName = $input['lender_name'] ?? 'Mortgage';

        $fields = [
            'has_mortgage' => true,
            'mortgage_lender_name' => $lenderName,
            'mortgage_outstanding_balance' => (float) $input['outstanding_balance'],
            'mortgage_interest_rate' => isset($input['interest_rate']) ? (float) $input['interest_rate'] : 4.5,
            'mortgage_type' => $input['mortgage_type'] ?? 'repayment',
            'mortgage_rate_type' => $input['rate_type'] ?? 'fixed',
            'mortgage_remaining_term_months' => isset($input['remaining_term_months']) ? (int) $input['remaining_term_months'] : 300,
            'mortgage_monthly_payment' => isset($input['monthly_payment']) ? (float) $input['monthly_payment'] : null,
            'mortgage_start_date' => $input['start_date'] ?? null,
            'mortgage_maturity_date' => $input['maturity_date'] ?? null,
        ];

        // Strip only empty strings — keep nulls so frontend form fill receives all fields
        $fields = array_filter($fields, fn ($v) => $v !== '');

        return [
            'action' => 'fill_form',
            'entity_type' => 'mortgage',
            'route' => '/net-worth/property',
            'fields' => $fields,
            'message' => "I'll fill in the mortgage details for \"{$lenderName}\" now.",
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

        // Map AI policy_type to the form's policyType select values
        $formPolicyType = match ($policyType) {
            'income_protection' => 'incomeProtection',
            'standalone_ci', 'accelerated_ci' => 'criticalIllness',
            default => 'life', // level_term, term, whole_of_life, decreasing_term, family_income_benefit
        };

        // Build fields that map to PolicyFormModal's formData keys
        $fields = [
            'policyType' => $formPolicyType,
            'provider' => $input['provider'] ?? null,
            'premium_amount' => isset($input['premium_amount']) ? (float) $input['premium_amount'] : null,
            'premium_frequency' => $input['premium_frequency'] ?? 'monthly',
        ];

        // Coverage amount: benefit_amount for income protection and family income benefit, sum_assured for others
        if ($policyType === 'income_protection' || $policyType === 'family_income_benefit') {
            $fields['coverage_amount'] = isset($input['benefit_amount']) ? (float) $input['benefit_amount'] : (isset($input['sum_assured']) ? (float) $input['sum_assured'] : 0);
        } else {
            $fields['coverage_amount'] = isset($input['sum_assured']) ? (float) $input['sum_assured'] : 0;
        }

        // Life insurance sub-type — map generic 'term' to 'level_term' (dropdown value)
        if ($formPolicyType === 'life') {
            $fields['policy_type'] = $policyType === 'term' ? 'level_term' : $policyType;
        }

        // Term years (for life and critical illness)
        if (isset($input['policy_term_years'])) {
            $fields['term_years'] = (int) $input['policy_term_years'];
        }

        // In trust (for life insurance)
        if (isset($input['in_trust'])) {
            $fields['in_trust'] = (bool) $input['in_trust'];
        }

        $providerLabel = $input['provider'] ?? str_replace('_', ' ', $policyType);

        return [
            'action' => 'fill_form',
            'entity_type' => 'protection_policy',
            'route' => '/protection',
            'fields' => $fields,
            'message' => "I'll fill in the form for your \"{$providerLabel}\" protection policy now.",
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

        $fields = [
            'asset_name' => $input['asset_name'],
            'asset_type' => $input['asset_type'],
            'current_value' => (float) $input['current_value'],
            'ownership_type' => 'individual',
            'valuation_date' => now()->toDateString(),
            'is_iht_exempt' => $input['is_iht_exempt'] ?? false,
        ];

        return [
            'action' => 'fill_form',
            'entity_type' => 'estate_asset',
            'route' => '/estate',
            'fields' => $fields,
            'message' => "I'll fill in the form for your \"{$input['asset_name']}\" estate asset now.",
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
        ]);
        if ($validationError) {
            return $validationError;
        }

        // Map AI liability_type to form-compatible values
        $formLiabilityType = match ($input['liability_type']) {
            'loan' => 'personal_loan',
            default => $input['liability_type'],
        };

        $fields = [
            'liability_name' => $input['liability_name'],
            'liability_type' => $formLiabilityType,
            'current_balance' => (float) $input['current_balance'],
            'monthly_payment' => isset($input['monthly_payment']) ? (float) $input['monthly_payment'] : null,
            'interest_rate' => isset($input['interest_rate']) ? (float) $input['interest_rate'] : null,
        ];

        return [
            'action' => 'fill_form',
            'entity_type' => 'estate_liability',
            'route' => '/net-worth/liabilities',
            'fields' => $fields,
            'message' => "I'll fill in the form for your \"{$input['liability_name']}\" liability now.",
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

        // Resolve family name references in recipient
        $recipient = $this->resolveFamilyNames($input['recipient'], $user) ?? $input['recipient'];

        $fields = [
            'gift_date' => substr($input['gift_date'], 0, 10),
            'recipient' => $recipient,
            'gift_type' => $input['gift_type'] ?? 'pet',
            'gift_value' => (float) $input['gift_value'],
        ];

        if (isset($input['notes'])) {
            $fields['notes'] = $input['notes'];
        }

        return [
            'action' => 'fill_form',
            'entity_type' => 'estate_gift',
            'route' => '/estate',
            'fields' => $fields,
            'message' => "I'll record your gift of £".number_format((float) $input['gift_value'])." to {$recipient} now.",
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

        $children = \App\Models\FamilyMember::where('user_id', $user->id)
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

        $fields = [
            'relationship' => $dbRelationship,
            'first_name' => $input['first_name'],
            'last_name' => $surname,
            'date_of_birth' => $input['date_of_birth'] ?? null,
            'gender' => $input['gender'] ?? null,
            'is_dependent' => $isDependent,
        ];

        // Append mapping note to existing notes
        if ($mappingNote) {
            $existingNotes = $input['notes'] ?? '';
            $fields['notes'] = trim($mappingNote.($existingNotes ? '. '.$existingNotes : ''));
        }

        // Child-specific fields
        if (in_array($dbRelationship, ['child'])) {
            $educationStatus = $input['education_status'] ?? null;

            // Infer education status from age if not provided
            if (empty($educationStatus) && ! empty($input['date_of_birth'])) {
                try {
                    $age = \Carbon\Carbon::parse($input['date_of_birth'])->age;
                    $educationStatus = match (true) {
                        $age < 5 => 'pre_school',
                        $age < 11 => 'primary',
                        $age < 16 => 'secondary',
                        $age < 18 => 'further_education',
                        $age < 22 => 'higher_education',
                        default => 'graduated',
                    };
                } catch (\Exception $e) {
                    // Ignore parse errors
                }
            }

            if (! empty($educationStatus)) {
                $fields['education_status'] = $educationStatus;
            }
            if (isset($input['receives_child_benefit'])) {
                $fields['receives_child_benefit'] = $input['receives_child_benefit'];
            }
        }

        if (! empty($input['notes'])) {
            $fields['notes'] = $input['notes'];
        }

        return [
            'action' => 'fill_form',
            'entity_type' => 'family_member',
            'route' => '/profile',
            'fields' => $fields,
            'message' => "I'll add {$input['first_name']} as a {$relationship} now.",
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

        $initialValue = isset($input['initial_value']) ? (float) $input['initial_value'] : (isset($input['current_value']) ? (float) $input['current_value'] : 0);
        $currentValue = isset($input['current_value']) ? (float) $input['current_value'] : $initialValue;

        // Resolve family references in beneficiaries and trustees
        $beneficiaries = $this->resolveFamilyNames($input['beneficiaries'] ?? null, $user);
        $trustees = $this->resolveFamilyNames($input['trustees'] ?? null, $user);

        // Default settlor to the user (they created the trust unless stated otherwise)
        $userName = trim($user->first_name.' '.$user->surname);
        $settlor = $userName;

        // Default creation date to today if not provided (trust is being recorded now)
        $creationDate = $input['trust_creation_date'] ?? date('Y-m-d');

        $fields = [
            'trust_name' => $input['trust_name'],
            'trust_type' => $input['trust_type'],
            'initial_value' => $initialValue,
            'current_value' => $currentValue,
            'trust_creation_date' => $creationDate,
            'beneficiaries' => $beneficiaries,
            'trustees' => $trustees,
            'settlor' => $settlor,
            'purpose' => $input['purpose'] ?? null,
        ];

        // Automatically record a CLT gift when a trust is created with an initial value.
        // A trust settlement is a Chargeable Lifetime Transfer for IHT purposes — the 7-year
        // rule and taper relief must be tracked. We save directly to DB since only one form
        // can be filled at a time.
        $cltMessage = '';
        if ($initialValue > 0) {
            try {
                \App\Models\Estate\Gift::create([
                    'user_id' => $user->id,
                    'gift_date' => substr($creationDate, 0, 10),
                    'recipient' => $input['trust_name'],
                    'gift_type' => 'clt',
                    'gift_value' => $initialValue,
                    'notes' => 'Chargeable Lifetime Transfer — settlement into trust. Auto-recorded.',
                ]);
                $cltMessage = " I've also recorded a Chargeable Lifetime Transfer of £".number_format($initialValue).' for Inheritance Tax tracking.';
            } catch (\Exception $e) {
                Log::warning('[CoordinatingAgent] Failed to auto-create CLT gift for trust', [
                    'trust_name' => $input['trust_name'],
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return [
            'action' => 'fill_form',
            'entity_type' => 'trust',
            'route' => '/trusts',
            'fields' => $fields,
            'message' => "I'll fill in the form for your \"{$input['trust_name']}\" trust now.{$cltMessage}",
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

        $fields = [
            'business_name' => $input['business_name'],
            'business_type' => $input['business_type'],
            'current_valuation' => isset($input['estimated_value']) ? (float) $input['estimated_value'] : 0,
        ];

        if (isset($input['industry_sector'])) {
            $fields['industry_sector'] = $input['industry_sector'];
        }
        if (isset($input['ownership_percentage'])) {
            $fields['ownership_percentage'] = (float) $input['ownership_percentage'];
        }
        if (isset($input['annual_revenue'])) {
            $fields['annual_revenue'] = (float) $input['annual_revenue'];
        }
        if (isset($input['annual_profit'])) {
            $fields['annual_profit'] = (float) $input['annual_profit'];
        }
        if (isset($input['annual_dividend_income'])) {
            $fields['annual_dividend_income'] = (float) $input['annual_dividend_income'];
        }
        if (isset($input['employee_count'])) {
            $fields['employee_count'] = (int) $input['employee_count'];
        }

        return [
            'action' => 'fill_form',
            'entity_type' => 'business_interest',
            'route' => '/net-worth/business',
            'fields' => $fields,
            'message' => "I'll fill in the form for your \"{$input['business_name']}\" business interest now.",
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

        // Map AI category values to form chattel_type values
        $chattelType = match ($input['category'] ?? 'other') {
            'jewellery' => 'jewelry',
            'art' => 'art',
            'antiques' => 'antique',
            'collectibles' => 'collectible',
            'vehicles' => 'vehicle',
            default => 'other',
        };

        $fields = [
            'chattel_type' => $chattelType,
            'name' => $input['description'],
            'current_value' => (float) $input['estimated_value'],
            'purchase_price' => isset($input['purchase_value']) ? (float) $input['purchase_value'] : null,
        ];

        return [
            'action' => 'fill_form',
            'entity_type' => 'chattel',
            'route' => '/net-worth/chattels',
            'fields' => $fields,
            'message' => "I'll fill in the form for your \"{$input['description']}\" now.",
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
        $total = 0;
        foreach ($categoryFields as $field) {
            if (isset($input[$field]) && is_numeric($input[$field]) && $input[$field] > 0) {
                $updateData[$field] = (float) $input[$field];
                $total += (float) $input[$field];
            }
        }

        if (empty($updateData)) {
            return ['error' => true, 'error_type' => 'validation_failed', 'message' => 'No expenditure amounts provided.'];
        }

        // Save directly to user model (same as manual form save)
        $updateData['monthly_expenditure'] = $total;
        $updateData['annual_expenditure'] = $total * 12;
        $updateData['use_simple_entry'] = false;
        $user->update($updateData);

        $formatted = collect($updateData)
            ->except(['monthly_expenditure', 'annual_expenditure', 'use_simple_entry'])
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

        // Map AI tool field names to actual model field names
        $fieldAliases = match ($entityType) {
            'business_interest' => ['estimated_value' => 'current_valuation'],
            'chattel' => ['estimated_value' => 'current_value', 'category' => 'chattel_type'],
            'dc_pension' => ['current_value' => 'current_fund_value'],
            'mortgage' => ['current_balance' => 'outstanding_balance'],
            'life_insurance' => ['life_policy_type' => 'policy_type', 'monthly_premium' => 'premium_amount'],
            default => [],
        };
        foreach ($fieldAliases as $aiName => $dbName) {
            if (array_key_exists($aiName, $fields) && ! array_key_exists($dbName, $fields)) {
                $fields[$dbName] = $fields[$aiName];
                unset($fields[$aiName]);
            }
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
            'message' => "I'll update the ".str_replace('_', ' ', $entityType).' for you now.',
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

        return ['deleted' => true, 'entity_type' => $entityType, 'entity_id' => $entityId, 'message' => ucfirst(str_replace('_', ' ', $entityType))." \"{$name}\" deleted."];
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

        $section = $input['section'];

        // Redirect expenditure to set_expenditure tool
        if ($section === 'expenditure') {
            return $this->handleSetExpenditure($input['fields'] ?? $input, $user, $isPreview);
        }
        $fields = $input['fields'] ?? [];

        if (empty($fields)) {
            return ['error' => true, 'error_type' => 'validation_failed', 'message' => 'No fields provided to update.'];
        }

        $allowedFields = match ($section) {
            // NI number excluded — sensitive PII should not be AI-writable
            'personal' => ['first_name', 'surname', 'date_of_birth', 'gender', 'marital_status', 'phone', 'address_line_1', 'address_line_2', 'city', 'county', 'postcode'],
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

        return ['updated' => true, 'section' => $section, 'fields_updated' => array_keys($safeFields), 'message' => 'Profile ('.str_replace('_', ' ', $section).') updated successfully.'];
    }
}
