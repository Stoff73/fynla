<?php

declare(strict_types=1);

use App\Models\User;
use Database\Seeders\TierConfigurationSeeder;
use Laravel\Sanctum\Sanctum;

beforeEach(fn () => $this->seed(TierConfigurationSeeder::class));

it('returns all tier configs for an admin', function () {
    Sanctum::actingAs(User::factory()->create(['is_admin' => true]));
    $this->getJson('/api/admin/tier-configurations')
        ->assertOk()
        ->assertJsonCount(4, 'data')
        ->assertJsonPath('data.0.tier', 'free');
});

it('forbids a non-admin', function () {
    Sanctum::actingAs(User::factory()->create(['is_admin' => false]));
    $this->getJson('/api/admin/tier-configurations')->assertForbidden();
});

it('updates a tier price and persists via the store', function () {
    Sanctum::actingAs(User::factory()->create(['is_admin' => true]));
    $this->putJson('/api/admin/tier-configurations/tier2', [
        'price_monthly_pence' => 1799,
    ])->assertOk()->assertJsonPath('data.price_monthly_pence', 1799);

    $this->assertDatabaseHas('tier_configurations', ['tier' => 'tier2', 'price_monthly_pence' => 1799]);
    $this->assertDatabaseHas('audit_logs', ['model_type' => 'tier_configuration']);
});

it('rejects an invalid price', function () {
    Sanctum::actingAs(User::factory()->create(['is_admin' => true]));
    $this->putJson('/api/admin/tier-configurations/tier2', ['price_monthly_pence' => -5])
        ->assertStatus(422);
});

it('exposes a public pricing endpoint reading the same store', function () {
    $this->getJson('/api/pricing-config')
        ->assertOk()
        ->assertJsonPath('data.0.tier', 'free')
        ->assertJsonPath('data.1.price_monthly_pence', 499); // tier1 placeholder
});
