<?php

declare(strict_types=1);

use App\Models\AuditLog;
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
            'error' => [
                'entity_key' => 'investment',
            ],
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
