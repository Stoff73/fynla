<?php

declare(strict_types=1);

use App\Models\SavingsMarketRate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->admin = User::factory()->create(['is_admin' => true, 'email_verified_at' => now()]);
    Sanctum::actingAs($this->admin);
});

it('lists savings market rates', function () {
    SavingsMarketRate::factory()->count(3)->create();

    $this->getJson('/api/admin/savings-market-rates')
        ->assertOk()
        ->assertJsonCount(3, 'data');
});

it('creates a rate via the store', function () {
    $payload = [
        'rate_key' => 'easy_access',
        'label' => 'Easy Access',
        'rate' => 0.045,
        'tax_year' => '2026/27',
        'effective_from' => '2026-04-06',
    ];

    $this->postJson('/api/admin/savings-market-rates', $payload)
        ->assertCreated()
        ->assertJsonPath('data.label', 'Easy Access')
        ->assertJsonPath('data.rate', 0.045);

    $this->assertDatabaseHas('savings_market_rates', ['label' => 'Easy Access', 'rate_key' => 'easy_access']);
});

it('updates a rate via the store', function () {
    $rate = SavingsMarketRate::factory()->create(['rate' => 0.03]);

    $this->patchJson("/api/admin/savings-market-rates/{$rate->id}", ['rate' => 0.05])
        ->assertOk()
        ->assertJsonPath('data.rate', 0.05);

    $this->assertDatabaseHas('savings_market_rates', ['id' => $rate->id, 'rate' => 0.05]);
});

it('deletes a rate via the store', function () {
    $rate = SavingsMarketRate::factory()->create();

    $this->deleteJson("/api/admin/savings-market-rates/{$rate->id}")->assertOk();

    $this->assertDatabaseMissing('savings_market_rates', ['id' => $rate->id]);
});

it('returns 422 for missing required fields', function () {
    $this->postJson('/api/admin/savings-market-rates', [
        'rate_key' => 'easy_access',
        'label' => 'Easy Access',
        // rate is missing
        'tax_year' => '2026/27',
        'effective_from' => '2026-04-06',
    ])->assertStatus(422);
});

it('returns 403 for non-admin users', function () {
    $user = User::factory()->create(['is_admin' => false, 'email_verified_at' => now()]);
    Sanctum::actingAs($user);

    $this->getJson('/api/admin/savings-market-rates')->assertForbidden();
});
