<?php

declare(strict_types=1);

namespace App\Services\Estate;

use App\Services\TaxConfigService;

/**
 * Quick succession relief — IHTA 1984 s141.
 *
 * Where the same property bears Inheritance Tax twice in quick succession — a
 * beneficiary inherits, tax is paid, and the beneficiary then dies within five
 * years — s141 reduces the tax on the second death, on a taper by the years
 * between the two deaths.
 *
 *     relief = tax paid on the first death
 *            × (net value received ÷ gross transfer on the first death)
 *            × the taper for the years elapsed
 *
 * **W-0527.** The taper was configured in `inheritance_tax.quick_succession_relief`
 * and read by nothing: `getQuickSuccessionRelief()` had zero callers, so the five
 * bands governed no calculation. This class is the caller, and it reads the bands
 * rather than reproducing them — a household under a different configured taper
 * gets that taper (Rule 2).
 *
 * The relief is DELIBERATELY not approximated where the inputs are absent. The
 * tax paid on the first death is the multiplicand of the whole formula and the
 * one figure the application did not capture; without it there is no relief to
 * state, and inventing one would be a made-up tax figure on a user's estate.
 */
class QuickSuccessionReliefCalculator
{
    public function __construct(private readonly TaxConfigService $taxConfig) {}

    /**
     * The s141 relief, or 0.0 where it cannot be established.
     *
     * @param  float  $taxPaidOnFirstDeath  Inheritance Tax borne on the earlier death
     * @param  float  $netValueReceived  what reached the beneficiary, after that tax
     * @param  float  $grossTransfer  the transfer on the earlier death, before it
     * @param  float  $yearsBetweenDeaths  elapsed years, fractional
     */
    public function reliefFor(
        float $taxPaidOnFirstDeath,
        float $netValueReceived,
        float $grossTransfer,
        float $yearsBetweenDeaths,
    ): float {
        if ($taxPaidOnFirstDeath <= 0.0 || $grossTransfer <= 0.0 || $yearsBetweenDeaths < 0.0) {
            return 0.0;
        }

        $taper = $this->taperFor($yearsBetweenDeaths);

        if ($taper <= 0.0) {
            return 0.0;
        }

        // Capped at 1. A net value exceeding the gross transfer is bad data, and
        // must not turn into relief larger than the tax that was actually paid.
        $fraction = min(1.0, $netValueReceived / $grossTransfer);

        return round($taxPaidOnFirstDeath * $fraction * $taper, 2);
    }

    /**
     * The configured taper for the years elapsed.
     *
     * Bands are read in configured order and the FIRST whose `max_years` the
     * elapsed time does not exceed wins, so the table can be re-banded — more
     * bands, fewer, different rates — without this method changing. Beyond the
     * last band there is no relief.
     */
    private function taperFor(float $yearsBetweenDeaths): float
    {
        $rules = $this->taxConfig->getQuickSuccessionRelief();

        foreach ($rules['relief_rates'] ?? [] as $band) {
            if ($yearsBetweenDeaths <= (float) ($band['max_years'] ?? 0)) {
                return (float) ($band['relief'] ?? 0.0);
            }
        }

        return 0.0;
    }
}
