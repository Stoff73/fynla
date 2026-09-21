<?php

declare(strict_types=1);

namespace App\Services\Investment;

use App\Models\Investment\InvestmentAccount;
use Carbon\Carbon;

class EmployeeSchemeCalculationService
{
    /**
     * Check if tax relief holding period has been met (3 years for EIS/SEIS).
     */
    public function isHoldingPeriodComplete(InvestmentAccount $account): bool
    {
        if (! $account->disposal_restriction_date) {
            return false;
        }

        return now()->gte($account->disposal_restriction_date);
    }

    /**
     * Calculate paper gain/loss for private investments.
     */
    public function calculatePaperGainLoss(InvestmentAccount $account): ?float
    {
        if (! $account->investment_amount || ! $account->latest_valuation) {
            return null;
        }

        return $account->latest_valuation - $account->investment_amount;
    }

    /**
     * Calculate paper return percentage for private investments.
     */
    public function calculatePaperReturnPercent(InvestmentAccount $account): ?float
    {
        if (! $account->investment_amount || $account->investment_amount == 0) {
            return null;
        }

        return (($account->latest_valuation - $account->investment_amount) / $account->investment_amount) * 100;
    }

    /**
     * Check if this is a tax-advantaged employee share scheme.
     * SAYE, CSOP, and EMI all have tax advantages when rules are followed.
     */
    public function isTaxAdvantagedScheme(InvestmentAccount $account): bool
    {
        return in_array($account->account_type, ['saye', 'csop', 'emi']);
    }

    /**
     * Calculate intrinsic value of vested options.
     * Intrinsic value = max(0, current_share_price - exercise_price) * units_vested
     */
    public function calculateIntrinsicValue(InvestmentAccount $account): ?float
    {
        if (! $account->isOptionsScheme() || ! $account->current_share_price || ! $account->exercise_price) {
            return null;
        }

        $spreadPerShare = max(0, (float) $account->current_share_price - (float) $account->exercise_price);
        $vestedUnits = (int) ($account->units_vested ?? 0);

        return $spreadPerShare * $vestedUnits;
    }

    /**
     * Calculate total current value of the share scheme.
     * For options: intrinsic value of vested options
     * For RSUs: current share price * vested units
     */
    public function calculateSchemeCurrentValue(InvestmentAccount $account): ?float
    {
        if (! $account->isEmployeeShareScheme() || ! $account->current_share_price) {
            return null;
        }

        $vestedUnits = (int) ($account->units_vested ?? 0);

        if ($account->isOptionsScheme()) {
            // Options: intrinsic value (gain on exercise)
            return $this->calculateIntrinsicValue($account);
        }

        // RSUs: direct share value
        return (float) $account->current_share_price * $vestedUnits;
    }

    /**
     * Calculate potential value of unvested units.
     * For options: max(0, current_share_price - exercise_price) * units_unvested
     * For RSUs: current share price * units_unvested
     */
    public function calculateUnvestedValue(InvestmentAccount $account): ?float
    {
        if (! $account->isEmployeeShareScheme() || ! $account->current_share_price) {
            return null;
        }

        $unvestedUnits = (int) ($account->units_unvested ?? 0);

        return $this->taxableValuePerUnit($account) * $unvestedUnits;
    }

    /**
     * What one unit is worth to the holder at vest or exercise.
     *
     * For an option the holder pays the exercise price to acquire the share, so
     * only the spread is theirs — market value less exercise price, floored at
     * nil for an underwater option. For an RSU nothing is paid, so the whole
     * market value is the value received.
     *
     * The one home for this branch: `calculateUnvestedValue()` and
     * `VestScheduleResolver` both read it, so the tax layer and the investment
     * screens cannot disagree about what a unit is worth.
     */
    public function taxableValuePerUnit(InvestmentAccount $account): float
    {
        if ($account->isOptionsScheme()) {
            return max(0, (float) $account->current_share_price - (float) $account->exercise_price);
        }

        return (float) $account->current_share_price;
    }

    /**
     * Check if CSOP options are within the tax-advantaged exercise window.
     * CSOP tax advantages require exercise between 3 and 10 years from grant.
     */
    public function isInCsopTaxAdvantageWindow(InvestmentAccount $account): bool
    {
        if ($account->account_type !== 'csop' || ! $account->grant_date) {
            return false;
        }

        $grantDate = $account->grant_date instanceof Carbon
            ? $account->grant_date
            : Carbon::parse($account->grant_date);

        $now = now();
        $threeYearsFromGrant = $grantDate->copy()->addYears(3);
        $tenYearsFromGrant = $grantDate->copy()->addYears(10);

        return $now->gte($threeYearsFromGrant) && $now->lte($tenYearsFromGrant);
    }

    /**
     * Calculate remaining units available (not exercised, forfeited, or expired).
     */
    public function calculateRemainingUnits(InvestmentAccount $account): int
    {
        $granted = (int) ($account->units_granted ?? 0);
        $exercised = (int) ($account->units_exercised ?? 0);
        $forfeited = (int) ($account->units_forfeited ?? 0);
        $expired = (int) ($account->units_expired ?? 0);

        return max(0, $granted - $exercised - $forfeited - $expired);
    }
}
