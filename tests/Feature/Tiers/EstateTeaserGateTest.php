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

it('returns an Estate teaser for Free users with a Premium call to action', function () {
    Sanctum::actingAs(User::factory()->create(['tier' => 'free']));

    $expected = app(TierConfigurationStore::class)->lowestTierWithCapability('estate', 'full');

    $response = $this->getJson('/api/estate')
        ->assertOk()
        ->assertJsonPath('mode', 'teaser')
        ->assertJsonStructure([
            'mode',
            'teaser' => ['exposed', 'headline', 'estimated_liability_gbp'],
            'cta' => ['label', 'target_tier'],
        ])
        ->json();

    expect($expected['tier'])->toBe('premium')
        ->and($response['cta']['target_tier'])->toBe('premium')
        ->and($response['cta']['label'])->toContain($expected['display_name']);
});

it('returns full Estate access for Premium users', function () {
    Sanctum::actingAs(User::factory()->create(['tier' => 'premium']));

    $this->getJson('/api/estate')
        ->assertOk()
        ->assertJsonPath('mode', 'full');
});

it('keeps score keys out of the Free Estate teaser', function () {
    Sanctum::actingAs(User::factory()->create(['tier' => 'free']));

    $teaser = $this->getJson('/api/estate')->assertOk()->json('teaser');

    expect($teaser)->toHaveKeys(['exposed', 'headline', 'estimated_liability_gbp'])
        ->not->toHaveKey('score')
        ->not->toHaveKey('rating');
});

it('blocks Free users from full Estate routes', function (string $method, string $uri) {
    Sanctum::actingAs(User::factory()->create(['tier' => 'free']));

    $this->json($method, $uri)->assertForbidden();
})->with([
    ['POST', '/api/estate/profile'],
    ['POST', '/api/estate/calculate-iht'],
    ['GET', '/api/estate/net-worth'],
    ['GET', '/api/estate/cash-flow'],
    ['POST', '/api/estate/will'],
    ['POST', '/api/estate/bequests'],
    ['POST', '/api/estate/calculate-intestacy'],
    ['POST', '/api/estate/will-builder'],
    ['POST', '/api/estate/lpa'],
    ['POST', '/api/estate/lpa/upload'],
]);

it('allows Premium users through full Estate route gates', function (string $uri) {
    Sanctum::actingAs(User::factory()->create(['tier' => 'premium']));

    expect($this->postJson($uri)->status())->not->toBe(403);
})->with([
    '/api/estate/calculate-iht',
    '/api/estate/will',
    '/api/estate/lpa',
    '/api/estate/will-builder',
]);
