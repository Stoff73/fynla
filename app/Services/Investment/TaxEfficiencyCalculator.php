<?php

declare(strict_types=1);

namespace App\Services\Investment;

use App\Services\TaxConfigService;
use Illuminate\Support\Collection;

class TaxEfficiencyCalculator
{
    /**
     * Tax configuration service
     */
    private TaxConfigService $taxConfig;

    /**
     * Constructor
     */
    public function __construct(TaxConfigService $taxConfig)
    {
        $this->taxConfig = $taxConfig;
    }

    /**
     * Calculate unrealized gains across all holdings
     */
    public function calculateUnrealizedGains(Collection $holdings): array
    {
        $gains = $holdings->map(function ($holding) {
            // Skip holdings without cost_basis (no price data provided)
            if ($holding->cost_basis === null || $holding->cost_basis === 0) {
                return null;
            }

            $gain = $holding->current_value - $holding->cost_basis;
            $gainPercent = $holding->cost_basis > 0
                ? ($gain / $holding->cost_basis) * 100
                : 0;

            return [
                'security_name' => $holding->security_name,
                'cost_basis' => round($holding->cost_basis, 2),
                'current_value' => round($holding->current_value, 2),
                'unrealized_gain' => round($gain, 2),
                'gain_percent' => round($gainPercent, 2),
            ];
        })->filter(fn ($h) => $h !== null && $h['unrealized_gain'] > 0);

        $totalGain = $gains->sum('unrealized_gain');

        return [
            'total_unrealized_gains' => round($totalGain, 2),
            'holdings_with_gains' => $gains->values()->toArray(),
            'count' => $gains->count(),
        ];
    }

    /**
     * Calculate dividend tax liability
     */
    public function calculateDividendTax(float $dividendIncome, float $totalIncome): float
    {
        $dividendConfig = $this->taxConfig->getDividendTax();
        $allowance = $dividendConfig['allowance'];

        // Dividend income above allowance
        $taxableDividends = max(0, $dividendIncome - $allowance);

        if ($taxableDividends == 0) {
            return 0;
        }

        // Determine tax band based on total income
        $incomeTaxConfig = $this->taxConfig->getIncomeTax();
        $personalAllowance = $incomeTaxConfig['personal_allowance'] ?? 12570;

        // Simplified calculation - in reality would need to work through bands
        $tax = 0;

        if ($totalIncome <= $personalAllowance + 37700) {
            // Basic rate
            $tax = $taxableDividends * $dividendConfig['basic_rate'];
        } elseif ($totalIncome <= 125140) {
            // Higher rate
            $tax = $taxableDividends * $dividendConfig['higher_rate'];
        } else {
            // Additional rate
            $tax = $taxableDividends * $dividendConfig['additional_rate'];
        }

        return round($tax, 2);
    }

    /**
     * Calculate CGT liability on realized gains
     */
    public function calculateCGTLiability(float $realizedGains, float $totalIncome = 0): float
    {
        $cgtConfig = $this->taxConfig->getCapitalGainsTax();
        $annualExemption = $cgtConfig['annual_exempt_amount'];

        // Gains above annual exemption
        $taxableGains = max(0, $realizedGains - $annualExemption);

        if ($taxableGains == 0) {
            return 0;
        }

        // Determine rate based on income
        $incomeTaxConfig = $this->taxConfig->getIncomeTax();
        $personalAllowance = $incomeTaxConfig['personal_allowance'] ?? 12570;
        $higherRateThreshold = $personalAllowance + 37700;

        $rate = $totalIncome > $higherRateThreshold
            ? $cgtConfig['higher_rate']
            : $cgtConfig['basic_rate'];

        $cgtLiability = $taxableGains * $rate;

        return round($cgtLiability, 2);
    }

    /**
     * Identify tax loss harvesting opportunities
     */
    public function identifyHarvestingOpportunities(Collection $holdings): array
    {
        // Find holdings with losses that could be harvested
        $lossHoldings = $holdings->filter(function ($holding) {
            // Skip holdings without cost_basis
            if ($holding->cost_basis === null || $holding->cost_basis === 0) {
                return false;
            }

            $gain = $holding->current_value - $holding->cost_basis;

            return $gain < -100; // Only significant losses worth harvesting
        })->map(function ($holding) {
            $loss = $holding->current_value - $holding->cost_basis;

            return [
                'security_name' => $holding->security_name,
                'cost_basis' => round($holding->cost_basis, 2),
                'current_value' => round($holding->current_value, 2),
                'unrealized_loss' => round($loss, 2),
                'loss_percent' => round(($loss / $holding->cost_basis) * 100, 2),
                'recommendation' => 'Consider selling to realize loss for tax purposes',
            ];
        })->values();

        $totalLosses = abs($lossHoldings->sum('unrealized_loss'));

        return [
            'opportunities_count' => $lossHoldings->count(),
            'total_harvestable_losses' => round($totalLosses, 2),
            'potential_tax_saving' => round($totalLosses * 0.20, 2), // Assume 20% CGT rate
            'holdings' => $lossHoldings->toArray(),
        ];
    }

    /**
     * Calculate tax efficiency score (0-100)
     * Penalizes having significant assets in taxable accounts (GIA)
     */
    public function calculateTaxEfficiencyScore(Collection $accounts, Collection $holdings): int
    {
        $score = 100;

        // Calculate tax-sheltered vs taxable split
        $totalValue = $accounts->sum('current_value');

        if ($totalValue <= 0) {
            return $score;
        }

        // Tax-sheltered account types
        $taxShelteredTypes = ['isa', 'stocks_shares_isa', 'lifetime_isa', 'sipp', 'pension'];

        $taxShelteredValue = $accounts->filter(function ($account) use ($taxShelteredTypes) {
            return in_array($account->account_type, $taxShelteredTypes);
        })->sum('current_value');

        $taxableValue = $totalValue - $taxShelteredValue;
        $taxablePercent = ($taxableValue / $totalValue) * 100;
        $taxShelteredPercent = ($taxShelteredValue / $totalValue) * 100;

        // Penalize high proportion in taxable accounts (GIA)
        // 0-10% taxable = no penalty
        // 10-25% taxable = -5 points
        // 25-50% taxable = -15 points
        // 50-75% taxable = -30 points
        // 75-100% taxable = -50 points
        if ($taxablePercent > 75) {
            $score -= 50;
        } elseif ($taxablePercent > 50) {
            $score -= 30;
        } elseif ($taxablePercent > 25) {
            $score -= 15;
        } elseif ($taxablePercent > 10) {
            $score -= 5;
        }

        // Small bonus for excellent tax efficiency (>90% tax-sheltered)
        if ($taxShelteredPercent >= 90) {
            $score += 5;
        }

        // Check for holdings with large unrealized gains in taxable accounts
        // (tax inefficient as selling would trigger CGT)
        $largeGainHoldings = $holdings->filter(function ($holding) {
            if ($holding->cost_basis === null || $holding->cost_basis <= 0) {
                return false;
            }
            $gain = $holding->current_value - $holding->cost_basis;
            $gainPercent = ($gain / $holding->cost_basis) * 100;

            return $gainPercent > 50;
        })->count();

        if ($largeGainHoldings > 3) {
            $score -= 15; // Many holdings with large gains
        } elseif ($largeGainHoldings > 1) {
            $score -= 5; // Some holdings with large gains
        }

        return max(0, min(100, $score));
    }
}
