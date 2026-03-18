<?php

declare(strict_types=1);

namespace App\Services\Investment\Tax;

use App\Models\Investment\InvestmentAccount;
use App\Models\SavingsAccount;
use App\Services\Risk\RiskPreferenceService;
use App\Services\TaxConfigService;
use Illuminate\Support\Collection;

/**
 * Comprehensive tax optimization analyzer
 * Analyzes portfolio for tax efficiency opportunities across UK tax wrappers
 * Uses active tax year rates from TaxConfigService
 */
class TaxOptimizationAnalyzer
{
    public function __construct(
        private readonly TaxConfigService $taxConfig,
        private readonly RiskPreferenceService $riskPreferenceService
    ) {}

    /**
     * Get default expected return from risk preference service
     */
    private function getDefaultExpectedReturn(): float
    {
        return $this->riskPreferenceService->getReturnParameters('medium')['expected_return_typical'] / 100;
    }

    /**
     * Analyze complete tax position and identify optimization opportunities
     *
     * @param  int  $userId  User ID
     * @param  array  $options  Analysis options
     * @return array Comprehensive tax optimization analysis
     */
    public function analyzeCompleteTaxPosition(int $userId, array $options = []): array
    {
        $taxYear = $options['tax_year'] ?? $this->getCurrentTaxYear();

        // Gather all investment data
        $investmentAccounts = InvestmentAccount::where('user_id', $userId)
            ->with('holdings')
            ->get();

        $savingsAccounts = SavingsAccount::where('user_id', $userId)->get();

        if ($investmentAccounts->isEmpty() && $savingsAccounts->isEmpty()) {
            return [
                'success' => false,
                'message' => 'No investment or savings accounts found',
            ];
        }

        // Calculate current tax position
        $currentPosition = $this->calculateCurrentTaxPosition(
            $investmentAccounts,
            $savingsAccounts,
            $taxYear
        );

        // Identify optimization opportunities
        $opportunities = $this->identifyOptimizationOpportunities(
            $investmentAccounts,
            $currentPosition,
            $taxYear
        );

        // Generate recommendations
        $recommendations = $this->generateTaxRecommendations($opportunities);

        // Calculate potential tax savings
        $potentialSavings = $this->calculatePotentialSavings($opportunities);

        // Calculate tax efficiency score
        $efficiencyScore = $this->calculateTaxOptimizationGrade($currentPosition, $opportunities);

        return [
            'success' => true,
            'tax_year' => $taxYear,
            'current_position' => $currentPosition,
            'opportunities' => $opportunities,
            'recommendations' => $recommendations,
            'potential_savings' => $potentialSavings,
            'efficiency_score' => $efficiencyScore,
            'summary' => $this->generateSummary($currentPosition, $opportunities, $potentialSavings),
        ];
    }

    /**
     * Calculate current tax position
     *
     * @param  Collection  $investmentAccounts  Investment accounts
     * @param  Collection  $savingsAccounts  Savings accounts
     * @param  string  $taxYear  Tax year
     * @return array Current tax position
     */
    private function calculateCurrentTaxPosition(
        Collection $investmentAccounts,
        Collection $savingsAccounts,
        string $taxYear
    ): array {
        // Get tax allowances from config
        $isaConfig = $this->taxConfig->getISAAllowances();
        $cgtConfig = $this->taxConfig->getCapitalGainsTax();
        $dividendConfig = $this->taxConfig->getDividendTax();

        // ISA allowance usage
        $isaAllowance = $isaConfig['annual_allowance'];
        $isaUsed = $this->calculateISAUsage($investmentAccounts, $savingsAccounts, $taxYear);
        $isaRemaining = max(0, $isaAllowance - $isaUsed);

        // Calculate unrealized gains/losses and taxable vs tax-sheltered split
        $unrealizedGains = 0;
        $unrealizedLosses = 0;
        $totalCostBasis = 0;
        $totalCurrentValue = 0;
        $taxShelteredValue = 0;
        $taxableValue = 0;
        $totalPortfolioValue = 0;

        foreach ($investmentAccounts as $account) {
            $accountValue = (float) ($account->current_value ?? 0);
            $totalPortfolioValue += $accountValue;

            // Categorize by tax treatment
            if (in_array($account->account_type, ['isa', 'stocks_shares_isa', 'lifetime_isa', 'sipp', 'pension'])) {
                $taxShelteredValue += $accountValue;
            } else {
                $taxableValue += $accountValue;
            }

            // Skip ISA/pension accounts for CGT calculations (no CGT on gains)
            if (in_array($account->account_type, ['isa', 'stocks_shares_isa', 'lifetime_isa', 'sipp', 'pension'])) {
                continue;
            }

            foreach ($account->holdings as $holding) {
                if ($holding->cost_basis && $holding->current_value) {
                    $gainLoss = $holding->current_value - $holding->cost_basis;
                    $totalCostBasis += $holding->cost_basis;
                    $totalCurrentValue += $holding->current_value;

                    if ($gainLoss > 0) {
                        $unrealizedGains += $gainLoss;
                    } else {
                        $unrealizedLosses += abs($gainLoss);
                    }
                }
            }
        }

        // Calculate taxable percentage of total portfolio
        $taxablePercentage = $totalPortfolioValue > 0
            ? ($taxableValue / $totalPortfolioValue) * 100
            : 0;

        // Calculate annual dividend income
        $dividendIncome = $this->calculateDividendIncome($investmentAccounts);

        // Tax allowances from config
        $cgtAllowance = $cgtConfig['annual_exempt_amount'];
        $dividendAllowance = $dividendConfig['allowance'];

        return [
            'isa_allowance' => $isaAllowance,
            'isa_used' => $isaUsed,
            'isa_remaining' => $isaRemaining,
            'isa_utilization' => $isaAllowance > 0 ? ($isaUsed / $isaAllowance) * 100 : 0,
            'cgt_allowance' => $cgtAllowance,
            'unrealized_gains' => round($unrealizedGains, 2),
            'unrealized_losses' => round($unrealizedLosses, 2),
            'net_unrealized_gains' => round($unrealizedGains - $unrealizedLosses, 2),
            'total_cost_basis' => round($totalCostBasis, 2),
            'total_current_value' => round($totalCurrentValue, 2),
            'dividend_allowance' => $dividendAllowance,
            'annual_dividend_income' => round($dividendIncome, 2),
            'dividend_excess' => round(max(0, $dividendIncome - $dividendAllowance), 2),
            'tax_sheltered_value' => round($taxShelteredValue, 2),
            'taxable_value' => round($taxableValue, 2),
            'total_portfolio_value' => round($totalPortfolioValue, 2),
            'taxable_percentage' => round($taxablePercentage, 1),
        ];
    }

    /**
     * Calculate ISA allowance usage for current tax year
     *
     * @param  Collection  $investmentAccounts  Investment accounts
     * @param  Collection  $savingsAccounts  Savings accounts
     * @param  string  $taxYear  Tax year (e.g., "2024/25")
     * @return float ISA usage in £
     */
    private function calculateISAUsage(
        Collection $investmentAccounts,
        Collection $savingsAccounts,
        string $taxYear
    ): float {
        $usage = 0;

        // Parse tax year dates
        [$startYear] = explode('/', $taxYear);
        $taxYearStart = "{$startYear}-04-06";
        $taxYearEnd = ((int) $startYear + 1).'-04-05';

        // Investment ISAs - use actual subscription amount for current tax year
        foreach ($investmentAccounts as $account) {
            if (in_array($account->account_type, ['isa', 'stocks_shares_isa'])) {
                $usage += $account->isa_subscription_current_year ?? 0;
            }
        }

        // Cash ISAs from Savings module - use actual subscription for current tax year
        foreach ($savingsAccounts as $account) {
            if ($account->account_type === 'isa' || $account->account_type === 'cash_isa') {
                // Check if subscription is for current tax year
                if ($account->isa_subscription_year === $taxYear) {
                    $usage += $account->isa_subscription_amount ?? 0;
                }
            }
        }

        return $usage;
    }

    /**
     * Calculate annual dividend income
     *
     * @param  Collection  $investmentAccounts  Investment accounts
     * @return float Annual dividend income
     */
    private function calculateDividendIncome(Collection $investmentAccounts): float
    {
        $totalDividends = 0;

        foreach ($investmentAccounts as $account) {
            // Skip ISA accounts (dividends are tax-free)
            if (in_array($account->account_type, ['isa', 'stocks_shares_isa'])) {
                continue;
            }

            foreach ($account->holdings as $holding) {
                if ($holding->dividend_yield && $holding->current_value) {
                    $annualDividend = $holding->current_value * $holding->dividend_yield;
                    $totalDividends += $annualDividend;
                }
            }
        }

        return $totalDividends;
    }

    /**
     * Identify optimization opportunities
     *
     * @param  Collection  $investmentAccounts  Investment accounts
     * @param  array  $currentPosition  Current tax position
     * @param  string  $taxYear  Tax year
     * @return array Optimization opportunities
     */
    private function identifyOptimizationOpportunities(
        Collection $investmentAccounts,
        array $currentPosition,
        string $taxYear
    ): array {
        $opportunities = [];

        // 1. ISA Underutilization
        if ($currentPosition['isa_remaining'] > 0) {
            $opportunities[] = [
                'type' => 'isa_underutilization',
                'priority' => 'high',
                'title' => 'ISA Allowance Available',
                'description' => sprintf(
                    'You have £%s of unused ISA allowance for %s',
                    number_format($currentPosition['isa_remaining'], 2),
                    $taxYear
                ),
                'potential_saving' => $this->estimateISATaxSaving($currentPosition['isa_remaining']),
                'action' => 'Transfer or contribute to ISA',
                'details' => [
                    'remaining_allowance' => $currentPosition['isa_remaining'],
                    'utilization' => $currentPosition['isa_utilization'],
                ],
            ];
        }

        // 2. CGT Allowance Utilization
        if ($currentPosition['unrealized_gains'] > $currentPosition['cgt_allowance']) {
            $excessGains = $currentPosition['unrealized_gains'] - $currentPosition['cgt_allowance'];
            $opportunities[] = [
                'type' => 'cgt_excess_gains',
                'priority' => 'high',
                'title' => 'Potential CGT Liability',
                'description' => sprintf(
                    'Unrealized gains of £%s exceed CGT allowance by £%s',
                    number_format($currentPosition['unrealized_gains'], 2),
                    number_format($excessGains, 2)
                ),
                'potential_saving' => 0, // This is a warning, not a saving
                'action' => 'Consider staged realization or Bed & ISA',
                'details' => [
                    'unrealized_gains' => $currentPosition['unrealized_gains'],
                    'cgt_allowance' => $currentPosition['cgt_allowance'],
                    'excess_gains' => $excessGains,
                    'potential_tax' => $excessGains * (float) ($this->taxConfig->getCapitalGainsTax()['basic_rate'] ?? 0.10),
                ],
            ];
        }

        // 4. Dividend Tax Optimization
        if ($currentPosition['dividend_excess'] > 0) {
            $opportunities[] = [
                'type' => 'dividend_tax',
                'priority' => 'medium',
                'title' => 'Dividend Allowance Exceeded',
                'description' => sprintf(
                    'Dividend income of £%s exceeds allowance by £%s',
                    number_format($currentPosition['annual_dividend_income'], 2),
                    number_format($currentPosition['dividend_excess'], 2)
                ),
                'potential_saving' => $currentPosition['dividend_excess'] * (float) ($this->taxConfig->getDividendTax()['basic_rate'] ?? 0.0875),
                'action' => 'Consider moving dividend-paying assets to ISA',
                'details' => [
                    'dividend_income' => $currentPosition['annual_dividend_income'],
                    'dividend_allowance' => $currentPosition['dividend_allowance'],
                    'excess_dividends' => $currentPosition['dividend_excess'],
                    'estimated_tax' => $currentPosition['dividend_excess'] * (float) ($this->taxConfig->getDividendTax()['basic_rate'] ?? 0.0875),
                ],
            ];
        }

        // 5. Bed & ISA Opportunities
        $bedAndISAOpportunities = $this->identifyBedAndISAOpportunities(
            $investmentAccounts,
            $currentPosition
        );

        if (! empty($bedAndISAOpportunities)) {
            $opportunities[] = [
                'type' => 'bed_and_isa',
                'priority' => 'high',
                'title' => 'Bed & ISA Opportunity',
                'description' => sprintf(
                    'Transfer £%s from GIA to ISA using CGT allowance',
                    number_format($bedAndISAOpportunities['transferable_amount'], 2)
                ),
                'potential_saving' => $bedAndISAOpportunities['potential_annual_saving'],
                'action' => 'Execute Bed & ISA transaction',
                'details' => $bedAndISAOpportunities,
            ];
        }

        return $opportunities;
    }

    /**
     * Identify Bed & ISA opportunities
     *
     * @param  Collection  $investmentAccounts  Investment accounts
     * @param  array  $currentPosition  Current tax position
     * @return array Bed & ISA opportunities
     */
    private function identifyBedAndISAOpportunities(
        Collection $investmentAccounts,
        array $currentPosition
    ): array {
        if ($currentPosition['isa_remaining'] <= 0) {
            return [];
        }

        // Find GIA holdings with gains within CGT allowance
        $suitableHoldings = [];
        $totalTransferable = 0;

        foreach ($investmentAccounts as $account) {
            if ($account->account_type !== 'gia' && $account->account_type !== 'general') {
                continue;
            }

            foreach ($account->holdings as $holding) {
                if (! $holding->cost_basis || ! $holding->current_value) {
                    continue;
                }

                $gain = $holding->current_value - $holding->cost_basis;

                // Only consider holdings with gains within allowance
                if ($gain > 0 && $gain <= $currentPosition['cgt_allowance']) {
                    $suitableHoldings[] = [
                        'holding_id' => $holding->id,
                        'security_name' => $holding->security_name ?? $holding->ticker,
                        'current_value' => $holding->current_value,
                        'gain' => $gain,
                    ];

                    $totalTransferable += $holding->current_value;

                    // Stop if we've found enough to use ISA allowance
                    if ($totalTransferable >= $currentPosition['isa_remaining']) {
                        break 2;
                    }
                }
            }
        }

        if (empty($suitableHoldings)) {
            return [];
        }

        $transferableAmount = min($totalTransferable, $currentPosition['isa_remaining']);

        $cgtConfig = $this->taxConfig->getCapitalGainsTax();
        $dividendConfig = $this->taxConfig->getDividendTax();
        $cgtRate = (float) ($cgtConfig['basic_rate'] ?? 0.10);
        $dividendBasicRate = (float) ($dividendConfig['basic_rate'] ?? 0.0875);

        $annualGrowth = $transferableAmount * $this->getDefaultExpectedReturn();
        $annualDividends = $transferableAmount * 0.02;
        $cgtSaving = $annualGrowth * $cgtRate;
        $dividendTaxSaving = $annualDividends * $dividendBasicRate;

        return [
            'suitable_holdings' => $suitableHoldings,
            'transferable_amount' => round($transferableAmount, 2),
            'potential_annual_saving' => round($cgtSaving + $dividendTaxSaving, 2),
            'cgt_on_transfer' => 0, // Within allowance
        ];
    }

    /**
     * Estimate ISA tax saving
     *
     * @param  float  $amount  Amount to invest in ISA
     * @return float Estimated annual tax saving
     */
    private function estimateISATaxSaving(float $amount): float
    {
        $cgtConfig = $this->taxConfig->getCapitalGainsTax();
        $dividendConfig = $this->taxConfig->getDividendTax();
        $cgtRate = (float) ($cgtConfig['basic_rate'] ?? 0.10);
        $dividendBasicRate = (float) ($dividendConfig['basic_rate'] ?? 0.0875);

        $annualGrowth = $amount * $this->getDefaultExpectedReturn();
        $annualDividends = $amount * 0.02;

        // Tax savings
        $cgtSaving = $annualGrowth * $cgtRate;
        $dividendTaxSaving = $annualDividends * $dividendBasicRate;

        return round($cgtSaving + $dividendTaxSaving, 2);
    }

    /**
     * Generate tax recommendations
     *
     * @param  array  $opportunities  Identified opportunities
     * @return array Prioritized recommendations
     */
    private function generateTaxRecommendations(array $opportunities): array
    {
        $recommendations = [];

        // Sort by priority and potential saving
        usort($opportunities, function ($a, $b) {
            $priorityOrder = ['high' => 1, 'medium' => 2, 'low' => 3];
            $aPriority = $priorityOrder[$a['priority']] ?? 4;
            $bPriority = $priorityOrder[$b['priority']] ?? 4;

            if ($aPriority === $bPriority) {
                return $b['potential_saving'] <=> $a['potential_saving'];
            }

            return $aPriority <=> $bPriority;
        });

        foreach ($opportunities as $index => $opportunity) {
            $recommendations[] = [
                'rank' => $index + 1,
                'type' => $opportunity['type'],
                'priority' => $opportunity['priority'],
                'title' => $opportunity['title'],
                'action' => $opportunity['action'],
                'potential_saving' => $opportunity['potential_saving'],
                'description' => $opportunity['description'],
            ];
        }

        return $recommendations;
    }

    /**
     * Calculate potential tax savings
     *
     * @param  array  $opportunities  Optimization opportunities
     * @return array Potential savings breakdown
     */
    private function calculatePotentialSavings(array $opportunities): array
    {
        $totalSavings = 0;
        $savingsByType = [];

        foreach ($opportunities as $opportunity) {
            $totalSavings += $opportunity['potential_saving'];
            $type = $opportunity['type'];

            if (! isset($savingsByType[$type])) {
                $savingsByType[$type] = 0;
            }
            $savingsByType[$type] += $opportunity['potential_saving'];
        }

        return [
            'total_potential_savings' => round($totalSavings, 2),
            'savings_by_type' => $savingsByType,
            'timeframe' => 'annual',
        ];
    }

    /**
     * Calculate tax efficiency score (0-100)
     *
     * @param  array  $currentPosition  Current tax position
     * @param  array  $opportunities  Optimization opportunities
     * @return array Tax efficiency score and breakdown
     */
    private function calculateTaxOptimizationGrade(array $currentPosition, array $opportunities): array
    {
        $score = 100;
        $deductions = [];

        // ISA utilization (max 30 points deduction)
        $isaUtilization = $currentPosition['isa_utilization'];
        if ($isaUtilization < 100) {
            $deduction = (100 - $isaUtilization) * 0.30;
            $score -= $deduction;
            $deductions[] = [
                'reason' => 'ISA underutilization',
                'points' => round($deduction, 1),
            ];
        }

        // Tax-loss harvesting opportunities (max 20 points deduction)
        if ($currentPosition['unrealized_losses'] > 0) {
            $deduction = min(20, ($currentPosition['unrealized_losses'] / 10000) * 20);
            $score -= $deduction;
            $deductions[] = [
                'reason' => 'Unharvested tax losses',
                'points' => round($deduction, 1),
            ];
        }

        // Excess dividend income (max 25 points deduction)
        if ($currentPosition['dividend_excess'] > 0) {
            $deduction = min(25, ($currentPosition['dividend_excess'] / 5000) * 25);
            $score -= $deduction;
            $deductions[] = [
                'reason' => 'Excess dividend income',
                'points' => round($deduction, 1),
            ];
        }

        // Bed & ISA opportunities (max 25 points deduction)
        $bedAndISAOpp = collect($opportunities)->firstWhere('type', 'bed_and_isa');
        if ($bedAndISAOpp) {
            $deduction = min(25, ($bedAndISAOpp['potential_saving'] / 1000) * 25);
            $score -= $deduction;
            $deductions[] = [
                'reason' => 'Missed Bed & ISA opportunities',
                'points' => round($deduction, 1),
            ];
        }

        // High proportion in taxable accounts (max 40 points deduction)
        // Penalize having significant assets in GIA when ISA space may be available
        $taxablePercentage = $currentPosition['taxable_percentage'] ?? 0;
        if ($taxablePercentage > 10) {
            // Deduct 1 point for each 2.5% above 10% in taxable accounts
            $deduction = min(40, (($taxablePercentage - 10) / 2.5) * 1);
            $score -= $deduction;
            $deductions[] = [
                'reason' => sprintf('%.0f%% of portfolio in taxable accounts', $taxablePercentage),
                'points' => round($deduction, 1),
            ];
        }

        $score = max(0, $score);

        return [
            'score' => round($score, 1),
            'grade' => $this->getEfficiencyGrade($score),
            'deductions' => $deductions,
            'interpretation' => $this->getScoreInterpretation($score),
        ];
    }

    /**
     * Get efficiency grade from score
     *
     * @param  float  $score  Efficiency score
     * @return string Grade (A-F)
     */
    private function getEfficiencyGrade(float $score): string
    {
        if ($score >= 90) {
            return 'A';
        }
        if ($score >= 80) {
            return 'B';
        }
        if ($score >= 70) {
            return 'C';
        }
        if ($score >= 60) {
            return 'D';
        }
        if ($score >= 50) {
            return 'E';
        }

        return 'F';
    }

    /**
     * Get score interpretation
     *
     * @param  float  $score  Efficiency score
     * @return string Interpretation
     */
    private function getScoreInterpretation(float $score): string
    {
        if ($score >= 90) {
            return 'Excellent tax efficiency. Your portfolio is well-optimized for UK tax rules.';
        }
        if ($score >= 80) {
            return 'Good tax efficiency. Minor improvements could save additional tax.';
        }
        if ($score >= 70) {
            return 'Moderate tax efficiency. Several opportunities for tax savings identified.';
        }
        if ($score >= 60) {
            return 'Below average tax efficiency. Significant tax savings available.';
        }

        return 'Poor tax efficiency. Immediate action recommended to reduce tax liability.';
    }

    /**
     * Generate summary text
     *
     * @param  array  $currentPosition  Current tax position
     * @param  array  $opportunities  Optimization opportunities
     * @param  array  $potentialSavings  Potential savings
     * @return string Summary text
     */
    private function generateSummary(array $currentPosition, array $opportunities, array $potentialSavings): string
    {
        $parts = [];

        // ISA usage
        $parts[] = sprintf(
            'ISA allowance: £%s used of £%s (%.1f%%)',
            number_format($currentPosition['isa_used'], 0),
            number_format($currentPosition['isa_allowance'], 0),
            $currentPosition['isa_utilization']
        );

        // Opportunities count
        $opportunityCount = count($opportunities);
        if ($opportunityCount > 0) {
            $parts[] = sprintf(
                '%d tax optimization opportunity/opportunities identified',
                $opportunityCount
            );
        }

        // Potential savings
        if ($potentialSavings['total_potential_savings'] > 0) {
            $parts[] = sprintf(
                'Potential annual tax saving: £%s',
                number_format($potentialSavings['total_potential_savings'], 2)
            );
        }

        return implode('. ', $parts).'.';
    }

    /**
     * Get current UK tax year.
     */
    private function getCurrentTaxYear(): string
    {
        return $this->taxConfig->getTaxYear();
    }
}
