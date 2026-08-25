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
 * W-0126 — the defined contribution pension holding endpoints read the ONE valuation
 * rule (`App\Support\HoldingValuation`) rather than carrying their own.
 *
 * This controller held three copies: `store()` wrote out
 * `cost_basis = quantity x purchase_price` line for line, `update()` hand-rolled the
 * supplied-versus-inherited fallback that W-0121 was raised about, and `bulkUpdate()`
 * reconciled nothing at all. Every case here asserts the STORED ROW, because all three
 * endpoints return a response that looks identical either way.
 */
beforeEach(function () {
    $this->seed(TaxConfigurationSeeder::class);
    $this->seed(TierConfigurationSeeder::class);

    $this->user = User::factory()->create();
    $this->actingAs($this->user, 'sanctum');

    $this->pension = DCPension::factory()->create([
        'user_id' => $this->user->id,
        'current_fund_value' => 100000,
    ]);
});

it('back-calculates the unit count when a holding is created with a value and a price', function () {
    $this->postJson("/api/retirement/pensions/dc/{$this->pension->id}/holdings", [
        'security_name' => 'Vanguard FTSE All-World',
        'asset_type' => 'etf',
        'current_value' => 45000,
        'current_price' => 450,
        'purchase_price' => 350,
    ])->assertCreated();

    $holding = Holding::query()->sole();

    // The typed value stands and the units follow from it. Before this the endpoint
    // stored no unit count at all, so the same security entered here and through
    // /api/investment/holdings came out as two different shapes of row.
    expect((float) $holding->current_value)->toBe(45000.0)
        ->and((float) $holding->quantity)->toBe(100.0)
        ->and((float) $holding->cost_basis)->toBe(35000.0);
});

it('still derives cost basis from units and the purchase price on create', function () {
    // The behaviour the deleted inline copy provided — proving the consolidation kept
    // it rather than dropping it.
    $this->postJson("/api/retirement/pensions/dc/{$this->pension->id}/holdings", [
        'security_name' => 'Fundsmith Equity',
        'asset_type' => 'fund',
        'current_value' => 2604.42,
        'quantity' => 351,
        'purchase_price' => 6.00,
    ])->assertCreated();

    expect((float) Holding::query()->sole()->cost_basis)->toBe(2106.00);
});

it('never overwrites a typed value with the unit count already on the holding', function () {
    // W-0121 at this endpoint: 100 stored units x a typed £500 would have turned a
    // typed £60,000 into £50,000, with a 200 and no indication anything was discarded.
    $holding = Holding::factory()->create([
        'holdable_id' => $this->pension->id,
        'holdable_type' => DCPension::class,
        'quantity' => 100,
        'current_price' => 450,
        'current_value' => 45000,
        'purchase_price' => 350,
    ]);

    $this->putJson("/api/retirement/pensions/dc/{$this->pension->id}/holdings/{$holding->id}", [
        'current_value' => 60000,
        'current_price' => 500,
    ])->assertOk();

    $holding->refresh();

    expect((float) $holding->current_value)->toBe(60000.0)
        ->and((float) $holding->quantity)->toBe(120.0);
});

it('revalues stored units when only the price is edited', function () {
    // The other half of the same rule: say nothing about the value and the stored
    // units are the fact, so they revalue at the new price.
    $holding = Holding::factory()->create([
        'holdable_id' => $this->pension->id,
        'holdable_type' => DCPension::class,
        'quantity' => 100,
        'current_price' => 450,
        'current_value' => 45000,
    ]);

    $this->putJson("/api/retirement/pensions/dc/{$this->pension->id}/holdings/{$holding->id}", [
        'current_price' => 500,
    ])->assertOk();

    $holding->refresh();

    expect((float) $holding->current_value)->toBe(50000.0)
        ->and((float) $holding->quantity)->toBe(100.0);
});

it('re-derives the unit count on a bulk re-valuation instead of leaving it contradicting', function () {
    $holding = Holding::factory()->create([
        'holdable_id' => $this->pension->id,
        'holdable_type' => DCPension::class,
        'quantity' => 100,
        'current_price' => 450,
        'current_value' => 45000,
    ]);

    $this->postJson("/api/retirement/pensions/dc/{$this->pension->id}/holdings/bulk-update", [
        'holdings' => [
            ['id' => $holding->id, 'current_value' => 60000, 'current_price' => 500],
        ],
    ])->assertOk();

    $holding->refresh();

    // Before this, the bulk editor wrote the new value and left the old 100 units
    // beside it — 100 x £500 is £50,000, so the row contradicted itself on save.
    expect((float) $holding->current_value)->toBe(60000.0)
        ->and((float) $holding->quantity)->toBe(120.0)
        ->and((float) $holding->current_price)->toBe(500.0);
});
