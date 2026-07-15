<?php

declare(strict_types=1);

use App\Models\User;
use App\Services\Stores\TierConfigurationStore;
use App\Services\Tiers\TierResolver;
use Database\Seeders\TierConfigurationSeeder;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    $this->seed(TierConfigurationSeeder::class);
});

it('exposes only the canonical Free and Premium tier keys', function () {
    expect(TierConfigurationStore::TIERS)->toBe(['free', 'premium']);
});

it('returns only Free and Premium from the public pricing configuration endpoint', function () {
    $this->getJson('/api/pricing-config')
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.tier', 'free')
        ->assertJsonPath('data.1.tier', 'premium');
});

it('returns Premium as the only selectable billing plan', function () {
    $this->getJson('/api/payment/plans')
        ->assertOk()
        ->assertJsonCount(1, 'plans')
        ->assertJsonPath('plans.0.slug', 'premium');
});

it('resolves canonical users only to Free or Premium', function () {
    $free = new User(['tier' => 'free']);
    $premium = new User(['tier' => 'premium']);
    $resolver = app(TierResolver::class);

    expect($resolver->resolve($free))->toBe('free')
        ->and($resolver->resolve($premium))->toBe('premium');
});

it('accepts Premium at the paid checkout validation boundary', function () {
    $user = User::factory()->create(['revolut_customer_id' => 'cust_premium']);

    Http::fake([
        '*' => Http::response([
            'id' => 'order_premium',
            'token' => 'token_premium',
            'state' => 'pending',
            'created_at' => now()->toIso8601String(),
        ], 200),
    ]);

    $this->actingAs($user, 'sanctum')
        ->postJson('/api/payment/create-order', [
            'plan' => 'premium',
            'billing_cycle' => 'monthly',
        ])
        ->assertOk()
        ->assertJsonPath('order_id', 'order_premium');
});

it('rejects retired plans at the paid checkout validation boundary', function (string $plan) {
    $user = User::factory()->create();

    $this->actingAs($user, 'sanctum')
        ->postJson('/api/payment/create-order', [
            'plan' => $plan,
            'billing_cycle' => 'monthly',
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['plan']);
})->with(['student', 'standard', 'family', 'pro', 'tier1', 'tier2', 'tier3']);

it('rejects Free at the paid checkout validation boundary', function () {
    $user = User::factory()->create();

    $this->actingAs($user, 'sanctum')
        ->postJson('/api/payment/create-order', [
            'plan' => 'free',
            'billing_cycle' => 'monthly',
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['plan']);
});
