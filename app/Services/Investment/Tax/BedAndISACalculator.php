<?php

declare(strict_types=1);

namespace App\Services\Investment\Tax;

use App\Models\Investment\Holding;
use App\Models\Investment\InvestmentAccount;
use App\Services\Risk\RiskPreferenceService;
use App\Services\TaxConfigService;
use Illuminate\Support\Collection;

/**
 * Bed and ISA Calculator
 * Calculate opportunities to transfer holdings from GIA to ISA
 * while utilizing CGT allowance
 * Uses active tax year rates from TaxConfigService
 *
 * Bed and ISA Strategy:
 * 1. Sell holdings in GIA (realizing gain within CGT allowance)
 * 2. Immediately repurchase same holding in ISA
 * 3. Future growth and income become tax-free
 *
 * UK Tax Rules:
 * - CGT allowance varies by tax year
 * - No "bed and breakfasting" rule when moving to ISA
 * - ISA allowance varies by tax year
 * - Can transfer up to ISA allowance remaining
 */
class BedAndISACalculator
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
     * Calculate Bed and ISA opportunities
     *
     * @param  int  $userId  User ID
     * @param  array  $options  Options
     * @return array Bed and ISA analysis
     */
    public function calculateOpportunities(int $userId, array $options = []): array
    {
        // Get tax allowances from config
        $cgtConfig = $this->taxConfig->getCapitalGainsTax();
        $isaConfig = $this->taxConfig->getISAAllowances();

        $cgtAllowance = $options['cgt_allowance'] ?? $cgtConfig['annual_exempt_amount'];
        $isaAllowance = $options['isa_allowance_remaining'] ?? $isaConfig['annual_allowance'];
        $taxRate = $options['tax_rate'] ?? (float) ($cgtConfig['higher_rate'] ?? 0.20);

        // Get GIA holdings
        $giaHoldings = $this->getGIAHoldings($userId);

        if ($giaHoldings->isEmpty()) {
            return [
                'success' => true,
                'opportunities' => [],
                'message' => 'No GIA holdings found for Bed and ISA',
            ];
        }

        // Analyze each holding
        $opportunities = $this->analyzeHoldings(
            $giaHoldings,
            $cgtAllowance,
            $isaAllowance,
            $taxRate
        );

        // Calculate optimal transfer strategy
        $transferStrategy = $this->calculateOptimalTransferStrategy(
            $opportunities,
            $cgtAllowance,
            $isaAllowance
        );

        // Generate step-by-step execution plan
        $executionPlan = $this->generateExecutionPlan($transferStrategy);

        return [
            'success' => true,
            'cgt_allowance' => $cgtAllowance,
            'isa_allowance_remaining' => $isaAllowance,
            'opportunities' => $opportunities,
            'transfer_strategy' => $transferStrategy,
            'execution_plan' => $executionPlan,
            'summary' => $this->generateSummary($transferStrategy),
        ];
    }

    /**
     * Get GIA holdings suitable for Bed and ISA
     *
     * @param  int  $userId  User ID
     * @return Collection GIA holdings
     */
    private function getGIAHoldings(int $userId): Collection
    {
        $accounts = InvestmentAccount::where('user_id', $userId)
            ->whereIn('account_type', ['gia', 'general'])
            ->with('holdings')
            ->get();

        return $accounts->flatMap->holdings;
    }

    /**
     * Analyze holdings for Bed and ISA potential
     *
     * @param  Collection  $holdings  GIA holdings
     * @param  float  $cgtAllowance  CGT annual allowance
     * @param  float  $isaAllowance  ISA allowance remaining
     * @param  float  $taxRate  CGT tax rate
     * @return array Bed and ISA opportunities
     */
    private function analyzeHoldings(
        Collection $holdings,
        float $cgtAllowance,
        float $isaAllowance,
        float $taxRate
    ): array {
        $opportunities = [];

        foreach ($holdings as $holding) {
            if (! $holding->cost_basis || ! $holding->current_value) {
                continue;
            }

            $gain = $holding->current_value - $holding->cost_basis;

            // Calculate potential transfer details
            $transferAnalysis = $this->analyzeTransferPotential(
                $holding,
                $gain,
                $cgtAllowance,
                $isaAllowance,
                $taxRate
            );

            if ($transferAnalysis['can_transfer']) {
                $opportunities[] = [
                    'holding_id' => $holding->id,
                    'account_id' => $holding->investment_account_id,
                    'security_name' => $holding->security_name ?? $holding->ticker,
                    'ticker' => $holding->ticker,
                    'isin' => $holding->isin,
                    'cost_basis' => $holding->cost_basis,
                    'current_value' => $holding->current_value,
                    'quantity' => $holding->quantity,
                    'current_price' => $holding->current_price,
                    'gain' => round($gain, 2),
                    'gain_percent' => round(($gain / $holding->cost_basis) * 100, 2),
                    'transfer_value' => $transferAnalysis['transfer_value'],
                    'cgt_on_transfer' => $transferAnalysis['cgt_liability'],
                    'annual_tax_saving' => $transferAnalysis['annual_tax_saving'],
                    'priority' => $transferAnalysis['priority'],
                    'rationale' => $transferAnalysis['rationale'],
                    'dividend_yield' => $holding->dividend_yield,
                ];
            }
        }

        // Sort by priority and annual tax saving
        usort($opportunities, function ($a, $b) {
            $priorityOrder = ['high' => 1, 'medium' => 2, 'low' => 3];
            $aPriority = $priorityOrder[$a['priority']] ?? 4;
            $bPriority = $priorityOrder[$b['priority']] ?? 4;

            if ($aPriority === $bPriority) {
                return $b['annual_tax_saving'] <=> $a['annual_tax_saving'];
            }

            return $aPriority <=> $bPriority;
        });

        return $opportunities;
    }

    /**
     * Analyze transfer potential for a holding
     *
     * @param  Holding  $holding  Holding
     * @param  float  $gain  Unrealized gain
     * @param  float  $cgtAllowance  CGT allowance
     * @param  float  $isaAllowance  ISA allowance remaining
     * @param  float  $taxRate  CGT tax rate
     * @return array Transfer analysis
     */
    private function analyzeTransferPotential(
        Holding $holding,
        float $gain,
        float $cgtAllowance,
        float $isaAllowance,
        float $taxRate
    ): array {
        $canTransfer = false;
        $transferValue = 0;
        $cgtLiability = 0;
        $priority = 'low';
        $rationale = '';

        // Strategy 1: Gain within CGT allowance - ideal Bed and ISA candidate
        if ($gain > 0 && $gain <= $cgtAllowance) {
            $canTransfer = true;
            $transferValue = min($holding->current_value, $isaAllowance);
            $cgtLiability = 0; // Within allowance
            $priority = $this->calculatePriority($holding, $gain, 'within_allowance');
            $rationale = sprintf(
                'Gain of £%s within CGT allowance - ideal for Bed and ISA',
                number_format($gain, 0)
            );
        }
        // Strategy 2: Large gain but can transfer partial holding
        elseif ($gain > $cgtAllowance && $holding->current_value > 1000) {
            // Calculate how much we can transfer while using CGT allowance
            $gainPerPound = $gain / $holding->current_value;
            $maxTransferForAllowance = $cgtAllowance / $gainPerPound;

            if ($maxTransferForAllowance >= 1000) {
                // Worth transferring
                $canTransfer = true;
                $transferValue = min($maxTransferForAllowance, $isaAllowance);
                $cgtLiability = 0;
                $priority = $this->calculatePriority($holding, $cgtAllowance, 'partial');
                $rationale = sprintf(
                    'Transfer £%s (partial holding) using full CGT allowance',
                    number_format($transferValue, 0)
                );
            }
        }
        // Strategy 3: Small gain - transfer regardless
        elseif ($gain > 0 && $gain < 1000) {
            $canTransfer = true;
            $transferValue = min($holding->current_value, $isaAllowance);
            $cgtLiability = max(0, $gain * $taxRate);
            $priority = 'medium';
            $rationale = 'Small gain - low CGT cost for future tax savings';
        }

        // Calculate annual tax saving
        $annualTaxSaving = $this->estimateAnnualTaxSaving($transferValue, $holding);

        return [
            'can_transfer' => $canTransfer,
            'transfer_value' => round($transferValue, 2),
            'cgt_liability' => round($cgtLiability, 2),
            'annual_tax_saving' => round($annualTaxSaving, 2),
            'priority' => $priority,
            'rationale' => $rationale,
        ];
    }

    /**
     * Calculate transfer priority
     *
     * @param  Holding  $holding  Holding
     * @param  float  $gain  Gain amount
     * @param  string  $strategy  Transfer strategy
     * @return string Priority (high, medium, low)
     */
    private function calculatePriority(Holding $holding, float $gain, string $strategy): string
    {
        $score = 0;

        // High dividend yield = high priority (save more dividend tax)
        if (($holding->dividend_yield ?? 0) > 0.04) {
            $score += 3;
        } elseif (($holding->dividend_yield ?? 0) > 0.02) {
            $score += 2;
        }

        // Large holding value = higher priority
        if ($holding->current_value > 10000) {
            $score += 2;
        } elseif ($holding->current_value > 5000) {
            $score += 1;
        }

        // Strategy bonus
        if ($strategy === 'within_allowance') {
            $score += 2; // Perfect fit
        }

        if ($score >= 5) {
            return 'high';
        }
        if ($score >= 3) {
            return 'medium';
        }

        return 'low';
    }

    /**
     * Estimate annual tax saving from ISA transfer
     *
     * @param  float  $transferValue  Amount to transfer
     * @param  Holding  $holding  Holding
     * @return float Annual tax saving
     */
    private function estimateAnnualTaxSaving(float $transferValue, Holding $holding): float
    {
        $cgtConfig = $this->taxConfig->getCapitalGainsTax();
        $dividendConfig = $this->taxConfig->getDividendTax();
        $cgtRate = (float) ($cgtConfig['higher_rate'] ?? 0.20);
        $dividendBasicRate = (float) ($dividendConfig['basic_rate'] ?? 0.0875);

        $annualGrowth = $transferValue * $this->getDefaultExpectedReturn();
        $cgtSaving = $annualGrowth * $cgtRate;

        // Dividend tax saving
        $annualDividend = $transferValue * ($holding->dividend_yield ?? 0.02);
        $dividendTaxSaving = $annualDividend * $dividendBasicRate;

        return $cgtSaving + $dividendTaxSaving;
    }

    /**
     * Calculate optimal transfer strategy
     *
     * @param  array  $opportunities  Bed and ISA opportunities
     * @param  float  $cgtAllowance  CGT allowance
     * @param  float  $isaAllowance  ISA allowance remaining
     * @return array Transfer strategy
     */
    private function calculateOptimalTransferStrategy(
        array $opportunities,
        float $cgtAllowance,
        float $isaAllowance
    ): array {
        $strategy = [
            'recommended_transfers' => [],
            'total_transfer_value' => 0,
            'total_cgt_liability' => 0,
            'total_annual_saving' => 0,
            'cgt_allowance_used' => 0,
            'isa_allowance_used' => 0,
        ];

        $cgtConfig = $this->taxConfig->getCapitalGainsTax();
        $cgtRate = (float) ($cgtConfig['higher_rate'] ?? 0.20);
        $remainingCGT = $cgtAllowance;
        $remainingISA = $isaAllowance;

        foreach ($opportunities as $opp) {
            // Check if we have room
            if ($remainingISA <= 0) {
                break;
            }

            // Adjust transfer value to fit remaining ISA allowance
            $actualTransferValue = min($opp['transfer_value'], $remainingISA);

            // Recalculate CGT for actual transfer
            $actualGain = ($opp['gain'] / $opp['current_value']) * $actualTransferValue;
            $cgtLiability = max(0, $actualGain - $remainingCGT) * $cgtRate;

            // Only proceed if CGT is acceptable (within allowance or very small)
            if ($cgtLiability > 500) {
                continue; // Skip if CGT is too high
            }

            $strategy['recommended_transfers'][] = [
                'holding_id' => $opp['holding_id'],
                'security_name' => $opp['security_name'],
                'ticker' => $opp['ticker'],
                'transfer_value' => round($actualTransferValue, 2),
                'quantity_to_transfer' => $opp['current_price'] > 0
                    ? round($actualTransferValue / $opp['current_price'], 6)
                    : $opp['quantity'],
                'gain_realized' => round($actualGain, 2),
                'cgt_liability' => round($cgtLiability, 2),
                'annual_tax_saving' => round(
                    $this->estimateAnnualTaxSaving($actualTransferValue, (object) $opp),
                    2
                ),
                'priority' => $opp['priority'],
            ];

            $strategy['total_transfer_value'] += $actualTransferValue;
            $strategy['total_cgt_liability'] += $cgtLiability;
            $strategy['total_annual_saving'] += $opp['annual_tax_saving'];
            $strategy['cgt_allowance_used'] += min($actualGain, $remainingCGT);
            $strategy['isa_allowance_used'] += $actualTransferValue;

            $remainingCGT = max(0, $remainingCGT - $actualGain);
            $remainingISA -= $actualTransferValue;
        }

        $strategy['cgt_allowance_remaining'] = round($remainingCGT, 2);
        $strategy['isa_allowance_remaining'] = round($remainingISA, 2);

        return $strategy;
    }

    /**
     * Generate step-by-step execution plan
     *
     * @param  array  $strategy  Transfer strategy
     * @return array Execution plan
     */
    private function generateExecutionPlan(array $strategy): array
    {
        if (empty($strategy['recommended_transfers'])) {
            return [
                'steps' => [],
                'timeline' => 'N/A',
                'notes' => ['No Bed and ISA transfers recommended at this time'],
            ];
        }

        $steps = [];
        $stepNumber = 1;

        // Step 1: Preparation
        $steps[] = [
            'step' => $stepNumber++,
            'action' => 'Prepare for Bed and ISA transfer',
            'details' => [
                'Ensure ISA account is open and funded',
                'Confirm current ISA allowance remaining',
                'Review holdings to transfer',
            ],
            'timing' => 'Before starting',
        ];

        // Step 2-N: Execute transfers
        foreach ($strategy['recommended_transfers'] as $transfer) {
            $steps[] = [
                'step' => $stepNumber++,
                'action' => sprintf('Transfer %s', $transfer['security_name']),
                'details' => [
                    sprintf('Sell %.6f shares in GIA', $transfer['quantity_to_transfer']),
                    sprintf('Immediately repurchase in ISA using £%s', number_format($transfer['transfer_value'], 2)),
                    sprintf('CGT liability: £%s', number_format($transfer['cgt_liability'], 2)),
                ],
                'timing' => 'Same day',
                'priority' => $transfer['priority'],
            ];
        }

        // Final step: Confirmation
        $steps[] = [
            'step' => $stepNumber++,
            'action' => 'Confirm transfers completed',
            'details' => [
                'Verify all sales in GIA settled',
                'Verify all purchases in ISA completed',
                'Keep records for CGT reporting',
            ],
            'timing' => 'Within 2 working days',
        ];

        return [
            'steps' => $steps,
            'timeline' => 'Can be completed in 1-2 days',
            'notes' => [
                'Execute all trades on the same day to minimize market risk',
                'No 30-day wash sale rule applies for ISA transfers',
                'Keep all trade confirmations for tax records',
                sprintf('Total CGT to pay: £%s', number_format($strategy['total_cgt_liability'], 2)),
                sprintf('Annual tax saving: £%s', number_format($strategy['total_annual_saving'], 2)),
            ],
        ];
    }

    /**
     * Generate summary
     *
     * @param  array  $strategy  Transfer strategy
     * @return string Summary text
     */
    private function generateSummary(array $strategy): string
    {
        if (empty($strategy['recommended_transfers'])) {
            return 'No Bed and ISA opportunities identified at this time.';
        }

        $parts = [];

        $parts[] = sprintf(
            '%d holding%s recommended for Bed and ISA transfer',
            count($strategy['recommended_transfers']),
            count($strategy['recommended_transfers']) === 1 ? '' : 's'
        );

        $parts[] = sprintf(
            'Total transfer value: £%s',
            number_format($strategy['total_transfer_value'], 0)
        );

        if ($strategy['total_cgt_liability'] > 0) {
            $parts[] = sprintf(
                'CGT liability: £%s',
                number_format($strategy['total_cgt_liability'], 0)
            );
        } else {
            $parts[] = 'No CGT liability (within annual allowance)';
        }

        $parts[] = sprintf(
            'Estimated annual tax saving: £%s',
            number_format($strategy['total_annual_saving'], 0)
        );

        // ROI calculation
        $yearsToBreakEven = $strategy['total_cgt_liability'] > 0
            ? $strategy['total_cgt_liability'] / max(1, $strategy['total_annual_saving'])
            : 0;

        if ($yearsToBreakEven > 0 && $yearsToBreakEven < 10) {
            $parts[] = sprintf(
                'Break-even in %.1f years',
                $yearsToBreakEven
            );
        }

        return implode('. ', $parts).'.';
    }
}
