<?php

declare(strict_types=1);

namespace App\Services\Investment;

use App\Constants\InvestmentDefaults;
use App\Constants\TaxDefaults;
use App\Models\Investment\InvestmentAccount;
use App\Models\User;
use App\Services\Investment\Rebalancing\DriftAnalyzer;
use App\Services\Investment\Tax\TaxOptimizationAnalyzer;
use App\Services\Risk\RiskPreferenceService;
use App\Services\TaxConfigService;
use App\Traits\FormatsCurrency;

/**
 * Portfolio Strategy Service
 * Aggregates recommendations from tax, fee, and rebalancing services
 * into a unified, prioritized strategy view
 */
class PortfolioStrategyService
{
    use FormatsCurrency;

    // Priority categories (lower = higher priority)
    private const PRIORITY_TAX = 1;

    private const PRIORITY_WRAPPER = 2;

    private const PRIORITY_FEES = 3;

    private const PRIORITY_REBALANCING = 4;

    // Bond wrapper thresholds
    private const BOND_WRAPPER_MIN_BALANCE = 50000;

    private const OFFSHORE_BOND_MIN_BALANCE = 100000;

    public function __construct(
        private readonly TaxOptimizationAnalyzer $taxAnalyzer,
        private readonly FeeAnalyzer $feeAnalyzer,
        private readonly DriftAnalyzer $driftAnalyzer,
        private readonly TaxConfigService $taxConfig,
        private readonly RiskPreferenceService $riskPreferenceService
    ) {}

    /**
     * Get comprehensive portfolio strategy
     */
    public function getPortfolioStrategy(int $userId): array
    {
        $user = User::find($userId);

        if (! $user) {
            return [
                'success' => false,
                'message' => 'User not found',
            ];
        }

        $accounts = InvestmentAccount::where('user_id', $userId)
            ->with('holdings')
            ->get();

        if ($accounts->isEmpty()) {
            return [
                'success' => false,
                'message' => 'No investment accounts found',
            ];
        }

        // Gather recommendations from all sources
        $allRecommendations = [];

        // 1. Tax recommendations (Priority 1)
        $taxAnalysis = $this->taxAnalyzer->analyzeCompleteTaxPosition($userId);
        if ($taxAnalysis['success'] ?? false) {
            $allRecommendations = array_merge(
                $allRecommendations,
                $this->formatTaxRecommendations($taxAnalysis)
            );
        }

        // 2. Bond wrapper recommendations (Priority 2)
        $bondOpportunities = $this->analyzeBondWrapperOpportunities($user, $accounts);
        if (! empty($bondOpportunities)) {
            $allRecommendations = array_merge($allRecommendations, $bondOpportunities);
        }

        // 3. Fee recommendations (Priority 3)
        $feeAnalysis = $this->feeAnalyzer->analyzePortfolioFees($userId);
        if ($feeAnalysis['success'] ?? false) {
            $allRecommendations = array_merge(
                $allRecommendations,
                $this->formatFeeRecommendations($feeAnalysis, $accounts)
            );
        }

        // 4. Rebalancing recommendations (Priority 4)
        $rebalancingRecs = $this->getRebalancingRecommendations($accounts);
        if (! empty($rebalancingRecs)) {
            $allRecommendations = array_merge($allRecommendations, $rebalancingRecs);
        }

        // Sort by priority
        usort($allRecommendations, function ($a, $b) {
            if ($a['priority'] === $b['priority']) {
                return ($b['potential_saving'] ?? 0) <=> ($a['potential_saving'] ?? 0);
            }

            return $a['priority'] <=> $b['priority'];
        });

        // Calculate summary stats
        $summary = $this->calculateSummary($allRecommendations, $taxAnalysis);

        // Group by account for per-account view
        $byAccount = $this->groupByAccount($allRecommendations, $accounts);

        return [
            'success' => true,
            'summary' => $summary,
            'recommendations' => $allRecommendations,
            'by_account' => $byAccount,
            'tax_analysis' => $taxAnalysis['success'] ?? false ? [
                'efficiency_score' => $taxAnalysis['efficiency_score'] ?? null,
                'current_position' => $taxAnalysis['current_position'] ?? null,
            ] : null,
        ];
    }

    /**
     * Get strategy for specific account
     */
    public function getAccountStrategy(int $userId, int $accountId): array
    {
        $account = InvestmentAccount::where('user_id', $userId)
            ->where('id', $accountId)
            ->with('holdings')
            ->first();

        if (! $account) {
            return [
                'success' => false,
                'message' => 'Account not found',
            ];
        }

        $portfolioStrategy = $this->getPortfolioStrategy($userId);

        if (! $portfolioStrategy['success']) {
            return $portfolioStrategy;
        }

        // Filter recommendations for this account
        $accountRecommendations = array_filter(
            $portfolioStrategy['recommendations'],
            fn ($rec) => ($rec['account_id'] ?? null) === $accountId || ($rec['account_id'] ?? null) === null
        );

        return [
            'success' => true,
            'account' => [
                'id' => $account->id,
                'name' => $account->account_name,
                'provider' => $account->provider,
                'type' => $account->account_type,
                'value' => $account->holdings->sum('current_value'),
            ],
            'recommendations' => array_values($accountRecommendations),
        ];
    }

    /**
     * Analyze bond wrapper opportunities for GIA accounts
     */
    private function analyzeBondWrapperOpportunities(User $user, $accounts): array
    {
        $recommendations = [];

        // Calculate user's tax band based on total income
        $taxBand = $this->calculateTaxBand($user);

        // Only recommend for higher/additional rate taxpayers
        if (! in_array($taxBand, ['higher', 'additional'])) {
            return [];
        }

        // Find GIA accounts
        $giaAccounts = $accounts->filter(function ($account) {
            return in_array($account->account_type, ['gia', 'general']);
        });

        foreach ($giaAccounts as $account) {
            $totalValue = $account->holdings->sum('current_value');

            if ($totalValue < self::BOND_WRAPPER_MIN_BALANCE) {
                continue;
            }

            // Calculate tax deferral benefit
            $riskLevel = $this->riskPreferenceService->getMainRiskLevel($user->id) ?? 'medium';
            $estimatedReturn = $this->riskPreferenceService->getReturnParameters($riskLevel)['expected_return_typical'] / 100;
            $annualGrowth = $totalValue * $estimatedReturn;

            // Tax savings: difference between income tax and effective bond rate
            $taxDeferralBenefit = $taxBand === 'additional'
                ? $annualGrowth * 0.25  // 45% -> ~20% = 25% saving
                : $annualGrowth * 0.20; // 40% -> ~20% = 20% saving

            $recommendedWrapper = $totalValue >= self::OFFSHORE_BOND_MIN_BALANCE && $taxBand === 'additional'
                ? 'offshore_bond'
                : 'onshore_bond';

            $accountDisplayName = $account->account_name ?: ($account->provider.' '.strtoupper($account->account_type));

            $recommendations[] = [
                'id' => 'bond_wrapper_'.$account->id,
                'category' => 'wrapper',
                'type' => 'bond_wrapper',
                'priority' => self::PRIORITY_WRAPPER,
                'title' => 'Consider Bond Wrapper for '.$accountDisplayName,
                'description' => sprintf(
                    'Your %s balance of %s qualifies for an %s wrapper. As a %s rate taxpayer, you could defer tax on investment growth.',
                    $accountDisplayName,
                    $this->formatCurrency($totalValue),
                    $recommendedWrapper === 'offshore_bond' ? 'Offshore Bond' : 'Onshore Bond',
                    ucfirst($taxBand)
                ),
                'potential_saving' => round($taxDeferralBenefit, 2),
                'timeframe' => 'annual',
                'urgency' => 'low',
                'action_type' => 'info',
                'action_data' => [
                    'account_id' => $account->id,
                    'gia_balance' => $totalValue,
                    'recommended_wrapper' => $recommendedWrapper,
                    'tax_deferral_benefit' => round($taxDeferralBenefit, 2),
                    'tax_band' => $taxBand,
                    'top_slicing_eligible' => true,
                    'modal' => 'BondWrapperInfoModal',
                ],
                'account_id' => $account->id,
            ];
        }

        return $recommendations;
    }

    /**
     * Format tax recommendations from analyzer
     */
    private function formatTaxRecommendations(array $taxAnalysis): array
    {
        $recommendations = [];

        foreach ($taxAnalysis['opportunities'] ?? [] as $opportunity) {
            $actionType = match ($opportunity['type']) {
                'isa_underutilization' => 'isa_transfer',
                'bed_and_isa' => 'bed_and_isa',
                default => 'info',
            };

            $modal = match ($opportunity['type']) {
                'isa_underutilization' => 'ISATransferModal',
                'bed_and_isa' => 'BedAndISAWizardModal',
                default => null,
            };

            // Calculate days remaining in tax year
            $daysRemaining = $this->getDaysRemainingInTaxYear();

            $recommendations[] = [
                'id' => $opportunity['type'].'_'.time(),
                'category' => 'tax',
                'type' => $opportunity['type'],
                'priority' => self::PRIORITY_TAX,
                'title' => $opportunity['title'],
                'description' => $opportunity['description'],
                'potential_saving' => $opportunity['potential_saving'] ?? 0,
                'timeframe' => 'annual',
                'urgency' => $opportunity['priority'] ?? 'medium',
                'days_remaining' => $daysRemaining,
                'action_type' => $actionType,
                'action_data' => array_merge(
                    $opportunity['details'] ?? [],
                    ['modal' => $modal]
                ),
                'account_id' => null, // Portfolio-wide
            ];
        }

        return $recommendations;
    }

    /**
     * Format fee recommendations
     */
    private function formatFeeRecommendations(array $feeAnalysis, $accounts): array
    {
        $recommendations = [];

        // Check for high-fee level assessment
        $assessment = $feeAnalysis['assessment'] ?? [];
        if (in_array($assessment['level'] ?? '', ['high', 'very_high'])) {
            $recommendations[] = [
                'id' => 'high_fees_portfolio',
                'category' => 'fees',
                'type' => 'high_total_fees',
                'priority' => self::PRIORITY_FEES,
                'title' => 'Portfolio Fees Above Average',
                'description' => sprintf(
                    'Your total portfolio fees of %.2f%% are %s. Industry average is %.2f%%.',
                    $assessment['your_fee'] ?? 0,
                    $assessment['level'] === 'very_high' ? 'significantly above average' : 'above average',
                    $assessment['benchmark_average'] ?? 0
                ),
                'potential_saving' => $feeAnalysis['potential_savings']['savings_vs_good'] ?? 0,
                'timeframe' => 'annual',
                'urgency' => $assessment['level'] === 'very_high' ? 'high' : 'medium',
                'action_type' => 'navigate',
                'action_data' => [
                    'navigate_to' => 'fees',
                    'current_fee_percent' => $assessment['your_fee'] ?? 0,
                    'target_fee_percent' => $assessment['benchmark_average'] ?? 0,
                ],
                'account_id' => null,
            ];
        }

        // Identify high-fee holdings across all accounts
        foreach ($accounts as $account) {
            $holdings = $account->holdings;
            $highFeeHoldings = $this->feeAnalyzer->identifyHighFeeHoldings($holdings);

            foreach ($highFeeHoldings['holdings'] ?? [] as $holding) {
                // Calculate potential saving by switching to low-cost alternative
                $currentCost = $holding['annual_cost'];
                $feeBenchmarks = $this->taxConfig->get('investment.fee_benchmarks', []);
                $lowCostOCF = $feeBenchmarks['low_cost_ocf'] ?? 0.0015;
                $lowCostCost = $holding['current_value'] * $lowCostOCF;
                $potentialSaving = max(0, $currentCost - $lowCostCost);

                if ($potentialSaving < 50) {
                    continue; // Skip trivial savings
                }

                $recommendations[] = [
                    'id' => 'high_fee_holding_'.($holding['holding_id'] ?? uniqid()),
                    'category' => 'fees',
                    'type' => 'high_fee_holding',
                    'priority' => self::PRIORITY_FEES,
                    'title' => 'High-Fee Fund: '.($holding['security_name'] ?? 'Unknown'),
                    'description' => sprintf(
                        'This fund has an OCF of %.2f%%, costing %s/year. Consider a low-cost index alternative.',
                        $holding['ocf_percent'],
                        $this->formatCurrency($holding['annual_cost'])
                    ),
                    'potential_saving' => round($potentialSaving, 2),
                    'timeframe' => 'annual',
                    'urgency' => 'low',
                    'action_type' => 'info',
                    'action_data' => [
                        'holding_id' => $holding['holding_id'] ?? null,
                        'current_ocf' => $holding['ocf_percent'],
                        'current_value' => $holding['current_value'],
                        'current_annual_cost' => $holding['annual_cost'],
                    ],
                    'account_id' => $account->id,
                ];
            }
        }

        return $recommendations;
    }

    /**
     * Get rebalancing recommendations
     */
    private function getRebalancingRecommendations($accounts): array
    {
        $recommendations = [];

        foreach ($accounts as $account) {
            if ($account->holdings->isEmpty()) {
                continue;
            }

            // Get target allocation from account or use default balanced allocation
            $targetAllocation = $account->target_allocation ?? InvestmentDefaults::TARGET_ALLOCATIONS[3];

            $driftAnalysis = $this->driftAnalyzer->analyzeDrift(
                $account->holdings,
                $targetAllocation
            );

            if (! ($driftAnalysis['success'] ?? false)) {
                continue;
            }

            $urgency = $driftAnalysis['urgency'] ?? [];
            $driftScore = $driftAnalysis['drift_score'] ?? 0;

            // Only recommend if action is needed
            if (! ($urgency['action_required'] ?? false)) {
                continue;
            }

            $threshold = $account->rebalance_threshold ?? 10;
            $accountDisplayName = $account->account_name ?: ($account->provider.' '.strtoupper($account->account_type));

            $recommendations[] = [
                'id' => 'rebalancing_'.$account->id,
                'category' => 'rebalancing',
                'type' => 'drift_threshold_exceeded',
                'priority' => self::PRIORITY_REBALANCING,
                'title' => 'Rebalancing Recommended: '.$accountDisplayName,
                'description' => sprintf(
                    'Portfolio drift of %.1f%% exceeds your %.0f%% threshold. %s',
                    $driftScore,
                    $threshold,
                    $driftAnalysis['recommendation'] ?? ''
                ),
                'potential_saving' => null, // Rebalancing is about risk, not savings
                'timeframe' => null,
                'urgency' => $urgency['level'] ?? 'medium',
                'action_type' => 'navigate',
                'action_data' => [
                    'navigate_to' => 'rebalancing',
                    'account_id' => $account->id,
                    'drift_score' => $driftScore,
                    'threshold' => $threshold,
                    'adjustments' => $driftAnalysis['adjustments_needed'] ?? [],
                ],
                'account_id' => $account->id,
            ];
        }

        return $recommendations;
    }

    /**
     * Calculate summary statistics
     */
    private function calculateSummary(array $recommendations, array $taxAnalysis): array
    {
        $totalPotentialSavings = 0;
        $highPriorityCount = 0;

        foreach ($recommendations as $rec) {
            $totalPotentialSavings += $rec['potential_saving'] ?? 0;
            if (in_array($rec['urgency'] ?? '', ['high']) || ($rec['priority'] ?? 5) <= 2) {
                $highPriorityCount++;
            }
        }

        return [
            'total_potential_savings' => round($totalPotentialSavings, 2),
            'recommendation_count' => count($recommendations),
            'high_priority_count' => $highPriorityCount,
            'tax_efficiency_score' => $taxAnalysis['efficiency_score']['score'] ?? null,
            'tax_efficiency_grade' => $taxAnalysis['efficiency_score']['grade'] ?? null,
        ];
    }

    /**
     * Group recommendations by account
     */
    private function groupByAccount(array $recommendations, $accounts): array
    {
        $byAccount = [];

        // Initialize with all accounts
        foreach ($accounts as $account) {
            $accountDisplayName = $account->account_name ?: ($account->provider.' '.strtoupper($account->account_type));
            $byAccount[$account->id] = [
                'account_id' => $account->id,
                'account_name' => $accountDisplayName,
                'provider' => $account->provider,
                'account_type' => $account->account_type,
                'recommendations' => [],
            ];
        }

        // Add portfolio-wide bucket
        $byAccount['portfolio'] = [
            'account_id' => null,
            'account_name' => 'Portfolio-Wide',
            'provider' => null,
            'account_type' => null,
            'recommendations' => [],
        ];

        // Distribute recommendations
        foreach ($recommendations as $rec) {
            $accountId = $rec['account_id'] ?? 'portfolio';
            if (isset($byAccount[$accountId])) {
                $byAccount[$accountId]['recommendations'][] = $rec;
            } else {
                $byAccount['portfolio']['recommendations'][] = $rec;
            }
        }

        // Remove empty accounts (except portfolio)
        $byAccount = array_filter($byAccount, function ($data) {
            return ! empty($data['recommendations']) || $data['account_id'] === null;
        });

        return array_values($byAccount);
    }

    /**
     * Get days remaining in current tax year
     */
    private function getDaysRemainingInTaxYear(): int
    {
        $now = new \DateTime;
        $currentYear = (int) $now->format('Y');
        $currentMonth = (int) $now->format('m');
        $currentDay = (int) $now->format('d');

        // Tax year ends April 5
        if ($currentMonth < 4 || ($currentMonth === 4 && $currentDay <= 5)) {
            $endDate = new \DateTime("{$currentYear}-04-05");
        } else {
            $endDate = new \DateTime(($currentYear + 1).'-04-05');
        }

        return $now->diff($endDate)->days;
    }

    /**
     * Calculate user's tax band based on total income
     */
    private function calculateTaxBand(User $user): string
    {
        // Sum all income sources
        $totalIncome = ($user->annual_employment_income ?? 0)
            + ($user->annual_self_employment_income ?? 0)
            + ($user->annual_rental_income ?? 0)
            + ($user->annual_dividend_income ?? 0)
            + ($user->annual_interest_income ?? 0)
            + ($user->annual_other_income ?? 0)
            + ($user->annual_trust_income ?? 0);

        // Get tax bands from config
        $incomeTax = $this->taxConfig->getIncomeTax();
        $higherRateThreshold = (float) ($incomeTax['bands'][0]['upper_limit'] ?? TaxDefaults::HIGHER_RATE_THRESHOLD);
        $additionalRateThreshold = (float) ($incomeTax['bands'][1]['upper_limit'] ?? TaxDefaults::ADDITIONAL_RATE_THRESHOLD);

        if ($totalIncome >= $additionalRateThreshold) {
            return 'additional';
        }

        if ($totalIncome >= $higherRateThreshold) {
            return 'higher';
        }

        return 'basic';
    }
}
