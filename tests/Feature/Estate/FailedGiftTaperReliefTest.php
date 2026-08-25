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
    expect($r['failed_gift_tax'])->toBe(0.0)
        ->and($r['total_nrb_used'])->toBe(100_000.0)
        ->and($r['failed_gifts'])->toBe([]);
});

it('charges the full rate on the excess of a recent gift', function () {
    gift($this->user, 'pet', 425_000, 1);

    $r = $this->calc->forMember($this->user, $this->nrb);

    // £325,000 covered, £100,000 chargeable, under three years so no taper: 40%.
    expect($r['failed_gift_tax'])->toBe(40_000.0)
        ->and($r['failed_gifts'][0]['chargeable_amount'])->toBe(100_000.0)
        ->and($r['failed_gifts'][0]['tax_rate_percent'])->toBe(40.0)
        ->and($r['failed_gift_taper_saving'])->toBe(0.0);
});

it('tapers the charge by years survived', function (float $yearsAgo, float $expectedRate, float $expectedTax) {
    gift($this->user, 'pet', 425_000, $yearsAgo);

    $r = $this->calc->forMember($this->user, $this->nrb);

    expect($r['failed_gifts'][0]['tax_rate'])->toBe($expectedRate)
        ->and($r['failed_gift_tax'])->toBe($expectedTax);
})->with([
    'under 3 years — no relief' => [1.0, 0.40, 40_000.0],
    '3-4 years — 20% relief' => [3.5, 0.32, 32_000.0],
    '4-5 years — 40% relief' => [4.5, 0.24, 24_000.0],
    '5-6 years — 60% relief' => [5.5, 0.16, 16_000.0],
    '6-7 years — 80% relief' => [6.5, 0.08, 8_000.0],
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

    // £100,000 chargeable: £40,000 at the full rate, £8,000 after taper.
    expect($r['failed_gift_taper_saving'])->toBe(32_000.0)
        ->and($r['failed_gifts'][0]['taper_saving'])->toBe(32_000.0);
});

it('cumulates each gift against the seven years before ITSELF, not one running band', function () {
    gift($this->user, 'pet', 300_000, 6.5);
    gift($this->user, 'pet', 200_000, 1.0);

    $r = $this->calc->forMember($this->user, $this->nrb);

    // Both are inside each other's seven-year lookback (5.5 years apart), so the
    // recent gift DOES cumulate the older one: band £325,000 − £300,000 = £25,000,
    // leaving £175,000 chargeable at the full 40% = £70,000.
    expect($r['failed_gift_tax'])->toBe(70_000.0)
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
    expect($r['nrb_used_by_pets'])->toBe(100_000.0)
        ->and($r['nrb_used_by_clts'])->toBe(0.0)
        ->and($r['total_nrb_used'])->toBe(100_000.0)
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
    // £100,000 chargeable. Tapered at 6.5 years: £8,000. Lifetime charge already
    // borne at 20%: £20,000. £8,000 < £20,000, so NO ADDITIONAL TAX IS DUE.
    expect($r['failed_gifts'][0]['tax_rate'])->toBe(0.08)
        ->and($r['failed_gifts'][0]['lifetime_tax_credited'])->toBe(20_000.0)
        ->and($r['failed_gift_tax'])->toBe(0.0);
});

it('charges only the excess over the lifetime tax when the taper has barely run', function () {
    gift($this->user, 'clt', 425_000, 1.0);

    $r = $this->calc->forMember($this->user, $this->nrb);

    // £100,000 chargeable at the full 40% = £40,000, less the £20,000 lifetime
    // charge = £20,000 additional. Total borne £40,000, which is the death rate.
    expect($r['failed_gift_tax'])->toBe(20_000.0);
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
    expect(app(FailedGiftTaxCalculator::class)->forMember($this->user, $this->nrb)['failed_gift_tax'])
        ->toBe(5_000.0);
});

it('never lets one person\'s gifts reach past their own allowance', function () {
    gift($this->user, 'pet', 900_000, 1);

    $r = $this->calc->forMember($this->user, $this->nrb);

    // s8A transfers the unused PERCENTAGE of a band, and that cannot go below zero.
    expect($r['total_nrb_used'])->toBe(325_000.0)
        ->and($r['failed_gift_tax'])->toBe(230_000.0);
});

it('does not cumulate a potentially exempt transfer that survived its seven years', function () {
    gift($this->user, 'pet', 300_000, 8.0);
    gift($this->user, 'pet', 300_000, 2.0);

    $r = $this->calc->forMember($this->user, $this->nrb);

    expect($r['failed_gift_tax'])->toBe(0.0)
        ->and($r['total_nrb_used'])->toBe(300_000.0);
});

it('reduces the estate band by the VALUE of in-window transfers, not the band they used', function () {
    gift($this->user, 'clt', 200_000, 8.0);
    gift($this->user, 'pet', 300_000, 2.0);

    $r = $this->calc->forMember($this->user, $this->nrb);

    expect($r['total_nrb_used'])->toBe(300_000.0);
});
