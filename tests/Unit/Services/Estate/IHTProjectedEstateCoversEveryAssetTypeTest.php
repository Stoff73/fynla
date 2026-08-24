<?php

declare(strict_types=1);

use App\Models\Estate\Asset;
use App\Models\User;
use App\Services\Estate\IHTCalculationService;
use App\Services\Investment\InvestmentProjectionService;
use Database\Seeders\TaxConfigurationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

/**
 * W-0475 — the projected estate must own everything the current estate owns.
 *
 * `calculate()` reports two estates in one response. The current column is built from
 * `gatherUserAssets()`. The projection was built from SOURCE TABLES — `properties` via
 * `PropertyStore`, `investment_accounts` via `calculateInvestmentTotal()`, savings via
 * the cash-flow projector — and only chattels and business read the collection. So a
 * row in the `assets` table was counted today and gone at death, taking the projected
 * taper base down with it. **UNDERSTATED projected tax**, and through a smaller taper
 * base, more residence band surviving than should.
 *
 * The board item named the `other` bucket. Measured against the code it was **four of
 * the five types a user can create**: `CoordinatingAgent:4055` lets Fyn record
 * `property`, `pension`, `investment`, `business` or `other`, and only `business`
 * survived — because that term filters the collection rather than a table. A row typed
 * `property` is "covered" by NAME and invisible to `PropertyStore`, which is why the
 * fix keys on provenance rather than on `asset_type`.
 *
 * The simulation is stubbed to force the deterministic compounded fallback: it
 * supplies no figure any assertion reads and removes Monte Carlo variance, so two
 * households differing only in one asset can be compared exactly.
 */
beforeEach(function () {
    $this->seed(TaxConfigurationSeeder::class);

    $stub = Mockery::mock(InvestmentProjectionService::class);
    $stub->shouldReceive('getPortfolioProjections')
        ->andReturn(['portfolio' => null, 'accounts' => [], 'message' => 'No investment accounts found']);
    app()->instance(InvestmentProjectionService::class, $stub);
});

afterEach(function () {
    Mockery::close();
});

/** The types this file proves are carried. Compared against the column below. */
const PROJECTED_ASSET_TYPES = ['property', 'pension', 'investment', 'business', 'other'];

function assetHolder(?string $type): User
{
    $user = User::factory()->create([
        'marital_status' => 'single',
        'date_of_birth' => '1970-01-01',
        'gender' => 'male',
        'monthly_expenditure' => 2_000,
    ]);

    if ($type !== null) {
        Asset::factory()->create([
            'user_id' => $user->id,
            'asset_type' => $type,
            'current_value' => 100_000,
            // Pinned: `AssetFactory` randomises `ownership_type` and can emit
            // `tenants_in_common`, which this column rejects outright (W-0481).
            'ownership_type' => 'individual',
            'is_iht_exempt' => false,
        ]);
    }

    return $user->fresh();
}

function estateColumns(User $user): array
{
    $r = app(IHTCalculationService::class)->calculate($user, null, false);

    return [
        'current' => (float) $r['total_gross_assets'],
        'projected' => (float) $r['projected_gross_assets'],
    ];
}

it('carries a stored asset into the PROJECTED estate, not just the current one', function (string $type) {
    $control = estateColumns(assetHolder(null));
    $holder = estateColumns(assetHolder($type));

    // The current column has always counted it — that half was never in doubt.
    expect($holder['current'] - $control['current'])->toEqualWithDelta(100_000.0, 0.01);

    // The projection is what vanished. Carried at current value like chattels and
    // business, so the increase is the asset's own value; `toBeGreaterThanOrEqual`
    // rather than an exact match so a future growth model does not fail this.
    expect($holder['projected'] - $control['projected'])
        ->toBeGreaterThanOrEqual(99_999.99);
})->with(PROJECTED_ASSET_TYPES);

it('proves every type the column can hold, so a new one cannot be added and forgotten', function () {
    // W-0475 acceptance 3. The enum and the projection are two lists that must agree,
    // and the only way to know they still do is to ask the column rather than to keep
    // a second copy of it here.
    $definition = DB::selectOne("SHOW COLUMNS FROM assets WHERE Field = 'asset_type'")->Type;

    preg_match_all("/'([^']+)'/", $definition, $matches);

    expect($matches[1])->toEqualCanonicalizing(PROJECTED_ASSET_TYPES);
});
