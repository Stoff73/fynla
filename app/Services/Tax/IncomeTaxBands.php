<?php

declare(strict_types=1);

namespace App\Services\Tax;

/**
 * The one home for UK income-tax band geometry (Rule 20).
 *
 * The basic-rate band WIDTH is the constant — £37,700. The familiar £50,270
 * higher-rate threshold is what that width plus a full Personal Allowance adds
 * up to; it is derived, not fixed. Building it the other way round — holding
 * £50,270 constant and calling the band `£50,270 − allowance` — widens the 20%
 * slice to £50,270 the moment the allowance tapers to zero, so the whole
 * withdrawn allowance is taxed at 20% instead of 40% (W-0174: £2,514 per
 * person, in the direction that flatters).
 *
 * The additional-rate threshold does not move with the allowance: by the time
 * income reaches it the allowance is already fully withdrawn, so it is the same
 * figure expressed against income or against taxable income.
 *
 * Every threshold, rate and taper parameter is read from the income-tax array
 * TaxConfigService hands out (Rule 2). Nothing here is hardcoded.
 *
 * All limits are ABSOLUTE positions on total income, not band widths — the same
 * space HMRC's published table uses ("higher rate: £50,271 to £125,140").
 */
final class IncomeTaxBands
{
    private function __construct(
        public readonly float $personalAllowance,
        public readonly float $basicRateLimit,
        public readonly float $higherRateLimit,
        public readonly float $basicRate,
        public readonly float $higherRate,
        public readonly float $additionalRate,
    ) {}

    /**
     * Bands for an explicit effective (post-taper) Personal Allowance.
     *
     * Pass the allowance the taper produced; omit it to use the configured full
     * allowance. The config array itself is never mutated — a tapered allowance
     * written back over `personal_allowance` destroys the only record of the
     * full one, which is what the band width has to be derived from.
     *
     * @param  array<string, mixed>  $incomeTaxConfig  TaxConfigService::getIncomeTax()
     */
    public static function forPersonalAllowance(array $incomeTaxConfig, ?float $personalAllowance = null): self
    {
        $allowance = max(0.0, $personalAllowance ?? (float) ($incomeTaxConfig['personal_allowance'] ?? 0));
        $bands = $incomeTaxConfig['bands'] ?? [];

        $basicRateLimit = $allowance + self::configuredBasicRateBandWidth($incomeTaxConfig);
        $higherRateLimit = max(
            $basicRateLimit,
            (float) ($incomeTaxConfig['additional_rate_threshold']
                ?? ($bands[1]['upper_limit'] ?? $basicRateLimit))
        );

        return new self(
            $allowance,
            $basicRateLimit,
            $higherRateLimit,
            (float) $bands[0]['rate'],
            (float) $bands[1]['rate'],
            (float) $bands[2]['rate'],
        );
    }

    /**
     * Bands for an adjusted net income, applying the Personal Allowance taper.
     *
     * @param  array<string, mixed>  $incomeTaxConfig  TaxConfigService::getIncomeTax()
     */
    public static function forAdjustedNetIncome(array $incomeTaxConfig, float $adjustedNetIncome): self
    {
        return self::forPersonalAllowance(
            $incomeTaxConfig,
            self::taperedPersonalAllowance($incomeTaxConfig, $adjustedNetIncome)
        );
    }

    /**
     * The Personal Allowance after the ITA 2007 s35 taper — £1 withdrawn for
     * every £2 of adjusted net income above the threshold, floored at zero.
     *
     * The taper rate is configured (`personal_allowance_taper_rate`), not the
     * literal 2 that five separate services had each written out for themselves.
     *
     * @param  array<string, mixed>  $incomeTaxConfig  TaxConfigService::getIncomeTax()
     */
    public static function taperedPersonalAllowance(array $incomeTaxConfig, float $adjustedNetIncome): float
    {
        $full = (float) ($incomeTaxConfig['personal_allowance'] ?? 0);
        $threshold = (float) ($incomeTaxConfig['personal_allowance_taper_threshold'] ?? 0);

        if ($threshold <= 0 || $adjustedNetIncome <= $threshold) {
            return $full;
        }

        $rate = (float) ($incomeTaxConfig['personal_allowance_taper_rate'] ?? 0);

        return max(0.0, $full - floor(($adjustedNetIncome - $threshold) * $rate));
    }

    /**
     * Width of the 20% slice — the figure that must not move when the allowance
     * tapers.
     */
    public function basicRateBandWidth(): float
    {
        return max(0.0, $this->basicRateLimit - $this->personalAllowance);
    }

    /**
     * Width of the 40% slice, which absorbs whatever the allowance gives up.
     */
    public function higherRateBandWidth(): float
    {
        return max(0.0, $this->higherRateLimit - $this->basicRateLimit);
    }

    /**
     * Both limits extended by a grossed-up Gift Aid donation (ITA 2007 s414) or
     * a relief-at-source pension contribution — the mechanism that delivers
     * higher- and additional-rate relief by moving income into a lower band.
     */
    public function extendedBy(float $amount): self
    {
        if ($amount <= 0) {
            return $this;
        }

        return new self(
            $this->personalAllowance,
            $this->basicRateLimit + $amount,
            $this->higherRateLimit + $amount,
            $this->basicRate,
            $this->higherRate,
            $this->additionalRate,
        );
    }

    /**
     * The seeder stores two different things under similar names on each band:
     * `upper_limit` is the display threshold (£50,270) and `max` is the
     * calculator-facing band width (£37,700). The width is what this needs.
     * Falling back to `higher_rate_threshold − full allowance` reconstructs the
     * same width for any year that omits it.
     *
     * @param  array<string, mixed>  $incomeTaxConfig
     */
    private static function configuredBasicRateBandWidth(array $incomeTaxConfig): float
    {
        $bands = $incomeTaxConfig['bands'] ?? [];

        if (isset($bands[0]['max'])) {
            return (float) $bands[0]['max'];
        }

        return max(0.0, (float) ($incomeTaxConfig['higher_rate_threshold'] ?? 0)
            - (float) ($incomeTaxConfig['personal_allowance'] ?? 0));
    }
}
