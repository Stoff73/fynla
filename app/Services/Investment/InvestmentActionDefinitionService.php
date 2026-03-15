<?php

declare(strict_types=1);

namespace App\Services\Investment;

use App\Constants\TaxDefaults;
use App\Models\Goal;
use App\Models\InvestmentActionDefinition;
use App\Services\Plans\PlanConfigService;
use App\Services\TaxConfigService;
use App\Traits\FormatsCurrency;

/**
 * Evaluates investment action definitions against user data
 * to produce configurable, database-driven recommendations.
 *
 * Mirrors RetirementActionDefinitionService — each trigger condition
 * maps to one private evaluator method that checks the condition
 * and returns zero or more recommendations.
 */
class InvestmentActionDefinitionService
{
    use FormatsCurrency;

    public function __construct(
        private readonly FeeAnalyzer $feeAnalyzer,
        private readonly TaxConfigService $taxConfig,
        private readonly PlanConfigService $planConfig
    ) {}

    /**
     * Evaluate all enabled agent-sourced action definitions against analysis data.
     *
     * @return array{recommendations: array, total_count: int, high_priority_count: int}
     */
    public function evaluateAgentActions(
        array $investmentAnalysis,
        array $savingsAnalysis,
        $investmentAccounts,
        $savingsAccounts,
        int $userId,
        array $accountFeeAnalyses = []
    ): array {
        $definitions = InvestmentActionDefinition::getEnabledBySource('agent');
        $recommendations = [];
        $priority = 1;

        foreach ($definitions as $definition) {
            $results = $this->evaluateAgentTrigger(
                $definition,
                $investmentAnalysis,
                $savingsAnalysis,
                $investmentAccounts,
                $savingsAccounts,
                $userId,
                $accountFeeAnalyses,
                $priority
            );

            foreach ($results as $rec) {
                $recommendations[] = $rec;
                $priority++;
            }
        }

        $recommendations = $this->resolveConflicts($recommendations);

        return [
            'recommendations' => $recommendations,
            'total_count' => count($recommendations),
            'high_priority_count' => count(array_filter($recommendations, fn ($r) => ($r['priority'] ?? 999) <= 2)),
        ];
    }

    /**
     * Evaluate all enabled goal-sourced action definitions against linked goals.
     *
     * @return array Recommendations in standard format consumed by structureActions()
     */
    public function evaluateGoalActions(array $linkedGoals): array
    {
        $definitions = InvestmentActionDefinition::getEnabledBySource('goal');
        $recommendations = [];

        foreach ($linkedGoals as $goal) {
            $progress = $goal['progress_percentage'] ?? 0;
            if ($progress >= 100) {
                continue;
            }

            foreach ($definitions as $definition) {
                $rec = $this->evaluateGoalTrigger($definition, $goal);
                if ($rec !== null) {
                    $recommendations[] = $rec;
                }
            }
        }

        return $recommendations;
    }

    /**
     * Look up the what_if_impact_type for a given action category.
     */
    public function getWhatIfImpactType(string $category): string
    {
        $definition = InvestmentActionDefinition::where('category', $category)->first();

        return $definition?->what_if_impact_type ?? 'default';
    }

    // =========================================================================
    // Agent trigger dispatch
    // =========================================================================

    /**
     * Dispatch a single agent-sourced trigger to the appropriate evaluator.
     *
     * @return array List of recommendations (may be empty or contain multiple for per-account triggers)
     */
    private function evaluateAgentTrigger(
        InvestmentActionDefinition $definition,
        array $investmentAnalysis,
        array $savingsAnalysis,
        $investmentAccounts,
        $savingsAccounts,
        int $userId,
        array $accountFeeAnalyses,
        int $priority
    ): array {
        $config = $definition->trigger_config;
        $condition = $config['condition'] ?? '';

        return match ($condition) {
            // Investment triggers
            'risk_profile_not_set' => $this->evaluateRiskProfileMissing($definition, $investmentAnalysis, $priority),
            'accounts_exist_but_no_holdings' => $this->evaluateNoHoldings($definition, $investmentAnalysis, $priority),
            'diversification_score_below' => $this->evaluateLowDiversification($definition, $investmentAnalysis, $config, $priority),
            'total_fee_percent_above' => $this->evaluateHighTotalFees($definition, $accountFeeAnalyses, $config, $priority),
            'weighted_ocf_above' => $this->evaluateHighFundFees($definition, $accountFeeAnalyses, $config, $priority),
            'platform_fee_percent_above' => $this->evaluateHighPlatformFees($definition, $accountFeeAnalyses, $config, $priority),
            'allocation_needs_rebalancing' => $this->evaluateRebalancePortfolio($definition, $investmentAnalysis, $priority),
            'has_harvesting_opportunities' => $this->evaluateTaxLossHarvesting($definition, $investmentAnalysis, $priority),

            // Tax efficiency triggers
            'has_gia_no_isa' => $this->evaluateOpenIsa($definition, $investmentAnalysis, $priority),
            'has_isa_remaining_and_gia' => $this->evaluateUseIsaAllowance($definition, $investmentAnalysis, $priority),
            'gia_value_above_and_no_bonds' => $this->evaluateConsiderBonds($definition, $investmentAnalysis, $config, $priority),

            // Savings triggers
            'emergency_runway_below' => $this->evaluateEmergencyFundCritical($definition, $savingsAnalysis, $config, $priority),
            'emergency_runway_between' => $this->evaluateEmergencyFundGrow($definition, $savingsAnalysis, $config, $priority),
            'has_poor_rate_accounts' => $this->evaluateSwitchSavingsRate($definition, $savingsAnalysis, $priority),
            'isa_remaining_and_runway_above' => $this->evaluateIsaAllowanceRemaining($definition, $savingsAnalysis, $config, $priority),

            // Surplus waterfall triggers
            'surplus_exists_and_isa_remaining' => $this->evaluateSurplusToIsa($definition, $savingsAnalysis, $userId, $priority),
            'surplus_exceeds_isa' => $this->evaluateSurplusToPension($definition, $savingsAnalysis, $userId, $priority),
            'surplus_exceeds_pension' => $this->evaluateSurplusToBond($definition, $savingsAnalysis, $userId, $priority),

            default => [],
        };
    }

    // =========================================================================
    // Investment evaluators (8)
    // =========================================================================

    /**
     * Risk profile missing: triggers when no allocation deviation is available.
     */
    private function evaluateRiskProfileMissing(
        InvestmentActionDefinition $definition,
        array $investmentAnalysis,
        int $priority
    ): array {
        $trace = [];
        $hasAllocation = isset($investmentAnalysis['allocation_deviation']);

        $accountsCount = $investmentAnalysis['portfolio_summary']['accounts_count'] ?? 0;
        $holdingsCount = $investmentAnalysis['portfolio_summary']['holdings_count'] ?? 0;
        $totalValue = $investmentAnalysis['portfolio_summary']['total_value'] ?? 0;

        $trace[] = [
            'question' => 'Has a risk profile been set with allocation targets?',
            'data_field' => 'investmentAnalysis.allocation_deviation',
            'data_value' => $hasAllocation ? 'Set' : 'Not set',
            'threshold' => 'Must be set for portfolio assessment',
            'passed' => ! $hasAllocation,
            'explanation' => $hasAllocation
                ? 'Risk profile is configured — portfolio can be assessed against target allocation.'
                : 'No risk profile found. The user has '.$accountsCount.' account(s) with '.$holdingsCount.' holding(s) (total £'.number_format($totalValue, 0).') but no allocation targets to measure against.',
        ];

        if ($hasAllocation) {
            return [];
        }

        $rec = $this->buildRecommendation($definition, [], $priority);
        $rec['decision_trace'] = $trace;

        return [$rec];
    }

    /**
     * No holdings: triggers when accounts exist but total holdings count is zero.
     */
    private function evaluateNoHoldings(
        InvestmentActionDefinition $definition,
        array $investmentAnalysis,
        int $priority
    ): array {
        $accountsCount = $investmentAnalysis['portfolio_summary']['accounts_count'] ?? 0;
        $holdingsCount = $investmentAnalysis['portfolio_summary']['holdings_count'] ?? 0;
        $totalValue = $investmentAnalysis['portfolio_summary']['total_value'] ?? 0;

        $trace = [];

        $trace[] = [
            'question' => 'Do investment accounts exist?',
            'data_field' => 'investmentAnalysis.portfolio_summary.accounts_count',
            'data_value' => $accountsCount.' account(s), total value £'.number_format($totalValue, 0),
            'threshold' => 'At least 1',
            'passed' => $accountsCount > 0,
            'explanation' => $accountsCount > 0
                ? $accountsCount.' investment account(s) found with total reported value of £'.number_format($totalValue, 0).'.'
                : 'No investment accounts — nothing to evaluate.',
        ];

        $trace[] = [
            'question' => 'Are the accounts empty of holdings?',
            'data_field' => 'investmentAnalysis.portfolio_summary.holdings_count',
            'data_value' => (string) $holdingsCount,
            'threshold' => '0 (no holdings recorded)',
            'passed' => $holdingsCount === 0,
            'explanation' => $holdingsCount > 0
                ? $holdingsCount.' holding(s) found across '.$accountsCount.' account(s) — accounts are populated.'
                : 'No holdings recorded in any of the '.$accountsCount.' account(s). Without holding data, fee analysis, diversification checks, and rebalancing recommendations cannot be generated.',
        ];

        if ($accountsCount === 0 || $holdingsCount > 0) {
            return [];
        }

        $rec = $this->buildRecommendation($definition, [], $priority);
        $rec['decision_trace'] = $trace;

        return [$rec];
    }

    /**
     * Low diversification: triggers when score is below threshold. Only fires when holdings exist.
     */
    private function evaluateLowDiversification(
        InvestmentActionDefinition $definition,
        array $investmentAnalysis,
        array $config,
        int $priority
    ): array {
        $holdingsCount = $investmentAnalysis['portfolio_summary']['holdings_count'] ?? 0;
        if ($holdingsCount === 0) {
            return [];
        }

        $threshold = (float) ($config['threshold'] ?? 70);
        $score = $investmentAnalysis['diversification_score'] ?? 100;
        $totalValue = $investmentAnalysis['portfolio_summary']['total_value'] ?? 0;

        $trace = [];

        $trace[] = [
            'question' => 'Are holdings present for diversification analysis?',
            'data_field' => 'investmentAnalysis.portfolio_summary.holdings_count',
            'data_value' => $holdingsCount.' holding(s) across portfolio (total £'.number_format($totalValue, 0).')',
            'threshold' => 'At least 1',
            'passed' => true,
            'explanation' => $holdingsCount.' holding(s) available for diversification analysis across a £'.number_format($totalValue, 0).' portfolio.',
        ];

        $trace[] = [
            'question' => 'Is the diversification level below the target?',
            'data_field' => 'investmentAnalysis.diversification_score',
            'data_value' => round($score, 1).'%',
            'threshold' => round($threshold, 1).'% (from action definition config)',
            'passed' => $score < $threshold,
            'explanation' => $score < $threshold
                ? 'Portfolio diversification at '.round($score, 1).'% is '.round($threshold - $score, 1).' percentage points below the '.round($threshold, 1).'% target. With '.$holdingsCount.' holding(s) in a £'.number_format($totalValue, 0).' portfolio, consider spreading holdings across more asset classes or geographies.'
                : 'Portfolio diversification at '.round($score, 1).'% meets the '.round($threshold, 1).'% target.',
        ];

        if ($score >= $threshold) {
            return [];
        }

        $rec = $this->buildRecommendation($definition, [], $priority);
        $rec['decision_trace'] = $trace;

        return [$rec];
    }

    /**
     * High total fees: triggers per-account when total fee exceeds threshold.
     */
    private function evaluateHighTotalFees(
        InvestmentActionDefinition $definition,
        array $accountFeeAnalyses,
        array $config,
        int $priority
    ): array {
        $threshold = (float) ($config['threshold'] ?? 1.0);
        $results = [];

        foreach ($accountFeeAnalyses as $acctFees) {
            $totalFeePercent = $acctFees['total_fee_percent'] ?? 0;
            $accountName = $acctFees['account_name'] ?? 'Unknown Account';
            $accountType = $acctFees['account_type'] ?? 'unknown';
            $platformName = $acctFees['platform_name'] ?? 'Unknown';
            $accountValue = $acctFees['account_value'] ?? 0;
            $annualFees = $acctFees['total_annual_fees'] ?? 0;
            $holdingsCount = $acctFees['holdings_count'] ?? 0;

            $platformFee = $acctFees['fees']['platform_fee'] ?? 0;
            $fundOcf = $acctFees['fees']['fund_ocf'] ?? 0;
            $transactionCosts = $acctFees['fees']['transaction_costs'] ?? 0;
            $advisoryFee = $acctFees['fees']['advisory_fee'] ?? 0;

            $excessPercent = $totalFeePercent - $threshold;
            $excessCost = $accountValue > 0 ? ($excessPercent / 100) * $accountValue : 0;

            $trace = [];

            $trace[] = [
                'question' => 'What is this account and what fees does it incur?',
                'data_field' => 'accountFeeAnalysis',
                'data_value' => $accountName.' ('.strtoupper($accountType).') at '.$platformName.', value £'.number_format($accountValue, 0).', '.$holdingsCount.' holding(s)',
                'threshold' => 'N/A',
                'passed' => true,
                'explanation' => 'Account: '.$accountName.' ('.strtoupper($accountType).') at '.$platformName.'. Value: £'.number_format($accountValue, 0).'. Fee breakdown: platform £'.number_format($platformFee, 0).'/year, fund charges £'.number_format($fundOcf, 0).'/year, transaction costs £'.number_format($transactionCosts, 0).'/year, advisory £'.number_format($advisoryFee, 0).'/year.',
            ];

            $trace[] = [
                'question' => 'Does the total fee percentage exceed the threshold?',
                'data_field' => 'total_fee_percent',
                'data_value' => round($totalFeePercent, 2).'% (£'.number_format($annualFees, 0).'/year on £'.number_format($accountValue, 0).')',
                'threshold' => round($threshold, 2).'% (from action definition config)',
                'passed' => $totalFeePercent > $threshold,
                'explanation' => $totalFeePercent > $threshold
                    ? $accountName.' total fees of '.round($totalFeePercent, 2).'% cost £'.number_format($annualFees, 0).'/year. This is '.round($excessPercent, 2).' percentage points above the '.round($threshold, 2).'% benchmark, costing an additional £'.number_format(max(0, $excessCost), 0).'/year vs target.'
                    : $accountName.' fees of '.round($totalFeePercent, 2).'% are within the '.round($threshold, 2).'% threshold.',
            ];

            if ($totalFeePercent <= $threshold) {
                continue;
            }

            $vars = [
                'account_name' => $accountName,
                'total_fee_percent' => number_format($totalFeePercent, 2),
                'annual_fees' => $this->formatCurrency($annualFees),
            ];

            $rec = $this->buildRecommendation($definition, $vars, $priority);
            $rec['scope'] = 'account';
            $rec['account_id'] = $acctFees['account_id'] ?? null;
            $rec['account_name'] = $accountName;
            $rec['estimated_impact'] = round($annualFees * 0.4, 2);
            $rec['decision_trace'] = $trace;
            $results[] = $rec;
        }

        return $results;
    }

    /**
     * High fund fees: triggers per-account when weighted OCF exceeds threshold.
     */
    private function evaluateHighFundFees(
        InvestmentActionDefinition $definition,
        array $accountFeeAnalyses,
        array $config,
        int $priority
    ): array {
        $threshold = (float) ($config['threshold'] ?? 0.5);
        $results = [];

        foreach ($accountFeeAnalyses as $acctFees) {
            $holdingsCount = $acctFees['holdings_count'] ?? 0;
            if ($holdingsCount === 0) {
                continue;
            }

            $weightedOcf = $acctFees['weighted_ocf'] ?? 0;
            $accountName = $acctFees['account_name'] ?? 'Unknown Account';
            $accountType = $acctFees['account_type'] ?? 'unknown';
            $platformName = $acctFees['platform_name'] ?? 'Unknown';
            $accountValue = $acctFees['account_value'] ?? 0;
            $fundOcfCost = $acctFees['fees']['fund_ocf'] ?? 0;

            $excessOcf = $weightedOcf - $threshold;
            $excessCost = $accountValue > 0 ? ($excessOcf / 100) * $accountValue : 0;

            $trace = [];

            $trace[] = [
                'question' => 'What account and holdings are being assessed for fund charges?',
                'data_field' => 'accountFeeAnalysis',
                'data_value' => $accountName.' ('.strtoupper($accountType).') at '.$platformName.', '.$holdingsCount.' holding(s), value £'.number_format($accountValue, 0),
                'threshold' => 'At least 1 holding',
                'passed' => true,
                'explanation' => $accountName.' at '.$platformName.' holds '.$holdingsCount.' fund(s) with a combined value of £'.number_format($accountValue, 0).'. Fund ongoing charges figure: £'.number_format($fundOcfCost, 0).'/year.',
            ];

            $trace[] = [
                'question' => 'Does the weighted ongoing charges figure exceed the threshold?',
                'data_field' => 'weighted_ocf',
                'data_value' => round($weightedOcf, 2).'% (£'.number_format($fundOcfCost, 0).'/year on £'.number_format($accountValue, 0).')',
                'threshold' => round($threshold, 2).'% (from action definition config)',
                'passed' => $weightedOcf > $threshold,
                'explanation' => $weightedOcf > $threshold
                    ? $accountName.' fund charges are '.round($weightedOcf, 2).'%, which is '.round($excessOcf, 2).' percentage points above the '.round($threshold, 2).'% threshold. The excess costs £'.number_format(max(0, $excessCost), 0).'/year. Consider switching to lower-cost index tracker funds.'
                    : $accountName.' fund charges of '.round($weightedOcf, 2).'% are within the '.round($threshold, 2).'% threshold.',
            ];

            if ($weightedOcf <= $threshold) {
                continue;
            }

            $vars = [
                'account_name' => $accountName,
                'weighted_ocf' => number_format($weightedOcf, 2),
            ];

            $rec = $this->buildRecommendation($definition, $vars, $priority);
            $rec['scope'] = 'account';
            $rec['account_id'] = $acctFees['account_id'] ?? null;
            $rec['account_name'] = $accountName;
            $rec['decision_trace'] = $trace;
            $results[] = $rec;
        }

        return $results;
    }

    /**
     * High platform fees: triggers per-account when platform fee exceeds threshold.
     */
    private function evaluateHighPlatformFees(
        InvestmentActionDefinition $definition,
        array $accountFeeAnalyses,
        array $config,
        int $priority
    ): array {
        $threshold = (float) ($config['threshold'] ?? 0.8);
        $results = [];

        foreach ($accountFeeAnalyses as $acctFees) {
            $platformFeePercent = 0;
            $accountValue = $acctFees['account_value'] ?? 0;
            $platformFeeCost = $acctFees['fees']['platform_fee'] ?? 0;
            if ($platformFeeCost > 0 && $accountValue > 0) {
                $platformFeePercent = ($platformFeeCost / $accountValue) * 100;
            }

            $accountName = $acctFees['account_name'] ?? 'Unknown Account';
            $accountType = $acctFees['account_type'] ?? 'unknown';
            $platformName = $acctFees['platform_name'] ?? 'Unknown';

            $excessPercent = $platformFeePercent - $threshold;
            $excessCost = $accountValue > 0 ? ($excessPercent / 100) * $accountValue : 0;

            $trace = [];

            $trace[] = [
                'question' => 'What is the platform fee for this account?',
                'data_field' => 'accountFeeAnalysis (fees.platform_fee / account_value)',
                'data_value' => $accountName.' ('.strtoupper($accountType).') at '.$platformName.': platform fee '.round($platformFeePercent, 2).'% (£'.number_format($platformFeeCost, 0).'/year on £'.number_format($accountValue, 0).')',
                'threshold' => round($threshold, 2).'% (from action definition config)',
                'passed' => $platformFeePercent > $threshold,
                'explanation' => $platformFeePercent > $threshold
                    ? $accountName.' at '.$platformName.' charges '.round($platformFeePercent, 2).'% platform fee (£'.number_format($platformFeeCost, 0).'/year on £'.number_format($accountValue, 0).'). This is '.round($excessPercent, 2).' percentage points above the '.round($threshold, 2).'% threshold, costing an additional £'.number_format(max(0, $excessCost), 0).'/year. Consider switching to a lower-cost platform.'
                    : $accountName.' platform fee of '.round($platformFeePercent, 2).'% (£'.number_format($platformFeeCost, 0).'/year) is within the '.round($threshold, 2).'% threshold.',
            ];

            if ($platformFeePercent <= $threshold) {
                continue;
            }

            $vars = [
                'account_name' => $accountName,
                'platform_fee_percent' => number_format($platformFeePercent, 2),
            ];

            $rec = $this->buildRecommendation($definition, $vars, $priority);
            $rec['scope'] = 'account';
            $rec['account_id'] = $acctFees['account_id'] ?? null;
            $rec['account_name'] = $accountName;
            $rec['decision_trace'] = $trace;
            $results[] = $rec;
        }

        return $results;
    }

    /**
     * Rebalance portfolio: triggers when allocation deviation indicates rebalancing is needed.
     * Only fires when holdings exist.
     */
    private function evaluateRebalancePortfolio(
        InvestmentActionDefinition $definition,
        array $investmentAnalysis,
        int $priority
    ): array {
        $holdingsCount = $investmentAnalysis['portfolio_summary']['holdings_count'] ?? 0;
        if ($holdingsCount === 0) {
            return [];
        }

        $needsRebalancing = $investmentAnalysis['allocation_deviation']['needs_rebalancing'] ?? false;
        $totalValue = $investmentAnalysis['portfolio_summary']['total_value'] ?? 0;
        $deviations = $investmentAnalysis['allocation_deviation']['deviations'] ?? [];

        $deviationSummary = '';
        if (! empty($deviations) && is_array($deviations)) {
            $deviationParts = [];
            foreach (array_slice($deviations, 0, 3) as $assetClass => $deviation) {
                if (is_array($deviation)) {
                    $actual = $deviation['actual'] ?? 0;
                    $target = $deviation['target'] ?? 0;
                    $diff = $actual - $target;
                    $deviationParts[] = $assetClass.': '.round($actual, 1).'% actual vs '.round($target, 1).'% target ('.($diff >= 0 ? '+' : '').round($diff, 1).'%)';
                }
            }
            $deviationSummary = implode('; ', $deviationParts);
        }

        $trace = [];

        $trace[] = [
            'question' => 'Are holdings present for rebalancing analysis?',
            'data_field' => 'investmentAnalysis.portfolio_summary.holdings_count',
            'data_value' => $holdingsCount.' holding(s), total £'.number_format($totalValue, 0),
            'threshold' => 'At least 1',
            'passed' => true,
            'explanation' => $holdingsCount.' holding(s) across a £'.number_format($totalValue, 0).' portfolio available for rebalancing check.',
        ];

        $trace[] = [
            'question' => 'Does the portfolio allocation deviate enough to require rebalancing?',
            'data_field' => 'investmentAnalysis.allocation_deviation.needs_rebalancing',
            'data_value' => $needsRebalancing ? 'Yes' : 'No',
            'threshold' => 'Yes (deviations outside target bands)',
            'passed' => $needsRebalancing,
            'explanation' => $needsRebalancing
                ? 'Asset allocation has drifted beyond target bands — rebalancing recommended.'.($deviationSummary ? ' Key deviations: '.$deviationSummary.'.' : '')
                : 'Portfolio allocation is within target bands — no rebalancing needed.',
        ];

        if (! $needsRebalancing) {
            return [];
        }

        $rec = $this->buildRecommendation($definition, [], $priority);
        $rec['decision_trace'] = $trace;

        return [$rec];
    }

    /**
     * Tax loss harvesting: triggers when harvesting opportunities exist.
     */
    private function evaluateTaxLossHarvesting(
        InvestmentActionDefinition $definition,
        array $investmentAnalysis,
        int $priority
    ): array {
        $opportunities = $investmentAnalysis['tax_efficiency']['harvesting_opportunities'] ?? [];
        $count = $opportunities['opportunities_count'] ?? 0;
        $saving = $opportunities['potential_tax_saving'] ?? 0;
        $totalLosses = $opportunities['total_losses'] ?? 0;

        $trace = [];

        $trace[] = [
            'question' => 'Are there tax loss harvesting opportunities in the portfolio?',
            'data_field' => 'investmentAnalysis.tax_efficiency.harvesting_opportunities',
            'data_value' => $count.' opportunity(s), potential saving £'.number_format($saving, 0),
            'threshold' => 'At least 1 opportunity',
            'passed' => $count > 0,
            'explanation' => $count > 0
                ? $count.' harvesting opportunity(s) identified with £'.number_format($totalLosses, 0).' in unrealised losses. Crystallising these losses could save £'.number_format($saving, 0).' in Capital Gains Tax by offsetting gains elsewhere in the portfolio.'
                : 'No tax loss harvesting opportunities identified — all holdings are in profit or losses are too small to be material.',
        ];

        if ($count <= 0) {
            return [];
        }

        $vars = [
            'opportunities_count' => (string) $count,
            'potential_saving' => $this->formatCurrency($saving),
        ];

        $rec = $this->buildRecommendation($definition, $vars, $priority);
        $rec['decision_trace'] = $trace;

        return [$rec];
    }

    // =========================================================================
    // Tax efficiency evaluators (3)
    // =========================================================================

    /**
     * Open ISA: triggers when user has GIA but no ISA.
     */
    private function evaluateOpenIsa(
        InvestmentActionDefinition $definition,
        array $investmentAnalysis,
        int $priority
    ): array {
        $taxWrappers = $investmentAnalysis['tax_wrappers'] ?? [];
        $hasGia = $taxWrappers['has_gia'] ?? false;
        $hasIsa = $taxWrappers['has_isa'] ?? false;
        $giaValue = $taxWrappers['gia_value'] ?? 0;

        $trace = [];

        $trace[] = [
            'question' => 'Does the user hold a General Investment Account?',
            'data_field' => 'investmentAnalysis.tax_wrappers.has_gia',
            'data_value' => $hasGia ? 'Yes (£'.number_format($giaValue, 0).')' : 'No',
            'threshold' => 'Yes',
            'passed' => $hasGia,
            'explanation' => $hasGia
                ? '£'.number_format($giaValue, 0).' held in General Investment Accounts — growth and income are subject to Capital Gains Tax and income tax.'
                : 'No General Investment Account — ISA transfer not relevant.',
        ];

        $trace[] = [
            'question' => 'Is there already an ISA account?',
            'data_field' => 'investmentAnalysis.tax_wrappers.has_isa',
            'data_value' => $hasIsa ? 'Yes' : 'No',
            'threshold' => 'No (must not already have one)',
            'passed' => ! $hasIsa,
            'explanation' => $hasIsa
                ? 'An ISA already exists — use the existing wrapper for new contributions and Bed and ISA transfers.'
                : 'No ISA exists — opening one would shelter up to the annual allowance of growth from income tax and Capital Gains Tax.',
        ];

        if (! $hasGia || $hasIsa) {
            return [];
        }

        $isaAllowance = $taxWrappers['isa_allowance']
            ?? $this->taxConfig->getISAAllowances()['annual_allowance']
            ?? TaxDefaults::ISA_ALLOWANCE;
        $vars = [
            'isa_allowance' => number_format($isaAllowance),
        ];

        $rec = $this->buildRecommendation($definition, $vars, $priority);
        $rec['decision_trace'] = $trace;

        return [$rec];
    }

    /**
     * Use ISA allowance: triggers when ISA has remaining allowance and GIA holdings exist.
     */
    private function evaluateUseIsaAllowance(
        InvestmentActionDefinition $definition,
        array $investmentAnalysis,
        int $priority
    ): array {
        $taxWrappers = $investmentAnalysis['tax_wrappers'] ?? [];
        $hasIsa = $taxWrappers['has_isa'] ?? false;
        $hasGia = $taxWrappers['has_gia'] ?? false;
        $isaRemaining = $taxWrappers['isa_remaining'] ?? 0;
        $giaValue = $taxWrappers['gia_value'] ?? 0;
        $isaUsed = $taxWrappers['isa_used_this_year'] ?? 0;
        $isaAllowance = $taxWrappers['isa_allowance'] ?? TaxDefaults::ISA_ALLOWANCE;

        $transferAmount = min($giaValue, $isaRemaining);

        $trace = [];

        $trace[] = [
            'question' => 'Does the user have both an ISA and a General Investment Account?',
            'data_field' => 'investmentAnalysis.tax_wrappers (has_isa + has_gia)',
            'data_value' => 'ISA: '.($hasIsa ? 'Yes' : 'No').'. General Investment Account: '.($hasGia ? 'Yes (£'.number_format($giaValue, 0).')' : 'No').'.',
            'threshold' => 'Both required',
            'passed' => $hasIsa && $hasGia,
            'explanation' => ($hasIsa && $hasGia)
                ? 'Both ISA and General Investment Account exist. £'.number_format($giaValue, 0).' in taxable General Investment Account holdings could be transferred to the tax-free ISA wrapper.'
                : 'Both ISA and General Investment Account are needed for a Bed and ISA transfer.',
        ];

        $trace[] = [
            'question' => 'Is there remaining ISA allowance this tax year?',
            'data_field' => 'investmentAnalysis.tax_wrappers.isa_remaining',
            'data_value' => '£'.number_format($isaRemaining, 0).' remaining (£'.number_format($isaUsed, 0).' of £'.number_format($isaAllowance, 0).' used)',
            'threshold' => 'More than £0',
            'passed' => $isaRemaining > 0,
            'explanation' => $isaRemaining > 0
                ? '£'.number_format($isaRemaining, 0).' of ISA allowance available. Potential Bed and ISA transfer: £'.number_format($transferAmount, 0).' (the lower of General Investment Account value and remaining allowance).'
                : 'ISA allowance has been fully used this tax year — no further transfers possible.',
        ];

        if (! $hasIsa || ! $hasGia || $isaRemaining <= 0) {
            return [];
        }

        $vars = [
            'isa_remaining' => number_format($isaRemaining),
            'gia_value' => number_format($giaValue),
        ];

        $rec = $this->buildRecommendation($definition, $vars, $priority);
        $rec['decision_trace'] = $trace;

        return [$rec];
    }

    /**
     * Consider bonds: triggers when GIA value exceeds threshold and no bond accounts exist.
     */
    private function evaluateConsiderBonds(
        InvestmentActionDefinition $definition,
        array $investmentAnalysis,
        array $config,
        int $priority
    ): array {
        $taxWrappers = $investmentAnalysis['tax_wrappers'] ?? [];
        $hasGia = $taxWrappers['has_gia'] ?? false;
        $giaValue = $taxWrappers['gia_value'] ?? 0;
        $hasOnshore = $taxWrappers['has_onshore_bond'] ?? false;
        $hasOffshore = $taxWrappers['has_offshore_bond'] ?? false;
        $hasBonds = $hasOnshore || $hasOffshore;
        $threshold = (float) ($config['threshold'] ?? 50000);

        $trace = [];

        $trace[] = [
            'question' => 'Does the user hold a General Investment Account with significant value?',
            'data_field' => 'investmentAnalysis.tax_wrappers (has_gia + gia_value)',
            'data_value' => $hasGia ? 'Yes, £'.number_format($giaValue, 0) : 'No',
            'threshold' => 'Above £'.number_format($threshold, 0),
            'passed' => $hasGia && $giaValue > $threshold,
            'explanation' => ($hasGia && $giaValue > $threshold)
                ? 'General Investment Account value of £'.number_format($giaValue, 0).' exceeds the £'.number_format($threshold, 0).' threshold by £'.number_format($giaValue - $threshold, 0).'. Investment bonds could provide tax deferral benefits on this surplus.'
                : ($hasGia ? 'General Investment Account value of £'.number_format($giaValue, 0).' is below the £'.number_format($threshold, 0).' threshold for bond consideration.' : 'No General Investment Account — bond wrapper not relevant.'),
        ];

        $trace[] = [
            'question' => 'Does the user already hold investment bonds?',
            'data_field' => 'investmentAnalysis.tax_wrappers (has_onshore_bond + has_offshore_bond)',
            'data_value' => 'Onshore: '.($hasOnshore ? 'Yes' : 'No').'. Offshore: '.($hasOffshore ? 'Yes' : 'No').'.',
            'threshold' => 'No existing bonds',
            'passed' => ! $hasBonds,
            'explanation' => $hasBonds
                ? 'Existing bond wrapper found — no additional recommendation needed.'
                : 'No bonds held — investment bonds could offer tax deferral benefits with the 5% annual tax-deferred withdrawal allowance.',
        ];

        if (! $hasGia || $giaValue <= $threshold || $hasBonds) {
            return [];
        }

        $vars = [
            'gia_value' => number_format($giaValue),
        ];

        $rec = $this->buildRecommendation($definition, $vars, $priority);
        $rec['decision_trace'] = $trace;

        return [$rec];
    }

    // =========================================================================
    // Savings evaluators (4)
    // =========================================================================

    /**
     * Emergency fund critical: triggers when runway is below threshold.
     */
    private function evaluateEmergencyFundCritical(
        InvestmentActionDefinition $definition,
        array $savingsAnalysis,
        array $config,
        int $priority
    ): array {
        $runway = $savingsAnalysis['emergency_fund']['runway_months'] ?? 0;
        $threshold = (float) ($config['threshold'] ?? 3);
        $totalSavings = $savingsAnalysis['summary']['total_savings'] ?? 0;
        $monthlyExpenditure = $savingsAnalysis['summary']['monthly_expenditure'] ?? 0;
        $targetAmount = $monthlyExpenditure * $threshold;
        $shortfall = max(0, $targetAmount - $totalSavings);

        $trace = [];

        $trace[] = [
            'question' => 'Is the emergency fund runway critically low?',
            'data_field' => 'savingsAnalysis.emergency_fund.runway_months',
            'data_value' => number_format($runway, 1).' months (£'.number_format($totalSavings, 0).' savings ÷ £'.number_format($monthlyExpenditure, 0).'/month expenditure)',
            'threshold' => number_format($threshold, 0).' months minimum (£'.number_format($targetAmount, 0).')',
            'passed' => $runway < $threshold,
            'explanation' => $runway < $threshold
                ? 'Emergency fund covers only '.number_format($runway, 1).' months — below the '.number_format($threshold, 0).'-month minimum. Shortfall: £'.number_format($shortfall, 0).'. Priority is building the emergency fund before considering investment.'
                : 'Emergency fund of '.number_format($runway, 1).' months meets the '.number_format($threshold, 0).'-month minimum threshold.',
        ];

        if ($runway >= $threshold) {
            return [];
        }

        $vars = [
            'runway_months' => number_format($runway, 0),
        ];

        $rec = $this->buildRecommendation($definition, $vars, $priority);
        $rec['decision_trace'] = $trace;

        return [$rec];
    }

    /**
     * Emergency fund grow: triggers when runway is between low and high thresholds.
     */
    private function evaluateEmergencyFundGrow(
        InvestmentActionDefinition $definition,
        array $savingsAnalysis,
        array $config,
        int $priority
    ): array {
        $runway = $savingsAnalysis['emergency_fund']['runway_months'] ?? 0;
        $low = (float) ($config['low'] ?? 3);
        $high = (float) ($config['high'] ?? 6);
        $totalSavings = $savingsAnalysis['summary']['total_savings'] ?? 0;
        $monthlyExpenditure = $savingsAnalysis['summary']['monthly_expenditure'] ?? 0;
        $targetAmount = $monthlyExpenditure * $high;
        $shortfall = max(0, $targetAmount - $totalSavings);

        $trace = [];

        $trace[] = [
            'question' => 'Is the emergency fund runway between the low and high thresholds?',
            'data_field' => 'savingsAnalysis.emergency_fund.runway_months',
            'data_value' => number_format($runway, 1).' months (£'.number_format($totalSavings, 0).' savings ÷ £'.number_format($monthlyExpenditure, 0).'/month)',
            'threshold' => number_format($low, 0).' to '.number_format($high, 0).' months (target: £'.number_format($targetAmount, 0).')',
            'passed' => $runway >= $low && $runway < $high,
            'explanation' => ($runway >= $low && $runway < $high)
                ? 'Emergency fund of '.number_format($runway, 1).' months is adequate but could be stronger. Shortfall to '.number_format($high, 0).'-month target: £'.number_format($shortfall, 0).'.'
                : ($runway < $low
                    ? 'Emergency fund of '.number_format($runway, 1).' months is below the '.number_format($low, 0).'-month minimum — handled by the critical alert.'
                    : 'Emergency fund of '.number_format($runway, 1).' months meets the '.number_format($high, 0).'-month target.'),
        ];

        if ($runway < $low || $runway >= $high) {
            return [];
        }

        $vars = [
            'runway_months' => number_format($runway, 0),
        ];

        $rec = $this->buildRecommendation($definition, $vars, $priority);
        $rec['decision_trace'] = $trace;

        return [$rec];
    }

    /**
     * Switch savings rate: triggers when poor-rated accounts exist with meaningful gain.
     */
    private function evaluateSwitchSavingsRate(
        InvestmentActionDefinition $definition,
        array $savingsAnalysis,
        int $priority
    ): array {
        $rateComparisons = $savingsAnalysis['rate_comparisons'] ?? [];
        $lowRateAccounts = collect($rateComparisons)->filter(
            fn ($comp) => ($comp['comparison']['rating'] ?? '') === 'Poor'
        );

        $totalGain = $lowRateAccounts->sum('potential_gain');
        $totalBalance = $lowRateAccounts->sum(fn ($comp) => $comp['current_balance'] ?? 0);

        $accountNames = $lowRateAccounts->map(function ($comp) {
            $name = $comp['account_name'] ?? 'Unknown';
            $institution = $comp['institution'] ?? 'Unknown';
            $rate = $comp['current_rate'] ?? 0;
            $bestRate = $comp['comparison']['best_rate'] ?? 0;
            $balance = $comp['current_balance'] ?? 0;

            return $name.' at '.$institution.' ('.$rate.'% vs '.$bestRate.'% best, £'.number_format($balance, 0).')';
        })->implode('; ');

        $trace = [];

        $trace[] = [
            'question' => 'Are there savings accounts with poor interest rates?',
            'data_field' => 'savingsAnalysis.rate_comparisons (rating = Poor)',
            'data_value' => $lowRateAccounts->count().' account(s) rated "Poor", total balance £'.number_format($totalBalance, 0),
            'threshold' => 'At least 1',
            'passed' => $lowRateAccounts->isNotEmpty(),
            'explanation' => $lowRateAccounts->isNotEmpty()
                ? $lowRateAccounts->count().' account(s) rated as poor vs market rates: '.$accountNames.'.'
                : 'All savings accounts have competitive interest rates.',
        ];

        $trace[] = [
            'question' => 'Is the potential gain from switching meaningful?',
            'data_field' => 'sum of potential_gain across poor-rated accounts',
            'data_value' => '£'.number_format($totalGain, 0).' per year',
            'threshold' => 'At least £100 per year',
            'passed' => $totalGain >= 100,
            'explanation' => $totalGain >= 100
                ? 'Switching could gain £'.number_format($totalGain, 0).' per year in additional interest across £'.number_format($totalBalance, 0).' of savings.'
                : 'Potential gain of £'.number_format($totalGain, 0).' per year is too small to justify the effort of switching.',
        ];

        if ($lowRateAccounts->isEmpty()) {
            return [];
        }

        if ($totalGain < 100) {
            return [];
        }

        $vars = [
            'potential_gain' => $this->formatCurrency($totalGain),
        ];

        $rec = $this->buildRecommendation($definition, $vars, $priority);
        $rec['estimated_impact'] = round($totalGain, 2);
        $rec['decision_trace'] = $trace;

        return [$rec];
    }

    /**
     * ISA allowance remaining: triggers when ISA allowance remains and runway is sufficient.
     */
    private function evaluateIsaAllowanceRemaining(
        InvestmentActionDefinition $definition,
        array $savingsAnalysis,
        array $config,
        int $priority
    ): array {
        $isaRemaining = $savingsAnalysis['isa_allowance']['remaining'] ?? 0;
        $isaUsed = $savingsAnalysis['isa_allowance']['used'] ?? 0;
        $isaAnnual = $savingsAnalysis['isa_allowance']['annual'] ?? TaxDefaults::ISA_ALLOWANCE;
        $runway = $savingsAnalysis['emergency_fund']['runway_months'] ?? 0;
        $runwayThreshold = (float) ($config['threshold'] ?? 6);
        $totalSavings = $savingsAnalysis['summary']['total_savings'] ?? 0;

        $trace = [];

        $trace[] = [
            'question' => 'Is there remaining ISA allowance this tax year?',
            'data_field' => 'savingsAnalysis.isa_allowance.remaining',
            'data_value' => '£'.number_format($isaRemaining, 0).' remaining (£'.number_format($isaUsed, 0).' of £'.number_format($isaAnnual, 0).' used)',
            'threshold' => 'More than £0',
            'passed' => $isaRemaining > 0,
            'explanation' => $isaRemaining > 0
                ? '£'.number_format($isaRemaining, 0).' of ISA allowance still available — interest earned within the ISA is tax-free.'
                : 'ISA allowance has been fully used this tax year.',
        ];

        $trace[] = [
            'question' => 'Is the emergency fund runway sufficient before using ISA allowance?',
            'data_field' => 'savingsAnalysis.emergency_fund.runway_months',
            'data_value' => number_format($runway, 1).' months (£'.number_format($totalSavings, 0).' total savings)',
            'threshold' => number_format($runwayThreshold, 0).' months',
            'passed' => $runway >= $runwayThreshold,
            'explanation' => $runway >= $runwayThreshold
                ? 'Emergency fund of '.number_format($runway, 1).' months exceeds the '.number_format($runwayThreshold, 0).'-month threshold — safe to deploy savings to ISA.'
                : 'Emergency fund of '.number_format($runway, 1).' months is below the '.number_format($runwayThreshold, 0).'-month threshold — prioritise building the emergency fund before using ISA allowance.',
        ];

        if ($isaRemaining <= 0 || $runway < $runwayThreshold) {
            return [];
        }

        $vars = [
            'isa_remaining' => $this->formatCurrency($isaRemaining),
        ];

        $rec = $this->buildRecommendation($definition, $vars, $priority);
        $rec['decision_trace'] = $trace;

        return [$rec];
    }

    // =========================================================================
    // Surplus waterfall evaluators (3)
    // =========================================================================

    /**
     * Calculate surplus amount: total savings minus target months of expenses.
     * Returns 0 if no surplus or if a goal is drawing down the emergency fund.
     *
     * NOTE: Investment uses a 6-month universal baseline via
     * PlanConfigService::getEmergencyFundTargetMonths(). Savings uses
     * employment-specific targets via EmergencyFundCalculator::getTargetMonths()
     * (e.g. 9 months for self-employed/contractors, 3 months for retired).
     * This divergence is intentional — investment surplus calculations use a
     * conservative universal floor, while savings recommendations personalise
     * the target based on employment stability.
     */
    private function calculateSurplus(array $savingsAnalysis, int $userId): float
    {
        if ($userId <= 0) {
            return 0;
        }

        $targetMonths = $this->planConfig->getEmergencyFundTargetMonths();
        $runway = $savingsAnalysis['emergency_fund']['runway_months'] ?? 0;
        if ($runway <= $targetMonths) {
            return 0;
        }

        // Check if any goals are drawing down savings
        $hasDrawdownGoal = Goal::where('user_id', $userId)
            ->whereNotNull('linked_savings_account_id')
            ->where('status', '!=', 'completed')
            ->exists();

        if ($hasDrawdownGoal) {
            return 0;
        }

        $monthlyExpenditure = $savingsAnalysis['summary']['monthly_expenditure'] ?? 0;
        $totalSavings = $savingsAnalysis['summary']['total_savings'] ?? 0;
        $targetFund = $monthlyExpenditure * $targetMonths;

        return max(0, $totalSavings - $targetFund);
    }

    /**
     * Surplus to ISA: triggers when surplus exists and ISA allowance remaining.
     */
    private function evaluateSurplusToIsa(
        InvestmentActionDefinition $definition,
        array $savingsAnalysis,
        int $userId,
        int $priority
    ): array {
        $surplus = $this->calculateSurplus($savingsAnalysis, $userId);
        $isaRemaining = $savingsAnalysis['isa_allowance']['remaining'] ?? 0;
        $totalSavings = $savingsAnalysis['summary']['total_savings'] ?? 0;
        $monthlyExpenditure = $savingsAnalysis['summary']['monthly_expenditure'] ?? 0;
        $targetMonths = $this->planConfig->getEmergencyFundTargetMonths();
        $targetFund = $monthlyExpenditure * $targetMonths;

        $trace = [];

        $trace[] = [
            'question' => 'Is there a savings surplus above the emergency fund target?',
            'data_field' => 'calculated: total_savings - (monthly_expenditure × target_months)',
            'data_value' => '£'.number_format($totalSavings, 0).' - (£'.number_format($monthlyExpenditure, 0).' × '.$targetMonths.' months = £'.number_format($targetFund, 0).') = £'.number_format(max(0, $surplus), 0).' surplus',
            'threshold' => 'More than £0',
            'passed' => $surplus > 0,
            'explanation' => $surplus > 0
                ? '£'.number_format($surplus, 0).' surplus available after meeting the '.$targetMonths.'-month emergency fund target of £'.number_format($targetFund, 0).'.'
                : 'No surplus — emergency fund target not yet met or goals are drawing down savings.',
        ];

        $trace[] = [
            'question' => 'Is there remaining ISA allowance to deploy the surplus?',
            'data_field' => 'savingsAnalysis.isa_allowance.remaining',
            'data_value' => '£'.number_format($isaRemaining, 0),
            'threshold' => 'More than £0',
            'passed' => $isaRemaining > 0,
            'explanation' => $isaRemaining > 0
                ? '£'.number_format($isaRemaining, 0).' of ISA allowance available. Deploy up to £'.number_format(min($surplus, $isaRemaining), 0).' for tax-free growth.'
                : 'ISA allowance fully used this tax year.',
        ];

        if ($surplus <= 0) {
            return [];
        }

        if ($isaRemaining <= 0) {
            return [];
        }

        $isaAmount = min($surplus, $isaRemaining);
        $vars = [
            'isa_amount' => $this->formatCurrency($isaAmount),
            'isa_remaining' => $this->formatCurrency($isaRemaining),
        ];

        $rec = $this->buildRecommendation($definition, $vars, $priority);
        $rec['estimated_impact'] = round($isaAmount, 2);
        $rec['decision_trace'] = $trace;

        return [$rec];
    }

    /**
     * Surplus to pension: triggers when surplus exceeds ISA capacity.
     */
    private function evaluateSurplusToPension(
        InvestmentActionDefinition $definition,
        array $savingsAnalysis,
        int $userId,
        int $priority
    ): array {
        $surplus = $this->calculateSurplus($savingsAnalysis, $userId);
        $isaRemaining = $savingsAnalysis['isa_allowance']['remaining'] ?? 0;
        $remaining = $surplus - $isaRemaining;

        $pensionAllowances = $this->taxConfig->getPensionAllowances();
        $annualAllowance = $pensionAllowances['annual_allowance'] ?? TaxDefaults::PENSION_ANNUAL_ALLOWANCE;
        $pensionAmount = min(max(0, $remaining), $annualAllowance);

        $trace = [];

        $trace[] = [
            'question' => 'Is there a savings surplus above the emergency fund target?',
            'data_field' => 'calculated surplus',
            'data_value' => '£'.number_format(max(0, $surplus), 0),
            'threshold' => 'More than £0',
            'passed' => $surplus > 0,
            'explanation' => $surplus > 0
                ? '£'.number_format($surplus, 0).' surplus identified above the emergency fund target.'
                : 'No surplus available.',
        ];

        $trace[] = [
            'question' => 'Does the surplus exceed the ISA allowance, leaving a remainder for pension?',
            'data_field' => 'calculated: surplus - isa_remaining',
            'data_value' => '£'.number_format(max(0, $surplus), 0).' surplus - £'.number_format($isaRemaining, 0).' ISA allowance = £'.number_format(max(0, $remaining), 0).' remainder',
            'threshold' => 'More than £0',
            'passed' => $remaining > 0,
            'explanation' => $remaining > 0
                ? '£'.number_format($remaining, 0).' remains after the ISA allowance is accounted for. This can be directed to pension for tax relief.'
                : 'Surplus of £'.number_format(max(0, $surplus), 0).' is fully absorbed by the ISA allowance of £'.number_format($isaRemaining, 0).' — no remainder for pension.',
        ];

        $trace[] = [
            'question' => 'How much can be directed to pension within the Annual Allowance?',
            'data_field' => 'calculated: min(remainder, annual_allowance)',
            'data_value' => 'min(£'.number_format(max(0, $remaining), 0).', £'.number_format($annualAllowance, 0).') = £'.number_format($pensionAmount, 0),
            'threshold' => '£'.number_format($annualAllowance, 0).' Annual Allowance',
            'passed' => $pensionAmount > 0,
            'explanation' => $pensionAmount > 0
                ? '£'.number_format($pensionAmount, 0).' can be contributed to pension with tax relief. Contributions receive at least 20% basic rate relief (claimed automatically) with higher/additional rate relief available via self-assessment.'
                : 'No pension contribution amount available.',
        ];

        if ($surplus <= 0 || $remaining <= 0 || $pensionAmount <= 0) {
            return [];
        }

        $vars = [
            'pension_amount' => $this->formatCurrency($pensionAmount),
        ];

        $rec = $this->buildRecommendation($definition, $vars, $priority);
        $rec['estimated_impact'] = round($pensionAmount, 2);
        $rec['decision_trace'] = $trace;

        return [$rec];
    }

    /**
     * Surplus to bond: triggers when surplus exceeds pension capacity.
     */
    private function evaluateSurplusToBond(
        InvestmentActionDefinition $definition,
        array $savingsAnalysis,
        int $userId,
        int $priority
    ): array {
        $surplus = $this->calculateSurplus($savingsAnalysis, $userId);
        $isaRemaining = $savingsAnalysis['isa_allowance']['remaining'] ?? 0;
        $pensionAllowances = $this->taxConfig->getPensionAllowances();
        $annualAllowance = $pensionAllowances['annual_allowance'] ?? TaxDefaults::PENSION_ANNUAL_ALLOWANCE;

        // Deduct only what would actually go to ISA and pension (capped amounts)
        $afterIsa = max(0, $surplus - $isaRemaining);
        $pensionAmount = min($afterIsa, $annualAllowance);
        $remaining = $afterIsa - $pensionAmount;

        $trace = [];

        $trace[] = [
            'question' => 'Is there a savings surplus above the emergency fund target?',
            'data_field' => 'calculated surplus',
            'data_value' => '£'.number_format(max(0, $surplus), 0),
            'threshold' => 'More than £0',
            'passed' => $surplus > 0,
            'explanation' => $surplus > 0
                ? '£'.number_format($surplus, 0).' surplus identified.'
                : 'No surplus available.',
        ];

        $trace[] = [
            'question' => 'Does surplus remain after ISA and pension allocations?',
            'data_field' => 'calculated: surplus - isa_remaining - pension_amount',
            'data_value' => '£'.number_format(max(0, $surplus), 0).' - £'.number_format($isaRemaining, 0).' (ISA) - £'.number_format($pensionAmount, 0).' (pension) = £'.number_format(max(0, $remaining), 0),
            'threshold' => 'More than £0',
            'passed' => $remaining > 0,
            'explanation' => $remaining > 0
                ? '£'.number_format($remaining, 0).' remains after ISA allowance (£'.number_format($isaRemaining, 0).') and pension Annual Allowance (£'.number_format($pensionAmount, 0).'). This could be deployed to an investment bond for tax deferral with the 5% annual withdrawal allowance.'
                : 'Surplus is fully absorbed by ISA (£'.number_format($isaRemaining, 0).') and pension (£'.number_format($pensionAmount, 0).') allowances.',
        ];

        if ($surplus <= 0 || $remaining <= 0) {
            return [];
        }

        $vars = [
            'bond_amount' => $this->formatCurrency($remaining),
        ];

        $rec = $this->buildRecommendation($definition, $vars, $priority);
        $rec['estimated_impact'] = round($remaining, 2);
        $rec['decision_trace'] = $trace;

        return [$rec];
    }

    // =========================================================================
    // Goal evaluators (3)
    // =========================================================================

    /**
     * Dispatch a single goal-sourced trigger to the appropriate evaluator.
     */
    private function evaluateGoalTrigger(InvestmentActionDefinition $definition, array $goal): ?array
    {
        $config = $definition->trigger_config;
        $condition = $config['condition'] ?? '';

        return match ($condition) {
            'linked_goal_no_monthly_contribution' => $this->evaluateGoalNoContribution($definition, $goal),
            'linked_goal_off_track' => $this->evaluateGoalOffTrack($definition, $goal),
            'goal_months_remaining_below_and_progress_below' => $this->evaluateGoalDeadline($definition, $goal, $config),
            default => null,
        };
    }

    /**
     * Goal no contribution: triggers when monthly contribution is zero but required > 0.
     */
    private function evaluateGoalNoContribution(InvestmentActionDefinition $definition, array $goal): ?array
    {
        $monthlyContribution = $goal['monthly_contribution'] ?? 0;
        $required = $goal['required_monthly_contribution'] ?? 0;
        $goalName = $goal['name'] ?? 'Unnamed goal';
        $targetAmount = $goal['target_amount'] ?? 0;
        $currentAmount = $goal['current_amount'] ?? 0;
        $progress = $goal['progress_percentage'] ?? 0;

        $trace = [];

        $trace[] = [
            'question' => 'Is there a monthly contribution to this goal?',
            'data_field' => 'goal.monthly_contribution',
            'data_value' => '£'.number_format($monthlyContribution, 0).' per month for "'.$goalName.'"',
            'threshold' => 'More than £0',
            'passed' => $monthlyContribution <= 0,
            'explanation' => $monthlyContribution > 0
                ? '"'.$goalName.'" already has a £'.number_format($monthlyContribution, 0).'/month contribution.'
                : '"'.$goalName.'" has no monthly contribution set up. Current balance: £'.number_format($currentAmount, 0).' ('.number_format($progress, 0).'% of £'.number_format($targetAmount, 0).' target).',
        ];

        $trace[] = [
            'question' => 'Is a monthly contribution required to reach the target?',
            'data_field' => 'goal.required_monthly_contribution',
            'data_value' => '£'.number_format($required, 0).'/month needed',
            'threshold' => 'More than £0',
            'passed' => $required > 0,
            'explanation' => $required > 0
                ? '£'.number_format($required, 0).'/month is needed to reach the £'.number_format($targetAmount, 0).' target from the current £'.number_format($currentAmount, 0).'.'
                : 'No monthly contribution is required — "'.$goalName.'" may already be fully funded.',
        ];

        if ($monthlyContribution > 0 || $required <= 0) {
            return null;
        }

        $vars = [
            'goal_name' => $goalName,
            'required_monthly' => $this->formatCurrency($required),
            'target_amount' => $this->formatCurrency($targetAmount),
        ];

        return [
            'title' => $definition->renderTitle($vars),
            'description' => $definition->renderDescription($vars),
            'category' => $definition->category,
            'priority' => $definition->priority,
            'source' => 'goal',
            'goal_id' => $goal['id'] ?? null,
            'decision_trace' => $trace,
        ];
    }

    /**
     * Goal off track: triggers when goal is_on_track is false and has contributions.
     */
    private function evaluateGoalOffTrack(InvestmentActionDefinition $definition, array $goal): ?array
    {
        $monthlyContribution = $goal['monthly_contribution'] ?? 0;
        $isOnTrack = $goal['is_on_track'] ?? true;
        $goalName = $goal['name'] ?? 'Unnamed goal';
        $required = $goal['required_monthly_contribution'] ?? 0;
        $shortfall = max(0, $required - $monthlyContribution);
        $progress = $goal['progress_percentage'] ?? 0;
        $targetAmount = $goal['target_amount'] ?? 0;
        $currentAmount = $goal['current_amount'] ?? 0;

        $trace = [];

        $trace[] = [
            'question' => 'Does the goal have an active monthly contribution?',
            'data_field' => 'goal.monthly_contribution',
            'data_value' => '£'.number_format($monthlyContribution, 0).'/month for "'.$goalName.'"',
            'threshold' => 'More than £0',
            'passed' => $monthlyContribution > 0,
            'explanation' => $monthlyContribution > 0
                ? '"'.$goalName.'" has a £'.number_format($monthlyContribution, 0).'/month contribution, but £'.number_format($required, 0).'/month is required to meet the £'.number_format($targetAmount, 0).' target.'
                : '"'.$goalName.'" has no contribution — handled by the missing contribution check.',
        ];

        $trace[] = [
            'question' => 'Is the goal off track despite having contributions?',
            'data_field' => 'goal.is_on_track',
            'data_value' => $isOnTrack ? 'On track' : 'Off track',
            'threshold' => 'Off track',
            'passed' => ! $isOnTrack,
            'explanation' => ! $isOnTrack
                ? '"'.$goalName.'" is at '.number_format($progress, 0).'% (£'.number_format($currentAmount, 0).' of £'.number_format($targetAmount, 0).'). Monthly shortfall: £'.number_format($shortfall, 0).'/month (contributing £'.number_format($monthlyContribution, 0).' vs £'.number_format($required, 0).' required).'
                : '"'.$goalName.'" is on track at '.number_format($progress, 0).'% to meet its £'.number_format($targetAmount, 0).' target.',
        ];

        // Skip if no contribution (caught by no-contribution check)
        if ($monthlyContribution <= 0) {
            return null;
        }

        if ($isOnTrack) {
            return null;
        }

        $vars = [
            'goal_name' => $goalName,
            'progress' => number_format($progress, 0),
            'shortfall' => $this->formatCurrency($shortfall),
        ];

        return [
            'title' => $definition->renderTitle($vars),
            'description' => $definition->renderDescription($vars),
            'category' => $definition->category,
            'priority' => $definition->priority,
            'source' => 'goal',
            'goal_id' => $goal['id'] ?? null,
            'decision_trace' => $trace,
        ];
    }

    /**
     * Goal deadline approaching: triggers when months remaining and progress below thresholds.
     */
    private function evaluateGoalDeadline(InvestmentActionDefinition $definition, array $goal, array $config): ?array
    {
        $isOnTrack = $goal['is_on_track'] ?? true;
        $monthsRemaining = $goal['months_remaining'] ?? 0;
        $progress = $goal['progress_percentage'] ?? 0;
        $monthsThreshold = (int) ($config['months_threshold'] ?? 6);
        $progressThreshold = (float) ($config['progress_threshold'] ?? 75);
        $goalName = $goal['name'] ?? 'Unnamed goal';
        $targetAmount = $goal['target_amount'] ?? 0;
        $currentAmount = $goal['current_amount'] ?? 0;
        $shortfall = max(0, $targetAmount - $currentAmount);

        $trace = [];

        $trace[] = [
            'question' => 'Is the goal currently marked as on track?',
            'data_field' => 'goal.is_on_track',
            'data_value' => $isOnTrack ? 'On track' : 'Off track',
            'threshold' => 'Must be on track (deadline check applies to on-track goals approaching risk)',
            'passed' => $isOnTrack,
            'explanation' => $isOnTrack
                ? '"'.$goalName.'" is currently on track at '.number_format($progress, 0).'% (£'.number_format($currentAmount, 0).' of £'.number_format($targetAmount, 0).') — checking for deadline pressure.'
                : '"'.$goalName.'" is already off track — handled by the off-track check.',
        ];

        $trace[] = [
            'question' => 'Is the deadline approaching with insufficient progress?',
            'data_field' => 'goal.months_remaining',
            'data_value' => $monthsRemaining.' months remaining, '.number_format($progress, 0).'% progress',
            'threshold' => 'Within '.$monthsThreshold.' months AND below '.number_format($progressThreshold, 0).'%',
            'passed' => $monthsRemaining <= $monthsThreshold && $progress < $progressThreshold,
            'explanation' => ($monthsRemaining <= $monthsThreshold && $progress < $progressThreshold)
                ? '"'.$goalName.'" has only '.$monthsRemaining.' months left but is at '.number_format($progress, 0).'% (below the '.number_format($progressThreshold, 0).'% expected). Remaining shortfall: £'.number_format($shortfall, 0).'.'
                : ($monthsRemaining > $monthsThreshold
                    ? $monthsRemaining.' months remaining — deadline is not imminent.'
                    : '"'.$goalName.'" progress of '.number_format($progress, 0).'% is sufficient for this stage.'),
        ];

        // Only triggers for goals that are otherwise on-track
        if (! $isOnTrack) {
            return null;
        }

        if ($monthsRemaining > $monthsThreshold || $progress >= $progressThreshold) {
            return null;
        }

        $vars = [
            'goal_name' => $goalName,
            'progress' => number_format($progress, 0),
            'months_remaining' => (string) $monthsRemaining,
            'target_amount' => $this->formatCurrency($targetAmount),
        ];

        return [
            'title' => $definition->renderTitle($vars),
            'description' => $definition->renderDescription($vars),
            'category' => $definition->category,
            'priority' => $definition->priority,
            'source' => 'goal',
            'goal_id' => $goal['id'] ?? null,
            'decision_trace' => $trace,
        ];
    }

    // =========================================================================
    // Conflict resolution
    // =========================================================================

    /**
     * Resolve conflicts between mutually exclusive recommendations.
     *
     * If both emergency_fund_critical and emergency_fund_grow fire,
     * keep only the critical one (they target overlapping scenarios).
     */
    private function resolveConflicts(array $recommendations): array
    {
        $keys = array_column($recommendations, 'definition_key');

        $hasCritical = in_array('emergency_fund_critical', $keys, true);
        $hasGrow = in_array('emergency_fund_grow', $keys, true);

        if ($hasCritical && $hasGrow) {
            $recommendations = array_values(array_filter(
                $recommendations,
                fn ($r) => ($r['definition_key'] ?? '') !== 'emergency_fund_grow'
            ));
        }

        return $recommendations;
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    /**
     * Build a standard recommendation array from a definition and template variables.
     */
    private function buildRecommendation(
        InvestmentActionDefinition $definition,
        array $vars,
        int $priority
    ): array {
        return [
            'priority' => $priority,
            'category' => $definition->category,
            'title' => $definition->renderTitle($vars),
            'description' => $definition->renderDescription($vars),
            'action' => $definition->renderAction($vars) ?? 'See detailed recommendations',
            'impact' => ucfirst($definition->priority),
            'scope' => $definition->scope,
            'definition_key' => $definition->key,
        ];
    }
}
