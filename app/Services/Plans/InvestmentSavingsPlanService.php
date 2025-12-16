<?php

declare(strict_types=1);

namespace App\Services\Plans;

use App\Agents\InvestmentAgent;
use App\Agents\SavingsAgent;
use App\Models\Investment\Holding;
use App\Models\Investment\InvestmentAccount;
use App\Models\SavingsAccount;
use App\Models\User;

class InvestmentSavingsPlanService
{
    public function __construct(
        private InvestmentAgent $investmentAgent,
        private SavingsAgent $savingsAgent
    ) {}

    /**
     * Generate comprehensive Investment & Savings Plan
     */
    public function generatePlan(int $userId): array
    {
        // Get user
        $user = User::findOrFail($userId);

        // Get Investment analysis
        $investmentAnalysis = $this->investmentAgent->analyze($userId);

        // Get Savings analysis
        $savingsAnalysis = $this->savingsAgent->analyze($userId);

        // Get raw data
        $investmentAccounts = InvestmentAccount::where('user_id', $userId)->with('holdings')->get();

        // Get all holdings from investment accounts (using polymorphic relationship)
        $accountIds = $investmentAccounts->pluck('id');
        $holdings = Holding::where('holdable_type', InvestmentAccount::class)
            ->whereIn('holdable_id', $accountIds)
            ->get();

        $savingsAccounts = SavingsAccount::where('user_id', $userId)->get();

        // Build comprehensive plan
        return [
            'plan_id' => 'inv_sav_'.time(),
            'generated_at' => now()->toIso8601String(),
            'user_name' => $user->name,

            // Executive Summary
            'executive_summary' => $this->buildExecutiveSummary(
                $investmentAnalysis,
                $savingsAnalysis,
                $investmentAccounts,
                $savingsAccounts
            ),

            // Investment Section
            'investment' => [
                'summary' => $this->buildInvestmentSummary($investmentAnalysis, $investmentAccounts, $holdings),
                'holdings' => $this->buildHoldingsOverview($holdings, $investmentAccounts),
                'performance' => $this->buildPerformanceOverview($investmentAnalysis),
                'strategy' => $this->buildInvestmentStrategy($investmentAnalysis),
            ],

            // Savings Section
            'savings' => [
                'summary' => $this->buildSavingsSummary($savingsAnalysis, $savingsAccounts),
                'emergency_fund' => $this->buildEmergencyFundAnalysis($savingsAnalysis),
                'accounts' => $this->buildSavingsAccountsOverview($savingsAccounts, $savingsAnalysis),
                'strategy' => $this->buildSavingsStrategy($savingsAnalysis),
            ],

            // Combined Strategy & Action Plan
            'action_plan' => $this->buildActionPlan($investmentAnalysis, $savingsAnalysis),
        ];
    }

    /**
     * Build executive summary combining both modules
     */
    private function buildExecutiveSummary(
        array $investmentAnalysis,
        array $savingsAnalysis,
        $investmentAccounts,
        $savingsAccounts
    ): array {
        $totalInvestmentValue = $investmentAccounts->sum('current_value');
        $totalSavingsValue = $savingsAccounts->sum('current_balance');
        $totalWealth = $totalInvestmentValue + $totalSavingsValue;

        return [
            'total_wealth' => round($totalWealth, 2),
            'total_investment_value' => round($totalInvestmentValue, 2),
            'total_savings_value' => round($totalSavingsValue, 2),
            'investment_accounts_count' => $investmentAccounts->count(),
            'savings_accounts_count' => $savingsAccounts->count(),
            'total_holdings' => $investmentAnalysis['portfolio_summary']['holdings_count'] ?? 0,
            'emergency_fund_runway' => $savingsAnalysis['emergency_fund']['runway_months'] ?? 0,
            'emergency_fund_status' => $savingsAnalysis['emergency_fund']['category'] ?? 'Unknown',
            'portfolio_health_score' => $investmentAnalysis['portfolio_health_score'] ?? null,
            'diversification_score' => $investmentAnalysis['diversification_score'] ?? null,
        ];
    }

    /**
     * Build investment summary
     */
    private function buildInvestmentSummary(array $analysis, $accounts, $holdings): array
    {
        // Calculate portfolio health score from available metrics
        $healthScore = $this->calculatePortfolioHealthScore($analysis);

        // Extract risk metrics with proper structure
        $riskMetrics = $analysis['risk_metrics'] ?? [];

        return [
            'total_value' => $accounts->sum('current_value'),
            'accounts_count' => $accounts->count(),
            'holdings_count' => $holdings->count(),
            'portfolio_health_score' => $healthScore,
            'diversification_score' => $analysis['diversification_score'] ?? null,
            'allocation' => $analysis['asset_allocation'] ?? [],
            'tax_efficiency' => $analysis['tax_efficiency']['efficiency_score'] ?? null,
            'fee_analysis' => $analysis['fee_analysis'] ?? null,
            'risk_metrics' => [
                'volatility' => $riskMetrics['volatility'] ?? null,
                'sharpe_ratio' => $riskMetrics['sharpe_ratio'] ?? null,
                'alpha' => $riskMetrics['alpha'] ?? null,
                'beta' => $riskMetrics['beta'] ?? null,
                'max_drawdown' => $riskMetrics['max_drawdown'] ?? null,
                'var_95' => $riskMetrics['var_95'] ?? null,
            ],
        ];
    }

    /**
     * Build holdings overview
     */
    private function buildHoldingsOverview($holdings, $accounts): array
    {
        $accountsWithHoldings = $accounts->map(function ($account) use ($holdings) {
            $accountHoldings = $holdings->where('holdable_type', InvestmentAccount::class)
                ->where('holdable_id', $account->id);

            return [
                'account_id' => $account->id,
                'account_name' => $account->account_name,
                'account_type' => $account->account_type,
                'provider' => $account->provider,
                'total_value' => $account->current_value,
                'holdings_count' => $accountHoldings->count(),
                'holdings' => $accountHoldings->map(function ($holding) {
                    return [
                        'ticker' => $holding->ticker,
                        'name' => $holding->holding_name,
                        'asset_class' => $holding->asset_class,
                        'quantity' => $holding->quantity,
                        'current_value' => $holding->current_value,
                        'percentage' => null, // Calculate if needed
                    ];
                })->values()->toArray(),
            ];
        })->values()->toArray();

        return [
            'accounts' => $accountsWithHoldings,
            'total_holdings' => $holdings->count(),
            'asset_classes' => $holdings->groupBy('asset_class')->map->count()->toArray(),
        ];
    }

    /**
     * Build performance overview
     */
    private function buildPerformanceOverview(array $analysis): array
    {
        $returns = $analysis['returns'] ?? [];
        $riskMetrics = $analysis['risk_metrics'] ?? [];

        return [
            'total_return' => $returns['total_return'] ?? null,
            'annualized_return' => $returns['annualized_return'] ?? null,
            'volatility' => $riskMetrics['volatility'] ?? null,
            'sharpe_ratio' => $riskMetrics['sharpe_ratio'] ?? null,
            'best_performing' => $returns['best_performer'] ?? null,
            'worst_performing' => $returns['worst_performer'] ?? null,
            'risk_metrics' => $riskMetrics,
            'note' => 'Performance calculated from current holdings and historical data',
        ];
    }

    /**
     * Build investment strategy
     */
    private function buildInvestmentStrategy(array $analysis): array
    {
        $recommendations = [];

        // Portfolio rebalancing if allocation is off target
        if (isset($analysis['allocation_deviation']) && $analysis['allocation_deviation']) {
            $maxDeviation = collect($analysis['allocation_deviation'])->max('deviation_percent') ?? 0;
            if ($maxDeviation > 5) {
                $recommendations[] = [
                    'category' => 'Portfolio Rebalancing',
                    'priority' => $maxDeviation > 10 ? 'High' : 'Medium',
                    'recommendations' => [
                        'Rebalance portfolio to target allocation',
                        'Maximum deviation: '.round($maxDeviation, 1).'%',
                        'Consider tax implications when rebalancing',
                    ],
                ];
            }
        }

        // Fee reduction recommendations
        $feeAnalysis = $analysis['fee_analysis'] ?? [];
        if (isset($feeAnalysis['total_annual_fees'])) {
            $lowCost = $analysis['low_cost_comparison'] ?? [];
            $potentialSaving = ($lowCost['potential_annual_saving'] ?? 0);

            if ($potentialSaving > 100) {
                $recommendations[] = [
                    'category' => 'Fee Reduction',
                    'priority' => 'High',
                    'potential_saving' => round($potentialSaving, 2),
                    'recommendations' => [
                        'Switch to lower-cost alternatives',
                        'Review platform and fund fees',
                        'Consider passive index trackers',
                    ],
                ];
            }
        }

        // Tax optimization recommendations
        $taxEfficiency = $analysis['tax_efficiency'] ?? [];
        if (isset($taxEfficiency['harvesting_opportunities'])) {
            $opportunities = collect($taxEfficiency['harvesting_opportunities']);
            if ($opportunities->isNotEmpty()) {
                $totalLoss = $opportunities->sum('unrealized_loss');
                $recommendations[] = [
                    'category' => 'Tax Loss Harvesting',
                    'priority' => 'Medium',
                    'potential_saving' => round(abs($totalLoss) * 0.20, 2), // 20% CGT
                    'recommendations' => [
                        'Harvest tax losses to offset gains',
                        count($opportunities).' opportunities identified',
                        'Potential CGT savings available',
                    ],
                ];
            }
        }

        // Diversification recommendations
        $diversificationScore = $analysis['diversification_score'] ?? 100;
        if ($diversificationScore < 60) {
            $recommendations[] = [
                'category' => 'Diversification',
                'priority' => $diversificationScore < 40 ? 'High' : 'Medium',
                'recommendations' => [
                    'Portfolio concentration risk identified',
                    'Consider adding more holdings',
                    'Spread investments across asset classes',
                ],
            ];
        }

        return [
            'recommendations' => $recommendations,
            'diversification_score' => $diversificationScore,
            'allocation' => $analysis['asset_allocation'] ?? [],
            'fee_analysis' => $feeAnalysis,
        ];
    }

    /**
     * Build savings summary
     */
    private function buildSavingsSummary(array $analysis, $accounts): array
    {
        return [
            'total_savings' => $analysis['summary']['total_savings'] ?? 0,
            'accounts_count' => $analysis['summary']['total_accounts'] ?? 0,
            'monthly_expenditure' => $analysis['summary']['monthly_expenditure'] ?? 0,
            'emergency_fund_runway' => $analysis['emergency_fund']['runway_months'] ?? 0,
            'emergency_fund_category' => $analysis['emergency_fund']['category'] ?? 'Unknown',
            'isa_allowance_used' => $analysis['isa_allowance']['total_used'] ?? 0,
            'isa_allowance_remaining' => $analysis['isa_allowance']['remaining'] ?? 0,
        ];
    }

    /**
     * Build emergency fund analysis
     */
    private function buildEmergencyFundAnalysis(array $analysis): array
    {
        $emergencyFund = $analysis['emergency_fund'] ?? [];
        $monthlyExpenditure = $analysis['summary']['monthly_expenditure'] ?? 0;
        $totalSavings = $analysis['summary']['total_savings'] ?? 0;

        $runway = $emergencyFund['runway_months'] ?? 0;
        $targetMonths = 6;
        $shortfall = max(0, $targetMonths - $runway);
        $shortfallAmount = $shortfall * $monthlyExpenditure;

        return [
            'current_runway_months' => $runway,
            'target_runway_months' => $targetMonths,
            'adequacy_category' => $emergencyFund['category'] ?? 'Unknown',
            'adequacy_score' => $emergencyFund['adequacy']['adequacy_score'] ?? 0,
            'shortfall_months' => $shortfall,
            'shortfall_amount' => round($shortfallAmount, 2),
            'current_amount' => $totalSavings,
            'target_amount' => $targetMonths * $monthlyExpenditure,
            'status_color' => $this->getEmergencyFundStatusColor($runway),
            'recommendation' => $emergencyFund['recommendation'] ?? 'Build emergency fund to 6 months of expenses',
        ];
    }

    /**
     * Build savings accounts overview
     */
    private function buildSavingsAccountsOverview($accounts, array $analysis): array
    {
        $rateComparisons = collect($analysis['rate_comparisons'] ?? []);

        $accountsData = $accounts->map(function ($account) use ($rateComparisons) {
            $comparison = $rateComparisons->firstWhere('account_id', $account->id);

            return [
                'id' => $account->id,
                'institution' => $account->institution,
                'account_type' => $account->account_type,
                'balance' => $account->current_balance,
                'interest_rate' => $account->interest_rate ?? 0,
                'is_instant_access' => $account->is_instant_access ?? true,
                'rate_comparison' => $comparison['comparison'] ?? null,
                'potential_gain' => $comparison['potential_gain'] ?? 0,
            ];
        })->values()->toArray();

        return [
            'accounts' => $accountsData,
            'total_balance' => $accounts->sum('current_balance'),
            'liquidity_summary' => $analysis['liquidity']['summary'] ?? [],
        ];
    }

    /**
     * Build savings strategy
     */
    private function buildSavingsStrategy(array $analysis): array
    {
        $emergencyFund = $analysis['emergency_fund'] ?? [];
        $monthlyExpenditure = $analysis['summary']['monthly_expenditure'] ?? 0;
        $runway = $emergencyFund['runway_months'] ?? 0;

        $recommendations = [];

        // Emergency fund recommendations
        if ($runway < 6) {
            $shortfall = 6 - $runway;
            $shortfallAmount = $shortfall * $monthlyExpenditure;
            $monthlyTopUp = $monthlyExpenditure / 2; // Suggest building over 12 months

            $recommendations[] = [
                'category' => 'Emergency Fund',
                'priority' => $runway < 3 ? 'Critical' : 'High',
                'current_status' => $emergencyFund['category'] ?? 'Unknown',
                'action_required' => 'Build emergency fund to 6 months of expenses',
                'shortfall_amount' => round($shortfallAmount, 2),
                'suggested_monthly_contribution' => round($monthlyTopUp, 2),
                'timeline' => '12 months',
            ];
        } else {
            $recommendations[] = [
                'category' => 'Emergency Fund',
                'priority' => 'Low',
                'current_status' => 'Excellent',
                'action_required' => 'Maintain emergency fund and consider investing surplus',
            ];
        }

        // Rate optimization recommendations
        $rateComparisons = collect($analysis['rate_comparisons'] ?? []);
        $lowRateAccounts = $rateComparisons->filter(function ($comp) {
            return ($comp['comparison']['rating'] ?? '') === 'Poor';
        });

        if ($lowRateAccounts->isNotEmpty()) {
            $totalPotentialGain = $lowRateAccounts->sum('potential_gain');
            $recommendations[] = [
                'category' => 'Interest Rate Optimization',
                'priority' => 'Medium',
                'action_required' => 'Move savings to higher-rate accounts',
                'accounts_affected' => $lowRateAccounts->count(),
                'potential_annual_gain' => round($totalPotentialGain, 2),
            ];
        }

        // ISA allowance recommendations
        $isaAllowance = $analysis['isa_allowance'] ?? [];
        $remaining = $isaAllowance['remaining'] ?? 0;
        if ($remaining > 0 && $runway >= 6) {
            $recommendations[] = [
                'category' => 'ISA Allowance',
                'priority' => 'Medium',
                'action_required' => 'Utilize remaining ISA allowance for tax-free growth',
                'remaining_allowance' => $remaining,
                'tax_year' => $isaAllowance['tax_year'] ?? '',
            ];
        }

        return [
            'recommendations' => $recommendations,
            'priority_actions' => $this->prioritizeSavingsActions($recommendations),
        ];
    }

    /**
     * Build combined action plan
     */
    private function buildActionPlan(array $investmentAnalysis, array $savingsAnalysis): array
    {
        $actions = [];
        $priority = 1;

        // Critical: Emergency fund if below 3 months
        $runway = $savingsAnalysis['emergency_fund']['runway_months'] ?? 0;
        if ($runway < 3) {
            $actions[] = [
                'priority' => $priority++,
                'category' => 'Emergency Fund (Critical)',
                'action' => 'Build emergency fund to minimum 3 months of expenses',
                'urgency' => 'Critical',
                'timeline' => 'Immediate',
            ];
        }

        // High: Portfolio rebalancing if significantly off target
        if (isset($investmentAnalysis['rebalancing_needed']) && $investmentAnalysis['rebalancing_needed']) {
            $actions[] = [
                'priority' => $priority++,
                'category' => 'Portfolio Rebalancing',
                'action' => 'Rebalance portfolio to target allocation',
                'urgency' => 'High',
                'timeline' => '1-2 weeks',
            ];
        }

        // High: Fee reduction opportunities
        if (isset($investmentAnalysis['fee_analysis']['potential_annual_saving'])) {
            $saving = $investmentAnalysis['fee_analysis']['potential_annual_saving'];
            if ($saving > 100) {
                $actions[] = [
                    'priority' => $priority++,
                    'category' => 'Fee Reduction',
                    'action' => "Reduce investment fees (potential saving: £{$saving}/year)",
                    'urgency' => 'High',
                    'timeline' => '1 month',
                ];
            }
        }

        // Medium: Build emergency fund to 6 months
        if ($runway >= 3 && $runway < 6) {
            $actions[] = [
                'priority' => $priority++,
                'category' => 'Emergency Fund',
                'action' => 'Build emergency fund to 6 months of expenses',
                'urgency' => 'Medium',
                'timeline' => '6-12 months',
            ];
        }

        // Medium: Tax optimization
        if (isset($investmentAnalysis['tax_optimization']['potential_saving'])) {
            $saving = $investmentAnalysis['tax_optimization']['potential_saving'];
            if ($saving > 0) {
                $actions[] = [
                    'priority' => $priority++,
                    'category' => 'Tax Optimization',
                    'action' => 'Implement tax-efficient investment strategy',
                    'urgency' => 'Medium',
                    'timeline' => '2-3 months',
                ];
            }
        }

        // Low: Interest rate optimization
        $rateComparisons = collect($savingsAnalysis['rate_comparisons'] ?? []);
        $totalPotentialGain = $rateComparisons->sum('potential_gain');
        if ($totalPotentialGain > 50) {
            $actions[] = [
                'priority' => $priority++,
                'category' => 'Interest Rate Optimization',
                'action' => "Move savings to higher-rate accounts (potential gain: £{$totalPotentialGain}/year)",
                'urgency' => 'Low',
                'timeline' => '1-2 months',
            ];
        }

        return [
            'actions' => $actions,
            'total_actions' => count($actions),
            'critical_actions' => collect($actions)->where('urgency', 'Critical')->count(),
            'high_priority_actions' => collect($actions)->where('urgency', 'High')->count(),
        ];
    }

    /**
     * Get emergency fund status color
     */
    private function getEmergencyFundStatusColor(float $runway): string
    {
        if ($runway >= 6) {
            return 'green';
        }
        if ($runway >= 3) {
            return 'yellow';
        }
        if ($runway >= 1) {
            return 'orange';
        }

        return 'red';
    }

    /**
     * Prioritize savings actions
     */
    private function prioritizeSavingsActions(array $recommendations): array
    {
        return collect($recommendations)
            ->sortByDesc(function ($rec) {
                $priorities = ['Critical' => 4, 'High' => 3, 'Medium' => 2, 'Low' => 1];

                return $priorities[$rec['priority']] ?? 0;
            })
            ->take(3)
            ->values()
            ->toArray();
    }

    /**
     * Calculate overall portfolio health score
     * Combines diversification, tax efficiency, fee efficiency, and risk metrics
     */
    private function calculatePortfolioHealthScore(array $analysis): float
    {
        $scores = [];

        // Diversification score (0-100)
        if (isset($analysis['diversification_score'])) {
            $scores[] = $analysis['diversification_score'];
        }

        // Tax efficiency score (0-100)
        if (isset($analysis['tax_efficiency']['efficiency_score'])) {
            $scores[] = $analysis['tax_efficiency']['efficiency_score'];
        }

        // Fee efficiency score (inverse of fee drag - estimate)
        if (isset($analysis['fee_analysis']['total_annual_fees'])) {
            $totalValue = $analysis['portfolio_summary']['total_value'] ?? 0;
            $feePercent = $totalValue > 0 ? ($analysis['fee_analysis']['total_annual_fees'] / $totalValue) * 100 : 0;
            // Score: 100 for 0% fees, decreases as fees increase
            $feeScore = max(0, 100 - ($feePercent * 50)); // Penalize heavily for high fees
            $scores[] = $feeScore;
        }

        // Risk-adjusted score (based on Sharpe ratio if available)
        if (isset($analysis['risk_metrics']['sharpe_ratio'])) {
            $sharpe = $analysis['risk_metrics']['sharpe_ratio'];
            // Convert Sharpe ratio to 0-100 scale (1.0 = 70, 2.0 = 85, 3.0+ = 95)
            $riskScore = min(100, 50 + ($sharpe * 20));
            $scores[] = $riskScore;
        }

        // Calculate weighted average
        if (empty($scores)) {
            return 50; // Default neutral score
        }

        return round(array_sum($scores) / count($scores), 1);
    }
}
