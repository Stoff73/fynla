<?php

declare(strict_types=1);

use App\Models\DCPension;
use App\Models\Investment\Holding;
use App\Models\User;
use Database\Seeders\TaxConfigurationSeeder;
use Database\Seeders\TierConfigurationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * W-0441 — editing a defined contribution pension must not destroy holdings the
 * pension form cannot see.
 *
 * `RetirementController::updateDCPension` used to run `holdings()->delete()` and
 * recreate the whole set from the nested payload. That payload carries FIVE keys
 * (`StoreDCPensionRequest`: security name, asset type, allocation, ongoing charge,
 * cost basis) and `DCPensionForm` maps stored rows into exactly those five when it
 * opens — so units, purchase price, current price, purchase date, ticker and ISIN
 * were dropped on the way in and annihilated on the way out.
 *
 * That mattered little while those fields were unenterable on a pension. W-0441
 * makes them enterable, and `hasAdditionalInfoData()` auto-expands the holdings
 * section whenever holdings exist — so the destructive path became the DEFAULT
 * one: open a pension, change a fee, press Update, units gone.
 *
 * **Every case asserts the STORED ROW and its id.** The endpoint returns a
 * success response that looks identical whether the row survived or was
 * annihilated and rebuilt, and the id is what distinguishes "kept" from
 * "replaced with something that looks the same".
 *
 * **Figures are mutually distinct on purpose** (tests/CLAUDE.md §4, Collision).
 * The fund is £320,000 and the allocations are 50% and 30%, so the values the
 * allocation would derive are £160,000 and £96,000 — while the rows actually
 * store £160,018 and £96,360. A revaluation the fix was meant to prevent
 * therefore CHANGES the number rather than landing on the same one. With round
 * figures every case here would pass against the pre-fix code.
 */
beforeEach(function () {
    $this->seed(TaxConfigurationSeeder::class);
    $this->seed(TierConfigurationSeeder::class);

    $this->user = User::factory()->create();
    $this->actingAs($this->user, 'sanctum');

    $this->pension = DCPension::factory()->create([
        'user_id' => $this->user->id,
        'scheme_name' => "David's SIPP",
        'pension_type' => 'sipp',
        'current_fund_value' => 320000,
        'retirement_age' => 62,
    ]);

    // 4,211 units at £38.00 is £160,018 — NOT the £160,000 that 50% of the pot
    // would give. The two must differ or nothing here can discriminate.
    $this->equity = Holding::factory()->create([
        'holdable_type' => DCPension::class,
        'holdable_id' => $this->pension->id,
        'security_name' => 'Vanguard Global Equity',
        'asset_type' => 'fund',
        'allocation_percent' => 50,
        'quantity' => 4211,
        'purchase_price' => 32.50,
        'current_price' => 38.00,
        'current_value' => 160018,
        'ocf_percent' => 0.23,
        'purchase_date' => '2019-03-11',
    ]);

    // 803 units at £120.00 is £96,360 — NOT the £96,000 that 30% would give.
    $this->bond = Holding::factory()->create([
        'holdable_type' => DCPension::class,
        'holdable_id' => $this->pension->id,
        'security_name' => 'BlackRock Corporate Bond',
        'asset_type' => 'bond',
        'allocation_percent' => 30,
        'quantity' => 803,
        'purchase_price' => 125.00,
        'current_price' => 120.00,
        'current_value' => 96360,
        'ocf_percent' => 0.18,
        'purchase_date' => '2020-07-02',
    ]);

    // The pension fields a PUT must restate. Holdings are deliberately absent —
    // each case adds the key it needs, so "no key at all" is a real case rather
    // than an accident of setup.
    $this->pensionPayload = [
        'scheme_name' => "David's SIPP",
        'provider' => 'Interactive Investor',
        'pension_type' => 'sipp',
        'current_fund_value' => 320000,
        'retirement_age' => 62,
    ];
});

it('leaves every holding alone when the update never mentions holdings', function () {
    // What the form sends with "Additional information" collapsed: the key is
    // deleted rather than sent empty (W-0322), so the sync is skipped entirely.
    $this->putJson("/api/retirement/pensions/dc/{$this->pension->id}", $this->pensionPayload)
        ->assertOk();

    $equity = $this->equity->fresh();

    expect(Holding::query()->count())->toBe(2)
        ->and($equity->deleted_at)->toBeNull()
        ->and((float) $equity->quantity)->toBe(4211.0)
        ->and((float) $equity->current_value)->toBe(160018.0)
        ->and((float) $equity->purchase_price)->toBe(32.5)
        ->and($equity->purchase_date->toDateString())->toBe('2019-03-11');
});

it('keeps the units, prices and purchase date of a holding the form echoes back', function () {
    // The destructive path: the section is EXPANDED — which it is by default once
    // holdings exist — so the form sends back the five keys it can express. Before
    // the sync this deleted both rows and rebuilt them with a null unit count, a
    // null purchase price and a value recomputed from the allocation.
    $this->putJson("/api/retirement/pensions/dc/{$this->pension->id}", $this->pensionPayload + [
        'holdings' => [
            ['security_name' => 'Vanguard Global Equity', 'asset_type' => 'fund', 'allocation_percent' => 50, 'ocf_percent' => 0.23],
            ['security_name' => 'BlackRock Corporate Bond', 'asset_type' => 'bond', 'allocation_percent' => 30, 'ocf_percent' => 0.18],
        ],
    ])->assertOk();

    $equity = $this->equity->fresh();
    $bond = $this->bond->fresh();

    // Same rows, not replacements that resemble them. The live count matters as
    // much as the values: a sync that matched nothing would leave the originals
    // in place AND create duplicates beside them, and every value assertion below
    // would still read the untouched original and pass.
    expect($equity->deleted_at)->toBeNull()
        ->and($bond->deleted_at)->toBeNull()
        ->and(Holding::query()->count())->toBe(3);

    expect((float) $equity->quantity)->toBe(4211.0)
        ->and((float) $equity->purchase_price)->toBe(32.5)
        ->and((float) $equity->current_price)->toBe(38.0)
        ->and($equity->purchase_date->toDateString())->toBe('2019-03-11')
        // £160,018, not the £160,000 an allocation-derived revaluation gives.
        ->and((float) $equity->current_value)->toBe(160018.0);

    expect((float) $bond->quantity)->toBe(803.0)
        ->and((float) $bond->purchase_price)->toBe(125.0)
        ->and($bond->purchase_date->toDateString())->toBe('2020-07-02')
        // £96,360, not £96,000.
        ->and((float) $bond->current_value)->toBe(96360.0);
});

it('creates the unallocated cash remainder without touching the rows beside it', function () {
    $this->putJson("/api/retirement/pensions/dc/{$this->pension->id}", $this->pensionPayload + [
        'holdings' => [
            ['security_name' => 'Vanguard Global Equity', 'asset_type' => 'fund', 'allocation_percent' => 50, 'ocf_percent' => 0.23],
            ['security_name' => 'BlackRock Corporate Bond', 'asset_type' => 'bond', 'allocation_percent' => 30, 'ocf_percent' => 0.18],
        ],
    ])->assertOk();

    $cash = Holding::query()->where('security_name', 'Cash')->sole();

    // 20% of £320,000 — and distinct from both stored values above.
    expect((float) $cash->allocation_percent)->toBe(20.0)
        ->and((float) $cash->current_value)->toBe(64000.0);

    // The LIVE row for that security, deliberately not `$this->equity->fresh()`.
    // `Model::fresh()` queries WITHOUT global scopes, so it returns a soft-deleted
    // row just as happily as a live one and its id is unchanged either way — an
    // assertion on it passes whether the row survived or was annihilated and
    // rebuilt. That is the Collision variant (tests/CLAUDE.md §4) and this case
    // carried it until mutation testing found it.
    expect(Holding::query()->where('security_name', 'Vanguard Global Equity')->sole()->id)
        ->toBe($this->equity->id);
});

it('deletes only the holdings the incoming set no longer names', function () {
    $this->putJson("/api/retirement/pensions/dc/{$this->pension->id}", $this->pensionPayload + [
        'holdings' => [
            // The bond is gone; the equity now takes the whole pot.
            ['security_name' => 'Vanguard Global Equity', 'asset_type' => 'fund', 'allocation_percent' => 100, 'ocf_percent' => 0.23],
        ],
    ])->assertOk();

    expect($this->bond->fresh()->deleted_at)->not->toBeNull()
        ->and($this->equity->fresh()->deleted_at)->toBeNull()
        // The kept row is the SAME row — id preserved, not deleted and recreated.
        ->and(Holding::query()->whereNull('deleted_at')->sole()->id)->toBe($this->equity->id);
});

it('revalues and re-derives the units when the allocation actually moves', function () {
    // Proves the sync is not inert. Moving the equity from 50% to 60% IS the form
    // asserting a new value, so the shared rule applies: £192,000 stands and the
    // unit count is back-calculated from it at the stored £38.00 price
    // (192000 / 38 = 5052.631579).
    $this->putJson("/api/retirement/pensions/dc/{$this->pension->id}", $this->pensionPayload + [
        'holdings' => [
            ['security_name' => 'Vanguard Global Equity', 'asset_type' => 'fund', 'allocation_percent' => 60, 'ocf_percent' => 0.23],
            ['security_name' => 'BlackRock Corporate Bond', 'asset_type' => 'bond', 'allocation_percent' => 30, 'ocf_percent' => 0.18],
        ],
    ])->assertOk();

    $equity = $this->equity->fresh();

    expect((float) $equity->current_value)->toBe(192000.0)
        ->and((float) $equity->quantity)->toBe(5052.631579)
        // The row it did not touch is untouched.
        ->and((float) $this->bond->fresh()->current_value)->toBe(96360.0);
});

it('still clears every holding when the payload states an empty array', function () {
    // **This is the RESERVED contract, pinned rather than changed.** W-0322's
    // acceptance 3 and 4 ask what an empty array should mean and are still open.
    // The sync deliberately leaves the answer exactly as it was — an empty set
    // names nothing, so nothing survives — so that whoever settles that question
    // changes it on purpose and sees this case go red when they do.
    $this->putJson("/api/retirement/pensions/dc/{$this->pension->id}", $this->pensionPayload + [
        'holdings' => [],
    ])->assertOk();

    expect(Holding::query()->whereNull('deleted_at')->count())->toBe(0)
        ->and($this->equity->fresh()->deleted_at)->not->toBeNull();
});
