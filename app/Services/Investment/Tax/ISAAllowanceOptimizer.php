<?php

declare(strict_types=1);

namespace App\Services\Investment\Tax;

use App\Models\Investment\Holding;
use App\Models\Investment\InvestmentAccount;
use App\Models\SavingsAccount;
use App\Services\Risk\RiskPreferenceService;
use App\Services\TaxConfigService;
use Illuminate\Support\Collection;

/**
 * ISA Allowance Optimizer
 * Recommends optimal ISA contribution and transfer strategies
 * Uses active tax year rates from TaxConfigService
 *
 * UK ISA Rules:
 * - Annual allowance varies by tax year
 * - Tax year: April 6 to April 5
 * - Can split between Cash ISA and Stocks & Shares ISA
 * - LISA: counts towards total allowance
 * - Junior ISA: separate allowance
 * - Tax benefits: No income tax on interest/dividends, no CGT on gains
 */
class ISAAllowanceOptimizer
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
     * Calculate optimal ISA contribution strategy
     *
     * @param  int  $userId  User ID
     * @param  array  $options  Options (target_contribution, risk_tolerance)
     * @return array ISA optimization recommendations
     */
    public function calculateOptimalStrategy(int $userId, array $options = []): array
    {
        $taxYear = $this->getCurrentTaxYear();

        // Get ISA allowance from tax config
        $isaConfig = $this->taxConfig->getISAAllowances();
        $annualAllowance = $isaConfig['annual_allowance'];

        // Get current ISA usage
        $currentUsage = $this->calculateAllowanceUsage($userId, $taxYear);

        // Get available funds
        $availableFunds = $options['available_funds'] ?? null;
        $monthlyContribution = $options['monthly_contribution'] ?? null;

        // Calculate remaining allowance
        $remainingAllowance = max(0, $annualAllowance - $currentUsage['total_used']);

        // Get GIA holdings (candidates for transfer)
        $giaHoldings = $this->getGIAHoldings($userId);

        // Generate transfer recommendations
        $transferRecommendations = $this->generateTransferRecommendations(
            $giaHoldings,
            $remainingAllowance,
            $options
        );

        // Generate contribution recommendations
        $contributionRecommendations = $this->generateContributionRecommendations(
            $remainingAllowance,
            $availableFunds,
            $monthlyContribution,
            $taxYear
        );

        // Calculate tax savings
        $potentialSavings = $this->calculatePotentialSavings(
            $remainingAllowance,
            $options
        );

        return [
            'success' => true,
            'tax_year' => $taxYear,
            'allowance' => [
                'total' => $annualAllowance,
                'used' => $currentUsage['total_used'],
                'remaining' => $remainingAllowance,
                'utilization_percent' => ($currentUsage['total_used'] / $annualAllowance) * 100,
            ],
            'current_usage' => $currentUsage,
            'transfer_recommendations' => $transferRecommendations,
            'contribution_recommendations' => $contributionRecommendations,
            'potential_savings' => $potentialSavings,
            'priority_actions' => $this->prioritizeActions(
                $transferRecommendations,
                $contributionRecommendations,
                $remainingAllowance
            ),
        ];
    }

    /**
     * Calculate current ISA usage for tax year
     *
     * @param  int  $userId  User ID
     * @param  string  $taxYear  Tax year
     * @return array Current ISA usage
     */
    public function calculateAllowanceUsage(int $userId, string $taxYear): array
    {
        $investmentISAs = InvestmentAccount::where('user_id', $userId)
            ->whereIn('account_type', ['isa', 'stocks_shares_isa', 'lifetime_isa'])
            ->get();

        $savingsISAs = SavingsAccount::where('user_id', $userId)
            ->whereIn('account_type', ['isa', 'cash_isa', 'lifetime_isa'])
            ->get();

        $stocksAndSharesISA = 0;
        $cashISA = 0;
        $lifetimeISA = 0;

        // Investment ISAs
        foreach ($investmentISAs as $account) {
            $value = $account->current_value ?? 0;

            if ($account->account_type === 'lifetime_isa') {
                $lifetimeISA += $value;
            } else {
                $stocksAndSharesISA += $value;
            }
        }

        // Savings ISAs
        foreach ($savingsISAs as $account) {
            $value = $account->current_balance ?? 0;

            if ($account->account_type === 'lifetime_isa') {
                $lifetimeISA += $value;
            } else {
                $cashISA += $value;
            }
        }

        // LISA contributions count towards total allowance
        $totalUsed = $stocksAndSharesISA + $cashISA + $lifetimeISA;

        return [
            'stocks_and_shares_isa' => round($stocksAndSharesISA, 2),
            'cash_isa' => round($cashISA, 2),
            'lifetime_isa' => round($lifetimeISA, 2),
            'total_used' => round($totalUsed, 2),
            'breakdown' => [
                'investment_accounts' => $investmentISAs->count(),
                'savings_accounts' => $savingsISAs->count(),
            ],
        ];
    }

    /**
     * Get GIA holdings suitable for ISA transfer
     *
     * @param  int  $userId  User ID
     * @return Collection GIA holdings
     */
    private function getGIAHoldings(int $userId): Collection
    {
        return InvestmentAccount::where('user_id', $userId)
            ->whereIn('account_type', ['gia', 'general'])
            ->with('holdings')
            ->get()
            ->flatMap->holdings;
    }

    /**
     * Generate transfer recommendations (GIA → ISA)
     *
     * @param  Collection  $giaHoldings  GIA holdings
     * @param  float  $remainingAllowance  Remaining ISA allowance
     * @param  array  $options  Options
     * @return array Transfer recommendations
     */
    private function generateTransferRecommendations(
        Collection $giaHoldings,
        float $remainingAllowance,
        array $options
    ): array {
        if ($giaHoldings->isEmpty() || $remainingAllowance <= 0) {
            return [];
        }

        $recommendations = [];
        $cumulativeValue = 0;

        // Prioritize high-dividend and high-growth holdings
        $prioritizedHoldings = $giaHoldings->sortByDesc(function ($holding) {
            // Score based on dividend yield and unrealized gains
            $dividendScore = ($holding->dividend_yield ?? 0) * 10;

            $gainScore = 0;
            if ($holding->cost_basis && $holding->current_value) {
                $gain = $holding->current_value - $holding->cost_basis;
                $gainPercent = ($gain / $holding->cost_basis) * 100;
                $gainScore = $gainPercent;
            }

            return $dividendScore + ($gainScore * 0.5);
        });

        foreach ($prioritizedHoldings as $holding) {
            if ($cumulativeValue >= $remainingAllowance) {
                break;
            }

            $transferValue = min(
                $holding->current_value,
                $remainingAllowance - $cumulativeValue
            );

            $cgtConfig = $this->taxConfig->getCapitalGainsTax();
            $dividendConfig = $this->taxConfig->getDividendTax();
            $cgtRate = (float) ($cgtConfig['higher_rate'] ?? 0.20);
            $dividendBasicRate = (float) ($dividendConfig['basic_rate'] ?? 0.0875);

            $annualDividend = ($holding->dividend_yield ?? 0) * $transferValue;
            $estimatedAnnualGrowth = $transferValue * $this->getDefaultExpectedReturn();

            // Tax savings
            $dividendTaxSaving = $annualDividend * $dividendBasicRate;
            $cgtSaving = $estimatedAnnualGrowth * $cgtRate;

            $recommendations[] = [
                'holding_id' => $holding->id,
                'security_name' => $holding->security_name ?? $holding->ticker,
                'ticker' => $holding->ticker,
                'current_value' => $holding->current_value,
                'transfer_value' => round($transferValue, 2),
                'dividend_yield' => $holding->dividend_yield,
                'annual_dividend' => round($annualDividend, 2),
                'estimated_annual_growth' => round($estimatedAnnualGrowth, 2),
                'annual_tax_saving' => round($dividendTaxSaving + $cgtSaving, 2),
                'priority' => $this->calculateTransferPriority($holding, $dividendTaxSaving + $cgtSaving),
                'rationale' => $this->generateTransferRationale($holding, $dividendTaxSaving + $cgtSaving),
            ];

            $cumulativeValue += $transferValue;
        }

        return $recommendations;
    }

    /**
     * Calculate transfer priority
     *
     * @param  Holding  $holding  Holding
     * @param  float  $taxSaving  Annual tax saving
     * @return string Priority (high, medium, low)
     */
    private function calculateTransferPriority(Holding $holding, float $taxSaving): string
    {
        // High priority: High dividend yield or significant tax saving
        if (($holding->dividend_yield ?? 0) > 0.04 || $taxSaving > 500) {
            return 'high';
        }

        // Medium priority: Moderate dividend or tax saving
        if (($holding->dividend_yield ?? 0) > 0.02 || $taxSaving > 200) {
            return 'medium';
        }

        return 'low';
    }

    /**
     * Generate transfer rationale
     *
     * @param  Holding  $holding  Holding
     * @param  float  $taxSaving  Annual tax saving
     * @return string Rationale
     */
    private function generateTransferRationale(Holding $holding, float $taxSaving): string
    {
        $reasons = [];

        if (($holding->dividend_yield ?? 0) > 0.03) {
            $reasons[] = sprintf(
                'High dividend yield (%.2f%%) - save dividend tax',
                ($holding->dividend_yield ?? 0) * 100
            );
        }

        if ($taxSaving > 300) {
            $reasons[] = sprintf(
                'Significant tax saving: £%s/year',
                number_format($taxSaving, 2)
            );
        }

        if ($holding->cost_basis && $holding->current_value) {
            $gain = $holding->current_value - $holding->cost_basis;
            $gainPercent = ($gain / $holding->cost_basis) * 100;

            if ($gainPercent > 20) {
                $reasons[] = sprintf(
                    'Strong growth (%.1f%%) - protect from CGT',
                    $gainPercent
                );
            }
        }

        if (empty($reasons)) {
            $reasons[] = 'Utilize ISA tax benefits';
        }

        return implode('. ', $reasons);
    }

    /**
     * Generate contribution recommendations
     *
     * @param  float  $remainingAllowance  Remaining ISA allowance
     * @param  float|null  $availableFunds  Available funds
     * @param  float|null  $monthlyContribution  Monthly contribution capacity
     * @param  string  $taxYear  Tax year
     * @return array Contribution recommendations
     */
    private function generateContributionRecommendations(
        float $remainingAllowance,
        ?float $availableFunds,
        ?float $monthlyContribution,
        string $taxYear
    ): array {
        if ($remainingAllowance <= 0) {
            return [
                'can_contribute' => false,
                'message' => 'ISA allowance fully utilized for this tax year',
            ];
        }

        $recommendations = [];

        // Lump sum recommendation
        if ($availableFunds && $availableFunds > 0) {
            $lumpSumAmount = min($availableFunds, $remainingAllowance);

            $recommendations['lump_sum'] = [
                'recommended_amount' => round($lumpSumAmount, 2),
                'available_funds' => $availableFunds,
                'rationale' => $this->getLumpSumRationale($lumpSumAmount, $remainingAllowance),
                'estimated_annual_saving' => round($lumpSumAmount * 0.08 * 0.2, 2), // 8% return, 20% tax
            ];
        }

        // Monthly contribution recommendation
        if ($monthlyContribution && $monthlyContribution > 0) {
            $monthsRemaining = $this->getMonthsRemainingInTaxYear($taxYear);
            $maxMonthly = $remainingAllowance / max(1, $monthsRemaining);
            $recommendedMonthly = min($monthlyContribution, $maxMonthly);

            $recommendations['monthly'] = [
                'recommended_amount' => round($recommendedMonthly, 2),
                'months_remaining' => $monthsRemaining,
                'total_contribution' => round($recommendedMonthly * $monthsRemaining, 2),
                'rationale' => $this->getMonthlyRationale($recommendedMonthly, $maxMonthly),
            ];
        }

        // End of tax year urgency
        $monthsRemaining = $this->getMonthsRemainingInTaxYear($taxYear);
        if ($monthsRemaining <= 2 && $remainingAllowance > 0) {
            $recommendations['urgency'] = [
                'level' => 'high',
                'message' => sprintf(
                    'Only %d month%s remaining to use £%s ISA allowance',
                    $monthsRemaining,
                    $monthsRemaining === 1 ? '' : 's',
                    number_format($remainingAllowance, 0)
                ),
                'deadline' => $this->getTaxYearEnd($taxYear),
            ];
        }

        $recommendations['can_contribute'] = true;

        return $recommendations;
    }

    /**
     * Get lump sum contribution rationale
     *
     * @param  float  $amount  Lump sum amount
     * @param  float  $remainingAllowance  Remaining allowance
     * @return string Rationale
     */
    private function getLumpSumRationale(float $amount, float $remainingAllowance): string
    {
        if ($amount >= $remainingAllowance) {
            return sprintf(
                'Maximize ISA allowance with £%s lump sum contribution',
                number_format($amount, 0)
            );
        }

        return sprintf(
            'Contribute £%s now - leaving £%s allowance for monthly contributions',
            number_format($amount, 0),
            number_format($remainingAllowance - $amount, 0)
        );
    }

    /**
     * Get monthly contribution rationale
     *
     * @param  float  $recommended  Recommended monthly amount
     * @param  float  $maximum  Maximum monthly amount
     * @return string Rationale
     */
    private function getMonthlyRationale(float $recommended, float $maximum): string
    {
        if ($recommended >= $maximum) {
            return sprintf(
                'Contribute £%s/month to fully utilize remaining allowance',
                number_format($recommended, 0)
            );
        }

        return sprintf(
            'Contribute £%s/month at current savings rate',
            number_format($recommended, 0)
        );
    }

    /**
     * Calculate potential tax savings
     *
     * @param  float  $remainingAllowance  Remaining ISA allowance
     * @param  array  $options  Options
     * @return array Potential savings
     */
    private function calculatePotentialSavings(float $remainingAllowance, array $options): array
    {
        if ($remainingAllowance <= 0) {
            return [
                'annual_saving' => 0,
                'five_year_saving' => 0,
                'ten_year_saving' => 0,
            ];
        }

        // Assumptions (sourced from TaxConfigService with fallbacks)
        $cgtConfig = $this->taxConfig->getCapitalGainsTax();
        $dividendConfig = $this->taxConfig->getDividendTax();
        $annualReturn = $options['expected_return'] ?? $this->getDefaultExpectedReturn();
        $dividendYield = $options['dividend_yield'] ?? 0.02; // 2% dividend yield
        $taxRate = $options['tax_rate'] ?? (float) ($cgtConfig['higher_rate'] ?? 0.20);
        $dividendBasicRate = (float) ($dividendConfig['basic_rate'] ?? 0.0875);

        // Annual savings
        $annualGrowth = $remainingAllowance * $annualReturn;
        $annualDividends = $remainingAllowance * $dividendYield;
        $cgtSaving = $annualGrowth * $taxRate;
        $dividendTaxSaving = $annualDividends * $dividendBasicRate;
        $annualSaving = $cgtSaving + $dividendTaxSaving;

        // Compound over time (simplified - assumes all savings reinvested)
        $fiveYearSaving = $annualSaving * 5 * 1.15; // Account for compounding
        $tenYearSaving = $annualSaving * 10 * 1.30;

        return [
            'annual_saving' => round($annualSaving, 2),
            'five_year_saving' => round($fiveYearSaving, 2),
            'ten_year_saving' => round($tenYearSaving, 2),
            'assumptions' => [
                'annual_return' => $annualReturn * 100,
                'dividend_yield' => $dividendYield * 100,
                'tax_rate' => $taxRate * 100,
            ],
        ];
    }

    /**
     * Prioritize actions
     *
     * @param  array  $transfers  Transfer recommendations
     * @param  array  $contributions  Contribution recommendations
     * @param  float  $remainingAllowance  Remaining allowance
     * @return array Priority actions
     */
    private function prioritizeActions(array $transfers, array $contributions, float $remainingAllowance): array
    {
        $actions = [];

        // Priority 1: High-priority transfers
        $highPriorityTransfers = collect($transfers)->where('priority', 'high')->take(3);
        foreach ($highPriorityTransfers as $transfer) {
            $actions[] = [
                'priority' => 1,
                'type' => 'transfer',
                'action' => sprintf('Transfer %s to ISA', $transfer['security_name']),
                'value' => $transfer['transfer_value'],
                'annual_saving' => $transfer['annual_tax_saving'],
            ];
        }

        // Priority 2: Lump sum if significant
        if (isset($contributions['lump_sum']) && $contributions['lump_sum']['recommended_amount'] > 5000) {
            $actions[] = [
                'priority' => 2,
                'type' => 'lump_sum',
                'action' => sprintf(
                    'Contribute £%s lump sum',
                    number_format($contributions['lump_sum']['recommended_amount'], 0)
                ),
                'value' => $contributions['lump_sum']['recommended_amount'],
                'annual_saving' => $contributions['lump_sum']['estimated_annual_saving'],
            ];
        }

        // Priority 3: Monthly contributions
        if (isset($contributions['monthly'])) {
            $actions[] = [
                'priority' => 3,
                'type' => 'monthly',
                'action' => sprintf(
                    'Set up £%s monthly contribution',
                    number_format($contributions['monthly']['recommended_amount'], 0)
                ),
                'value' => $contributions['monthly']['total_contribution'],
                'annual_saving' => null,
            ];
        }

        // Sort by priority
        usort($actions, fn ($a, $b) => $a['priority'] <=> $b['priority']);

        return $actions;
    }

    /**
     * Get months remaining in tax year
     *
     * @param  string  $taxYear  Tax year (e.g., "2024/25")
     * @return int Months remaining
     */
    private function getMonthsRemainingInTaxYear(string $taxYear): int
    {
        $taxYearEnd = $this->getTaxYearEnd($taxYear);
        $now = new \DateTime;
        $end = new \DateTime($taxYearEnd);

        $interval = $now->diff($end);
        $monthsRemaining = ($interval->y * 12) + $interval->m;

        // If we're in the last partial month, count it
        if ($interval->d > 0) {
            $monthsRemaining++;
        }

        return max(0, $monthsRemaining);
    }

    /**
     * Get tax year end date
     *
     * @param  string  $taxYear  Tax year (e.g., "2024/25")
     * @return string Tax year end date (Y-m-d)
     */
    private function getTaxYearEnd(string $taxYear): string
    {
        [$startYear] = explode('/', $taxYear);
        $endYear = (int) $startYear + 1;

        return "{$endYear}-04-05";
    }

    /**
     * Get current UK tax year.
     */
    private function getCurrentTaxYear(): string
    {
        return $this->taxConfig->getTaxYear();
    }
}
