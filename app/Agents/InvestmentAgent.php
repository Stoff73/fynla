<?php

declare(strict_types=1);

namespace App\Agents;

use App\Models\Investment\InvestmentAccount;
use App\Models\Investment\InvestmentGoal;
use App\Models\Investment\RiskProfile;
use App\Models\User;
use App\Services\Investment\AssetAllocationOptimizer;
use App\Services\Investment\FeeAnalyzer;
use App\Services\Investment\InvestmentProjectionService;
use App\Services\Investment\MonteCarloSimulator;
use App\Services\Investment\PortfolioAnalyzer;
use App\Services\Investment\TaxEfficiencyCalculator;
use App\Services\TaxConfigService;
use Illuminate\Support\Facades\Cache;

class InvestmentAgent extends BaseAgent
{
    public function __construct(
        private PortfolioAnalyzer $portfolioAnalyzer,
        private MonteCarloSimulator $monteCarloSimulator,
        private AssetAllocationOptimizer $allocationOptimizer,
        private FeeAnalyzer $feeAnalyzer,
        private TaxEfficiencyCalculator $taxCalculator,
        private TaxConfigService $taxConfig
    ) {}

    /**
     * Comprehensive investment portfolio analysis
     */
    public function analyze(int $userId): array
    {
        return $this->remember("investment_analysis_{$userId}", function () use ($userId) {
            // Get all user data
            $accounts = InvestmentAccount::where('user_id', $userId)->get();
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
            $diversificationScore = $this->portfolioAnalyzer->calculateDiversificationScore($allocation);
            $riskMetrics = $this->portfolioAnalyzer->calculatePortfolioRisk($holdings, $riskProfile);

            // Fee analysis
            $feeAnalysis = $this->feeAnalyzer->calculateTotalFees($accounts, $holdings);
            $lowCostComparison = $this->feeAnalyzer->compareToLowCostAlternatives($holdings);
            $highFeeHoldings = $this->feeAnalyzer->identifyHighFeeHoldings($holdings);
            $feeAnalysis['high_fee_holdings'] = $highFeeHoldings['holdings'];

            // Tax efficiency
            $unrealizedGains = $this->taxCalculator->calculateUnrealizedGains($holdings);
            $taxEfficiencyScore = $this->taxCalculator->calculateTaxEfficiencyScore($accounts, $holdings);
            $harvestingOpportunities = $this->taxCalculator->identifyHarvestingOpportunities($holdings);

            // Asset allocation vs target
            $allocationDeviation = null;
            if ($riskProfile) {
                $targetAllocation = $this->allocationOptimizer->getTargetAllocation($riskProfile);
                $allocationDeviation = $this->allocationOptimizer->calculateDeviation($allocation, $targetAllocation);
            }

            // Tax wrapper summary
            $isaAccounts = $accounts->where('account_type', 'isa');
            $isaAllowance = $this->taxConfig->getISAAllowances()['annual_allowance'] ?? 20000;
            $isaUsedThisYear = $isaAccounts->sum('isa_subscription_current_year');
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
     * Generate personalized recommendations
     */
    public function generateRecommendations(array $analysis): array
    {
        $recommendations = [];
        $priority = 1;

        $holdingsCount = $analysis['portfolio_summary']['holdings_count'] ?? 0;
        $hasRiskProfile = isset($analysis['allocation_deviation']);

        // No risk profile recommendation - always show if not set
        if (! $hasRiskProfile) {
            $recommendations[] = [
                'category' => 'Risk Profile',
                'priority' => $priority++,
                'title' => 'Complete Your Risk Profile',
                'description' => 'Set up your risk profile to get personalised investment recommendations and target allocations.',
                'action' => 'Complete the risk questionnaire to determine your investment strategy',
            ];
        }

        // No holdings recommendation - show if accounts exist but no holdings
        if ($holdingsCount === 0 && ($analysis['portfolio_summary']['accounts_count'] ?? 0) > 0) {
            $recommendations[] = [
                'category' => 'Portfolio Setup',
                'priority' => $priority++,
                'title' => 'Add Your Holdings',
                'description' => 'Add your fund holdings to get detailed fee analysis, diversification scores, and tax efficiency recommendations.',
                'action' => 'Click on your investment account and add your holdings',
            ];
        }

        // Diversification recommendations (threshold: 70 - room for improvement if not well diversified)
        // Only show when there are actual holdings (not for accounts with no holdings)
        if ($holdingsCount > 0 && $analysis['diversification_score'] < 70) {
            $recommendations[] = [
                'category' => 'Diversification',
                'priority' => $priority++,
                'title' => 'Improve Portfolio Diversification',
                'description' => 'Your diversification score is '.$analysis['diversification_score'].'/100. Consider spreading investments across more asset types.',
                'action' => 'Review asset allocation and consider adding different asset classes',
            ];
        }

        // Fee recommendations (threshold: £50 annual savings - meaningful savings)
        if (isset($analysis['low_cost_comparison']['annual_saving']) && $analysis['low_cost_comparison']['annual_saving'] > 50) {
            $saving = round($analysis['low_cost_comparison']['annual_saving']);
            $recommendations[] = [
                'category' => 'Fees',
                'priority' => $priority++,
                'title' => 'Reduce Investment Fees',
                'description' => "You could save £{$saving} per year by switching to lower-cost funds",
                'action' => 'Review holdings and consider lower-cost index funds',
            ];
        }

        // High-fee holdings recommendation (any holding with OCF > 0.5%)
        if (isset($analysis['fee_analysis']['high_fee_holdings']) && count($analysis['fee_analysis']['high_fee_holdings']) > 0) {
            $count = count($analysis['fee_analysis']['high_fee_holdings']);
            $recommendations[] = [
                'category' => 'High Fees',
                'priority' => $priority++,
                'title' => 'Review High-Fee Holdings',
                'description' => "You have {$count} holding(s) with fees above 0.5%. Consider lower-cost alternatives.",
                'action' => 'Compare fund fees and switch to low-cost index alternatives where appropriate',
            ];
        }

        // High platform fee recommendation (platform fees above 0.8% of portfolio)
        $platformFeeEntry = collect($analysis['fee_analysis']['fee_breakdown'] ?? [])
            ->firstWhere('type', 'Platform Fees');
        if ($platformFeeEntry && ($platformFeeEntry['percent_of_portfolio'] ?? 0) > 0.8) {
            $recommendations[] = [
                'category' => 'Platform Fees',
                'priority' => $priority++,
                'title' => 'Review Platform Fees',
                'description' => 'Your platform fees are '.round($platformFeeEntry['percent_of_portfolio'], 2).'% of your portfolio. Consider switching to a lower-cost platform.',
                'action' => 'Compare platform fees across providers',
            ];
        }

        // Allocation recommendations (only when holdings exist - can't rebalance with no holdings)
        if ($holdingsCount > 0 && isset($analysis['allocation_deviation']['needs_rebalancing']) && $analysis['allocation_deviation']['needs_rebalancing']) {
            $recommendations[] = [
                'category' => 'Asset Allocation',
                'priority' => $priority++,
                'title' => 'Rebalance Portfolio',
                'description' => 'Your current allocation deviates significantly from your risk profile',
                'action' => 'Consider rebalancing to match your target allocation',
            ];
        }

        // Tax efficiency recommendations - practical hierarchy
        $taxWrappers = $analysis['tax_wrappers'] ?? [];
        $hasGia = $taxWrappers['has_gia'] ?? false;
        $hasIsa = $taxWrappers['has_isa'] ?? false;
        $isaRemaining = $taxWrappers['isa_remaining'] ?? 0;
        $giaValue = $taxWrappers['gia_value'] ?? 0;

        if ($hasGia && ! $hasIsa) {
            // Priority 1: Has GIA but no ISA - most tax inefficient
            $recommendations[] = [
                'category' => 'Tax Efficiency',
                'priority' => $priority++,
                'title' => 'Open a Stocks & Shares ISA',
                'description' => 'Your investments are in a General Investment Account where gains and dividends are taxable. An ISA shelters up to '.number_format($taxWrappers['isa_allowance'] ?? 20000).'/year from income tax and capital gains tax.',
                'action' => 'Open an ISA and transfer or contribute up to the annual allowance',
            ];
        } elseif ($hasIsa && $isaRemaining > 0 && $hasGia) {
            // Priority 2: Has ISA with allowance remaining and GIA holdings to shelter
            $recommendations[] = [
                'category' => 'Tax Efficiency',
                'priority' => $priority++,
                'title' => 'Use Your ISA Allowance',
                'description' => 'You have '.number_format($isaRemaining).' ISA allowance remaining this tax year. Consider moving GIA holdings ('.number_format($giaValue).') into your ISA before 5 April.',
                'action' => 'Transfer or contribute GIA funds into your ISA to shelter from tax',
            ];
        } elseif ($hasGia && $giaValue > 50000 && ! ($taxWrappers['has_onshore_bond'] ?? false) && ! ($taxWrappers['has_offshore_bond'] ?? false)) {
            // Priority 3: ISA used but significant GIA value - suggest bonds
            $recommendations[] = [
                'category' => 'Tax Efficiency',
                'priority' => $priority++,
                'title' => 'Consider Tax-Efficient Bonds',
                'description' => 'With '.number_format($giaValue).' in your GIA, consider onshore bonds (tax-deferred growth, 5% annual tax-free withdrawal) or offshore bonds (gross roll-up, no annual UK tax on gains) for additional tax efficiency.',
                'action' => 'Speak to your adviser about investment bonds for tax-deferred growth',
            ];
        }

        // Tax loss harvesting
        if (isset($analysis['tax_efficiency']['harvesting_opportunities']['opportunities_count']) &&
            $analysis['tax_efficiency']['harvesting_opportunities']['opportunities_count'] > 0) {
            $count = $analysis['tax_efficiency']['harvesting_opportunities']['opportunities_count'];
            $saving = $analysis['tax_efficiency']['harvesting_opportunities']['potential_tax_saving'];

            $recommendations[] = [
                'category' => 'Tax Planning',
                'priority' => $priority++,
                'title' => 'Tax Loss Harvesting Opportunity',
                'description' => "{$count} holdings have unrealized losses. Potential tax saving: £{$saving}",
                'action' => 'Consider selling losing positions to offset capital gains',
            ];
        }

        return [
            'recommendation_count' => count($recommendations),
            'recommendations' => $recommendations,
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
     * Clear cache for a user
     */
    public function clearCache(int $userId): void
    {
        Cache::forget("investment_analysis_{$userId}");
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
