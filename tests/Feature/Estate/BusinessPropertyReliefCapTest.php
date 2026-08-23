<?php

declare(strict_types=1);

use App\Models\BusinessInterest;
use App\Models\TaxConfiguration;
use App\Models\User;
use App\Services\Estate\EstateAssetAggregatorService;
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
    expect($totalRelief)->toBe(3_250_000.0);
});

it('spends the cap on the largest holding first', function () {
    qualifyingBusiness($this->user, 3_000_000);
    qualifyingBusiness($this->user, 500_000);

    $assets = businessAssets($this->user)->keyBy(fn ($a) => (int) $a->current_value);

    // The cap relieves the £3m holding first: £2.5m at 100% + £0.5m at 50%
    // = £2.75m. The £500k holding is then wholly above the cap, at 50% = £250k.
    expect((float) $assets[3_000_000]->iht_relief_amount)->toBe(2_750_000.0)
        ->and((float) $assets[500_000]->iht_relief_amount)->toBe(250_000.0);
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
