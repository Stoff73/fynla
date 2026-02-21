<?php

declare(strict_types=1);

namespace App\Services\Investment;

use App\Models\Investment\InvestmentAccount;
use Carbon\Carbon;

/**
 * Return Calculation Service
 *
 * Handles financial return calculations for investment accounts,
 * including annualised return computation from holdings data.
 */
class ReturnCalculationService
{
    /**
     * Calculate annualised return for an account based on holdings.
     *
     * Uses purchase_date to calculate holding period, defaults to 3 years if not set.
     * Computes a cost-basis-weighted average holding period and derives the annualised
     * return using the CAGR formula: ((1 + total_return)^(1/years) - 1) * 100
     *
     * @param  InvestmentAccount  $account  Account with holdings loaded
     * @return float|null Annualised return percentage or null if cannot calculate
     */
    public function calculateAnnualisedReturn(InvestmentAccount $account): ?float
    {
        $holdings = $account->holdings;

        if ($holdings->isEmpty()) {
            return null;
        }

        $totalCostBasis = 0;
        $totalCurrentValue = 0;
        $weightedYears = 0;

        foreach ($holdings as $holding) {
            $costBasis = (float) ($holding->cost_basis ?? 0);
            $currentValue = (float) ($holding->current_value ?? 0);

            if ($costBasis <= 0) {
                continue;
            }

            // Calculate years held (default 3 years if no purchase_date)
            $years = 3.0;
            if ($holding->purchase_date) {
                $purchaseDate = $holding->purchase_date instanceof Carbon
                    ? $holding->purchase_date
                    : Carbon::parse($holding->purchase_date);
                $years = max(0.25, $purchaseDate->diffInDays(now()) / 365.25); // Min 3 months
            }

            $totalCostBasis += $costBasis;
            $totalCurrentValue += $currentValue;
            $weightedYears += $costBasis * $years;
        }

        if ($totalCostBasis <= 0) {
            return null;
        }

        // Calculate weighted average holding period
        $avgYears = $weightedYears / $totalCostBasis;

        // Calculate total return
        $totalReturn = ($totalCurrentValue - $totalCostBasis) / $totalCostBasis;

        // Annualise the return: ((1 + total_return)^(1/years) - 1) * 100
        if ($totalReturn <= -1) {
            // Prevent math errors for total loss
            return -100.0;
        }

        $annualisedReturn = (pow(1 + $totalReturn, 1 / $avgYears) - 1) * 100;

        return round($annualisedReturn, 2);
    }
}
