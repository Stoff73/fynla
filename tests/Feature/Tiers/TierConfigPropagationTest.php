<?php

declare(strict_types=1);

use App\Models\User;
use App\Services\Stores\IngestSource;
use App\Services\Stores\TierConfigurationStore;
use App\Services\Tiers\TierResolver;
use Database\Seeders\TierConfigurationSeeder;

beforeEach(fn () => $this->seed(TierConfigurationSeeder::class));

it('a store price change shows on the public pricing endpoint', function () {
    app(TierConfigurationStore::class)->updateTier('tier2',
        ['price_monthly_pence' => 1234], User::factory()->create(['is_admin' => true]), IngestSource::ADMIN);

    $this->getJson('/api/pricing-config')
        ->assertJsonPath('data.2.tier', 'tier2')
        ->assertJsonPath('data.2.price_monthly_pence', 1234);
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
