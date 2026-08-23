<?php

declare(strict_types=1);

use App\Models\BusinessInterest;
use App\Models\Estate\Liability;
use App\Models\Property;
use App\Models\TaxConfiguration;
use App\Models\User;
use App\Services\Estate\EstateAssetAggregatorService;
use App\Services\Estate\IHTCalculationService;
use App\Services\TaxConfigService;
use Database\Seeders\TaxConfigurationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;

/**
 * W-0091 / W-0463 — Business Property Relief is a capped, graduated relief, not a
 * flag.
 *
 * It was modelled as `is_iht_exempt = true`: a qualifying business was worth its
 * full value to the estate or nothing at all. The £2,500,000 cap has been in force
 * since 2026-04-06 — 100% relief on the first £2.5m, 50% above — and every field of
 * it sat in `TaxConfigService::getBusinessRelief()`, dated and switched on, read by
 * nothing.
 *
 * **These figures cannot be produced by any persona.** The largest business interest
 * on the development database is £750,000, comfortably under the cap, which is
 * exactly why the defect survived a persona run: nothing in the fixtures could
 * exercise the boundary. The fixture is purpose-built for that reason.
 */
uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(TaxConfigurationSeeder::class);
    $this->user = User::factory()->create();
    $this->aggregator = app(EstateAssetAggregatorService::class);
});

function qualifyingBusiness(User $user, float $value): BusinessInterest
{
    return BusinessInterest::factory()->create([
        'user_id' => $user->id,
        'current_valuation' => $value,
        'ownership_percentage' => 100,
        'ownership_type' => 'individual',
        'bpr_eligible' => true,
        'trading_status' => 'trading',
        'acquisition_date' => now()->subYears(10),
    ]);
}

function businessAssets(User $user): Collection
{
    return app(EstateAssetAggregatorService::class)
        ->gatherUserAssets($user)
        ->filter(fn ($a) => ($a->asset_type ?? null) === 'business')
        ->values();
}

it('relieves a business under the cap in full', function () {
    qualifyingBusiness($this->user, 1_000_000);

    $asset = businessAssets($this->user)->first();

    expect((float) $asset->iht_relief_amount)->toBe(1_000_000.0)
        ->and($asset->is_iht_exempt)->toBeTrue();
});

it('relieves only the first £2.5m in full, and half of the rest', function () {
    // The board's worked example: a £6m trading business.
    qualifyingBusiness($this->user, 6_000_000);

    $asset = businessAssets($this->user)->first();

    // £2.5m at 100% + £3.5m at 50% = £4.25m relieved, £1.75m chargeable.
    expect((float) $asset->iht_relief_amount)->toBe(4_250_000.0)
        ->and($asset->is_iht_exempt)->toBeFalse('a partly relieved business must stay in the estate, or its chargeable remainder vanishes with it');

    $chargeable = (float) $asset->current_value - (float) $asset->iht_relief_amount;
    expect($chargeable)->toBe(1_750_000.0);
});

it('shares ONE cap across several businesses rather than giving each its own', function () {
    qualifyingBusiness($this->user, 2_000_000);
    qualifyingBusiness($this->user, 2_000_000);

    $assets = businessAssets($this->user);
    $totalRelief = (float) $assets->sum(fn ($a) => (float) $a->iht_relief_amount);

    // £2.5m at 100% + £1.5m at 50% = £3.25m. Two uncapped reliefs would give £4m,
    // and two separate £2.5m caps would relieve both in full — both wrong.
    // Equal values, so pro rata splits the allowance evenly between them.
    expect($totalRelief)->toBe(3_250_000.0)
        ->and((float) $assets->first()->iht_relief_amount)->toBe(1_625_000.0);
});

it('apportions the allowance pro rata across every qualifying asset', function () {
    qualifyingBusiness($this->user, 3_000_000);
    qualifyingBusiness($this->user, 500_000);

    $assets = businessAssets($this->user)->keyBy(fn ($a) => (int) $a->current_value);

    // s124D(7): the allowance is available against all qualifying assets "in the
    // same proportion as each asset represents as part of all the qualifying
    // assets" (IHTM25524). Mandatory — no election, no ordering choice.
    //
    // Qualifying total £3.5m, cap £2.5m. The £3m asset takes 3/3.5 of the cap
    // (£2,142,857) at 100% and the remaining £857,143 at 50%; the £500k asset takes
    // 0.5/3.5 (£357,143) at 100% and £142,857 at 50%.
    //
    // This asserted largest-first, on the claim that it "relieves the most". It does
    // not: total relief is min(cap, total) + max(0, total − cap) × rate, which is
    // invariant to order — £3,000,000 either way. Order changes only WHICH asset is
    // wholly relieved, and a wholly relieved asset is dropped from gross assets, so
    // the wrong order moved a line the user reads.
    expect((float) $assets[3_000_000]->iht_relief_amount)->toBe(2_571_428.57)
        ->and((float) $assets[500_000]->iht_relief_amount)->toBe(428_571.43);

    $total = (float) businessAssets($this->user)->sum(fn ($a) => (float) $a->iht_relief_amount);
    expect(round($total, 2))->toBe(3_000_000.0, 'total relief is order-invariant');
});

it('gives nothing to a business that does not qualify', function () {
    BusinessInterest::factory()->create([
        'user_id' => $this->user->id,
        'current_valuation' => 1_000_000,
        'ownership_percentage' => 100,
        'bpr_eligible' => false,
        'trading_status' => 'trading',
        'acquisition_date' => now()->subYears(10),
    ]);

    $asset = businessAssets($this->user)->first();

    expect((float) $asset->iht_relief_amount)->toBe(0.0)
        ->and($asset->is_iht_exempt)->toBeFalse();
});

it('refuses relief until the minimum ownership period is met', function () {
    qualifyingBusiness($this->user, 1_000_000)->update(['acquisition_date' => now()->subMonths(6)]);

    $asset = businessAssets($this->user)->first();

    expect((float) $asset->iht_relief_amount)->toBe(0.0);
});

it('reads the cap from configuration rather than a literal', function () {
    qualifyingBusiness($this->user, 6_000_000);

    $config = TaxConfiguration::where('is_active', true)->first();
    $data = is_string($config->config_data) ? json_decode($config->config_data, true) : $config->config_data;
    $data['inheritance_tax']['business_relief']['allowance_cap'] = 1_000_000;
    $config->update(['config_data' => $data]);
    app(TaxConfigService::class)->clearCache();

    // Moved to a value nothing else in the codebase uses: £1m at 100% + £5m at
    // 50% = £3.5m. A hardcoded £2.5m would still answer £4.25m.
    expect((float) businessAssets($this->user)->first()->iht_relief_amount)->toBe(3_500_000.0);
});

it('does not apply the cap before its effective date', function () {
    qualifyingBusiness($this->user, 6_000_000);

    $config = TaxConfiguration::where('is_active', true)->first();
    $data = is_string($config->config_data) ? json_decode($config->config_data, true) : $config->config_data;
    $data['inheritance_tax']['business_relief']['allowance_cap_effective_date'] = now()->addYear()->toDateString();
    $config->update(['config_data' => $data]);
    app(TaxConfigService::class)->clearCache();

    // The date decides, so a death before the cap took effect still gets the old
    // uncapped 100% — the answer follows the configuration, not the calendar in
    // someone's head.
    expect((float) businessAssets($this->user)->first()->iht_relief_amount)->toBe(6_000_000.0)
        ->and(businessAssets($this->user)->first()->is_iht_exempt)->toBeTrue();
});

/**
 * R2 — the taper base is the estate BEFORE reliefs (IHTM46023), and adding the
 * relief back on top of assets that never left produced an estate 70% too large.
 */
it('measures the residence-band taper on the estate before reliefs, without double counting', function () {
    // £2.6m business (partly relieved), £100k home, £700k of liabilities.
    // True taper base = 2.6m + 0.1m − 0.7m = £2.0m — exactly at the threshold, so
    // no taper. The double count made it £4.55m and tapered the band to nothing.
    qualifyingBusiness($this->user, 2_600_000);

    Property::factory()->create([
        'user_id' => $this->user->id,
        'property_type' => 'main_residence',
        'current_value' => 100_000,
        'ownership_percentage' => 100,
    ]);
    Liability::factory()->create([
        'user_id' => $this->user->id,
        'current_balance' => 700_000,
    ]);

    $r = app(IHTCalculationService::class)
        ->calculate($this->user->fresh(), null, false);

    // Relief: £2.5m at 100% + £0.1m at 50% = £2.55m, leaving £50k chargeable.
    expect((float) $r['business_relief_deduction'])->toBe(2_550_000.0);

    // The taper base is £2.0m, not above the threshold, so the band is not tapered.
    expect((float) $r['rnrb_taper_reduction'])->toBe(0.0);
});
