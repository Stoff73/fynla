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
 * W-0441 acceptance 1 — the persona's three SIPP holdings enter and persist.
 *
 * `peak_earners.md` gives David's SIPP three holdings, each with a unit count, a
 * purchase price, a current price and an ongoing charge figure. None of them
 * could be entered against a pension at all: the detail panel gated its Holdings
 * tab on already having holdings, so there was no way in, and the only other
 * route — the pension form's inline editor — offers name, type, allocation and
 * amount invested and nothing else.
 *
 * The payload here is what `HoldingForm` posts once the Holdings tab exists.
 *
 * **`sub_type` is the field with no rule.** The form makes the user choose a fund
 * type whenever the asset type is Fund, and this endpoint validated no rule for
 * it — so `validated()` dropped it and the choice was reported as saved and never
 * stored. It is asserted below because a rule with nothing reading it back proves
 * nothing.
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
    ]);
});

it('stores every field the holding form offers, including the fund type', function () {
    $this->postJson("/api/retirement/pensions/dc/{$this->pension->id}/holdings", [
        'security_name' => 'Vanguard Global Equity',
        'ticker' => 'VHVG',
        'isin' => 'IE00BKX55S42',
        'asset_type' => 'fund',
        'sub_type' => 'equity_fund',
        'allocation_percent' => 50,
        'quantity' => 4211,
        'purchase_price' => 32.50,
        'current_price' => 38.00,
        'purchase_date' => '2019-03-11',
        'current_value' => 160018,
        'ocf_percent' => 0.23,
    ])->assertCreated();

    $holding = Holding::query()->sole();

    expect($holding->security_name)->toBe('Vanguard Global Equity')
        ->and($holding->ticker)->toBe('VHVG')
        ->and($holding->isin)->toBe('IE00BKX55S42')
        // The field that had no rule. Before this it arrived, validated, and was
        // dropped by `validated()` before the write.
        ->and($holding->sub_type)->toBe('equity_fund')
        ->and((float) $holding->quantity)->toBe(4211.0)
        ->and((float) $holding->purchase_price)->toBe(32.5)
        ->and((float) $holding->current_price)->toBe(38.0)
        ->and($holding->purchase_date->toDateString())->toBe('2019-03-11')
        ->and((float) $holding->ocf_percent)->toBe(0.23)
        // 4,211 x £38.00. Distinct from the £160,000 that 50% of the pot gives,
        // so a value derived from the allocation instead would fail here.
        ->and((float) $holding->current_value)->toBe(160018.0)
        // 4,211 x £32.50.
        ->and((float) $holding->cost_basis)->toBe(136857.5);
});

it('accepts all three of the SIPP holdings and leaves the fund charge non-zero', function () {
    $holdings = [
        ['security_name' => 'Vanguard Global Equity', 'ticker' => 'VHVG', 'asset_type' => 'fund', 'sub_type' => 'equity_fund',
            'allocation_percent' => 50, 'quantity' => 4211, 'purchase_price' => 32.50, 'current_price' => 38.00,
            'current_value' => 160018, 'ocf_percent' => 0.23],
        ['security_name' => 'BlackRock Corporate Bond', 'ticker' => 'SLXX', 'asset_type' => 'bond',
            'allocation_percent' => 30, 'quantity' => 800, 'purchase_price' => 125.00, 'current_price' => 120.00,
            'current_value' => 96000, 'ocf_percent' => 0.18],
        ['security_name' => 'L&G UK Property', 'ticker' => 'LGUKP', 'asset_type' => 'property',
            'allocation_percent' => 20, 'quantity' => 50000, 'purchase_price' => 1.35, 'current_price' => 1.28,
            'current_value' => 64000, 'ocf_percent' => 0.68],
    ];

    foreach ($holdings as $holding) {
        $this->postJson("/api/retirement/pensions/dc/{$this->pension->id}/holdings", $holding)
            ->assertCreated();
    }

    $stored = Holding::query()->orderBy('id')->get();

    expect($stored)->toHaveCount(3);

    // **The unit counts are the discriminating assertions here, and deliberately
    // so.** Two of the persona's own values COINCIDE with what the allocation
    // percentage would derive from a £320,000 pot — 30% is £96,000 and 20% is
    // £64,000, which are exactly the figures those holdings hold. Asserting those
    // two values proves nothing about which mechanism produced them
    // (tests/CLAUDE.md §4, Collision). The unit counts and prices cannot be
    // derived from an allocation at all, so they are what this case rests on.
    expect((float) $stored[0]->quantity)->toBe(4211.0)
        ->and((float) $stored[1]->quantity)->toBe(800.0)
        ->and((float) $stored[2]->quantity)->toBe(50000.0)
        ->and((float) $stored[0]->current_price)->toBe(38.0)
        ->and((float) $stored[1]->current_price)->toBe(120.0)
        ->and((float) $stored[2]->current_price)->toBe(1.28);

    // The complaint that started this: a £320,000 pension reporting a 0.00% fund
    // charge against a persona whose funds charge 0.23%, 0.18% and 0.68%. Weighted
    // by value over the pot: (160018 x 0.23 + 96000 x 0.18 + 64000 x 0.68) / 320000.
    $weighted = $stored->sum(fn ($h) => (float) $h->current_value * (float) $h->ocf_percent)
        / (float) $this->pension->current_fund_value;

    // 0.305% — and distinct from each individual charge (0.23, 0.18, 0.68), so a
    // weighting that read only one holding could not land here either.
    expect(round($weighted, 4))->toBe(0.305)
        ->and($weighted)->toBeGreaterThan(0.0);
});
