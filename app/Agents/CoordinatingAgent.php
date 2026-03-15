<?php

declare(strict_types=1);

namespace App\Agents;

use App\Services\Coordination\CashFlowCoordinator;
use App\Services\Coordination\ConflictResolver;
use App\Services\Coordination\CrossModuleStrategyService;
use App\Services\Coordination\HolisticPlanner;
use App\Services\Coordination\PriorityRanker;
use App\Services\TaxConfigService;

/**
 * CoordinatingAgent
 *
 * Orchestrates cross-module analysis by coordinating all module agents.
 * Resolves conflicts, ranks recommendations, and generates holistic financial plans.
 */
class CoordinatingAgent extends BaseAgent
{
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
        private readonly TaxConfigService $taxConfig
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
}
