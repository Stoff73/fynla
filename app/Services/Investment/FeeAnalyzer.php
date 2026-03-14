<?php

declare(strict_types=1);

namespace App\Services\Investment;

use App\Constants\InvestmentDefaults;
use App\Models\Investment\InvestmentAccount;
use App\Services\Risk\RiskPreferenceService;
use App\Services\TaxConfigService;
use App\Traits\CalculatesOCF;
use Illuminate\Support\Collection;

/**
 * Fee Analyzer
 * Calculates total fees (platform fees, fund OCF, transaction costs) and their impact
 *
 * Consolidated from:
 * - App\Services\Investment\FeeAnalyzer (collection-based methods)
 * - App\Services\Investment\Fees\FeeAnalyzer (comprehensive analysis)
 *
 * Fee Types:
 * - Platform fees (% of portfolio value)
 * - Fund OCF (Ongoing Charges Figure)
 * - Transaction costs (buying/selling)
 * - Advisory fees (if applicable)
 */
class FeeAnalyzer
{
    use CalculatesOCF;

    public function __construct(
        private readonly RiskPreferenceService $riskPreferenceService,
        private readonly TaxConfigService $taxConfig
    ) {}

    /**
     * Get default expected return from risk preference service
     */
    private function getDefaultExpectedReturn(): float
    {
        return $this->riskPreferenceService->getReturnParameters('medium')['expected_return_typical'] / 100;
    }

    // =========================================================================
    // COLLECTION-BASED METHODS (for use with pre-fetched data)
    // =========================================================================

    /**
     * Calculate total fees across all accounts and holdings
     */
    public function calculateTotalFees(Collection $accounts, Collection $holdings): array
    {
        $portfolioValue = $accounts->sum('current_value');

        if ($portfolioValue == 0) {
            return [
                'total_annual_fees' => 0.0,
                'fee_breakdown' => [],
                'fee_drag_percent' => 0.0,
            ];
        }

        // Calculate platform fees
        $platformFees = $accounts->sum(function ($account) {
            if ($account->platform_fee_type === 'fixed') {
                $amount = (float) ($account->platform_fee_amount ?? 0);
                $frequency = $account->platform_fee_frequency ?? 'annually';
                if ($frequency === 'monthly') {
                    return $amount * 12;
                }
                if ($frequency === 'quarterly') {
                    return $amount * 4;
                }

                return $amount;
            }

            return $account->current_value * (($account->platform_fee_percent ?? 0) / 100);
        });

        // Calculate fund OCF (Ongoing Charges Figure)
        $fundFees = $holdings->sum(function ($holding) {
            return $holding->current_value * (($holding->ocf_percent ?? 0) / 100);
        });

        // Estimated transaction costs
        $transactionCosts = $this->estimateTransactionCosts($portfolioValue, 0.10);

        $totalFees = $platformFees + $fundFees + $transactionCosts;
        $feeDragPercent = ($totalFees / $portfolioValue) * 100;

        return [
            'portfolio_value' => round($portfolioValue, 2),
            'total_annual_fees' => round($totalFees, 2),
            'fee_breakdown' => [
                [
                    'type' => 'Platform Fees',
                    'amount' => round($platformFees, 2),
                    'percent_of_portfolio' => round(($platformFees / $portfolioValue) * 100, 4),
                ],
                [
                    'type' => 'Fund Charges (OCF)',
                    'amount' => round($fundFees, 2),
                    'percent_of_portfolio' => round(($fundFees / $portfolioValue) * 100, 4),
                ],
                [
                    'type' => 'Transaction Costs',
                    'amount' => round($transactionCosts, 2),
                    'percent_of_portfolio' => round(($transactionCosts / $portfolioValue) * 100, 4),
                ],
            ],
            'fee_drag_percent' => round($feeDragPercent, 4),
            'fees_over_10_years' => round($totalFees * 10, 2),
            'fees_over_20_years' => round($totalFees * 20, 2),
        ];
    }

    /**
     * Calculate fee drag on returns (simple version)
     */
    public function calculateSimpleFeeDrag(float $totalFees, float $portfolioValue): float
    {
        if ($portfolioValue == 0) {
            return 0;
        }

        return round(($totalFees / $portfolioValue) * 100, 4);
    }

    /**
     * Compare to low-cost alternatives
     */
    public function compareToLowCostAlternatives(Collection $holdings): array
    {
        $totalValue = $holdings->sum('current_value');

        if ($totalValue == 0) {
            return [
                'current_ocf' => 0.0,
                'low_cost_ocf' => 0.0,
                'annual_saving' => 0.0,
            ];
        }

        // Calculate current weighted average OCF
        $currentOCF = $holdings->sum(function ($holding) use ($totalValue) {
            return ($holding->current_value / $totalValue) * ($holding->ocf_percent ?? 0);
        });

        // Low-cost index funds OCF threshold from config
        $feeBenchmarks = $this->taxConfig->get('investment.fee_benchmarks', []);
        $lowCostOCF = ($feeBenchmarks['low_cost_ocf'] ?? 0.0015) * 100; // Convert decimal to percent

        $currentAnnualCost = $totalValue * ($currentOCF / 100);
        $lowCostAnnualCost = $totalValue * ($lowCostOCF / 100);
        $annualSaving = $currentAnnualCost - $lowCostAnnualCost;

        return [
            'current_average_ocf' => round($currentOCF, 4),
            'low_cost_average_ocf' => $lowCostOCF,
            'current_annual_cost' => round($currentAnnualCost, 2),
            'low_cost_annual_cost' => round($lowCostAnnualCost, 2),
            'annual_saving' => round($annualSaving, 2),
            'ten_year_saving' => round($annualSaving * 10, 2),
            'recommendation' => $annualSaving > 100 ? 'Consider switching to lower-cost funds' : 'Fees are competitive',
        ];
    }

    /**
     * Identify high-fee holdings
     *
     * Note: Advisory fees are excluded from the threshold comparison because they
     * represent a conscious choice to pay for financial advice and shouldn't
     * trigger high-fee warnings for platform/fund costs.
     *
     * @param  Collection  $holdings  Holdings to analyze
     * @param  float|null  $advisoryFeePercent  Advisory fee to exclude from threshold comparison
     */
    public function identifyHighFeeHoldings(Collection $holdings, ?float $advisoryFeePercent = null): array
    {
        $highFeeThreshold = InvestmentDefaults::HIGH_OCF_THRESHOLD_PERCENT; // Excluding advisory fees

        $highFeeHoldings = $holdings->filter(function ($holding) use ($highFeeThreshold, $advisoryFeePercent) {
            // Calculate fees excluding advisory fee for threshold comparison
            $feesForComparison = $holding->ocf_percent ?? 0;
            if ($advisoryFeePercent !== null && $advisoryFeePercent > 0) {
                // Don't let advisory fee reduction push below zero
                $feesForComparison = max(0, $feesForComparison);
            }

            return $feesForComparison > $highFeeThreshold;
        })->map(function ($holding) {
            return [
                'security_name' => $holding->security_name,
                'ocf_percent' => round($holding->ocf_percent ?? 0, 4),
                'current_value' => round($holding->current_value, 2),
                'annual_cost' => round($holding->current_value * (($holding->ocf_percent ?? 0) / 100), 2),
                'recommendation' => 'Consider lower-cost alternative',
            ];
        })->values()->toArray();

        return [
            'high_fee_count' => count($highFeeHoldings),
            'holdings' => $highFeeHoldings,
            'total_value_in_high_fee_funds' => round(array_sum(array_column($highFeeHoldings, 'current_value')), 2),
        ];
    }

    /**
     * Assess fee level using simple tier system (excluding advisory fees)
     *
     * Tiers:
     * - < 0.8%: Acceptable (no warning)
     * - 0.8% - 1.0%: Higher than average
     * - 1.0% - 1.5%: High
     * - > 1.5%: Much higher than average
     *
     * @param  float  $totalFeePercent  Total fee percentage
     * @param  float|null  $advisoryFeePercent  Advisory fee to exclude from assessment
     */
    public function assessFeeTier(float $totalFeePercent, ?float $advisoryFeePercent = null): array
    {
        // Exclude advisory fees from assessment
        $feesForAssessment = $totalFeePercent;
        if ($advisoryFeePercent !== null && $advisoryFeePercent > 0) {
            $feesForAssessment = max(0, $totalFeePercent - $advisoryFeePercent);
        }

        if ($feesForAssessment < 0.8) {
            return [
                'level' => 'acceptable',
                'message' => null,
                'fee_assessed' => round($feesForAssessment, 3),
                'advisory_excluded' => $advisoryFeePercent ?? 0,
            ];
        } elseif ($feesForAssessment <= 1.0) {
            return [
                'level' => 'higher_than_average',
                'message' => 'Your fees are higher than average',
                'fee_assessed' => round($feesForAssessment, 3),
                'advisory_excluded' => $advisoryFeePercent ?? 0,
            ];
        } elseif ($feesForAssessment <= 1.5) {
            return [
                'level' => 'high',
                'message' => 'Your fees are high',
                'fee_assessed' => round($feesForAssessment, 3),
                'advisory_excluded' => $advisoryFeePercent ?? 0,
            ];
        } else {
            return [
                'level' => 'very_high',
                'message' => 'Your fees are much higher than average',
                'fee_assessed' => round($feesForAssessment, 3),
                'advisory_excluded' => $advisoryFeePercent ?? 0,
            ];
        }
    }

    // =========================================================================
    // DATABASE-DRIVEN METHODS (for direct user analysis)
    // =========================================================================

    /**
     * Analyze fees for user's entire investment portfolio
     *
     * @param  int  $userId  User ID
     * @return array Fee analysis
     */
    public function analyzePortfolioFees(int $userId): array
    {
        $accounts = InvestmentAccount::where('user_id', $userId)
            ->with('holdings')
            ->get();

        if ($accounts->isEmpty()) {
            return [
                'success' => false,
                'message' => 'No investment accounts found',
            ];
        }

        $totalValue = 0;
        $totalAnnualFees = 0;
        $accountAnalyses = [];

        foreach ($accounts as $account) {
            $analysis = $this->analyzeAccountFees($account);

            if ($analysis['success']) {
                $accountAnalyses[] = $analysis;
                $totalValue += $analysis['account_value'];
                $totalAnnualFees += $analysis['total_annual_fees'];
            }
        }

        if ($totalValue == 0) {
            return [
                'success' => false,
                'message' => 'No portfolio value to analyze',
            ];
        }

        $weightedAverageFeePercent = ($totalAnnualFees / $totalValue) * 100;

        // Calculate fee drag over time
        $feeDrag = $this->calculateFeeDrag($totalValue, $weightedAverageFeePercent, 10, $this->getDefaultExpectedReturn());

        // Industry benchmarks
        $benchmark = $this->getBenchmarkFees($totalValue);

        return [
            'success' => true,
            'total_portfolio_value' => $totalValue,
            'total_annual_fees' => $totalAnnualFees,
            'average_fee_percent' => round($weightedAverageFeePercent, 3),
            'accounts' => $accountAnalyses,
            'fee_drag' => $feeDrag,
            'benchmark' => $benchmark,
            'assessment' => $this->assessFeeLevel($weightedAverageFeePercent, $benchmark),
            'potential_savings' => $this->calculatePotentialSavings($totalAnnualFees, $benchmark, $totalValue),
        ];
    }

    /**
     * Analyze fees for a single investment account
     *
     * @param  InvestmentAccount  $account  Investment account
     * @return array Fee analysis
     */
    public function analyzeAccountFees(InvestmentAccount $account): array
    {
        $holdings = $account->holdings;

        // Use holdings value if available, otherwise use account's current_value
        $accountValue = $holdings->isNotEmpty()
            ? $holdings->sum('current_value')
            : (float) ($account->current_value ?? 0);

        if ($accountValue == 0) {
            return [
                'success' => false,
                'message' => 'Account has zero value',
            ];
        }

        // Calculate platform fee from user-entered values (same logic as calculateTotalFees)
        if ($account->platform_fee_type === 'fixed') {
            $amount = (float) ($account->platform_fee_amount ?? 0);
            $frequency = $account->platform_fee_frequency ?? 'annually';
            if ($frequency === 'monthly') {
                $platformFee = $amount * 12;
            } elseif ($frequency === 'quarterly') {
                $platformFee = $amount * 4;
            } else {
                $platformFee = $amount;
            }
        } else {
            // Percentage-based platform fee
            $platformFee = $accountValue * (($account->platform_fee_percent ?? 0) / 100);
        }

        // Calculate weighted average OCF (will be 0 if no holdings)
        $weightedOCF = $holdings->isNotEmpty()
            ? $this->calculateWeightedOCF($holdings, $accountValue)
            : 0;

        // Calculate transaction costs (estimated) - only if holdings exist
        $transactionCosts = $holdings->isNotEmpty()
            ? $this->estimateTransactionCosts($accountValue, $account->turnover_rate ?? 0.10, $account->platform_name ?? null)
            : 0;

        // Advisory fees (if applicable)
        $advisoryFee = (float) ($account->advisor_fee_percent ?? 0) * $accountValue / 100;

        // Total annual fees
        $totalAnnualFees = $platformFee + ($weightedOCF * $accountValue) + $transactionCosts + $advisoryFee;
        $totalFeePercent = ($totalAnnualFees / $accountValue) * 100;

        return [
            'success' => true,
            'account_id' => $account->id,
            'account_name' => $account->account_name,
            'platform_name' => $account->platform_name ?? 'Unknown',
            'account_type' => $account->account_type,
            'account_value' => $accountValue,
            'fees' => [
                'platform_fee' => round($platformFee, 2),
                'fund_ocf' => round($weightedOCF * $accountValue, 2),
                'transaction_costs' => round($transactionCosts, 2),
                'advisory_fee' => round($advisoryFee, 2),
            ],
            'total_annual_fees' => round($totalAnnualFees, 2),
            'total_fee_percent' => round($totalFeePercent, 3),
            'weighted_ocf' => round($weightedOCF * 100, 3),
            'holdings_count' => $holdings->count(),
        ];
    }

    /**
     * Calculate fee breakdown by holding
     *
     * @param  int  $userId  User ID
     * @return array Holdings fee breakdown
     */
    public function analyzeHoldingFees(int $userId): array
    {
        $accounts = InvestmentAccount::where('user_id', $userId)
            ->with('holdings')
            ->get();

        $allHoldings = collect();
        foreach ($accounts as $account) {
            $allHoldings = $allHoldings->merge($account->holdings);
        }

        if ($allHoldings->isEmpty()) {
            return [
                'success' => false,
                'message' => 'No holdings found',
            ];
        }

        $holdingsAnalysis = [];

        foreach ($allHoldings as $holding) {
            $ocf = $holding->ocf ?? $this->estimateOCF($holding->asset_type);
            $annualFee = $holding->current_value * $ocf;

            $holdingsAnalysis[] = [
                'holding_id' => $holding->id,
                'security_name' => $holding->security_name ?? $holding->ticker,
                'ticker' => $holding->ticker,
                'asset_type' => $holding->asset_type,
                'current_value' => $holding->current_value,
                'ocf' => round($ocf * 100, 3),
                'annual_fee' => round($annualFee, 2),
                'account_name' => $holding->holdable->account_name ?? 'Unknown',
            ];
        }

        // Sort by annual fee descending
        usort($holdingsAnalysis, fn ($a, $b) => $b['annual_fee'] <=> $a['annual_fee']);

        return [
            'success' => true,
            'holdings' => $holdingsAnalysis,
            'highest_cost_holdings' => array_slice($holdingsAnalysis, 0, 10),
            'total_holdings' => count($holdingsAnalysis),
        ];
    }

    // =========================================================================
    // PRIVATE HELPER METHODS
    // =========================================================================

    /**
     * Calculate platform fee based on portfolio value and platform
     */
    private function calculatePlatformFee(float $portfolioValue, string $platformName): float
    {
        // Platform fee tiers (UK typical)
        $platformFees = match (strtolower($platformName)) {
            'vanguard' => $this->calculateTieredFee($portfolioValue, [
                [0, 250000, 0.0015],
                [250000, PHP_FLOAT_MAX, 0.00375],
            ]),
            'hargreaves lansdown', 'hl' => $this->calculateTieredFee($portfolioValue, [
                [0, 250000, 0.0045],
                [250000, 1000000, 0.0025],
                [1000000, PHP_FLOAT_MAX, 0.0010],
            ]),
            'aj bell' => $this->calculateCappedFee($portfolioValue, 0.0025, 3.50, 7.50),
            'interactive investor', 'ii' => 9.99 * 12, // Flat monthly fee
            'fidelity' => $this->calculateCappedFee($portfolioValue, 0.0035, 0, 45),
            'charles stanley direct' => $this->calculateTieredFee($portfolioValue, [
                [0, 50000, 0.0025],
                [50000, 500000, 0.0015],
                [500000, PHP_FLOAT_MAX, 0.0010],
            ]),
            default => $portfolioValue * 0.0030, // Industry average ~0.30%
        };

        return $platformFees;
    }

    /**
     * Calculate tiered fee structure
     */
    private function calculateTieredFee(float $value, array $tiers): float
    {
        $totalFee = 0;

        foreach ($tiers as [$min, $max, $rate]) {
            if ($value <= $min) {
                break;
            }

            $tierValue = min($value, $max) - $min;
            $totalFee += $tierValue * $rate;

            if ($value <= $max) {
                break;
            }
        }

        return $totalFee;
    }

    /**
     * Calculate capped fee (percentage with min/max)
     */
    private function calculateCappedFee(float $value, float $rate, float $minFee, float $maxFee): float
    {
        $fee = $value * $rate;

        return max($minFee, min($fee, $maxFee));
    }

    /**
     * Estimate annual transaction costs, using platform-specific dealing charges when available.
     */
    private function estimateTransactionCosts(float $portfolioValue, float $turnoverRate, ?string $platform = null): float
    {
        if ($platform !== null) {
            $platformKey = strtolower(str_replace([' ', '-'], '_', $platform));
            $platformConfig = config("investment_platforms.platforms.{$platformKey}");

            if ($platformConfig) {
                // Estimate ~12 trades per year per £100k at the given turnover rate
                $estimatedTrades = max(1, (int) round(($portfolioValue / 100000) * $turnoverRate * 12));
                // Average of fund and equity dealing costs
                $avgDealingCost = ($platformConfig['fund_dealing_cost'] + $platformConfig['equity_dealing_cost']) / 2;

                return $estimatedTrades * $avgDealingCost;
            }
        }

        // Fallback: percentage-based estimate
        $defaultCostPercent = config('investment_platforms.default_cost_percent', 0.001);
        $annualTradedValue = $portfolioValue * $turnoverRate;

        return $annualTradedValue * $defaultCostPercent;
    }

    /**
     * Calculate fee drag on returns over time
     */
    private function calculateFeeDrag(float $initialValue, float $feePercent, int $years, float $grossReturn): array
    {
        $feeRate = $feePercent / 100;
        $netReturn = $grossReturn - $feeRate;

        $valueWithoutFees = $initialValue * pow(1 + $grossReturn, $years);
        $valueWithFees = $initialValue * pow(1 + $netReturn, $years);

        $feeDragValue = $valueWithoutFees - $valueWithFees;
        $feeDragPercent = ($feeDragValue / $valueWithoutFees) * 100;

        return [
            'years' => $years,
            'gross_return_percent' => $grossReturn * 100,
            'net_return_percent' => $netReturn * 100,
            'value_without_fees' => round($valueWithoutFees, 2),
            'value_with_fees' => round($valueWithFees, 2),
            'fee_drag_value' => round($feeDragValue, 2),
            'fee_drag_percent' => round($feeDragPercent, 1),
            'interpretation' => sprintf(
                'Over %d years, fees reduce your final portfolio by £%s (%.1f%%)',
                $years,
                number_format($feeDragValue, 0),
                $feeDragPercent
            ),
        ];
    }

    /**
     * Get benchmark fees for portfolio size
     */
    private function getBenchmarkFees(float $portfolioValue): array
    {
        // UK industry benchmarks (2025)
        if ($portfolioValue < 50000) {
            return [
                'typical_range' => [0.40, 0.80],
                'excellent' => 0.30,
                'good' => 0.50,
                'average' => 0.65,
                'high' => 0.80,
            ];
        } elseif ($portfolioValue < 250000) {
            return [
                'typical_range' => [0.30, 0.60],
                'excellent' => 0.25,
                'good' => 0.40,
                'average' => 0.50,
                'high' => 0.65,
            ];
        } else {
            return [
                'typical_range' => [0.20, 0.45],
                'excellent' => 0.15,
                'good' => 0.30,
                'average' => 0.40,
                'high' => 0.50,
            ];
        }
    }

    /**
     * Assess fee level against benchmark
     */
    private function assessFeeLevel(float $feePercent, array $benchmark): array
    {
        if ($feePercent <= $benchmark['excellent']) {
            $level = 'excellent';
            $message = 'Excellent - Your fees are significantly below average';
        } elseif ($feePercent <= $benchmark['good']) {
            $level = 'good';
            $message = 'Good - Your fees are below average';
        } elseif ($feePercent <= $benchmark['average']) {
            $level = 'average';
            $message = 'Average - Your fees are in line with industry norms';
        } elseif ($feePercent <= $benchmark['high']) {
            $level = 'high';
            $message = 'High - Consider reviewing your platform and fund choices';
        } else {
            $level = 'very_high';
            $message = 'Very High - Significant savings available by switching platform or funds';
        }

        return [
            'level' => $level,
            'message' => $message,
            'your_fee' => round($feePercent, 3),
            'benchmark_average' => $benchmark['average'],
            'difference' => round($feePercent - $benchmark['average'], 3),
        ];
    }

    /**
     * Calculate potential savings
     */
    private function calculatePotentialSavings(float $currentFees, array $benchmark, float $portfolioValue): array
    {
        $currentFeePercent = ($currentFees / $portfolioValue) * 100;

        // Calculate savings vs good benchmark
        $goodFees = $portfolioValue * ($benchmark['good'] / 100);
        $savingsVsGood = max(0, $currentFees - $goodFees);

        // Calculate savings vs excellent benchmark
        $excellentFees = $portfolioValue * ($benchmark['excellent'] / 100);
        $savingsVsExcellent = max(0, $currentFees - $excellentFees);

        return [
            'savings_vs_good' => round($savingsVsGood, 2),
            'savings_vs_excellent' => round($savingsVsExcellent, 2),
            'savings_10_years_good' => round($this->calculateCompoundSavings($portfolioValue, $savingsVsGood, 10, $this->getDefaultExpectedReturn()), 2),
            'savings_10_years_excellent' => round($this->calculateCompoundSavings($portfolioValue, $savingsVsExcellent, 10, $this->getDefaultExpectedReturn()), 2),
            'has_savings_opportunity' => $savingsVsGood > 100,
        ];
    }
}
