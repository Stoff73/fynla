<?php

declare(strict_types=1);

use App\Models\CurrencyRate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->admin = User::factory()->create(['is_admin' => true, 'email_verified_at' => now()]);
    Sanctum::actingAs($this->admin);
});

it('lists currency rates', function () {
    CurrencyRate::factory()->count(3)->create();

    $this->getJson('/api/admin/currency-rates')
        ->assertOk()
        ->assertJsonCount(3, 'data');
});

it('creates a rate via the store', function () {
    $payload = [
        'from_ccy' => 'GBP',
        'to_ccy' => 'EUR',
        'rate' => 1.17,
        'effective_at' => '2026-04-06 00:00:00',
        'source' => 'manual',
    ];

    $this->postJson('/api/admin/currency-rates', $payload)
        ->assertCreated()
        ->assertJsonPath('data.from_ccy', 'GBP')
        ->assertJsonPath('data.to_ccy', 'EUR')
        ->assertJsonPath('data.rate', 1.17);

    $this->assertDatabaseHas('currency_rates', [
        'from_ccy' => 'GBP',
        'to_ccy' => 'EUR',
    ]);
});

it('updates a rate via the store', function () {
    $rate = CurrencyRate::factory()->create([
        'from_ccy' => 'GBP', 'to_ccy' => 'EUR', 'rate' => 1.15,
    ]);

    $this->patchJson("/api/admin/currency-rates/{$rate->id}", ['rate' => 1.25])
        ->assertOk()
        ->assertJsonPath('data.rate', 1.25);

    $this->assertDatabaseHas('currency_rates', [
        'id' => $rate->id, 'rate' => 1.25000000,
    ]);
});

it('deletes a rate via the store', function () {
    $rate = CurrencyRate::factory()->create();

    $this->deleteJson("/api/admin/currency-rates/{$rate->id}")->assertOk();

    $this->assertDatabaseMissing('currency_rates', ['id' => $rate->id]);
});

it('returns 422 for missing required fields', function () {
    $this->postJson('/api/admin/currency-rates', [
        'from_ccy' => 'GBP',
        'to_ccy' => 'EUR',
        // rate + effective_at missing
    ])->assertStatus(422);
});

it('returns 422 for identical from/to currencies', function () {
    $this->postJson('/api/admin/currency-rates', [
        'from_ccy' => 'GBP',
        'to_ccy' => 'GBP',
        'rate' => 1.0,
        'effective_at' => '2026-04-06 00:00:00',
    ])->assertStatus(422);
});

it('returns 403 for non-admin users', function () {
    $user = User::factory()->create(['is_admin' => false, 'email_verified_at' => now()]);
    Sanctum::actingAs($user);

    $this->getJson('/api/admin/currency-rates')->assertForbidden();
});
