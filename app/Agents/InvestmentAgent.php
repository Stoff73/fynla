<?php

declare(strict_types=1);

namespace App\Agents;

use App\Constants\TaxDefaults;
use App\Models\Investment\InvestmentAccount;
use App\Models\Investment\InvestmentGoal;
use App\Models\Investment\RiskProfile;
use App\Models\SavingsAccount;
use App\Models\User;
use App\Services\Investment\DiversificationAnalyzer;
use App\Services\Investment\FeeAnalyzer;
use App\Services\Investment\InvestmentActionDefinitionService;
use App\Services\Investment\InvestmentProjectionService;
use App\Services\Investment\MonteCarloSimulator;
use App\Services\Investment\PortfolioAnalyzer;
use App\Services\Investment\Recommendation\DataReadinessService;
use App\Services\Investment\SimpleAssetAllocationOptimizer;
use App\Services\Investment\TaxEfficiencyCalculator;
use App\Services\TaxConfigService;
use Illuminate\Support\Facades\Cache;

class InvestmentAgent extends BaseAgent
{
    public function __construct(
        private readonly PortfolioAnalyzer $portfolioAnalyzer,
        private readonly DiversificationAnalyzer $diversificationAnalyzer,
        private readonly MonteCarloSimulator $monteCarloSimulator,
        private readonly SimpleAssetAllocationOptimizer $allocationOptimizer,
        private readonly FeeAnalyzer $feeAnalyzer,
        private readonly TaxEfficiencyCalculator $taxCalculator,
        private readonly TaxConfigService $taxConfig,
        private readonly InvestmentActionDefinitionService $actionDefinitionService,
        private readonly DataReadinessService $readinessService
    ) {}

    /**
     * Comprehensive investment portfolio analysis
     */
    public function analyze(int $userId): array
    {
        // Data readiness gate — return early if blocking checks fail
        $gateUser = User::find($userId);
        if ($gateUser) {
            $readiness = $this->readinessService->assess($gateUser);
            if (! $readiness['can_proceed']) {
                return [
                    'can_proceed' => false,
                    'readiness_checks' => $readiness,
                    'portfolio_summary' => null,
                    'returns' => null,
                    'asset_allocation' => null,
                    'diversification_score' => null,
                    'risk_metrics' => null,
                    'fee_analysis' => null,
                    'low_cost_comparison' => null,
                    'tax_efficiency' => null,
                    'tax_wrappers' => null,
                    'allocation_deviation' => null,
                    'goals' => null,
                ];
            }
        }

        return $this->remember("investment_analysis_{$userId}", function () use ($userId) {
            // Get all user data (eager load holdings to avoid lazy loading)
            $accounts = InvestmentAccount::where('user_id', $userId)->with('holdings')->get();
            $holdings = $accounts->flatMap->holdings;
            $riskProfile = RiskProfile::where('user_id', $userId)->first();
            $goals = InvestmentGoal::where('user_id', $userId)->get();

            if ($accounts->isEmpty()) {
                return [
                    'message' => 'No investment accounts found',
                    'accounts_count' => 0,
                ];
            }

            // Portfolio analysis
            $totalValue = $this->portfolioAnalyzer->calculateTotalValue($accounts);
            $returns = $this->portfolioAnalyzer->calculateReturns($holdings);
            $allocation = $this->portfolioAnalyzer->calculateAssetAllocation($holdings);
            $diversificationScore = $this->diversificationAnalyzer->calculateScoreFromHoldings($holdings);
            $riskMetrics = $this->portfolioAnalyzer->calculatePortfolioRisk($holdings, $riskProfile);

            // Fee analysis
            $feeAnalysis = $this->feeAnalyzer->calculateTotalFees($accounts, $holdings);
            $lowCostComparison = $this->feeAnalyzer->compareToLowCostAlternatives($holdings);
            $highFeeHoldings = $this->feeAnalyzer->identifyHighFeeHoldings($holdings);
            $feeAnalysis['high_fee_holdings'] = $highFeeHoldings['holdings'];

            // Tax efficiency
            $unrealizedGains = $this->taxCalculator->calculateUnrealizedGains($holdings);
            $taxEfficiencyScore = $this->taxCalculator->calculateTaxShelterRatio($accounts, $holdings);
            $harvestingOpportunities = $this->taxCalculator->identifyHarvestingOpportunities($userId);

            // Asset allocation vs target
            $allocationDeviation = null;
            if ($riskProfile) {
                $targetAllocation = $this->allocationOptimizer->getTargetAllocation($riskProfile);
                $allocationDeviation = $this->allocationOptimizer->calculateDeviation($allocation, $targetAllocation);
            }

            // Tax wrapper summary — include both investment and savings ISAs
            $isaAccounts = $accounts->where('account_type', 'isa');
            $isaAllowance = $this->taxConfig->getISAAllowances()['annual_allowance'] ?? TaxDefaults::ISA_ALLOWANCE;
            $investmentIsaUsed = $isaAccounts->sum('isa_subscription_current_year');

            // Include savings ISA subscriptions for accurate allowance remaining
            $taxYear = $this->taxConfig->getTaxYear();
            $savingsIsaUsed = SavingsAccount::where('user_id', $userId)
                ->whereIn('account_type', ['isa', 'cash_isa'])
                ->where('isa_subscription_year', $taxYear)
                ->sum('isa_subscription_amount');

            $isaUsedThisYear = $investmentIsaUsed + $savingsIsaUsed;
            $isaRemaining = max(0, $isaAllowance - $isaUsedThisYear);

            $taxWrappers = [
                'has_isa' => $isaAccounts->isNotEmpty(),
                'isa_allowance' => $isaAllowance,
                'isa_used_this_year' => round($isaUsedThisYear, 2),
                'isa_remaining' => round($isaRemaining, 2),
                'has_gia' => $accounts->where('account_type', 'gia')->isNotEmpty(),
                'gia_value' => round($accounts->where('account_type', 'gia')->sum('current_value'), 2),
                'has_onshore_bond' => $accounts->where('account_type', 'onshore_bond')->isNotEmpty(),
                'has_offshore_bond' => $accounts->where('account_type', 'offshore_bond')->isNotEmpty(),
            ];

            return [
                'portfolio_summary' => [
                    'total_value' => round($totalValue, 2),
                    'accounts_count' => $accounts->count(),
                    'holdings_count' => $holdings->count(),
                ],
                'returns' => $returns,
                'asset_allocation' => $allocation,
                'diversification_score' => $diversificationScore,
                'risk_metrics' => $riskMetrics,
                'fee_analysis' => $feeAnalysis,
                'low_cost_comparison' => $lowCostComparison,
                'tax_efficiency' => [
                    'unrealized_gains' => $unrealizedGains,
                    'efficiency_score' => $taxEfficiencyScore,
                    'harvesting_opportunities' => $harvestingOpportunities,
                ],
                'tax_wrappers' => $taxWrappers,
                'allocation_deviation' => $allocationDeviation,
                'goals' => $goals->map(function ($goal) use ($totalValue) {
                    $progress = $totalValue > 0 ? ($totalValue / $goal->target_amount) * 100 : 0;

                    return [
                        'goal_name' => $goal->goal_name,
                        'target_amount' => $goal->target_amount,
                        'current_value' => $totalValue,
                        'progress_percent' => round($progress, 2),
                        'target_date' => $goal->target_date->format('Y-m-d'),
                    ];
                }),
            ];
        }, null, ['investment', 'user_'.$userId]);
    }

    /**
     * Generate personalized recommendations.
     *
     * Delegates to InvestmentActionDefinitionService for DB-driven evaluation.
     * This agent-level path provides a simplified subset of recommendations:
     * - Investment-only triggers fire (risk, diversification, rebalancing, tax wrappers)
     * - Savings/surplus triggers do NOT fire (empty savings data)
     * - Fee triggers do NOT fire (no account fee analyses at agent level)
     *
     * The full evaluation (all triggers including savings, surplus waterfall, and fees)
     * happens in InvestmentPlanService::getRecommendations() which calls the service directly.
     */
    public function generateRecommendations(array $analysis): array
    {
        $result = $this->actionDefinitionService->evaluateAgentActions(
            $analysis,
            [],                 // No savings analysis (handled by InvestmentPlanService)
            collect(),          // No investment accounts collection at agent level
            collect(),          // No savings accounts collection at agent level
            0,                  // No userId needed for investment-only triggers
            []                  // No fee analyses at agent level
        );

        return [
            'recommendation_count' => $result['total_count'],
            'recommendations' => $result['recommendations'],
        ];
    }

    /**
     * Build what-if scenarios
     */
    public function buildScenarios(int $userId, array $parameters): array
    {
        $scenarios = [];

        // Get current analysis data
        $analysis = $this->analyze($userId);

        // Extract inputs from parameters
        $currentValue = $analysis['portfolio_summary']['total_value'] ?? 0;
        $currentContribution = $parameters['monthly_contribution'] ?? 0;

        // Scenario 1: Conservative growth
        $scenarios[] = [
            'name' => 'Conservative Growth (4% return)',
            'description' => 'Low-risk scenario with 4% annual return',
            'parameters' => [
                'expected_return' => 0.04,
                'volatility' => 0.08,
                'monthly_contribution' => $currentContribution,
            ],
            'requires_monte_carlo' => true,
        ];

        // Scenario 2: Balanced growth
        $scenarios[] = [
            'name' => 'Balanced Growth (7% return)',
            'description' => 'Moderate-risk scenario with 7% annual return',
            'parameters' => [
                'expected_return' => 0.07,
                'volatility' => 0.12,
                'monthly_contribution' => $currentContribution,
            ],
            'requires_monte_carlo' => true,
        ];

        // Scenario 3: Aggressive growth
        $scenarios[] = [
            'name' => 'Aggressive Growth (10% return)',
            'description' => 'High-risk scenario with 10% annual return',
            'parameters' => [
                'expected_return' => 0.10,
                'volatility' => 0.18,
                'monthly_contribution' => $currentContribution,
            ],
            'requires_monte_carlo' => true,
        ];

        // Scenario 4: Increased contributions
        if ($currentContribution > 0) {
            $increasedContribution = $currentContribution * 1.5;
            $scenarios[] = [
                'name' => 'Increased Contributions',
                'description' => "Increase monthly contribution to £{$increasedContribution}",
                'parameters' => [
                    'expected_return' => 0.07,
                    'volatility' => 0.12,
                    'monthly_contribution' => $increasedContribution,
                ],
                'requires_monte_carlo' => true,
            ];
        }

        return [
            'scenario_count' => count($scenarios),
            'scenarios' => $scenarios,
            'note' => 'Run Monte Carlo simulations to see detailed projections for each scenario',
        ];
    }

    /**
     * Clear cache for a user.
     *
     * Uses the standardised cache invalidation from BaseAgent.
     *
     * @param  int  $userId  User ID
     */
    public function clearCache(int $userId): void
    {
        $this->invalidateUserCache($userId, [
            "investment_analysis_{$userId}",
        ]);
    }

    /**
     * Get portfolio projections with Monte Carlo simulation.
     */
    public function getPortfolioProjections(
        int $userId,
        array $projectionPeriods = [5, 10, 20, 30],
        ?array $contributionOverrides = null,
        ?int $selectedPeriod = null
    ): array {
        $user = User::findOrFail($userId);

        return app(InvestmentProjectionService::class)->getPortfolioProjections(
            $user,
            $projectionPeriods,
            $contributionOverrides,
            $selectedPeriod
        );
    }
}
