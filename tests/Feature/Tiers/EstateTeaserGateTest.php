<?php

declare(strict_types=1);

use App\Models\User;
use App\Services\Stores\TierConfigurationStore;
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

it('free user teaser CTA targets the lowest tier with full estate (tier2, not tier1)', function () {
    Sanctum::actingAs(User::factory()->create(['tier' => 'free']));

    // Derive expected values from the store — test stays correct if seeder changes
    $store = app(TierConfigurationStore::class);
    $expected = $store->lowestTierWithCapability('estate', 'full');

    $response = $this->getJson('/api/estate')->assertOk()->json();

    expect($response['cta']['target_tier'])->toBe($expected['tier'])
        ->and($response['cta']['label'])->toContain($expected['display_name']);
});

it('tier1 user teaser CTA also targets the lowest tier with full estate (tier2, not tier3)', function () {
    Sanctum::actingAs(User::factory()->create(['tier' => 'tier1']));

    $store = app(TierConfigurationStore::class);
    $expected = $store->lowestTierWithCapability('estate', 'full');

    $response = $this->getJson('/api/estate')->assertOk()->json();

    expect($response['cta']['target_tier'])->toBe($expected['tier'])
        ->and($response['cta']['label'])->toContain($expected['display_name']);
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

// --- Will Builder + Power of Attorney are Estate-module routes: spec §7 has no
// separate will/POA capability key, so they fall under "Estate planning"
// (teaser for Free/Tier1). §10.2 requires them gated server-side, not just
// hidden. These were missed in SP2 PR7 — only IHT/EstateController were gated.

it('free user cannot create/update a will (full Estate sub-route, spec §10.2)', function () {
    Sanctum::actingAs(User::factory()->create(['tier' => 'free']));

    $this->postJson('/api/estate/will', [])->assertForbidden();
});

it('tier1 user cannot create/update a will (full Estate sub-route, spec §10.2)', function () {
    Sanctum::actingAs(User::factory()->create(['tier' => 'tier1']));

    $this->postJson('/api/estate/will', [])->assertForbidden();
});

it('free user cannot create a bequest (full Estate sub-route, spec §10.2)', function () {
    Sanctum::actingAs(User::factory()->create(['tier' => 'free']));

    $this->postJson('/api/estate/bequests', [])->assertForbidden();
});

it('free user cannot calculate intestacy (full Estate engine, spec §10.2)', function () {
    Sanctum::actingAs(User::factory()->create(['tier' => 'free']));

    $this->postJson('/api/estate/calculate-intestacy', [])->assertForbidden();
});

it('free user cannot create a will-builder document (full Estate sub-route, spec §10.2)', function () {
    Sanctum::actingAs(User::factory()->create(['tier' => 'free']));

    $this->postJson('/api/estate/will-builder', [])->assertForbidden();
});

it('tier1 user cannot create a will-builder document (full Estate sub-route, spec §10.2)', function () {
    Sanctum::actingAs(User::factory()->create(['tier' => 'tier1']));

    $this->postJson('/api/estate/will-builder', [])->assertForbidden();
});

it('free user cannot create a Lasting Power of Attorney (full Estate sub-route, spec §10.2)', function () {
    Sanctum::actingAs(User::factory()->create(['tier' => 'free']));

    $this->postJson('/api/estate/lpa', [])->assertForbidden();
});

it('tier1 user cannot create a Lasting Power of Attorney (full Estate sub-route, spec §10.2)', function () {
    Sanctum::actingAs(User::factory()->create(['tier' => 'tier1']));

    $this->postJson('/api/estate/lpa', [])->assertForbidden();
});

it('free user cannot upload a Lasting Power of Attorney (full Estate sub-route, spec §10.2)', function () {
    Sanctum::actingAs(User::factory()->create(['tier' => 'free']));

    $this->postJson('/api/estate/lpa/upload', [])->assertForbidden();
});

it('tier2 user can POST to will (gate allows through to validation, not 403)', function () {
    Sanctum::actingAs(User::factory()->create(['tier' => 'tier2']));

    expect($this->postJson('/api/estate/will', [])->status())->not->toBe(403);
});

it('tier2 user can POST to lpa (gate allows through to validation, not 403)', function () {
    Sanctum::actingAs(User::factory()->create(['tier' => 'tier2']));

    expect($this->postJson('/api/estate/lpa', [])->status())->not->toBe(403);
});

it('tier3 user can POST to will-builder (gate allows through, not 403)', function () {
    Sanctum::actingAs(User::factory()->create(['tier' => 'tier3']));

    expect($this->postJson('/api/estate/will-builder', [])->status())->not->toBe(403);
});
