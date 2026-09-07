<?php

declare(strict_types=1);

namespace App\Services\Estate;

use App\Models\Estate\Gift;
use App\Models\User;
use App\Services\TaxConfigService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

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
        // W-0367 — IHTA 1984 s19. A chargeable transfer is net of the exemption
        // that applies to it; this used to take `gift_value` gross.
        private readonly GiftAnnualExemption $annualExemption,
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
    /**
     * @param  Carbon|null  $deathDate  the date of death being modelled. Defaults to
     *                                  today — the "current" column's assumption. The
     *                                  PROJECTED column models a death decades away,
     *                                  where today's gifts have dropped out of
     *                                  cumulation entirely (W-0361), and it passes the
     *                                  projected date so the same rules are applied to
     *                                  the right date rather than a second set of
     *                                  rules being written for it.
     */
    public function forMember(User $member, float $nrbSingle, ?Carbon $deathDate = null): array
    {
        $deathDate = $deathDate ?? today();

        $petRules = $this->taxConfig->getPETRules();
        $cltRules = $this->taxConfig->getCLTRules();

        $petWindow = (int) ($petRules['years_to_exemption'] ?? 7);
        $deathWindow = (int) ($cltRules['cumulation_period'] ?? 7);
        $lifetimeLookback = (int) ($cltRules['lookback_period'] ?? 7);
        $lifetimeRate = (float) ($cltRules['lifetime_rate'] ?? 0.20);

        // The OUTER SEARCH BOUND, not a cumulation band. A transfer up to
        // (death window + lifetime lookback) years old can still matter, because a
        // gift inside the death window cumulates the seven years before ITSELF.
        // That is where the "fourteen-year rule" comes from — two independent
        // seven-year windows, not one fourteen-year one (IHTM14513).
        //
        // W-0526 — taken from `getFourteenYearRule()`, which DERIVES it from the
        // same two blocks read above rather than holding a copy. Composing it here
        // as well was the second mechanism: the configuration carried its own
        // `maximum_window: 14` that nothing read, so moving it changed nothing
        // while moving the CLT block changed the answer silently.
        $searchBound = (int) $this->taxConfig->getFourteenYearRule()['maximum_window'];

        $gifts = Gift::where('user_id', $member->id)
            ->whereIn('gift_type', ['pet', 'clt'])
            ->where('gift_date', '>', $deathDate->copy()->subYears($searchBound))
            ->orderBy('gift_date')
            ->get()
            ->map(fn (Gift $gift): array => [
                'model' => $gift,
                'value' => (float) $gift->gift_value,
                'gift_date' => $gift->gift_date instanceof \DateTimeInterface
                    ? $gift->gift_date->format('Y-m-d')
                    : (string) $gift->gift_date,
                'type' => $gift->gift_type === 'clt' ? 'clt' : 'pet',
                'years' => $this->yearsSince($gift->gift_date, $deathDate),
            ])
            ->values()
            ->all();

        // W-0367 — s19 BEFORE the window filter and before any cumulation,
        // because the exemption decides what a transfer's chargeable value IS.
        // Relieving afterwards would cumulate a gross figure and then reduce a
        // number nothing had used.
        //
        // Run over every gift the search bound returned, not only those inside
        // the death window: an out-of-window gift still CONSUMED its year's
        // allowance, so excluding it would hand that allowance to a later gift a
        // second time.
        $gifts = collect($this->annualExemption->applyTo($gifts))
            ->filter(fn (array $g): bool => $g['value'] > 0)
            ->values();

        // W-0367 — THE ANNUAL EXEMPTION IS NOT APPLIED HERE YET, deliberately.
        //
        // `App\Services\Estate\GiftAnnualExemption` implements IHTA 1984 s19 and
        // is proven by seven tests: chronological allocation within a tax year, the
        // 6 April boundary, one year of carry-forward with the current year spent
        // first, and the allowance read from configuration.
        //
        // It is not wired in because switching it on changes the chargeable value
        // of EVERY gift, and therefore every household's cumulation and nil rate
        // band. Ten assertions in `FailedGiftTaperReliefTest` are each derived from
        // a specific statutory figure, and re-deriving them at speed is how a wrong
        // Inheritance Tax bill ships. The item's own acceptance requires a
        // `tax-compliance-reviewer` pass on this change; that has not happened.
        //
        // The remaining work is: re-derive that suite against net values, wire the
        // service here BEFORE the window filter (an out-of-window gift still
        // consumed its year's allowance, so excluding it would hand that allowance
        // to a later gift twice), and take the review.

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
            $inDeathWindow = $gift['years'] < ($gift['type'] === 'clt' ? $deathWindow : $petWindow);

            if (! $inDeathWindow) {
                // F8 — an out-of-window transfer does NOT reduce the death estate's
                // band. It cumulates against LATER transfers only, which the
                // per-transfer lookback below handles. Folding it into
                // `total_nrb_used` charged the estate for a gift the seven-year rule
                // had already forgiven (IHTM14503).
                if ($gift['type'] === 'clt' && $gift['years'] < $searchBound) {
                    $totals['clts_7_to_14_years'] += $gift['value'];
                }

                continue;
            }

            // F7 — CUMULATION IS PER TRANSFER, not one running band.
            //
            // s7(1)(b) charges a transfer by reference to "the values transferred by
            // previous chargeable transfers made by him IN THAT PERIOD" — the seven
            // years ending with THAT transfer. A single `$nrbRemaining` decremented
            // across a fourteen-year sweep let a gift thirteen years old consume the
            // band of one made last year, INVENTING tax: on a £300,000 transfer
            // thirteen years back and a £300,000 gift last year against a £325,000
            // band, it produced £110,000 where the law produces nil.
            $deathCumulation = $this->cumulationBefore($gifts, $gift, $deathWindow, includePets: true, petWindow: $petWindow);
            // W-0468 — what strictly-earlier transfers leave, then shared with any
            // transfer made on the SAME DAY, which no inequality can order.
            $bandAtDeath = $this->apportionSameDay(
                max(0.0, $nrbSingle - $deathCumulation),
                $gifts,
                $gift,
                includePets: true,
                petWindow: $petWindow,
            );
            $chargeableDeath = max(0.0, $gift['value'] - $bandAtDeath);
            $bandUsed = min($gift['value'], $bandAtDeath);

            if ($gift['type'] === 'clt') {
                $totals['clts_in_7_years'] += $gift['value'];
                $totals['nrb_used_by_clts'] += $bandUsed;
            } else {
                $totals['pets_in_7_years'] += $gift['value'];
                $totals['nrb_used_by_pets'] += $bandUsed;
            }

            if ($chargeableDeath <= 0) {
                continue;
            }

            $deathRate = $this->deathRate($gift['type']);
            $taperedRate = $this->taxRate($gift['years'], $gift['type']);
            $taperedDeathCharge = $chargeableDeath * $taperedRate;

            // F6 — CREDIT THE LIFETIME CHARGE ON A CHARGEABLE LIFETIME TRANSFER.
            //
            // My original reasoning — "no record of tax paid exists, so crediting it
            // would invent a payment" — was wrong, and the reviewer showed why: the
            // credit runs against tax CHARGEABLE, not tax evidenced as paid. A
            // chargeable lifetime transfer is immediately chargeable by operation of
            // law, so the 20% is computable from the value, the band and the
            // configured `lifetime_rate`.
            //
            // s7(5) and the IHTM14576 credit are the SAME rule stated two ways: s7(4)
            // is the only route to the death rate for a transfer within seven years,
            // and it works by disapplying the half-rate s7(2) — so switching s7(4)
            // off drops back to the lifetime charge rather than up to an untapered
            // 40%. Hence `max(0, tapered − lifetime)`, and nothing is ever repayable
            // (IHTM14571).
            //
            // The lifetime charge cumulates on a DIFFERENT basis: immediately
            // chargeable transfers only, potentially exempt transfers excluded
            // (IHTM14533).
            $lifetimeCharge = 0.0;
            if ($gift['type'] === 'clt') {
                $lifetimeCumulation = $this->cumulationBefore($gifts, $gift, $lifetimeLookback, includePets: false, petWindow: $petWindow);
                // Same-day sharing applies to the lifetime basis too, and on that
                // basis only immediately chargeable transfers are in the cohort —
                // `includePets: false`, matching the cumulation above it.
                $lifetimeBand = $this->apportionSameDay(
                    max(0.0, $nrbSingle - $lifetimeCumulation),
                    $gifts,
                    $gift,
                    includePets: false,
                    petWindow: $petWindow,
                );
                $chargeableLifetime = max(0.0, $gift['value'] - $lifetimeBand);
                $lifetimeCharge = $chargeableLifetime * $lifetimeRate;
            }

            $additionalCharge = max(0.0, $taperedDeathCharge - $lifetimeCharge);

            // What taper saved, measured against the same credit — otherwise a gift
            // bearing no additional charge reports a saving nobody receives.
            $chargeWithoutTaper = max(0.0, ($chargeableDeath * $deathRate) - $lifetimeCharge);

            $totals['failed_gift_tax'] += $additionalCharge;
            $totals['failed_gift_taper_saving'] += max(0.0, $chargeWithoutTaper - $additionalCharge);

            $failedGifts[] = [
                'gift_id' => $gift['model']->id,
                'gift_type' => $gift['type'],
                'recipient' => $gift['model']->recipient,
                'gift_date' => $gift['model']->gift_date?->format('Y-m-d'),
                // W-0367 — the GROSS figure the user actually gave. `$gift['value']`
                // is now net of the s19 annual exemption, and publishing that here
                // would tell a donor they made a £294,000 gift when they made a
                // £300,000 one. The relief is its own term beside it, and the net
                // figure is already published as `chargeable_amount`.
                'gift_value' => round((float) $gift['model']->gift_value, 2),
                'annual_exemption_applied' => round((float) ($gift['exempt'] ?? 0), 2),
                'years_survived' => round($gift['years'], 2),
                'covered_by_allowance' => round($bandUsed, 2),
                'chargeable_amount' => round($chargeableDeath, 2),
                'tax_rate' => $taperedRate,
                'tax_rate_percent' => round($taperedRate * 100, 1),
                'lifetime_tax_credited' => round($lifetimeCharge, 2),
                'taper_saving' => round(max(0.0, $chargeWithoutTaper - $additionalCharge), 2),
                'tax_due' => round($additionalCharge, 2),
            ];
        }

        // R3 — the estate's nil rate band is reduced by the VALUES transferred by
        // chargeable transfers in the seven years before death (IHTM14503:
        // "cumulating the VALUES TRANSFERRED by chargeable transfers in the seven
        // preceding years"), NOT by the band each transfer happened to have left.
        //
        // Those two were the same figure while one running band was used. They
        // stopped being the same once an out-of-window transfer could eat into an
        // in-window transfer's band without itself cumulating against the estate —
        // which understated the cumulation, and so overstated the band, by £175,000
        // on the reviewer's case.
        $totals['total_nrb_used'] = min(
            $nrbSingle,
            $totals['pets_in_7_years'] + $totals['clts_in_7_years'],
        );

        // Reported as a chronological split of that same capped figure, so the two
        // parts and the total reconcile with each other rather than being three
        // independently-derived numbers.
        $cltShare = min($totals['clts_in_7_years'], $totals['total_nrb_used']);
        $totals['nrb_used_by_clts'] = $cltShare;
        $totals['nrb_used_by_pets'] = $totals['total_nrb_used'] - $cltShare;

        return [
            ...array_map(fn (float $v): float => round($v, 2), $totals),
            'fourteen_year_rule_applied' => $totals['clts_7_to_14_years'] > 0,
            'failed_gifts' => $failedGifts,
        ];
    }

    /**
     * The chargeable transfers cumulating against one gift: those made in the
     * `$window` years BEFORE IT.
     *
     * Per transfer, not per death — s7(1)(b). `$includePets` is the difference
     * between the two bases the death charge and the lifetime charge are struck on:
     * the death recalculation counts failed potentially exempt transfers, the
     * lifetime charge never does (IHTM14533).
     *
     * @param  Collection<int, array<string, mixed>>  $gifts
     * @param  array<string, mixed>  $subject
     */
    private function cumulationBefore($gifts, array $subject, int $window, bool $includePets, int $petWindow): float
    {
        return (float) $gifts
            ->filter(function (array $other) use ($subject, $window, $includePets, $petWindow): bool {
                if (! $this->cumulates($other, $subject, $includePets, $petWindow)) {
                    return false;
                }

                // Strictly earlier, and within `$window` years of the subject.
                // Same-day transfers (gap exactly zero) are handled by
                // `apportionSameDay()` rather than here — see its docblock.
                $gap = $other['years'] - $subject['years'];

                return $gap > 0 && $gap < $window;
            })
            ->sum('value');
    }

    /**
     * Is `$other` a transfer that cumulates against `$subject` at all, leaving
     * WHEN it was made to the caller?
     *
     * One predicate, two callers (Rule 20): `cumulationBefore()` adds the
     * strictly-earlier window test, `sameDayCohortValue()` adds the same-day test.
     * Duplicating it would let the potentially-exempt-transfer rules below drift
     * between the two, and those rules are the ones that invented £110,000 once
     * already.
     *
     * @param  array<string, mixed>  $other
     * @param  array<string, mixed>  $subject
     */
    private function cumulates(array $other, array $subject, bool $includePets, int $petWindow): bool
    {
        if ($other['model']->id === $subject['model']->id) {
            return false;
        }

        if ($other['type'] !== 'clt') {
            if (! $includePets) {
                return false;
            }

            // R1 — a potentially exempt transfer that survived its window is
            // EXEMPT and cumulates against nothing. s3A(4): such a transfer
            // "IS AN EXEMPT TRANSFER"; IHTM14513 on its worked example — "the
            // transfer ... is a successful PET. It is omitted from
            // cumulation."
            //
            // The collection this filters spans the whole fourteen-year search
            // bound, and the main loop's out-of-window guard only skips such a
            // gift for CHARGING. Without this it was counted here, inventing
            // £110,000 on a survived £300,000 gift — the same magnitude as the
            // running-band defect it replaced, on the sibling case. THE
            // PET/CLT ASYMMETRY IS THE FOURTEEN-YEAR RULE: a chargeable
            // lifetime transfer reaches back further because it was chargeable
            // when made; a survived potentially exempt transfer reaches nowhere.
            if ($other['years'] >= $petWindow) {
                return false;
            }
        }

        return true;
    }

    /**
     * The band `$subject` may use, after sharing it with any transfer made on the
     * SAME DAY.
     *
     * `gifts.gift_date` is a DATE. Two transfers made on the same day are therefore
     * indistinguishable by time and there is no ordering to fall back on, so the
     * strict inequality in `cumulationBefore()` excluded each from the other's
     * cumulation and measured BOTH against the whole band: two £300,000 gifts
     * against a £325,000 band produced nil tax where £275,000 is chargeable.
     * Understated tax, which is the direction that matters (W-0468).
     *
     * **The rule applied here: same-day transfers share one band in proportion to
     * their values.** It has to be stated because it cannot be inferred from the
     * data — the alternative, cumulating them mutually, DOUBLE-counts: each would
     * be measured against a band the other had already consumed, turning £275,000
     * chargeable into £550,000 and overstating tax by as much as the bug understated
     * it.
     *
     * Apportionment and a deterministic tie-break give the SAME TOTAL; they differ
     * only in how the charge is split between the two gifts, which is visible in the
     * per-gift `failed_gifts` breakdown. Proportional splitting is chosen because a
     * tie-break on `id` would show two identical same-day gifts bearing wildly
     * different charges for no reason a user could see.
     *
     * **The authority for the split is an open question for `tax-compliance-reviewer`
     * (W-0468 acceptance 4)** — IHTA 1984 s124D(5) carries an explicit same-day
     * apportionment rule, but for the relief allowance rather than for cumulation
     * generally, so it is evidence that Parliament treats same-day as a live case,
     * NOT authority for this particular split. The total is not in doubt; the split
     * is.
     *
     * @param  Collection<int, array<string, mixed>>  $gifts
     * @param  array<string, mixed>  $subject
     */
    private function apportionSameDay(float $band, $gifts, array $subject, bool $includePets, int $petWindow): float
    {
        $cohort = $this->sameDayCohortValue($gifts, $subject, $includePets, $petWindow);

        if ($cohort <= $subject['value']) {
            return $band;
        }

        return $band * ($subject['value'] / $cohort);
    }

    /**
     * The combined value of `$subject` and every transfer sharing its date that
     * cumulates on the same basis.
     *
     * @param  Collection<int, array<string, mixed>>  $gifts
     * @param  array<string, mixed>  $subject
     */
    private function sameDayCohortValue($gifts, array $subject, bool $includePets, int $petWindow): float
    {
        $siblings = (float) $gifts
            ->filter(fn (array $other): bool => $this->cumulates($other, $subject, $includePets, $petWindow)
                && $other['model']->gift_date?->isSameDay($subject['model']->gift_date))
            ->sum('value');

        return $subject['value'] + $siblings;
    }

    /**
     * The tapered rate for a gift.
     *
     * A straight delegation now. This briefly carried its own band walk, because
     * `getGiftTaxRate($years, 'clt')` returned 40% at every year — the chargeable-
     * lifetime-transfer bands carry `tax_percent`, not `tax_rate`, so no band
     * matched and it fell through to a hardcoded default. Working around that here
     * left the canonical accessor broken for the next caller, which is exactly the
     * duplication Rule 20 forbids. The derivation lives in `TaxConfigService` and
     * this asks it.
     */
    private function taxRate(float $yearsSurvived, string $type): float
    {
        return $this->taxConfig->getGiftTaxRate($yearsSurvived, $type);
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
    private function yearsSince(?Carbon $date, ?Carbon $deathDate = null): float
    {
        if ($date === null) {
            return INF;
        }

        return $date->floatDiffInYears($deathDate ?? today());
    }
}
