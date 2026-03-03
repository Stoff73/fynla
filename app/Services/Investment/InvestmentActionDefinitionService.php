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
        if (isset($investmentAnalysis['allocation_deviation'])) {
            return [];
        }

        return [$this->buildRecommendation($definition, [], $priority)];
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

        if ($accountsCount === 0 || $holdingsCount > 0) {
            return [];
        }

        return [$this->buildRecommendation($definition, [], $priority)];
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

        if ($score >= $threshold) {
            return [];
        }

        return [$this->buildRecommendation($definition, [], $priority)];
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
            if ($totalFeePercent <= $threshold) {
                continue;
            }

            $accountName = $acctFees['account_name'] ?? 'Unknown Account';
            $annualFees = $acctFees['total_annual_fees'] ?? 0;

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
            if ($weightedOcf <= $threshold) {
                continue;
            }

            $accountName = $acctFees['account_name'] ?? 'Unknown Account';
            $vars = [
                'account_name' => $accountName,
                'weighted_ocf' => number_format($weightedOcf, 2),
            ];

            $rec = $this->buildRecommendation($definition, $vars, $priority);
            $rec['scope'] = 'account';
            $rec['account_id'] = $acctFees['account_id'] ?? null;
            $rec['account_name'] = $accountName;
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
            if (isset($acctFees['fees']['platform_fee']) && ($acctFees['account_value'] ?? 0) > 0) {
                $platformFeePercent = ($acctFees['fees']['platform_fee'] / $acctFees['account_value']) * 100;
            }

            if ($platformFeePercent <= $threshold) {
                continue;
            }

            $accountName = $acctFees['account_name'] ?? 'Unknown Account';
            $vars = [
                'account_name' => $accountName,
                'platform_fee_percent' => number_format($platformFeePercent, 2),
            ];

            $rec = $this->buildRecommendation($definition, $vars, $priority);
            $rec['scope'] = 'account';
            $rec['account_id'] = $acctFees['account_id'] ?? null;
            $rec['account_name'] = $accountName;
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
        if (! $needsRebalancing) {
            return [];
        }

        return [$this->buildRecommendation($definition, [], $priority)];
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

        if ($count <= 0) {
            return [];
        }

        $saving = $opportunities['potential_tax_saving'] ?? 0;
        $vars = [
            'opportunities_count' => (string) $count,
            'potential_saving' => $this->formatCurrency($saving),
        ];

        return [$this->buildRecommendation($definition, $vars, $priority)];
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

        if (! $hasGia || $hasIsa) {
            return [];
        }

        $isaAllowance = $taxWrappers['isa_allowance']
            ?? $this->taxConfig->getISAAllowances()['annual_allowance']
            ?? TaxDefaults::ISA_ALLOWANCE;
        $vars = [
            'isa_allowance' => number_format($isaAllowance),
        ];

        return [$this->buildRecommendation($definition, $vars, $priority)];
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

        if (! $hasIsa || ! $hasGia || $isaRemaining <= 0) {
            return [];
        }

        $giaValue = $taxWrappers['gia_value'] ?? 0;
        $vars = [
            'isa_remaining' => number_format($isaRemaining),
            'gia_value' => number_format($giaValue),
        ];

        return [$this->buildRecommendation($definition, $vars, $priority)];
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

        if (! $hasGia || $giaValue <= $threshold || $hasBonds) {
            return [];
        }

        $vars = [
            'gia_value' => number_format($giaValue),
        ];

        return [$this->buildRecommendation($definition, $vars, $priority)];
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

        if ($runway >= $threshold) {
            return [];
        }

        $vars = [
            'runway_months' => number_format($runway, 0),
        ];

        return [$this->buildRecommendation($definition, $vars, $priority)];
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

        if ($runway < $low || $runway >= $high) {
            return [];
        }

        $vars = [
            'runway_months' => number_format($runway, 0),
        ];

        return [$this->buildRecommendation($definition, $vars, $priority)];
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

        if ($lowRateAccounts->isEmpty()) {
            return [];
        }

        $totalGain = $lowRateAccounts->sum('potential_gain');
        if ($totalGain < 100) {
            return [];
        }

        $vars = [
            'potential_gain' => $this->formatCurrency($totalGain),
        ];

        $rec = $this->buildRecommendation($definition, $vars, $priority);
        $rec['estimated_impact'] = round($totalGain, 2);

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

        if ($isaRemaining <= 0 || $runway < $runwayThreshold) {
            return [];
        }

        $vars = [
            'isa_remaining' => $this->formatCurrency($isaRemaining),
        ];

        return [$this->buildRecommendation($definition, $vars, $priority)];
    }

    // =========================================================================
    // Surplus waterfall evaluators (3)
    // =========================================================================

    /**
     * Calculate surplus amount: total savings minus target months of expenses.
     * Returns 0 if no surplus or if a goal is drawing down the emergency fund.
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
        if ($surplus <= 0) {
            return [];
        }

        $isaRemaining = $savingsAnalysis['isa_allowance']['remaining'] ?? 0;
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
        if ($surplus <= 0) {
            return [];
        }

        $isaRemaining = $savingsAnalysis['isa_allowance']['remaining'] ?? 0;
        $remaining = $surplus - $isaRemaining;
        if ($remaining <= 0) {
            return [];
        }

        $pensionAllowances = $this->taxConfig->getPensionAllowances();
        $annualAllowance = $pensionAllowances['annual_allowance'] ?? TaxDefaults::PENSION_ANNUAL_ALLOWANCE;
        $pensionAmount = min($remaining, $annualAllowance);

        if ($pensionAmount <= 0) {
            return [];
        }

        $vars = [
            'pension_amount' => $this->formatCurrency($pensionAmount),
        ];

        $rec = $this->buildRecommendation($definition, $vars, $priority);
        $rec['estimated_impact'] = round($pensionAmount, 2);

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
        if ($surplus <= 0) {
            return [];
        }

        $isaRemaining = $savingsAnalysis['isa_allowance']['remaining'] ?? 0;
        $pensionAllowances = $this->taxConfig->getPensionAllowances();
        $annualAllowance = $pensionAllowances['annual_allowance'] ?? TaxDefaults::PENSION_ANNUAL_ALLOWANCE;

        // Deduct only what would actually go to ISA and pension (capped amounts)
        $afterIsa = max(0, $surplus - $isaRemaining);
        $pensionAmount = min($afterIsa, $annualAllowance);
        $remaining = $afterIsa - $pensionAmount;
        if ($remaining <= 0) {
            return [];
        }

        $vars = [
            'bond_amount' => $this->formatCurrency($remaining),
        ];

        $rec = $this->buildRecommendation($definition, $vars, $priority);
        $rec['estimated_impact'] = round($remaining, 2);

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

        if ($monthlyContribution > 0 || $required <= 0) {
            return null;
        }

        $vars = [
            'goal_name' => $goal['name'] ?? 'Unnamed goal',
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
        ];
    }

    /**
     * Goal off track: triggers when goal is_on_track is false and has contributions.
     */
    private function evaluateGoalOffTrack(InvestmentActionDefinition $definition, array $goal): ?array
    {
        $monthlyContribution = $goal['monthly_contribution'] ?? 0;

        // Skip if no contribution (caught by no-contribution check)
        if ($monthlyContribution <= 0) {
            return null;
        }

        if ($goal['is_on_track'] ?? true) {
            return null;
        }

        $required = $goal['required_monthly_contribution'] ?? 0;
        $shortfall = max(0, $required - $monthlyContribution);
        $progress = $goal['progress_percentage'] ?? 0;

        $vars = [
            'goal_name' => $goal['name'] ?? 'Unnamed goal',
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
        ];
    }

    /**
     * Goal deadline approaching: triggers when months remaining and progress below thresholds.
     */
    private function evaluateGoalDeadline(InvestmentActionDefinition $definition, array $goal, array $config): ?array
    {
        // Only triggers for goals that are otherwise on-track
        if (! ($goal['is_on_track'] ?? true)) {
            return null;
        }

        $monthsRemaining = $goal['months_remaining'] ?? 0;
        $progress = $goal['progress_percentage'] ?? 0;
        $monthsThreshold = (int) ($config['months_threshold'] ?? 6);
        $progressThreshold = (float) ($config['progress_threshold'] ?? 75);

        if ($monthsRemaining > $monthsThreshold || $progress >= $progressThreshold) {
            return null;
        }

        $vars = [
            'goal_name' => $goal['name'] ?? 'Unnamed goal',
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
