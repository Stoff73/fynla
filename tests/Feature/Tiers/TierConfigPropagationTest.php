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

it('CheckSubscription denies a free user a tier2-only capability route', function () {
    // Pick a route guarded by a tier capability (estate full-module route,
    // wired in PR 7). Until PR 7, assert the helper the middleware will use:
    $free = User::factory()->create(['tier' => 'free']);
    expect(app(TierConfigurationStore::class)->capabilityFor(
        app(TierResolver::class)->resolve($free), 'estate'
    ))->toBe('teaser');
});
