<?php

declare(strict_types=1);

use App\Models\Subscription;
use App\Models\User;
use Database\Seeders\TaxConfigurationSeeder;
use Database\Seeders\TierConfigurationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(TaxConfigurationSeeder::class);
    $this->seed(TierConfigurationSeeder::class);
    config(['app.payment_enabled' => true]);
});

it('allows a free user (no subscription) to perform writes', function () {
    $user = User::factory()->create(['tier' => 'free']);
    Sanctum::actingAs($user);

    // POST /api/savings/accounts is guarded by CheckSubscription and is NOT
    // feature-gated, so it isolates the subscription lockout under test.
    $response = $this->postJson('/api/savings/accounts', [
        'account_name' => 'Free Tier Savings',
        'account_type' => 'easy_access',
        'current_balance' => 100,
        'ownership_type' => 'individual',
    ]);

    // Must NOT be the subscription lockout (403 subscription_required).
    expect($response->json('error') ?? '')->not->toBe('subscription_required');
    expect($response->status())->not->toBe(403);
});

it('allows Free writes while checkout is pending without granting Premium', function () {
    $user = User::factory()->create(['tier' => 'free']);
    Subscription::factory()->pending()->create([
        'user_id' => $user->id,
    ]);
    Sanctum::actingAs($user);

    $response = $this->postJson('/api/savings/accounts', [
        'account_name' => 'Pending Checkout Savings',
        'account_type' => 'easy_access',
        'current_balance' => 100,
        'ownership_type' => 'individual',
    ]);

    expect($response->json('error') ?? '')->not->toBe('subscription_required');
    expect($response->status())->not->toBe(403)
        ->and($user->fresh()->tier)->toBe('free');
});

it('does not inherit stale Premium capabilities through a newer pending checkout', function () {
    $user = User::factory()->create(['tier' => 'premium', 'plan' => 'premium']);
    Subscription::factory()->plan('premium')->create([
        'user_id' => $user->id,
        'status' => 'active',
        'auto_renew' => false,
        'current_period_end' => now()->subMinute(),
    ]);
    Subscription::factory()->pending()->plan('premium')->create(['user_id' => $user->id]);
    Sanctum::actingAs($user);

    $this->postJson('/api/holistic/analyze')
        ->assertForbidden()
        ->assertJsonPath('error', 'capability_denied');
});

it('temporarily preserves Free writes for a historical trialing row until compatibility removal', function () {
    $user = User::factory()->create(['tier' => 'free']);
    Subscription::factory()->trialing()->create([
        'user_id' => $user->id,
    ]);
    Sanctum::actingAs($user);

    $response = $this->postJson('/api/savings/accounts', [
        'account_name' => 'Historical Compatibility Savings',
        'account_type' => 'easy_access',
        'current_balance' => 100,
        'ownership_type' => 'individual',
    ]);

    expect($response->json('error') ?? '')->not->toBe('subscription_required');
    expect($response->status())->not->toBe(403);
});

it('blocks writes for a churned paid user with a terminal subscription past grace', function () {
    $user = User::factory()->create(['tier' => null, 'plan' => 'pro']);
    Subscription::factory()->expired()->create([
        'user_id' => $user->id,
        'plan' => 'pro',
        'data_retention_starts_at' => now()->subDays(40), // past 30-day grace
    ]);
    Sanctum::actingAs($user);

    $response = $this->postJson('/api/savings/accounts', [
        'account_name' => 'Blocked',
        'account_type' => 'easy_access',
        'current_balance' => 1,
        'ownership_type' => 'individual',
    ]);

    $response->assertStatus(403);
    expect($response->json('error'))->toBe('subscription_required');
});

it('allows read (GET) for a churned paid user', function () {
    $user = User::factory()->create(['tier' => null, 'plan' => 'pro']);
    Subscription::factory()->expired()->create([
        'user_id' => $user->id,
        'plan' => 'pro',
        'data_retention_starts_at' => now()->subDays(40),
    ]);
    Sanctum::actingAs($user);

    $response = $this->getJson('/api/savings');
    expect($response->status())->not->toBe(403);
});
