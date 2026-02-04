<?php

declare(strict_types=1);

namespace App\Constants;

/**
 * EstateDefaults - Estimated values used in estate planning calculations.
 *
 * These values are used when specific data is not available, typically during
 * onboarding or initial estate estimations. They represent conservative
 * UK averages and should be replaced with actual user data when available.
 *
 * Last reviewed: 4 February 2026
 *
 * @see https://www.ons.gov.uk/peoplepopulationandcommunity/housing
 */
final class EstateDefaults
{
    // ==================== Property Value Estimates ====================

    /**
     * Average UK property value estimate.
     * Based on ONS House Price Index (UK average).
     * Used when user indicates property ownership but hasn't provided values.
     */
    public const ESTIMATED_PROPERTY_VALUE = 300000;

    /**
     * Conservative investment portfolio estimate.
     * Used when user indicates investments but hasn't provided values.
     */
    public const ESTIMATED_INVESTMENT_VALUE = 100000;

    /**
     * Conservative savings estimate.
     * Used when user indicates savings but hasn't provided values.
     */
    public const ESTIMATED_SAVINGS_VALUE = 50000;

    /**
     * Conservative business interest estimate.
     * Used when user indicates business ownership but hasn't provided values.
     * Note: Business interests vary wildly; this is very conservative.
     */
    public const ESTIMATED_BUSINESS_VALUE = 200000;

    // ==================== Thresholds ====================

    /**
     * RNRB taper threshold.
     * When estate exceeds this value, Residence Nil Rate Band begins to taper.
     * RNRB is reduced by £1 for every £2 above this threshold.
     */
    public const RNRB_TAPER_THRESHOLD = 2000000;

    /**
     * Threshold for suggesting trust structures.
     * When estate value exceeds this, advanced planning may be beneficial.
     */
    public const TRUST_SUGGESTION_THRESHOLD = 2000000;

    /**
     * Combined Nil Rate Band threshold for couples.
     * Maximum transferable NRB between spouses (2 × NRB).
     */
    public const COMBINED_NRB_THRESHOLD = 650000;

    /**
     * Combined RNRB threshold for couples.
     * Maximum transferable RNRB between spouses (2 × RNRB).
     */
    public const COMBINED_RNRB_THRESHOLD = 350000;

    // ==================== Life Expectancy Estimates ====================

    /**
     * Default life expectancy used for planning calculations.
     * Conservative estimate based on UK actuarial data.
     */
    public const DEFAULT_LIFE_EXPECTANCY = 85;

    /**
     * Default current age when user age is unknown.
     */
    public const DEFAULT_CURRENT_AGE = 50;
}
