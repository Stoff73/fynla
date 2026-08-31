<?php

declare(strict_types=1);

use App\Models\Estate\Gift;
use App\Models\TaxConfiguration;
use App\Models\User;
use App\Services\Estate\FailedGiftTaxCalculator;
use App\Services\TaxConfigService;
use Database\Seeders\TaxConfigurationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * W-0463 — taper relief on failed potentially exempt transfers. CSJ: critical.
 *
 * Taper relief was a boolean. `GiftingStrategy` published
 * `'taper_relief_applicable' => $yearsAgo >= 3` and stopped there, while the
 * graduated schedule — 40% / 32% / 24% / 16% / 8% / 0% by years survived — sat in
 * `TaxConfigService`, read by nothing. `getGiftTaxRate()` was written, correct, and
 * had zero callers. **No tax on a failed gift was computed anywhere.**
 *
 * The rule these assert, and the one most easily got wrong: taper reduces the TAX,
 * not the value, and only where there IS tax — a gift covered by the nil rate band
 * bears none, so it gets no taper however long ago it was made.
 */
uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(TaxConfigurationSeeder::class);
    $this->user = User::factory()->create();
    $this->calc = app(FailedGiftTaxCalculator::class);
    $this->nrb = 325_000.0;
});

/**
 * W-0367 — every figure below is now NET of the IHTA 1984 s19 annual exemption.
 *
 * A single gift draws £3,000 for its own tax year plus £3,000 carried forward
 * from the unused year before it, so a lone gift is relieved by £6,000 before
 * anything else happens to it. Gifts on the same day divide that between them
 * pro rata (IHTM14143).
 *
 * The expectations were re-derived by hand rather than read off the new output,
 * and each carries its arithmetic. The taper bands, the cumulation rules and the
 * lifetime credit are all unchanged — only the chargeable value they operate on
 * has moved.
 */
function gift(User $user, string $type, float $value, float $yearsAgo): Gift
{
    return Gift::factory()->create([
        'user_id' => $user->id,
        'gift_type' => $type,
        'gift_value' => $value,
        // Half a year past the boundary, so a band is unambiguously selected.
        'gift_date' => today()->subDays((int) round($yearsAgo * 365.25)),
    ]);
}

it('charges nothing on a gift the allowance covers, however recent', function () {
    gift($this->user, 'pet', 100_000, 1);

    $r = $this->calc->forMember($this->user, $this->nrb);

    // Taper reduces tax. There is no tax here, so there is nothing to reduce —
    // the gift simply consumes £100,000 of the band.
    // £100,000 less the £6,000 annual exemption = £94,000 chargeable, all of it
    // inside the £325,000 band.
    expect($r['failed_gift_tax'])->toBe(0.0)
        ->and($r['total_nrb_used'])->toBe(94_000.0)
        ->and($r['failed_gifts'])->toBe([]);
});

it('charges the full rate on the excess of a recent gift', function () {
    gift($this->user, 'pet', 425_000, 1);

    $r = $this->calc->forMember($this->user, $this->nrb);

    // £425,000 less £6,000 exemption = £419,000. £325,000 covered, £94,000
    // chargeable, under three years so no taper: 40% = £37,600.
    expect($r['failed_gift_tax'])->toBe(37_600.0)
        ->and($r['failed_gifts'][0]['chargeable_amount'])->toBe(94_000.0)
        ->and($r['failed_gifts'][0]['tax_rate_percent'])->toBe(40.0)
        ->and($r['failed_gift_taper_saving'])->toBe(0.0);
});

it('tapers the charge by years survived', function (float $yearsAgo, float $expectedRate, float $expectedTax) {
    gift($this->user, 'pet', 425_000, $yearsAgo);

    $r = $this->calc->forMember($this->user, $this->nrb);

    expect($r['failed_gifts'][0]['tax_rate'])->toBe($expectedRate)
        ->and($r['failed_gift_tax'])->toBe($expectedTax);
})->with([
    // £94,000 chargeable throughout (£425,000 − £6,000 exemption − £325,000 band).
    'under 3 years — no relief' => [1.0, 0.40, 37_600.0],
    '3-4 years — 20% relief' => [3.5, 0.32, 30_080.0],
    '4-5 years — 40% relief' => [4.5, 0.24, 22_560.0],
    '5-6 years — 60% relief' => [5.5, 0.16, 15_040.0],
    '6-7 years — 80% relief' => [6.5, 0.08, 7_520.0],
]);

it('drops a gift out entirely once the seven years are survived', function () {
    gift($this->user, 'pet', 425_000, 7.5);

    $r = $this->calc->forMember($this->user, $this->nrb);

    expect($r['failed_gift_tax'])->toBe(0.0)
        ->and($r['total_nrb_used'])->toBe(0.0, 'a survived gift releases the band it was using');
});

it('reports what taper actually saved', function () {
    gift($this->user, 'pet', 425_000, 6.5);

    $r = $this->calc->forMember($this->user, $this->nrb);

    // £94,000 chargeable: £37,600 at the full rate, £7,520 after taper.
    expect($r['failed_gift_taper_saving'])->toBe(30_080.0)
        ->and($r['failed_gifts'][0]['taper_saving'])->toBe(30_080.0);
});

it('cumulates each gift against the seven years before ITSELF, not one running band', function () {
    gift($this->user, 'pet', 300_000, 6.5);
    gift($this->user, 'pet', 200_000, 1.0);

    $r = $this->calc->forMember($this->user, $this->nrb);

    // Each gift is relieved by £6,000 in its own tax year: £294,000 and £194,000.
    // Both are inside each other's seven-year lookback (5.5 years apart), so the
    // recent gift DOES cumulate the older one: band £325,000 − £294,000 = £31,000,
    // leaving £163,000 chargeable at the full 40% = £65,200.
    expect($r['failed_gift_tax'])->toBe(65_200.0)
        ->and(collect($r['failed_gifts'])->firstWhere('gift_value', 200_000.0)['tax_rate_percent'])->toBe(40.0);
});

it('does not let a transfer outside a gift\'s own lookback consume its allowance', function () {
    // F7. Thirteen years and one year — twelve years apart, so the older transfer
    // is outside the recent gift's seven-year lookback and cumulates nothing.
    gift($this->user, 'clt', 300_000, 13.0);
    gift($this->user, 'pet', 300_000, 1.0);

    $r = $this->calc->forMember($this->user, $this->nrb);

    // £300,000 is under the £325,000 band, so no tax. A single running band across
    // a fourteen-year sweep charged £110,000 here — tax the law does not levy.
    expect($r['failed_gift_tax'])->toBe(0.0);
});

it('does not let an out-of-window transfer reduce the death estate\'s allowance', function () {
    // F8. A chargeable transfer ten years before death is forgiven by the
    // seven-year rule; it cumulates against LATER gifts only, never against the
    // estate. Folding it into total_nrb_used charged the estate £80,000 too much.
    gift($this->user, 'clt', 200_000, 10.0);
    gift($this->user, 'pet', 100_000, 2.0);

    $r = $this->calc->forMember($this->user, $this->nrb);

    // Only the failed £100,000 potentially exempt transfer touches the estate's
    // band. It IS inside the older transfer's reach for its own cumulation, but
    // that is a different question from what the estate may claim.
    // £100,000 less £6,000 exemption = £94,000.
    expect($r['nrb_used_by_pets'])->toBe(94_000.0)
        ->and($r['nrb_used_by_clts'])->toBe(0.0)
        ->and($r['total_nrb_used'])->toBe(94_000.0)
        ->and($r['fourteen_year_rule_applied'])->toBeTrue();
});

it('credits the lifetime charge already borne by a chargeable lifetime transfer', function () {
    gift($this->user, 'clt', 425_000, 6.5);

    $r = $this->calc->forMember($this->user, $this->nrb);

    // This asserted £8,000 — self-consistent arithmetic (0.08 × 100,000) rather
    // than law, and the error ran against the user.
    //
    // s7(4) is the only route to the death rate for a transfer within seven years,
    // and it works by disapplying the half-rate s7(2). s7(5) switches s7(4) off
    // when the tapered figure is lower — which drops back to the lifetime charge,
    // it does not rise to an untapered 40%. So s7(5) and the IHTM14576 credit are
    // one rule: additional = max(0, tapered − lifetime), nothing repayable
    // (IHTM14571).
    //
    // £94,000 chargeable. Tapered at 6.5 years: £7,520. Lifetime charge already
    // borne at 20%: £18,800. £7,520 < £18,800, so NO ADDITIONAL TAX IS DUE.
    expect($r['failed_gifts'][0]['tax_rate'])->toBe(0.08)
        ->and($r['failed_gifts'][0]['lifetime_tax_credited'])->toBe(18_800.0)
        ->and($r['failed_gift_tax'])->toBe(0.0);
});

it('charges only the excess over the lifetime tax when the taper has barely run', function () {
    gift($this->user, 'clt', 425_000, 1.0);

    $r = $this->calc->forMember($this->user, $this->nrb);

    // £94,000 chargeable at the full 40% = £37,600, less the £18,800 lifetime
    // charge = £18,800 additional. Total borne £37,600, which is the death rate.
    expect($r['failed_gift_tax'])->toBe(18_800.0);
});

it('stops charging a chargeable lifetime transfer once the tapered rate falls below the lifetime rate', function () {
    // Derived from configuration, not asserted as "five years": the crossover is
    // wherever the tapered rate drops below `lifetime_rate`. Move lifetime_rate and
    // this must move with it.
    $cltRules = app(TaxConfigService::class)->getCLTRules();
    $lifetimeRate = (float) $cltRules['lifetime_rate'];

    $stillCharged = null;
    $noLongerCharged = null;

    foreach ([1.0, 3.5, 4.5, 5.5, 6.5] as $years) {
        $user = User::factory()->create();
        gift($user, 'clt', 425_000, $years);
        $tax = app(FailedGiftTaxCalculator::class)->forMember($user, $this->nrb)['failed_gift_tax'];
        $rate = app(TaxConfigService::class)->getGiftTaxRate($years, 'clt');

        $rate > $lifetimeRate
            ? $stillCharged = $tax
            : $noLongerCharged = $tax;
    }

    expect($stillCharged)->toBeGreaterThan(0.0)
        ->and($noLongerCharged)->toBe(0.0);
});

it('reads the schedule from configuration rather than a literal', function () {
    gift($this->user, 'pet', 425_000, 6.5);

    $config = TaxConfiguration::where('is_active', true)->first();
    $data = is_string($config->config_data) ? json_decode($config->config_data, true) : $config->config_data;
    foreach ($data['inheritance_tax']['potentially_exempt_transfers']['taper_relief'] as $i => $band) {
        if (($band['min_years'] ?? null) === 6) {
            $data['inheritance_tax']['potentially_exempt_transfers']['taper_relief'][$i]['tax_rate'] = 0.05;
        }
    }
    $config->update(['config_data' => $data]);
    app(TaxConfigService::class)->clearCache();

    // Moved to a rate nothing else in the codebase uses. A hardcoded schedule
    // would still answer £8,000.
    // £94,000 chargeable at the moved 5% band.
    expect(app(FailedGiftTaxCalculator::class)->forMember($this->user, $this->nrb)['failed_gift_tax'])
        ->toBe(4_700.0);
});

it('never lets one person\'s gifts reach past their own allowance', function () {
    gift($this->user, 'pet', 900_000, 1);

    $r = $this->calc->forMember($this->user, $this->nrb);

    // s8A transfers the unused PERCENTAGE of a band, and that cannot go below zero.
    // £900,000 less £6,000 = £894,000, capped at the £325,000 band, leaving
    // £569,000 chargeable at 40% = £227,600.
    expect($r['total_nrb_used'])->toBe(325_000.0)
        ->and($r['failed_gift_tax'])->toBe(227_600.0);
});

it('does not cumulate a potentially exempt transfer that survived its seven years', function () {
    gift($this->user, 'pet', 300_000, 8.0);
    gift($this->user, 'pet', 300_000, 2.0);

    $r = $this->calc->forMember($this->user, $this->nrb);

    // The surviving gift is out of the window entirely; the recent one is
    // £300,000 less £6,000 exemption = £294,000, inside the £325,000 band.
    expect($r['failed_gift_tax'])->toBe(0.0)
        ->and($r['total_nrb_used'])->toBe(294_000.0);
});

it('reduces the estate band by the VALUE of in-window transfers, not the band they used', function () {
    gift($this->user, 'clt', 200_000, 8.0);
    gift($this->user, 'pet', 300_000, 2.0);

    $r = $this->calc->forMember($this->user, $this->nrb);

    // £300,000 less £6,000 exemption.
    expect($r['total_nrb_used'])->toBe(294_000.0);
});

/**
 * W-0468 — two transfers on the same day.
 *
 * `cumulationBefore()` selected transfers with `gap > 0` — STRICTLY earlier than
 * the subject. `gifts.gift_date` is a DATE, so two transfers made on the same day
 * have an identical `years` and a gap of exactly zero: each was excluded from the
 * other's cumulation and measured against the whole band. Two £300,000 gifts
 * against a £325,000 band produced NIL tax and reported £600,000 of a £325,000
 * band as used — more band consumed than exists.
 */
it('makes two same-day transfers share one nil rate band', function () {
    gift($this->user, 'pet', 300_000, 1);
    gift($this->user, 'pet', 300_000, 1);

    $r = $this->calc->forMember($this->user, $this->nrb);

    // £600,000 given. The day's £6,000 exemption divides pro rata — £3,000 each —
    // leaving £594,000 chargeable. £325,000 of band, so £269,000 is chargeable.
    // One year, so no taper: £269,000 at the full 40% death rate = £107,600.
    expect($r['total_nrb_used'])->toBe(325_000.0)
        ->and($r['failed_gift_tax'])->toBe(107_600.0);
});

it('splits the shared band in proportion to value, not equally', function () {
    // Unequal on purpose: at 50/50 a proportional split and a fixed tie-break give
    // the same answer, so the test could not tell them apart (tests/CLAUDE.md §4).
    gift($this->user, 'pet', 450_000, 1);
    gift($this->user, 'pet', 150_000, 1);

    $r = $this->calc->forMember($this->user, $this->nrb);

    $byValue = collect($r['failed_gifts'])->keyBy('gift_value');

    // The day's £6,000 exemption divides 75/25 too — £4,500 and £1,500 — leaving
    // £445,500 and £148,500. The 75/25 ratio is therefore unchanged, so the band
    // still splits £243,750 / £81,250, leaving £201,750 and £67,250 chargeable at
    // the full rate: £80,700 and £26,900.
    expect($byValue[450_000.0]['covered_by_allowance'])->toBe(243_750.0)
        ->and($byValue[450_000.0]['tax_due'])->toBe(80_700.0)
        ->and($byValue[150_000.0]['covered_by_allowance'])->toBe(81_250.0)
        ->and($byValue[150_000.0]['tax_due'])->toBe(26_900.0);
});

it('charges the same total however the same-day gifts are sized', function () {
    // The split is a presentational choice; the TOTAL is the part that is not in
    // doubt, and it must not move with the shape of the cohort. Both of these are
    // £600,000 given on one day against a £325,000 band.
    gift($this->user, 'pet', 450_000, 1);
    gift($this->user, 'pet', 150_000, 1);
    $uneven = $this->calc->forMember($this->user, $this->nrb);

    Gift::query()->delete();

    gift($this->user, 'pet', 300_000, 1);
    gift($this->user, 'pet', 300_000, 1);
    $even = $this->calc->forMember($this->user, $this->nrb);

    expect($uneven['failed_gift_tax'])->toBe($even['failed_gift_tax'])
        ->and($uneven['failed_gift_tax'])->toBe(107_600.0)
        ->and($uneven['total_nrb_used'])->toBe($even['total_nrb_used']);
});

it('shares the band across same-day chargeable lifetime transfers too', function () {
    // The lifetime basis cumulates immediately chargeable transfers only, so the
    // same-day cohort on that basis must exclude potentially exempt transfers.
    // A same-day PET must not shrink the band a CLT is measured against for its
    // lifetime credit.
    gift($this->user, 'clt', 300_000, 1);
    gift($this->user, 'pet', 300_000, 1);

    $r = $this->calc->forMember($this->user, $this->nrb);

    $clt = collect($r['failed_gifts'])->firstWhere('gift_type', 'clt');

    // On the DEATH basis both transfers share the band, so the chargeable lifetime
    // transfer is measured against £162,500. On the LIFETIME basis it is alone, so
    // the whole £325,000 covers it and no lifetime charge arises to credit.
    expect($clt['covered_by_allowance'])->toBe(162_500.0)
        ->and($clt['lifetime_tax_credited'])->toBe(0.0);
});
