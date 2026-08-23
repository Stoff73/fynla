<?php

declare(strict_types=1);

use App\Models\Estate\Gift;
use App\Models\User;
use App\Services\Estate\FailedGiftTaxCalculator;
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
    'under 3 years — no relief'   => [1.0, 0.40, 40_000.0],
    '3-4 years — 20% relief'      => [3.5, 0.32, 32_000.0],
    '4-5 years — 40% relief'      => [4.5, 0.24, 24_000.0],
    '5-6 years — 60% relief'      => [5.5, 0.16, 16_000.0],
    '6-7 years — 80% relief'      => [6.5, 0.08, 8_000.0],
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

it('cumulates chronologically: the earlier gift takes the band, the later one pays', function () {
    gift($this->user, 'pet', 300_000, 6.5);
    gift($this->user, 'pet', 200_000, 1.0);

    $r = $this->calc->forMember($this->user, $this->nrb);

    // Earliest first: £300,000 consumes £300,000 of band, leaving £25,000. The
    // recent £200,000 is then chargeable on £175,000 at the full 40% = £70,000.
    // Allocating to the older gift instead would charge it at 8% and understate
    // the bill by more than half.
    expect($r['total_nrb_used'])->toBe(325_000.0)
        ->and($r['failed_gift_tax'])->toBe(70_000.0)
        ->and(collect($r['failed_gifts'])->firstWhere('gift_value', 200_000.0)['tax_rate_percent'])->toBe(40.0);
});

it('tapers a chargeable lifetime transfer too', function () {
    gift($this->user, 'clt', 425_000, 6.5);

    $r = $this->calc->forMember($this->user, $this->nrb);

    // The bug this covers: the chargeable-lifetime-transfer bands carry
    // `tax_percent` (the percentage of the death rate still due), not `tax_rate`,
    // so `TaxConfigService::getGiftTaxRate($years, 'clt')` matched no band and fell
    // through to its default. EVERY such transfer was rated at the full 40%
    // however long the donor had survived. 20% of 40% = 8%.
    expect($r['failed_gifts'][0]['tax_rate'])->toBe(0.08)
        ->and($r['failed_gift_tax'])->toBe(8_000.0);
});

it('reads the schedule from configuration rather than a literal', function () {
    gift($this->user, 'pet', 425_000, 6.5);

    $config = \App\Models\TaxConfiguration::where('is_active', true)->first();
    $data = is_string($config->config_data) ? json_decode($config->config_data, true) : $config->config_data;
    foreach ($data['inheritance_tax']['potentially_exempt_transfers']['taper_relief'] as $i => $band) {
        if (($band['min_years'] ?? null) === 6) {
            $data['inheritance_tax']['potentially_exempt_transfers']['taper_relief'][$i]['tax_rate'] = 0.05;
        }
    }
    $config->update(['config_data' => $data]);
    app(\App\Services\TaxConfigService::class)->clearCache();

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
