<?php

declare(strict_types=1);

use App\Models\User;
use App\Services\Stores\IngestSource;
use App\Services\Stores\TierConfigurationStore;
use App\Services\Tiers\TierResolver;
use Database\Seeders\TierConfigurationSeeder;

beforeEach(fn () => $this->seed(TierConfigurationSeeder::class));

it('a store price change shows on the public pricing endpoint', function () {
    app(TierConfigurationStore::class)->updateTier('premium',
        ['price_monthly_pence' => 1234], User::factory()->create(['is_admin' => true]), IngestSource::ADMIN);

    $this->getJson('/api/pricing-config')
        ->assertJsonPath('data.1.tier', 'premium')
        ->assertJsonPath('data.1.price_monthly_pence', 1234);
});

it('publishes the ordered server-owned comparison for every availability state', function () {
    $response = $this->getJson('/api/pricing-config')
        ->assertOk()
        ->assertJsonPath('data.0.features.0', [
            'key' => 'dashboard',
            'label' => 'Financial dashboard',
            'included' => true,
            'availability' => 'full',
        ])
        ->assertJsonPath('data.0.features.5', [
            'key' => 'savings_account',
            'label' => 'Up to 2 bank accounts',
            'included' => true,
            'availability' => 'limited',
        ])
        ->assertJsonPath('data.0.features.7', [
            'key' => 'investments_exotic',
            'label' => 'Alternative investments',
            'included' => false,
            'availability' => 'none',
        ])
        ->assertJsonPath('data.0.features.11', [
            'key' => 'chattels',
            'label' => 'Valuables',
            'included' => true,
            'availability' => 'full',
        ])
        ->assertJsonPath('data.0.features.15', [
            'key' => 'estate',
            'label' => 'Estate planning — preview only',
            'included' => true,
            'availability' => 'teaser',
        ]);

    expect($response->json('data.0.features.*.key'))
        ->toBe($response->json('data.1.features.*.key'));
});

it('capabilityFor returns teaser for the free tier on the estate key (CheckSubscription middleware wiring deferred to PR7)', function () {
    // This asserts ONLY the store helper the middleware will call; the actual
    // CheckSubscription route-level enforcement is deferred to PR7 (estate
    // route added to CAPABILITY_ROUTE_MAP there). No middleware is exercised here.
    $free = User::factory()->create(['tier' => 'free']);
    expect(app(TierConfigurationStore::class)->capabilityFor(
        app(TierResolver::class)->resolve($free), 'estate'
    ))->toBe('teaser');
});
