<?php

declare(strict_types=1);

namespace App\Constants;

use App\Services\TaxConfigService;

/**
 * EstateDefaults - Threshold constants used in estate planning calculations.
 *
 * Onboarding estimates (ESTIMATED_PROPERTY_VALUE, ESTIMATED_INVESTMENT_VALUE,
 * ESTIMATED_SAVINGS_VALUE, ESTIMATED_BUSINESS_VALUE) and default life expectancy/age
 * constants have been moved to TaxConfigService under 'estate.onboarding_estimates'.
 *
 * Threshold constants are retained for convenience but sourced from TaxConfigService
 * where possible, with hardcoded fallbacks for when the service is unavailable.
 *
 * Last reviewed: 14 March 2026
 *
 * @see https://www.ons.gov.uk/peoplepopulationandcommunity/housing
 */
final class EstateDefaults
{
    // ==================== Thresholds ====================

    /**
     * RNRB taper threshold.
     * When estate exceeds this value, Residence Nil Rate Band begins to taper.
     * RNRB is reduced by £1 for every £2 above this threshold.
     *
     * Sourced from TaxConfigService inheritance_tax.rnrb_taper_threshold.
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

    /**
     * Get RNRB taper threshold from TaxConfigService, falling back to constant.
     */
    public static function getRnrbTaperThreshold(): int
    {
        try {
            $taxConfig = app(TaxConfigService::class);
            $ihtConfig = $taxConfig->getInheritanceTax();

            return (int) ($ihtConfig['rnrb_taper_threshold'] ?? self::RNRB_TAPER_THRESHOLD);
        } catch (\Throwable) {
            return self::RNRB_TAPER_THRESHOLD;
        }
    }
}
