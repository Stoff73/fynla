<?php

declare(strict_types=1);

use App\Models\User;
use Database\Seeders\TaxConfigurationSeeder;
use Database\Seeders\TierConfigurationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(TierConfigurationSeeder::class);
    $this->seed(TaxConfigurationSeeder::class);
});

it('free user hitting the full Estate endpoint gets the teaser, not the module', function () {
    Sanctum::actingAs(User::factory()->create(['tier' => 'free']));

    $this->getJson('/api/estate')
        ->assertOk()
        ->assertJsonPath('mode', 'teaser')
        ->assertJsonStructure([
            'mode',
            'teaser' => ['exposed', 'headline', 'estimated_liability_gbp'],
            'cta' => ['label', 'target_tier'],
        ]);
});

it('tier1 user hitting the full Estate endpoint gets the teaser', function () {
    Sanctum::actingAs(User::factory()->create(['tier' => 'tier1']));

    $this->getJson('/api/estate')
        ->assertOk()
        ->assertJsonPath('mode', 'teaser');
});

it('tier2 user gets the full Estate module', function () {
    Sanctum::actingAs(User::factory()->create(['tier' => 'tier2']));

    $this->getJson('/api/estate')
        ->assertOk()
        ->assertJsonPath('mode', 'full');
});

it('tier3 user gets the full Estate module', function () {
    Sanctum::actingAs(User::factory()->create(['tier' => 'tier3']));

    $this->getJson('/api/estate')
        ->assertOk()
        ->assertJsonPath('mode', 'full');
});

it('free user cannot reach a full-Estate write/calc sub-route (server-side, not just UI)', function () {
    Sanctum::actingAs(User::factory()->create(['tier' => 'free']));

    $this->postJson('/api/estate/profile', [])
        ->assertForbidden();
});

it('teaser response contains no score keys — currency and headline only (Rule #13)', function () {
    Sanctum::actingAs(User::factory()->create(['tier' => 'free']));

    $response = $this->getJson('/api/estate')->assertOk()->json();

    expect($response['teaser'])->toHaveKeys(['exposed', 'headline', 'estimated_liability_gbp'])
        ->not->toHaveKey('score')
        ->not->toHaveKey('rating');
});

it('free user POSTing to calculate-iht is forbidden (full engine gated, spec §10.2)', function () {
    Sanctum::actingAs(User::factory()->create(['tier' => 'free']));

    $this->postJson('/api/estate/calculate-iht', [])
        ->assertForbidden();
});

it('tier1 user POSTing to calculate-iht is forbidden (full engine gated, spec §10.2)', function () {
    Sanctum::actingAs(User::factory()->create(['tier' => 'tier1']));

    $this->postJson('/api/estate/calculate-iht', [])
        ->assertForbidden();
});

it('free user GETting estate/net-worth is forbidden (full engine gated, spec §10.2)', function () {
    Sanctum::actingAs(User::factory()->create(['tier' => 'free']));

    $this->getJson('/api/estate/net-worth')
        ->assertForbidden();
});

it('free user GETting estate/cash-flow is forbidden (full engine gated, spec §10.2)', function () {
    Sanctum::actingAs(User::factory()->create(['tier' => 'free']));

    $this->getJson('/api/estate/cash-flow')
        ->assertForbidden();
});

it('tier2 user can POST to calculate-iht (full module access)', function () {
    Sanctum::actingAs(User::factory()->create(['tier' => 'tier2']));

    // Just verify the gate allows through (may return calculation result or validation error, not 403)
    $response = $this->postJson('/api/estate/calculate-iht', []);
    expect($response->status())->not->toBe(403);
});
