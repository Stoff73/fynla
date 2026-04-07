<?php

declare(strict_types=1);

namespace App\Constants;

/**
 * TaxDefaults - Centralised fallback values for UK tax calculations.
 *
 * These values are used as fallbacks when TaxConfigService cannot retrieve
 * values from the database. They should be reviewed and updated annually
 * when HMRC publishes new rates.
 *
 * IMPORTANT: Always prefer TaxConfigService for live values.
 * These constants exist only for resilience when the service fails.
 *
 * Last verified: 5 April 2026 (2026/27 tax year)
 *
 * @see https://www.gov.uk/government/publications/rates-and-allowances-inheritance-tax-thresholds-and-interest-rates
 * @see https://www.gov.uk/individual-savings-accounts
 * @see https://www.gov.uk/tax-on-your-private-pension/annual-allowance
 */
final class TaxDefaults
{
    // ==================== Inheritance Tax (2026/27) ====================

    /**
     * Nil Rate Band - amount that can be passed on free of IHT.
     * Frozen until April 2031.
     */
    public const NRB = 325000;

    /**
     * Residence Nil Rate Band - additional allowance for main residence passed to descendants.
     * Frozen until April 2031.
     */
    public const RNRB = 175000;

    /**
     * IHT rate on estates above the threshold.
     */
    public const IHT_RATE = 0.40;

    /**
     * Reduced IHT rate when 10%+ of net estate is left to charity.
     */
    public const IHT_CHARITABLE_RATE = 0.36;

    /**
     * Annual exemption for gifts (per donor).
     */
    public const ANNUAL_GIFT_EXEMPTION = 3000;

    /**
     * Small gifts exemption per recipient.
     */
    public const SMALL_GIFT_EXEMPTION = 250;

    /**
     * Chargeable Lifetime Transfer rate (immediate charge on gifts into trust).
     */
    public const CLT_RATE = 0.20;

    // ==================== ISA Allowances (2026/27) ====================

    /**
     * Total annual ISA allowance across all ISA types.
     */
    public const ISA_ALLOWANCE = 20000;

    /**
     * Junior ISA annual allowance.
     */
    public const JISA_ALLOWANCE = 9000;

    /**
     * Lifetime ISA annual contribution limit.
     */
    public const LISA_ALLOWANCE = 4000;

    // ==================== Pension Allowances (2026/27) ====================

    /**
     * Annual allowance for pension contributions with tax relief.
     */
    public const PENSION_ANNUAL_ALLOWANCE = 60000;

    /**
     * Threshold for tapered annual allowance.
     */
    public const PENSION_TAPER_THRESHOLD = 260000;

    /**
     * Minimum tapered annual allowance.
     */
    public const PENSION_MINIMUM_ALLOWANCE = 10000;

    /**
     * Money Purchase Annual Allowance (triggered by flexi-access drawdown).
     */
    public const MPAA = 10000;

    // ==================== Income Tax (2026/27) ====================

    /**
     * Personal allowance - income below this is tax-free.
     */
    public const PERSONAL_ALLOWANCE = 12570;

    /**
     * Basic rate band - taxed at 20%.
     */
    public const BASIC_RATE_BAND = 37700;

    /**
     * Higher rate threshold (Personal Allowance + Basic Rate Band).
     */
    public const HIGHER_RATE_THRESHOLD = 50270;

    /**
     * Additional rate threshold.
     */
    public const ADDITIONAL_RATE_THRESHOLD = 125140;

    /**
     * Income level at which Personal Allowance starts to taper.
     */
    public const PERSONAL_ALLOWANCE_TAPER = 100000;

    // ==================== National Insurance (2026/27) ====================

    /**
     * National Insurance Class 1 employee primary threshold.
     */
    public const NI_PRIMARY_THRESHOLD = 12570;

    // ==================== Capital Gains Tax (2026/27) ====================

    /**
     * CGT annual exempt amount.
     */
    public const CGT_ANNUAL_EXEMPT = 3000;

    /**
     * CGT basic rate (residential property).
     */
    public const CGT_BASIC_RATE_PROPERTY = 0.18;

    /**
     * CGT higher rate (residential property).
     */
    public const CGT_HIGHER_RATE_PROPERTY = 0.24;

    /**
     * CGT basic rate (other assets).
     * Aligned with residential rates from 30 October 2024.
     */
    public const CGT_BASIC_RATE = 0.18;

    /**
     * CGT higher rate (other assets).
     * Aligned with residential rates from 30 October 2024.
     */
    public const CGT_HIGHER_RATE = 0.24;

    /**
     * Business Asset Disposal Relief rate.
     * Increased from 14% to 18% for disposals on or after 6 April 2026.
     */
    public const BADR_RATE = 0.18;

    // ==================== Dividend Tax (2026/27) ====================

    /**
     * Dividend allowance - tax-free dividend income.
     */
    public const DIVIDEND_ALLOWANCE = 500;

    /**
     * Dividend ordinary rate (basic rate taxpayers).
     * Increased from 8.75% to 10.75% for 2026/27.
     */
    public const DIVIDEND_BASIC_RATE = 0.1075;

    /**
     * Dividend upper rate (higher rate taxpayers).
     * Increased from 33.75% to 35.75% for 2026/27.
     */
    public const DIVIDEND_HIGHER_RATE = 0.3575;

    /**
     * Dividend additional rate.
     */
    public const DIVIDEND_ADDITIONAL_RATE = 0.3935;

    // ==================== Child Benefit (2026/27) ====================

    /**
     * High Income Child Benefit Charge threshold.
     */
    public const HICBC_THRESHOLD = 60000;

    /**
     * Income at which Child Benefit is fully withdrawn.
     */
    public const HICBC_FULL_WITHDRAWAL = 80000;

    // ==================== Default Growth Rates ====================

    /**
     * Default assumed growth rate for pension projections.
     * Conservative estimate net of inflation.
     */
    public const DEFAULT_GROWTH_RATE = 0.05;

    /**
     * Default safe withdrawal rate for retirement income calculations.
     */
    public const SAFE_WITHDRAWAL_RATE = 0.04;

    // ==================== Cache TTL Values ====================

    /**
     * Standard cache TTL for analysis data (24 hours).
     * Caches are invalidated immediately on data change via CacheInvalidationService.
     */
    public const CACHE_TTL_STANDARD = 86400;

    /**
     * Extended cache TTL for Monte Carlo simulations (24 hours).
     */
    public const CACHE_TTL_SIMULATION = 86400;
}
