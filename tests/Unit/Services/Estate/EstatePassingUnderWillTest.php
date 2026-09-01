<?php

declare(strict_types=1);

use App\Models\BusinessInterest;
use App\Models\DCPension;
use App\Models\User;
use App\Services\Estate\EstateAssetAggregatorService;
use Database\Seeders\TaxConfigurationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * W-0392. `is_iht_exempt` carried TWO different facts and no consumer could tell them
 * apart:
 *
 *   1. **Passes outside the estate** — a pension with a nominated beneficiary. Excluding
 *      it from "what your will leaves" is right.
 *   2. **In the estate but wholly relieved from tax** — a qualifying trading business.
 *      **Business Property Relief removes an asset from the TAX, not from the estate**,
 *      so the business does pass under the will.
 *
 * The Will Planning tab rejected on that one boolean, so it understated the estate of
 * any business owner by the whole value of their trading business — on a screen
 * describing a legal instrument.
 *
 * Not visible on the seeded persona: David Jones holds no business interest, which is
 * why this was found by reading the flag's writers rather than by a wrong number.
 */
beforeEach(function () {
    $this->seed(TaxConfigurationSeeder::class);
    $this->user = User::factory()->create(['date_of_birth' => now()->subYears(55)]);
    $this->aggregator = app(EstateAssetAggregatorService::class);
});

describe('the two facts are distinguished at source', function () {
    it('marks a nominated pension as passing outside the estate', function () {
        DCPension::factory()->create([
            'user_id' => $this->user->id,
            'current_fund_value' => 250000,
        ]);

        $pension = collect($this->aggregator->gatherUserAssets($this->user->fresh()))
            ->firstWhere('asset_type', 'dc_pension');

        expect($pension->is_iht_exempt)->toBeTrue()
            ->and($pension->passes_outside_estate)->toBeTrue();
    });

    /**
     * The distinction that matters. A wholly relieved business is `is_iht_exempt` —
     * correctly, for tax — and must NOT be marked as leaving the estate.
     */
    it('does not mark a relieved trading business as passing outside the estate', function () {
        BusinessInterest::factory()->create([
            'user_id' => $this->user->id,
            'current_valuation' => 400000,
            'bpr_eligible' => true,
            'trading_status' => 'trading',
            'acquisition_date' => now()->subYears(10),
            'ownership_type' => 'individual',
            'ownership_percentage' => 100,
        ]);

        $business = collect($this->aggregator->gatherUserAssets($this->user->fresh()))
            ->firstWhere('asset_type', 'business');

        expect($business->passes_outside_estate ?? false)->toBeFalse(
            'Business Property Relief removes an asset from the tax, not from the estate'
        );
    });
});

/**
 * The guard. The defect was invisible because both facts produced the same boolean, so
 * no test of a single figure could see the conflation — the number was simply lower,
 * plausibly. This asserts the two sets differ for exactly the asset that distinguishes
 * them.
 */
it('leaves a relieved business in the will estate and out of the taxable one', function () {
    BusinessInterest::factory()->create([
        'user_id' => $this->user->id,
        'current_valuation' => 400000,
        'bpr_eligible' => true,
        'trading_status' => 'trading',
        'acquisition_date' => now()->subYears(10),
        'ownership_type' => 'individual',
        'ownership_percentage' => 100,
    ]);
    DCPension::factory()->create([
        'user_id' => $this->user->id,
        'current_fund_value' => 250000,
    ]);

    $assets = collect($this->aggregator->gatherUserAssets($this->user->fresh()));

    $taxable = $assets->reject(fn ($a) => $a->is_iht_exempt ?? false)->sum('current_value');
    $underWill = $assets->reject(fn ($a) => $a->passes_outside_estate ?? false)->sum('current_value');

    // The pension is out of both. The business is out of the taxable estate only.
    expect($underWill)->toBeGreaterThan($taxable)
        ->and($underWill - $taxable)->toBe(400000.0);
});
