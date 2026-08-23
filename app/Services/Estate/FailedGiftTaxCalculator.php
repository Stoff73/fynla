<?php

declare(strict_types=1);

namespace App\Services\Estate;

use App\Models\Estate\Gift;
use App\Models\User;
use App\Services\TaxConfigService;
use Illuminate\Support\Carbon;

/**
 * THE one answer to "what tax is due on this person's lifetime gifts, and how much
 * of their nil rate band have those gifts consumed" (Rule 20).
 *
 * Both questions come out of the SAME chronological walk, deliberately. They are two
 * readings of one allocation — a gift consumes the band first and is taxed only on
 * what is left over — and answering them in two places is how they drift apart.
 *
 * ## What was wrong before
 *
 * Taper relief was a boolean. `GiftingStrategy:71` emitted
 * `'taper_relief_applicable' => $yearsAgo >= 3` and `GiftingStrategyOptimizer:268`
 * hardcoded `'taper_relief_from_year' => 3`, while the graduated schedule sat in
 * `TaxConfigService` — 40% / 32% / 24% / 16% / 8% / 0% by years survived — read by
 * nothing. `getGiftTaxRate()` existed, correct, with zero callers. So a user with a
 * failed gift was told relief was "applicable" and never shown a figure, and no
 * tax on a failed gift was computed anywhere in the application.
 *
 * ## The rules this implements
 *
 * - A gift within the exemption window of death is chargeable (a "failed" PET).
 * - Gifts are cumulated **chronologically**: each consumes the nil rate band
 *   remaining at its own date, so an earlier gift shelters and a later one pays.
 * - Tax falls only on the part of a gift ABOVE the band still available to it.
 *   A gift covered by the band bears no tax, and therefore no taper — taper
 *   reduces tax, and there is nothing to reduce.
 * - Taper is set by years survived since the gift, from the configured schedule.
 * - Chargeable lifetime transfers made between the lookback and the full
 *   cumulation window consume band for later gifts but are not themselves taxed:
 *   they fall outside the window.
 *
 * ## Stated assumptions
 *
 * - **Death is assumed to be today**, which is what the whole estate service
 *   assumes ("PETs within 7 years of today (assumed death date)"). Years survived
 *   is therefore measured from the gift to today.
 * - **Lifetime tax already paid on a chargeable lifetime transfer is not credited
 *   against the death charge.** A CLT over the band attracts 20% when made, and
 *   that is set against the death tax. `lifetime_rate` is configured; no record of
 *   tax actually paid exists on `gifts`, so crediting it would be inventing a
 *   payment. Recorded rather than guessed — see the board item.
 */
final class FailedGiftTaxCalculator
{
    public function __construct(
        private readonly TaxConfigService $taxConfig,
    ) {}

    /**
     * Walk one member's gifts chronologically, allocating their nil rate band and
     * taxing what it does not cover.
     *
     * @return array{
     *   pets_in_7_years: float,
     *   clts_in_7_years: float,
     *   clts_7_to_14_years: float,
     *   nrb_used_by_clts: float,
     *   nrb_used_by_pets: float,
     *   total_nrb_used: float,
     *   fourteen_year_rule_applied: bool,
     *   failed_gift_tax: float,
     *   failed_gift_taper_saving: float,
     *   failed_gifts: list<array<string, mixed>>
     * }
     */
    public function forMember(User $member, float $nrbSingle): array
    {
        $petRules = $this->taxConfig->getPETRules();
        $cltRules = $this->taxConfig->getCLTRules();

        $petWindow = (int) ($petRules['years_to_exemption'] ?? 7);
        $cltWindow = (int) ($cltRules['lookback_period'] ?? 7);
        $cltCumulativeWindow = $cltWindow + (int) ($cltRules['cumulation_period'] ?? 7);

        $earliest = today()->subYears(max($petWindow, $cltCumulativeWindow));

        $gifts = Gift::where('user_id', $member->id)
            ->whereIn('gift_type', ['pet', 'clt'])
            ->where('gift_date', '>', $earliest)
            ->orderBy('gift_date')
            ->get();

        $nrbRemaining = $nrbSingle;

        $totals = [
            'pets_in_7_years' => 0.0,
            'clts_in_7_years' => 0.0,
            'clts_7_to_14_years' => 0.0,
            'nrb_used_by_clts' => 0.0,
            'nrb_used_by_pets' => 0.0,
            'failed_gift_tax' => 0.0,
            'failed_gift_taper_saving' => 0.0,
        ];
        $failedGifts = [];

        foreach ($gifts as $gift) {
            $value = (float) $gift->gift_value;
            if ($value <= 0) {
                continue;
            }

            $type = $gift->gift_type === 'clt' ? 'clt' : 'pet';
            $yearsSurvived = $this->yearsSince($gift->gift_date);
            $window = $type === 'clt' ? $cltWindow : $petWindow;
            $insideWindow = $yearsSurvived < $window;

            // Outside its own window a PET is exempt and drops out entirely; a
            // chargeable lifetime transfer still consumes band for later gifts
            // while it sits inside the cumulation window (the fourteen-year rule).
            if (! $insideWindow && $type === 'pet') {
                continue;
            }
            if (! $insideWindow && $yearsSurvived >= $cltCumulativeWindow) {
                continue;
            }

            $nrbUsed = min($value, $nrbRemaining);
            $nrbRemaining -= $nrbUsed;
            $chargeable = $value - $nrbUsed;

            if ($type === 'clt') {
                $totals['nrb_used_by_clts'] += $nrbUsed;
                $insideWindow
                    ? $totals['clts_in_7_years'] += $value
                    : $totals['clts_7_to_14_years'] += $value;
            } else {
                $totals['nrb_used_by_pets'] += $nrbUsed;
                $totals['pets_in_7_years'] += $value;
            }

            // Outside the window there is no death charge, only band consumption.
            if (! $insideWindow || $chargeable <= 0) {
                continue;
            }

            $taperedRate = $this->taxRate($yearsSurvived, $type);
            $fullRate = $this->deathRate($type);

            $tax = $chargeable * $taperedRate;
            $taxWithoutTaper = $chargeable * $fullRate;

            $totals['failed_gift_tax'] += $tax;
            $totals['failed_gift_taper_saving'] += max(0.0, $taxWithoutTaper - $tax);

            $failedGifts[] = [
                'gift_id' => $gift->id,
                'gift_type' => $type,
                'recipient' => $gift->recipient,
                'gift_date' => $gift->gift_date?->format('Y-m-d'),
                'gift_value' => round($value, 2),
                'years_survived' => round($yearsSurvived, 2),
                'covered_by_allowance' => round($nrbUsed, 2),
                'chargeable_amount' => round($chargeable, 2),
                'tax_rate' => $taperedRate,
                'tax_rate_percent' => round($taperedRate * 100, 1),
                'taper_saving' => round(max(0.0, $taxWithoutTaper - $tax), 2),
                'tax_due' => round($tax, 2),
            ];
        }

        // Capped at the member's OWN band by construction: `$nrbRemaining` starts at
        // `$nrbSingle` and only decreases, so one person's gifts can never reach
        // into their spouse's band (IHTA 1984 s8A transfers the unused PERCENTAGE,
        // which cannot go below zero).
        $totals['total_nrb_used'] = $totals['nrb_used_by_clts'] + $totals['nrb_used_by_pets'];

        return [
            ...array_map(fn (float $v): float => round($v, 2), $totals),
            'fourteen_year_rule_applied' => $totals['clts_7_to_14_years'] > 0,
            'failed_gifts' => $failedGifts,
        ];
    }

    /**
     * The tapered rate for a gift, from the configured schedule.
     *
     * The two schedules are shaped differently and only one of them was usable.
     * The potentially-exempt-transfer bands carry `tax_rate` outright (0.32 = "80%
     * of 40%"); the chargeable-lifetime-transfer bands carry `tax_percent` — the
     * PERCENTAGE OF THE DEATH RATE still payable — and no `tax_rate` at all. So
     * `TaxConfigService::getGiftTaxRate($years, 'clt')` matched no band and fell
     * through to its default: **every chargeable lifetime transfer was rated at the
     * full 40% however long the donor had survived, and taper never applied to one.**
     */
    private function taxRate(float $yearsSurvived, string $type): float
    {
        if ($type !== 'clt') {
            return $this->taxConfig->getGiftTaxRate($yearsSurvived, 'pet');
        }

        $deathRate = $this->deathRate('clt');

        foreach ($this->taxConfig->getTaperRelief('clt') as $band) {
            $min = (float) ($band['min_years'] ?? 0);
            $max = $band['max_years'] === null ? INF : (float) $band['max_years'];

            if ($yearsSurvived >= $min && $yearsSurvived < $max) {
                if (array_key_exists('tax_rate', $band)) {
                    return (float) $band['tax_rate'];
                }

                // Rounded: 0.40 × 20/100 lands on 0.08000000000000002, and this
                // rate is published, printed as a percentage and compared against
                // the full rate.
                return round($deathRate * ((float) ($band['tax_percent'] ?? 100) / 100), 6);
            }
        }

        return $deathRate;
    }

    private function deathRate(string $type): float
    {
        if ($type === 'clt') {
            $rate = $this->taxConfig->getCLTRules()['death_rate'] ?? null;
            if ($rate !== null) {
                return (float) $rate;
            }
        }

        return (float) ($this->taxConfig->getInheritanceTax()['standard_rate'] ?? 0.40);
    }

    /**
     * Years between the gift and the assumed date of death, as a fraction.
     *
     * Fractional deliberately: `diffInYears()` truncates, so a gift made 3 years
     * less a day ago reads as 2 and is charged at the full rate — the correct
     * answer — but one made 6 years and 11 months ago reads as 6 and is charged at
     * 8%, which is also correct. The band boundaries are exact anniversaries, so
     * comparing whole years works only if the truncation direction happens to be
     * right at every boundary. Comparing the real elapsed time removes the question.
     */
    private function yearsSince(?Carbon $date): float
    {
        if ($date === null) {
            return INF;
        }

        return $date->floatDiffInYears(today());
    }
}
