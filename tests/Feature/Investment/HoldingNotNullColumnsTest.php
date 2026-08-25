<?php

declare(strict_types=1);

use App\Http\Requests\Investment\StoreHoldingRequest;
use App\Http\Requests\Investment\UpdateHoldingRequest;
use App\Models\Investment\Holding;
use App\Models\Investment\InvestmentAccount;
use App\Models\User;
use App\Support\HoldingValuation;
use Database\Seeders\TaxConfigurationSeeder;
use Database\Seeders\TierConfigurationSeeder;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;

/**
 * W-0261 — a field labelled "(Optional)" was NOT NULL, and leaving it blank
 * printed the raw INSERT statement to the user.
 *
 * `holdings.dividend_yield` is NOT NULL DEFAULT '0.0000' (decimal(5,4) when this
 * item was written; widened to decimal(7,4) by W-0263 — see the range tests at
 * the foot of this file).
 * `HoldingForm` labels it "Dividend Yield % (Optional)" and initialises it to an
 * explicit `null`; both form requests validate it `nullable`, so `validated()`
 * stopped stripping it and the null reached the column. `ocf_percent` is the
 * identical column and carried the identical latent failure — it only escaped
 * notice because the tester happened to fill it in.
 *
 * The third instance of the W-0052 pattern, fixed the same way: drop the null at
 * the write boundary so the column's own default applies.
 *
 * TEST DESIGN — the fixture variant of `tests/CLAUDE.md` §4. A test that supplies
 * `dividend_yield` cannot fail, because the branch it needs is never entered. Every
 * case below either sends the field as null or omits the key entirely, which is
 * what the user did.
 */
beforeEach(function () {
    $this->seed(TaxConfigurationSeeder::class);
    $this->seed(TierConfigurationSeeder::class);
    $this->user = User::factory()->withActivePremiumSubscription()->create(['tier' => 'premium']);
    Sanctum::actingAs($this->user);

    $this->account = InvestmentAccount::factory()->create([
        'user_id' => $this->user->id,
        'current_value' => 95000.00,
    ]);
});

it('creates a holding with the optional dividend yield left blank', function () {
    // The exact payload HoldingForm sends when the user fills the form and leaves
    // "Dividend Yield % (Optional)" alone: the key is present and null, because
    // that is the field's initial value. This is what raised
    // "Column 'dividend_yield' cannot be null" in front of the user.
    $response = $this->postJson('/api/investment/holdings', [
        'investment_account_id' => $this->account->id,
        'security_name' => 'Fundsmith Equity',
        'asset_type' => 'fund',
        'sub_type' => 'equity_fund',
        'allocation_percent' => 36.80,
        'quantity' => 351,
        'purchase_price' => 85.50,
        'current_price' => 99.86,
        'current_value' => 35050.86,
        'dividend_yield' => null,
        'ocf_percent' => 0.95,
    ]);

    $response->assertCreated();

    $holding = Holding::where('holdable_id', $this->account->id)
        ->where('security_name', 'Fundsmith Equity')
        ->firstOrFail();

    // The column falls back to its own default rather than rejecting the insert.
    expect((float) $holding->dividend_yield)->toEqual(0.0)
        ->and((float) $holding->ocf_percent)->toEqual(0.95);
});

it('creates a holding with the ongoing charge figure left blank', function () {
    // The latent twin. `ocf_percent` is the same column type with the same default
    // and the same `nullable` rule; nothing but the tester's choice of test data
    // stopped it failing identically.
    $this->postJson('/api/investment/holdings', [
        'investment_account_id' => $this->account->id,
        'security_name' => 'Scottish Mortgage Investment Trust',
        'asset_type' => 'equity',
        'allocation_percent' => 20.00,
        'quantity' => 100,
        'current_price' => 95.00,
        'current_value' => 9500.00,
        'dividend_yield' => null,
        'ocf_percent' => null,
    ])->assertCreated();

    $holding = Holding::where('holdable_id', $this->account->id)
        ->where('security_name', 'Scottish Mortgage Investment Trust')
        ->firstOrFail();

    expect((float) $holding->ocf_percent)->toEqual(0.0)
        ->and((float) $holding->dividend_yield)->toEqual(0.0);
});

it('creates a holding when the optional keys are absent entirely', function () {
    // An API caller that simply does not mention the fields, rather than nulling
    // them. Distinct from the case above: it proves the fix is not "coalesce null
    // to zero on a key we always expect".
    $this->postJson('/api/investment/holdings', [
        'investment_account_id' => $this->account->id,
        'security_name' => 'Vanguard FTSE All-World',
        'asset_type' => 'etf',
        'allocation_percent' => 15.00,
        'quantity' => 50,
        'current_price' => 100.00,
        'current_value' => 5000.00,
    ])->assertCreated();

    $holding = Holding::where('holdable_id', $this->account->id)
        ->where('security_name', 'Vanguard FTSE All-World')
        ->firstOrFail();

    expect((float) $holding->dividend_yield)->toEqual(0.0)
        ->and((float) $holding->ocf_percent)->toEqual(0.0);
});

it('still stores a dividend yield that IS supplied', function () {
    // The drop must not become "these fields are always zero". A stated figure is
    // a fact and survives.
    $this->postJson('/api/investment/holdings', [
        'investment_account_id' => $this->account->id,
        'security_name' => 'Vanguard UK Government Bond',
        'asset_type' => 'bond',
        'allocation_percent' => 26.30,
        'quantity' => 1316,
        'current_price' => 19.00,
        'current_value' => 25004.00,
        'dividend_yield' => 2.10,
        'ocf_percent' => 0.12,
    ])->assertCreated();

    $holding = Holding::where('holdable_id', $this->account->id)
        ->where('security_name', 'Vanguard UK Government Bond')
        ->firstOrFail();

    expect((float) $holding->dividend_yield)->toEqual(2.10)
        ->and((float) $holding->ocf_percent)->toEqual(0.12);
});

it('leaves a stored dividend yield alone when an update nulls it', function () {
    // The update path validates the same two fields `nullable`. Dropping the key
    // rather than coalescing to zero is what stops an edit of an unrelated field
    // silently wiping a figure the user entered earlier — the same rule the
    // investment-account fix settled on (W-0052).
    $holding = Holding::factory()->create([
        'holdable_id' => $this->account->id,
        'holdable_type' => InvestmentAccount::class,
        'dividend_yield' => 3.50,
        'ocf_percent' => 0.22,
    ]);

    $this->putJson("/api/investment/holdings/{$holding->id}", [
        'allocation_percent' => 42.00,
        'dividend_yield' => null,
        'ocf_percent' => null,
    ])->assertOk();

    $holding->refresh();

    expect((float) $holding->dividend_yield)->toEqual(3.50)
        ->and((float) $holding->ocf_percent)->toEqual(0.22)
        ->and((float) $holding->allocation_percent)->toEqual(42.00);
});

it('keeps the NOT NULL list in step with the actual holdings schema', function () {
    // The guard that stops a fourth occurrence. Adding a `nullable` rule for a
    // NOT NULL column — the exact mistake behind W-0008, W-0052 and this item — is
    // what moves a column into this set, so the error now fails a test instead of
    // a user's save.
    //
    // `COLUMN_DEFAULT IS NOT NULL` is load-bearing: `current_value`,
    // `security_name` and `asset_type` are NOT NULL with NO default, so there is
    // nothing to fall back to and dropping the key would fabricate a figure rather
    // than defer to one. They are correctly outside this set.
    $notNull = collect(DB::select(
        'SELECT COLUMN_NAME FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE()
           AND TABLE_NAME = ?
           AND IS_NULLABLE = ?
           AND COLUMN_DEFAULT IS NOT NULL',
        ['holdings', 'NO']
    ))->pluck('COLUMN_NAME');

    $reachable = array_merge(
        array_keys((new StoreHoldingRequest)->rules()),
        array_keys((new UpdateHoldingRequest)->rules()),
    );

    $actual = $notNull->intersect($reachable)->sort()->values()->all();

    $declared = collect(HoldingValuation::NOT_NULL_WITH_DEFAULT)->sort()->values()->all();

    expect($declared)->toBe($actual);
});

it('stores a double-digit dividend yield', function () {
    // The range sibling of this item's headline bug, found in the browser while
    // verifying it. `dividend_yield` was decimal(5,4) — it stopped at 9.9999 —
    // while the rule said max:100, so an ordinary-looking 50 passed validation and
    // raised SQLSTATE[22003] at the column.
    //
    // W-0261 capped the rule at 9.9999 to make that a 422 rather than a 500, and
    // said in the same breath that the cap was half a fix: a real dividend yield
    // CAN exceed 10%, so refusing one was a wrong answer delivered politely. It
    // handed the range decision to W-0263, which widened the column to
    // decimal(7,4) and restored the honest bound.
    //
    // 50 is the probe precisely because it is the value that used to fail — first
    // with a 500, then with a 422. Anything under 10 would pass under all three
    // states of this code and prove nothing.
    $this->postJson('/api/investment/holdings', [
        'investment_account_id' => $this->account->id,
        'security_name' => 'High Yield Probe',
        'asset_type' => 'equity',
        'allocation_percent' => 1,
        'quantity' => 10,
        'current_price' => 10,
        'current_value' => 100,
        'dividend_yield' => 50,
    ])->assertCreated();

    expect((float) Holding::where('security_name', 'High Yield Probe')->firstOrFail()->dividend_yield)
        ->toEqual(50.0);
});

it('still rejects a dividend yield beyond the widened column', function () {
    // The widening must not become "any number at all". decimal(7,4) reaches
    // 999.9999, so the rule at max:100 is now the binding constraint — which is
    // the right way round: the user is told, by a rule that means what it says.
    $this->postJson('/api/investment/holdings', [
        'investment_account_id' => $this->account->id,
        'security_name' => 'Absurd Yield Probe',
        'asset_type' => 'equity',
        'allocation_percent' => 1,
        'quantity' => 10,
        'current_price' => 10,
        'current_value' => 100,
        'dividend_yield' => 150,
    ])->assertStatus(422)->assertJsonValidationErrors(['dividend_yield']);

    expect(Holding::where('security_name', 'Absurd Yield Probe')->exists())->toBeFalse();
});

it('still accepts an ordinary single-digit yield', function () {
    // The commonest case must keep working through both changes.
    $this->postJson('/api/investment/holdings', [
        'investment_account_id' => $this->account->id,
        'security_name' => 'Ordinary Yield Probe',
        'asset_type' => 'equity',
        'allocation_percent' => 1,
        'quantity' => 10,
        'current_price' => 10,
        'current_value' => 100,
        'dividend_yield' => 4.25,
    ])->assertCreated();

    expect((float) Holding::where('security_name', 'Ordinary Yield Probe')->firstOrFail()->dividend_yield)
        ->toEqual(4.25);
});
