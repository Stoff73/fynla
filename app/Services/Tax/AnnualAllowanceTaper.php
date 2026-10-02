<?php

declare(strict_types=1);

namespace App\Services\Tax;

/**
 * The tapered Annual Allowance (Finance Act 2004 s228ZA): when threshold income
 * is above its limit AND adjusted income is above its limit, the allowance is
 * reduced by the taper rate for every £1 of adjusted income over, rounded down,
 * and never below the minimum. The one home for the rule (CSJ 2026-10-01: one
 * figure, every surface; audit item 42), as IncomeTaxBands::taperedPersonalAllowance
 * is for the Personal Allowance. Every figure is read from tax config (Rule 2).
 */
final class AnnualAllowanceTaper
{
    /**
     * @param  array<string, mixed>  $pensionConfig  TaxConfigService::getPensionAllowances()
     */
    public static function allowance(array $pensionConfig, float $thresholdIncome, float $adjustedIncome): float
    {
        $full = (float) $pensionConfig['annual_allowance'];
        $taper = $pensionConfig['tapered_annual_allowance'];

        if ($thresholdIncome <= (float) $taper['threshold_income']
            || $adjustedIncome <= (float) $taper['adjusted_income_threshold']) {
            return $full;
        }

        $reduction = floor(($adjustedIncome - (float) $taper['adjusted_income_threshold']) * (float) $taper['taper_rate']);

        return max((float) $taper['minimum_allowance'], $full - $reduction);
    }
}
