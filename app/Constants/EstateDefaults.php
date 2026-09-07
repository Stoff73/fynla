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
    // ==================== Lasting Power of Attorney registration ====================

    /**
     * What the Office of the Public Guardian charges to register one Lasting
     * Power of Attorney, and how long it says registration takes.
     *
     * **W-0109 — one home.** These two figures were written out in FOUR places
     * with no shared source: twice in `LpaComplianceService` and twice in the
     * frontend (`LpaWizardSteps/ReviewStep.vue`, `PowerOfAttorneyTab.vue`).
     * Nothing connected them, so the OPG changing either would have needed four
     * edits by someone who knew all four existed.
     *
     * **The timescale was already stale, which is the proof the arrangement does
     * not work:** every copy said "up to 8 weeks" long after the OPG's published
     * figure moved to 20. Four copies drifted together because none of them was
     * anybody's responsibility.
     *
     * Not in `TaxConfigService`: this is an administrative fee charged by an
     * agency, not a tax rate, and putting it there would imply it moves with the
     * tax year. It does not.
     *
     * The frontend has its own copy in `resources/js/constants/lpaRegistration.js`
     * — two languages, one home each, cross-referenced. Change one, change both.
     */
    public const LPA_REGISTRATION_FEE = 82;

    /** Upper bound of the OPG's published registration timescale, in weeks. */
    public const LPA_REGISTRATION_WEEKS = 20;

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
