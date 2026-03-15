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

        $trace[] = [
            'question' => 'Has a risk profile been set with allocation targets?',
            'data_field' => 'allocation_deviation',
            'data_value' => $hasAllocation ? 'Set' : 'Not set',
            'threshold' => 'Must be set',
            'passed' => ! $hasAllocation,
            'explanation' => $hasAllocation
                ? 'Risk profile is configured — no action needed.'
                : 'No risk profile found — portfolio cannot be assessed against targets.',
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

        $trace = [];

        $trace[] = [
            'question' => 'Do investment accounts exist?',
            'data_field' => 'accounts_count',
            'data_value' => (string) $accountsCount,
            'threshold' => 'At least 1',
            'passed' => $accountsCount > 0,
            'explanation' => $accountsCount > 0
                ? $accountsCount.' investment account(s) found.'
                : 'No investment accounts — nothing to evaluate.',
        ];

        $trace[] = [
            'question' => 'Are the accounts empty of holdings?',
            'data_field' => 'holdings_count',
            'data_value' => (string) $holdingsCount,
            'threshold' => '0',
            'passed' => $holdingsCount === 0,
            'explanation' => $holdingsCount > 0
                ? $holdingsCount.' holding(s) found — accounts are populated.'
                : 'No holdings recorded in any account.',
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

        $trace = [];

        $trace[] = [
            'question' => 'Are holdings present for diversification analysis?',
            'data_field' => 'holdings_count',
            'data_value' => (string) $holdingsCount,
            'threshold' => 'At least 1',
            'passed' => true,
            'explanation' => $holdingsCount.' holding(s) available for analysis.',
        ];

        $trace[] = [
            'question' => 'Is the diversification level below the target?',
            'data_field' => 'diversification_score',
            'data_value' => round($score, 1).'%',
            'threshold' => round($threshold, 1).'%',
            'passed' => $score < $threshold,
            'explanation' => $score < $threshold
                ? 'Portfolio diversification is below the target — consider spreading holdings.'
                : 'Portfolio diversification meets the target.',
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
            $annualFees = $acctFees['total_annual_fees'] ?? 0;

            $trace = [];

            $trace[] = [
                'question' => 'Does the total fee percentage exceed the threshold?',
                'data_field' => 'total_fee_percent',
                'data_value' => round($totalFeePercent, 2).'%',
                'threshold' => round($threshold, 2).'%',
                'passed' => $totalFeePercent > $threshold,
                'explanation' => $totalFeePercent > $threshold
                    ? $accountName.' has total fees of '.round($totalFeePercent, 2).'%, costing £'.number_format($annualFees, 0).' per year.'
                    : $accountName.' fees are within acceptable limits.',
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

            $trace = [];

            $trace[] = [
                'question' => 'Does the account have holdings to assess?',
                'data_field' => 'holdings_count',
                'data_value' => (string) $holdingsCount,
                'threshold' => 'At least 1',
                'passed' => true,
                'explanation' => $holdingsCount.' holding(s) in '.$accountName.'.',
            ];

            $trace[] = [
                'question' => 'Does the weighted ongoing charges figure exceed the threshold?',
                'data_field' => 'weighted_ocf',
                'data_value' => round($weightedOcf, 2).'%',
                'threshold' => round($threshold, 2).'%',
                'passed' => $weightedOcf > $threshold,
                'explanation' => $weightedOcf > $threshold
                    ? $accountName.' fund charges are '.round($weightedOcf, 2).'% — above the '.round($threshold, 2).'% threshold.'
                    : $accountName.' fund charges are within acceptable limits.',
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
            if (isset($acctFees['fees']['platform_fee']) && $accountValue > 0) {
                $platformFeePercent = ($acctFees['fees']['platform_fee'] / $accountValue) * 100;
            }

            $accountName = $acctFees['account_name'] ?? 'Unknown Account';

            $trace = [];

            $trace[] = [
                'question' => 'Does the platform fee percentage exceed the threshold?',
                'data_field' => 'platform_fee_percent',
                'data_value' => round($platformFeePercent, 2).'%',
                'threshold' => round($threshold, 2).'%',
                'passed' => $platformFeePercent > $threshold,
                'explanation' => $platformFeePercent > $threshold
                    ? $accountName.' platform fee of '.round($platformFeePercent, 2).'% exceeds the '.round($threshold, 2).'% threshold.'
                    : $accountName.' platform fee is within acceptable limits.',
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

        $trace = [];

        $trace[] = [
            'question' => 'Are holdings present for rebalancing analysis?',
            'data_field' => 'holdings_count',
            'data_value' => (string) $holdingsCount,
            'threshold' => 'At least 1',
            'passed' => true,
            'explanation' => $holdingsCount.' holding(s) available for rebalancing check.',
        ];

        $trace[] = [
            'question' => 'Does the portfolio allocation deviate enough to require rebalancing?',
            'data_field' => 'needs_rebalancing',
            'data_value' => $needsRebalancing ? 'Yes' : 'No',
            'threshold' => 'Yes',
            'passed' => $needsRebalancing,
            'explanation' => $needsRebalancing
                ? 'Asset allocation has drifted beyond target bands — rebalancing recommended.'
                : 'Portfolio allocation is within target bands.',
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

        $trace = [];

        $trace[] = [
            'question' => 'Are there tax loss harvesting opportunities?',
            'data_field' => 'opportunities_count',
            'data_value' => (string) $count,
            'threshold' => 'At least 1',
            'passed' => $count > 0,
            'explanation' => $count > 0
                ? $count.' harvesting opportunity(s) found with potential saving of £'.number_format($saving, 0).'.'
                : 'No tax loss harvesting opportunities identified.',
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

        $trace = [];

        $trace[] = [
            'question' => 'Does the user hold a General Investment Account?',
            'data_field' => 'has_gia',
            'data_value' => $hasGia ? 'Yes' : 'No',
            'threshold' => 'Yes',
            'passed' => $hasGia,
            'explanation' => $hasGia
                ? 'General Investment Account holdings found — taxable growth may apply.'
                : 'No General Investment Account — ISA transfer not relevant.',
        ];

        $trace[] = [
            'question' => 'Is there already an ISA account?',
            'data_field' => 'has_isa',
            'data_value' => $hasIsa ? 'Yes' : 'No',
            'threshold' => 'No',
            'passed' => ! $hasIsa,
            'explanation' => $hasIsa
                ? 'An ISA is already open — use the existing wrapper.'
                : 'No ISA exists — opening one would shelter growth from tax.',
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

        $trace = [];

        $trace[] = [
            'question' => 'Does the user have both an ISA and a General Investment Account?',
            'data_field' => 'has_isa + has_gia',
            'data_value' => ($hasIsa ? 'ISA: Yes' : 'ISA: No').', '.($hasGia ? 'GIA: Yes' : 'GIA: No'),
            'threshold' => 'Both required',
            'passed' => $hasIsa && $hasGia,
            'explanation' => ($hasIsa && $hasGia)
                ? 'Both ISA and General Investment Account exist — transfer opportunity available.'
                : 'Both ISA and General Investment Account are needed for a transfer.',
        ];

        $trace[] = [
            'question' => 'Is there remaining ISA allowance this tax year?',
            'data_field' => 'isa_remaining',
            'data_value' => '£'.number_format($isaRemaining, 0),
            'threshold' => 'More than £0',
            'passed' => $isaRemaining > 0,
            'explanation' => $isaRemaining > 0
                ? '£'.number_format($isaRemaining, 0).' of ISA allowance available to shelter General Investment Account holdings.'
                : 'ISA allowance has been fully used this tax year.',
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
        $hasBonds = ($taxWrappers['has_onshore_bond'] ?? false) || ($taxWrappers['has_offshore_bond'] ?? false);
        $threshold = (float) ($config['threshold'] ?? 50000);

        $trace = [];

        $trace[] = [
            'question' => 'Does the user hold a General Investment Account?',
            'data_field' => 'has_gia',
            'data_value' => $hasGia ? 'Yes' : 'No',
            'threshold' => 'Yes',
            'passed' => $hasGia,
            'explanation' => $hasGia
                ? 'General Investment Account holdings found.'
                : 'No General Investment Account — bond wrapper not relevant.',
        ];

        $trace[] = [
            'question' => 'Does the General Investment Account value exceed the threshold?',
            'data_field' => 'gia_value',
            'data_value' => '£'.number_format($giaValue, 0),
            'threshold' => '£'.number_format($threshold, 0),
            'passed' => $giaValue > $threshold,
            'explanation' => $giaValue > $threshold
                ? 'General Investment Account value of £'.number_format($giaValue, 0).' exceeds the £'.number_format($threshold, 0).' threshold.'
                : 'General Investment Account value is below the threshold for bond consideration.',
        ];

        $trace[] = [
            'question' => 'Does the user already hold investment bonds?',
            'data_field' => 'has_bonds',
            'data_value' => $hasBonds ? 'Yes' : 'No',
            'threshold' => 'No',
            'passed' => ! $hasBonds,
            'explanation' => $hasBonds
                ? 'Existing bond wrapper found — no additional recommendation needed.'
                : 'No bonds held — investment bonds could offer tax deferral benefits.',
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

        $trace = [];

        $trace[] = [
            'question' => 'Is the emergency fund runway critically low?',
            'data_field' => 'runway_months',
            'data_value' => number_format($runway, 1).' months',
            'threshold' => number_format($threshold, 0).' months',
            'passed' => $runway < $threshold,
            'explanation' => $runway < $threshold
                ? 'Emergency fund covers only '.number_format($runway, 1).' months — below the '.number_format($threshold, 0).'-month minimum.'
                : 'Emergency fund runway meets the minimum threshold.',
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

        $trace = [];

        $trace[] = [
            'question' => 'Is the emergency fund runway between the low and high thresholds?',
            'data_field' => 'runway_months',
            'data_value' => number_format($runway, 1).' months',
            'threshold' => number_format($low, 0).' to '.number_format($high, 0).' months',
            'passed' => $runway >= $low && $runway < $high,
            'explanation' => ($runway >= $low && $runway < $high)
                ? 'Emergency fund of '.number_format($runway, 1).' months is adequate but could be stronger.'
                : ($runway < $low
                    ? 'Emergency fund is below the minimum — handled by the critical alert.'
                    : 'Emergency fund of '.number_format($runway, 1).' months meets the target.'),
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

        $trace = [];

        $trace[] = [
            'question' => 'Are there savings accounts with poor interest rates?',
            'data_field' => 'poor_rate_accounts',
            'data_value' => (string) $lowRateAccounts->count(),
            'threshold' => 'At least 1',
            'passed' => $lowRateAccounts->isNotEmpty(),
            'explanation' => $lowRateAccounts->isNotEmpty()
                ? $lowRateAccounts->count().' account(s) rated as poor compared to market rates.'
                : 'All savings accounts have competitive interest rates.',
        ];

        $trace[] = [
            'question' => 'Is the potential gain from switching meaningful?',
            'data_field' => 'potential_gain',
            'data_value' => '£'.number_format($totalGain, 0),
            'threshold' => '£100',
            'passed' => $totalGain >= 100,
            'explanation' => $totalGain >= 100
                ? 'Switching could gain £'.number_format($totalGain, 0).' per year in additional interest.'
                : 'Potential gain of £'.number_format($totalGain, 0).' is too small to justify switching.',
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
        $runway = $savingsAnalysis['emergency_fund']['runway_months'] ?? 0;
        $runwayThreshold = (float) ($config['threshold'] ?? 6);

        $trace = [];

        $trace[] = [
            'question' => 'Is there remaining ISA allowance this tax year?',
            'data_field' => 'isa_remaining',
            'data_value' => '£'.number_format($isaRemaining, 0),
            'threshold' => 'More than £0',
            'passed' => $isaRemaining > 0,
            'explanation' => $isaRemaining > 0
                ? '£'.number_format($isaRemaining, 0).' of ISA allowance still available.'
                : 'ISA allowance has been fully used this tax year.',
        ];

        $trace[] = [
            'question' => 'Is the emergency fund runway sufficient before using ISA allowance?',
            'data_field' => 'runway_months',
            'data_value' => number_format($runway, 1).' months',
            'threshold' => number_format($runwayThreshold, 0).' months',
            'passed' => $runway >= $runwayThreshold,
            'explanation' => $runway >= $runwayThreshold
                ? 'Emergency fund of '.number_format($runway, 1).' months is sufficient — safe to deploy savings to ISA.'
                : 'Emergency fund is below the '.number_format($runwayThreshold, 0).'-month threshold — prioritise building the emergency fund first.',
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

        $trace = [];

        $trace[] = [
            'question' => 'Is there a savings surplus above the emergency fund target?',
            'data_field' => 'surplus',
            'data_value' => '£'.number_format($surplus, 0),
            'threshold' => 'More than £0',
            'passed' => $surplus > 0,
            'explanation' => $surplus > 0
                ? '£'.number_format($surplus, 0).' surplus available after meeting the emergency fund target.'
                : 'No surplus — emergency fund target not yet met or goals are drawing down savings.',
        ];

        $trace[] = [
            'question' => 'Is there remaining ISA allowance to deploy the surplus?',
            'data_field' => 'isa_remaining',
            'data_value' => '£'.number_format($isaRemaining, 0),
            'threshold' => 'More than £0',
            'passed' => $isaRemaining > 0,
            'explanation' => $isaRemaining > 0
                ? '£'.number_format($isaRemaining, 0).' of ISA allowance available for tax-free growth.'
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
            'data_field' => 'surplus',
            'data_value' => '£'.number_format($surplus, 0),
            'threshold' => 'More than £0',
            'passed' => $surplus > 0,
            'explanation' => $surplus > 0
                ? '£'.number_format($surplus, 0).' surplus identified.'
                : 'No surplus available.',
        ];

        $trace[] = [
            'question' => 'Does the surplus exceed the ISA allowance, leaving a remainder for pension?',
            'data_field' => 'surplus_after_isa',
            'data_value' => '£'.number_format(max(0, $remaining), 0),
            'threshold' => 'More than £0',
            'passed' => $remaining > 0,
            'explanation' => $remaining > 0
                ? '£'.number_format($remaining, 0).' remains after the ISA allowance is accounted for.'
                : 'Surplus is fully absorbed by ISA allowance — no remainder for pension.',
        ];

        $trace[] = [
            'question' => 'How much can be directed to pension within the Annual Allowance?',
            'data_field' => 'pension_amount',
            'data_value' => '£'.number_format($pensionAmount, 0),
            'threshold' => '£'.number_format($annualAllowance, 0).' Annual Allowance',
            'passed' => $pensionAmount > 0,
            'explanation' => $pensionAmount > 0
                ? '£'.number_format($pensionAmount, 0).' can be contributed to pension with tax relief.'
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
            'data_field' => 'surplus',
            'data_value' => '£'.number_format($surplus, 0),
            'threshold' => 'More than £0',
            'passed' => $surplus > 0,
            'explanation' => $surplus > 0
                ? '£'.number_format($surplus, 0).' surplus identified.'
                : 'No surplus available.',
        ];

        $trace[] = [
            'question' => 'Does surplus remain after ISA and pension allocations?',
            'data_field' => 'remaining_after_isa_and_pension',
            'data_value' => '£'.number_format(max(0, $remaining), 0),
            'threshold' => 'More than £0',
            'passed' => $remaining > 0,
            'explanation' => $remaining > 0
                ? '£'.number_format($remaining, 0).' remains after ISA (£'.number_format($isaRemaining, 0).') and pension (£'.number_format($pensionAmount, 0).') allocations.'
                : 'Surplus is fully absorbed by ISA and pension allowances.',
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

        $trace = [];

        $trace[] = [
            'question' => 'Is there a monthly contribution to this goal?',
            'data_field' => 'monthly_contribution',
            'data_value' => '£'.number_format($monthlyContribution, 0),
            'threshold' => '£0',
            'passed' => $monthlyContribution <= 0,
            'explanation' => $monthlyContribution > 0
                ? $goalName.' already has a £'.number_format($monthlyContribution, 0).' monthly contribution.'
                : $goalName.' has no monthly contribution set up.',
        ];

        $trace[] = [
            'question' => 'Is a monthly contribution required to reach the target?',
            'data_field' => 'required_monthly_contribution',
            'data_value' => '£'.number_format($required, 0),
            'threshold' => 'More than £0',
            'passed' => $required > 0,
            'explanation' => $required > 0
                ? '£'.number_format($required, 0).' per month is needed to reach the £'.number_format($goal['target_amount'] ?? 0, 0).' target.'
                : 'No monthly contribution is required — goal may already be funded.',
        ];

        if ($monthlyContribution > 0 || $required <= 0) {
            return null;
        }

        $vars = [
            'goal_name' => $goalName,
            'required_monthly' => $this->formatCurrency($required),
            'target_amount' => $this->formatCurrency($goal['target_amount'] ?? 0),
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

        $trace = [];

        $trace[] = [
            'question' => 'Does the goal have an active monthly contribution?',
            'data_field' => 'monthly_contribution',
            'data_value' => '£'.number_format($monthlyContribution, 0),
            'threshold' => 'More than £0',
            'passed' => $monthlyContribution > 0,
            'explanation' => $monthlyContribution > 0
                ? $goalName.' has a £'.number_format($monthlyContribution, 0).' monthly contribution.'
                : $goalName.' has no contribution — handled by the missing contribution check.',
        ];

        $trace[] = [
            'question' => 'Is the goal off track despite having contributions?',
            'data_field' => 'is_on_track',
            'data_value' => $isOnTrack ? 'On track' : 'Off track',
            'threshold' => 'Off track',
            'passed' => ! $isOnTrack,
            'explanation' => ! $isOnTrack
                ? $goalName.' is at '.number_format($progress, 0).'% with a £'.number_format($shortfall, 0).' monthly shortfall.'
                : $goalName.' is on track to meet its target.',
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

        $trace = [];

        $trace[] = [
            'question' => 'Is the goal currently marked as on track?',
            'data_field' => 'is_on_track',
            'data_value' => $isOnTrack ? 'On track' : 'Off track',
            'threshold' => 'On track',
            'passed' => $isOnTrack,
            'explanation' => $isOnTrack
                ? $goalName.' is on track — checking for deadline pressure.'
                : $goalName.' is already off track — handled by the off-track check.',
        ];

        $trace[] = [
            'question' => 'Is the deadline approaching with insufficient progress?',
            'data_field' => 'months_remaining',
            'data_value' => $monthsRemaining.' months',
            'threshold' => $monthsThreshold.' months',
            'passed' => $monthsRemaining <= $monthsThreshold,
            'explanation' => $monthsRemaining <= $monthsThreshold
                ? 'Only '.$monthsRemaining.' months remaining — deadline is approaching.'
                : $monthsRemaining.' months remaining — deadline is not imminent.',
        ];

        $trace[] = [
            'question' => 'Is progress below the target for this stage?',
            'data_field' => 'progress_percentage',
            'data_value' => number_format($progress, 0).'%',
            'threshold' => number_format($progressThreshold, 0).'%',
            'passed' => $progress < $progressThreshold,
            'explanation' => $progress < $progressThreshold
                ? $goalName.' is at '.number_format($progress, 0).'% — below the '.number_format($progressThreshold, 0).'% expected at this stage.'
                : $goalName.' progress of '.number_format($progress, 0).'% is sufficient.',
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
            'target_amount' => $this->formatCurrency($goal['target_amount'] ?? 0),
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
