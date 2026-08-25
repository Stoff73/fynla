<?php

declare(strict_types=1);

use App\Models\AuditLog;
use App\Models\Investment\Holding;
use App\Models\Investment\InvestmentAccount;
use App\Models\User;
use Database\Seeders\TaxConfigurationSeeder;
use Database\Seeders\TierConfigurationSeeder;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->seed(TaxConfigurationSeeder::class);
    $this->seed(TierConfigurationSeeder::class);
    config(['audit.in_tests' => true]);
    $this->user = User::factory()->withActivePremiumSubscription()->create(['tier' => 'premium']);
    Sanctum::actingAs($this->user);
});

it('creates an investment account via POST and records FORM audit context', function () {
    $payload = [
        'account_type' => 'gia',
        'account_name' => 'General Investment Account',
        'provider' => 'Hargreaves Lansdown',
        'current_value' => 50000.00,
        'ownership_type' => 'individual',
        'ownership_percentage' => 100.00,
    ];

    $response = $this->postJson('/api/investment/accounts', $payload);

    $response->assertCreated();
    $this->assertDatabaseHas('investment_accounts', [
        'user_id' => $this->user->id,
        'provider' => 'Hargreaves Lansdown',
        'account_type' => 'gia',
    ]);

    // Scope to the acting user (freshly created per test) and take the newest
    // row. The InvestmentAccount factory's default provider is also
    // "Hargreaves Lansdown", so an unscoped where(provider)->first() can return
    // a row another test committed/leaked — whose CREATED audit doesn't exist in
    // this transaction — yielding a flaky null. user_id isolates this test's row.
    $account = InvestmentAccount::where('user_id', $this->user->id)
        ->where('provider', 'Hargreaves Lansdown')
        ->latest('id')
        ->first();
    $auditRow = AuditLog::where('model_type', InvestmentAccount::class)
        ->where('model_id', $account->id)
        ->where('action', AuditLog::ACTION_CREATED)
        ->latest('id')
        ->first();
    expect($auditRow)->not->toBeNull();
    expect($auditRow->metadata['ingest_source'] ?? null)->toBe('form');
});

it('updates an investment account via PUT', function () {
    $account = InvestmentAccount::factory()->gia()->create([
        'user_id' => $this->user->id,
        'current_value' => 30000.00,
        'ownership_type' => 'individual',
        'ownership_percentage' => 100.00,
    ]);

    $response = $this->putJson("/api/investment/accounts/{$account->id}", [
        'current_value' => 35000.00,
        'ownership_type' => 'individual',
        'ownership_percentage' => 100.00,
    ]);

    $response->assertOk();
    expect((float) $account->fresh()->current_value)->toEqual(35000.00);
});

it('stores a joint investment account 50/50 when the form states no share', function () {
    // W-0014 gave this test its original shape: AccountForm.vue has no
    // ownership-share input, initialised the field to the individual default of
    // 100 and sent it, so every joint account was stored 100/0 — the whole value
    // on the primary owner and nothing on the spouse. The fix then was for the
    // controller to rewrite a submitted 100 to 50.
    //
    // W-0040 changed what this test asserts. CSJ ruled that a 100/0 split IS
    // individual ownership, so a STATED 100 is refused rather than silently
    // rewritten (see the test below), and AccountForm.vue now omits the field on
    // a shared payload instead of echoing an inherited one. So the payload here
    // no longer carries a share at all — which is what the form now sends — and
    // the outcome it asserts is unchanged: one row, full value, 50/50.
    $spouse = User::factory()->withActivePremiumSubscription()->create(['tier' => 'premium']);
    $this->user->update(['spouse_id' => $spouse->id]);
    $spouse->update(['spouse_id' => $this->user->id]);

    $response = $this->postJson('/api/investment/accounts', [
        'account_type' => 'gia',
        'account_name' => 'Joint General Investment Account',
        'provider' => 'AJ Bell',
        'current_value' => 95000.00,
        'ownership_type' => 'joint',
        'joint_owner_id' => $spouse->id,
    ]);

    $response->assertCreated();

    $account = InvestmentAccount::where('user_id', $this->user->id)
        ->where('provider', 'AJ Bell')
        ->latest('id')
        ->firstOrFail();

    // Rule 6: ONE row holding the FULL value, split 50/50.
    expect($account->ownership_type)->toBe('joint')
        ->and($account->joint_owner_id)->toBe($spouse->id)
        ->and((float) $account->ownership_percentage)->toEqual(50.00)
        ->and((float) $account->current_value)->toEqual(95000.00);

    // And both sides are told the same story: half each, never the whole thing twice.
    expect((float) $response->json('data.user_share'))->toEqual(47500.00);

    Sanctum::actingAs($spouse);
    $spouseView = collect($this->getJson('/api/investment')->json('data.accounts'))
        ->firstWhere('id', $account->id);

    expect((float) $spouseView['user_share'])->toEqual(47500.00)
        ->and($spouseView['is_primary_owner'])->toBeFalse();
});

it('refuses a stated 100% share on a joint account instead of rewriting it', function () {
    // W-0040. The other half of the rule above. A caller stating "I own all of
    // it" on a shared asset used to be answered 201 and stored as "I own half of
    // it", while a caller stating 0 was refused — an asymmetry nobody chose, it
    // fell out of the coercion. A 100/0 split IS individual ownership, so the
    // boundary now says so rather than quietly altering the figure.
    $spouse = User::factory()->withActivePremiumSubscription()->create(['tier' => 'premium']);
    $this->user->update(['spouse_id' => $spouse->id]);
    $spouse->update(['spouse_id' => $this->user->id]);

    $response = $this->postJson('/api/investment/accounts', [
        'account_type' => 'gia',
        'account_name' => 'Wrongly Joint Account',
        'provider' => 'Vanguard',
        'current_value' => 40000.00,
        'ownership_type' => 'joint',
        'joint_owner_id' => $spouse->id,
        'ownership_percentage' => 100.00,
    ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors('ownership_percentage');

    // Refused means refused: nothing was stored, and nothing was rewritten.
    expect(InvestmentAccount::where('user_id', $this->user->id)
        ->where('provider', 'Vanguard')
        ->exists())->toBeFalse();
});

it('keeps a deliberate uneven joint share instead of forcing 50/50', function () {
    $spouse = User::factory()->withActivePremiumSubscription()->create(['tier' => 'premium']);
    $this->user->update(['spouse_id' => $spouse->id]);
    $spouse->update(['spouse_id' => $this->user->id]);

    $this->postJson('/api/investment/accounts', [
        'account_type' => 'gia',
        'account_name' => 'Uneven Joint Account',
        'provider' => 'Interactive Investor',
        'current_value' => 100000.00,
        'ownership_type' => 'joint',
        'joint_owner_id' => $spouse->id,
        'ownership_percentage' => 70.00,
    ])->assertCreated();

    $account = InvestmentAccount::where('user_id', $this->user->id)
        ->where('provider', 'Interactive Investor')
        ->latest('id')
        ->firstOrFail();

    expect((float) $account->ownership_percentage)->toEqual(70.00);
});

it('accepts and persists an adviser fee on an investment account', function () {
    // The column, the fee breakdown and the projections all read
    // advisor_fee_percent already; there was simply no validation rule for it,
    // so a submitted value was stripped and it read 0% forever (W-0008).
    $this->postJson('/api/investment/accounts', [
        'account_type' => 'isa',
        'account_name' => 'Stocks and Shares ISA',
        'provider' => 'Hargreaves Lansdown',
        'current_value' => 85000.00,
        'ownership_type' => 'individual',
        'platform_fee_percent' => 0.45,
        'advisor_fee_percent' => 0.75,
    ])->assertCreated();

    $account = InvestmentAccount::where('user_id', $this->user->id)
        ->where('provider', 'Hargreaves Lansdown')
        ->latest('id')
        ->firstOrFail();

    expect((float) $account->advisor_fee_percent)->toEqual(0.75)
        ->and((float) $account->platform_fee_percent)->toEqual(0.45);

    $this->putJson("/api/investment/accounts/{$account->id}", [
        'current_value' => 85000.00,
        'advisor_fee_percent' => 0.50,
    ])->assertOk();

    expect((float) $account->fresh()->advisor_fee_percent)->toEqual(0.50);
});

it('accepts a holding with its unit count and values it from units x price', function () {
    // Ten of the peak_earners holdings carry a unit count and none of them could
    // be entered: the form had no units input and the server derived quantity
    // FROM the value, putting the user's actual fact at the end of the chain
    // (W-0039).
    $account = InvestmentAccount::factory()->gia()->create([
        'user_id' => $this->user->id,
        'current_value' => 100000.00,
    ]);

    $this->postJson('/api/investment/holdings', [
        'investment_account_id' => $account->id,
        'security_name' => 'Vanguard LifeStrategy 80',
        'asset_type' => 'fund',
        'sub_type' => 'mixed_fund',
        'allocation_percent' => 100,
        'quantity' => 333,
        'current_price' => 255.00,
        'purchase_price' => 225.00,
        'current_value' => 84915.00,
        'dividend_yield' => 2.10,
    ])->assertCreated();

    $holding = Holding::where('holdable_id', $account->id)
        ->where('security_name', 'Vanguard LifeStrategy 80')
        ->firstOrFail();

    expect((float) $holding->quantity)->toEqual(333.0)
        ->and((float) $holding->current_value)->toEqual(84915.00)
        ->and((float) $holding->cost_basis)->toEqual(74925.00)
        ->and((float) $holding->dividend_yield)->toEqual(2.10);
});

it('revalues a holding from its stored units when only the price is edited', function () {
    $account = InvestmentAccount::factory()->gia()->create([
        'user_id' => $this->user->id,
        'current_value' => 100000.00,
    ]);

    $this->postJson('/api/investment/holdings', [
        'investment_account_id' => $account->id,
        'security_name' => 'Fundsmith Equity',
        'asset_type' => 'fund',
        'sub_type' => 'equity_fund',
        'allocation_percent' => 100,
        'quantity' => 351,
        'current_price' => 7.42,
        'current_value' => 2604.42,
    ])->assertCreated();

    $holding = Holding::where('security_name', 'Fundsmith Equity')->firstOrFail();

    $this->putJson("/api/investment/holdings/{$holding->id}", ['current_price' => 8.00])
        ->assertOk();

    $holding->refresh();

    // Units unchanged; the value follows the new price rather than going stale.
    expect((float) $holding->quantity)->toEqual(351.0)
        ->and((float) $holding->current_value)->toEqual(2808.00);
});

it('clears joint_owner_id when switching a joint account to individual via PUT', function () {
    $spouse = User::factory()->withActivePremiumSubscription()->create(['tier' => 'premium']);
    $account = InvestmentAccount::factory()->gia()->create([
        'user_id' => $this->user->id,
        'ownership_type' => 'joint',
        'ownership_percentage' => 50.00,
        'joint_owner_id' => $spouse->id,
    ]);

    $response = $this->putJson("/api/investment/accounts/{$account->id}", [
        'current_value' => 40000.00,
        'ownership_type' => 'individual',
    ]);

    $response->assertOk();

    $fresh = $account->fresh();
    expect($fresh->ownership_type)->toBe('individual');
    expect($fresh->joint_owner_id)->toBeNull();
    expect((float) $fresh->ownership_percentage)->toEqual(100.00);
});

it('preserves include_in_retirement on a partial update that omits the field', function () {
    $account = InvestmentAccount::factory()->gia()->create([
        'user_id' => $this->user->id,
        'current_value' => 30000.00,
        'include_in_retirement' => true,
        'ownership_type' => 'individual',
        'ownership_percentage' => 100.00,
    ]);

    $response = $this->putJson("/api/investment/accounts/{$account->id}", [
        'current_value' => 36000.00,
        'ownership_type' => 'individual',
        'ownership_percentage' => 100.00,
    ]);

    $response->assertOk();

    $fresh = $account->fresh();
    expect((float) $fresh->current_value)->toEqual(36000.00);
    expect($fresh->include_in_retirement)->toBeTrue();
});

it('toggles include_in_retirement via PATCH', function () {
    $account = InvestmentAccount::factory()->gia()->create([
        'user_id' => $this->user->id,
        'include_in_retirement' => false,
        'ownership_type' => 'individual',
        'ownership_percentage' => 100.00,
    ]);

    $response = $this->patchJson("/api/investment/accounts/{$account->id}/toggle-retirement");

    $response->assertOk();
    expect($account->fresh()->include_in_retirement)->toBeTrue();

    $this->patchJson("/api/investment/accounts/{$account->id}/toggle-retirement")->assertOk();
    expect($account->fresh()->include_in_retirement)->toBeFalse();
});

it('soft-deletes an investment account via DELETE', function () {
    $account = InvestmentAccount::factory()->gia()->create([
        'user_id' => $this->user->id,
        'ownership_type' => 'individual',
        'ownership_percentage' => 100.00,
    ]);

    $response = $this->deleteJson("/api/investment/accounts/{$account->id}");

    $response->assertOk();
    $this->assertSoftDeleted('investment_accounts', ['id' => $account->id]);
});

it('returns 403 with structured payload when free-tier investment cap is exceeded', function () {
    $freeUser = User::factory()->create(['tier' => 'free']);
    Sanctum::actingAs($freeUser);

    InvestmentAccount::factory(2)->create([
        'user_id' => $freeUser->id,
        'ownership_type' => 'individual',
        'ownership_percentage' => 100.00,
    ]);

    $response = $this->postJson('/api/investment/accounts', [
        'account_type' => 'gia',
        'provider' => 'Third Account',
        'current_value' => 1000.00,
        'ownership_type' => 'individual',
        'ownership_percentage' => 100.00,
    ]);

    $response->assertStatus(403)
        ->assertJson([
            'success' => false,
            'error' => 'tier_limit_reached',
            'entity_key' => 'investment',
            'action' => 'subscription_options',
        ]);
});

it('rejects update from non-owner (404)', function () {
    $other = User::factory()->withActivePremiumSubscription()->create(['tier' => 'premium']);
    $account = InvestmentAccount::factory()->gia()->create([
        'user_id' => $other->id,
        'ownership_type' => 'individual',
        'ownership_percentage' => 100.00,
    ]);

    $response = $this->putJson("/api/investment/accounts/{$account->id}", [
        'current_value' => 999999.00,
        'ownership_type' => 'individual',
        'ownership_percentage' => 100.00,
    ]);

    $response->assertStatus(404);
});

it('rejects delete from non-owner (404)', function () {
    $other = User::factory()->withActivePremiumSubscription()->create(['tier' => 'premium']);
    $account = InvestmentAccount::factory()->gia()->create([
        'user_id' => $other->id,
        'ownership_type' => 'individual',
        'ownership_percentage' => 100.00,
    ]);

    $this->deleteJson("/api/investment/accounts/{$account->id}")
        ->assertStatus(404);
});
