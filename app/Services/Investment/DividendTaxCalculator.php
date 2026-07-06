<?php

declare(strict_types=1);

namespace App\Services\Investment;

use App\Services\TaxConfigService;

class DividendTaxCalculator
{
    public function __construct(
        private readonly TaxConfigService $taxConfig
    ) {}

    /**
     * Calculate UK dividend tax with proper band-splitting.
     *
     * Dividends sit on top of non-dividend income in the tax stack.
     * Personal allowance taper: reduce by £1 per £2 over £100,000 adjusted net income.
     *
     * @param  float  $dividendIncome  Total dividend income for the year
     * @param  float  $nonDividendIncome  All other income (employment, rental, etc.)
     * @param  float  $pensionContributions  Gross employee pension contributions — deducted from ANI for PA taper
     * @param  float  $giftAidGross  Grossed-up Gift Aid donations — deducted from ANI for PA taper
     * @return float Tax due on dividends
     */
    public function calculate(
        float $dividendIncome,
        float $nonDividendIncome,
        float $pensionContributions = 0,
        float $giftAidGross = 0
    ): float {
        if ($dividendIncome <= 0) {
            return 0.0;
        }

        $dividendConfig = $this->taxConfig->getDividendTax();
        $incomeTaxConfig = $this->taxConfig->getIncomeTax();

        $personalAllowance = (float) ($incomeTaxConfig['personal_allowance'] ?? 12570);
        $basicRateBand = (float) ($incomeTaxConfig['bands'][0]['max'] ?? 37700);
        $additionalRateThreshold = (float) ($incomeTaxConfig['bands'][1]['upper_limit'] ?? 125140);

        $dividendAllowance = (float) ($dividendConfig['allowance'] ?? 500);
        $basicRate = (float) ($dividendConfig['basic_rate'] ?? TaxDefaults::DIVIDEND_BASIC_RATE);
        $higherRate = (float) ($dividendConfig['higher_rate'] ?? TaxDefaults::DIVIDEND_HIGHER_RATE);
        $additionalRate = (float) ($dividendConfig['additional_rate'] ?? TaxDefaults::DIVIDEND_ADDITIONAL_RATE);

        // Personal allowance taper applies to Adjusted Net Income, NOT gross income.
        // ANI = total income − pension contributions − grossed-up Gift Aid (HMRC ITA
        // 2007 s35-s37). Defaults preserve legacy gross-income behaviour for callers
        // that have not yet been updated to pass deduction values.
        $totalIncome = $nonDividendIncome + $dividendIncome;
        $adjustedNetIncome = max(0.0, $totalIncome - $pensionContributions - $giftAidGross);
        $taperThreshold = (float) ($incomeTaxConfig['personal_allowance_taper_threshold'] ?? 100000);
        if ($adjustedNetIncome > $taperThreshold) {
            $reduction = ($adjustedNetIncome - $taperThreshold) / 2;
            $personalAllowance = max(0, $personalAllowance - $reduction);
        }

        // Recalculate band boundaries with tapered PA
        $basicBandCeiling = $personalAllowance + $basicRateBand;
        $higherBandCeiling = $additionalRateThreshold;

        // Subtract dividend allowance from taxable dividends
        $taxableDividends = max(0, $dividendIncome - $dividendAllowance);

        if ($taxableDividends <= 0) {
            return 0.0;
        }

        // Non-dividend income determines where dividends start in the bands
        $incomeUsed = $nonDividendIncome;

        // Calculate how much of each band remains for dividends
        $tax = 0.0;
        $dividendsRemaining = $taxableDividends;

        // Basic rate band: from PA to PA + basic_rate_limit
        if ($incomeUsed < $basicBandCeiling && $dividendsRemaining > 0) {
            $spaceInBasicBand = max(0, $basicBandCeiling - $incomeUsed);
            $dividendsInBasicBand = min($dividendsRemaining, $spaceInBasicBand);
            $tax += $dividendsInBasicBand * $basicRate;
            $dividendsRemaining -= $dividendsInBasicBand;
            $incomeUsed += $dividendsInBasicBand;
        }

        // Higher rate band: from basic band ceiling to additional rate threshold
        if ($incomeUsed < $higherBandCeiling && $dividendsRemaining > 0) {
            $spaceInHigherBand = max(0, $higherBandCeiling - $incomeUsed);
            $dividendsInHigherBand = min($dividendsRemaining, $spaceInHigherBand);
            $tax += $dividendsInHigherBand * $higherRate;
            $dividendsRemaining -= $dividendsInHigherBand;
        }

        // Additional rate band: everything above additional rate threshold
        if ($dividendsRemaining > 0) {
            $tax += $dividendsRemaining * $additionalRate;
        }

        return round($tax, 2);
    }
}
