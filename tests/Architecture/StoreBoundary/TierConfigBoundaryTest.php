<?php

declare(strict_types=1);

/**
 * SP2 — tier_configurations boundary. Only TierConfigurationStore (+ the
 * spec §14.2 permanents: seeder, factory, migrations, console commands)
 * mutates the table. The "no hardcoded tier numbers outside the store"
 * clause is added and made HARD in PR 9 — until then this asserts the
 * mutation boundary only.
 */
arch('TierConfiguration is only mutated inside the canonical set')
    ->expect('App\Models\TierConfiguration')
    ->toOnlyBeUsedIn([
        'App\Services\Stores\TierConfigurationStore',
        'App\Services\Tiers\TierResolver',          // read-only (added PR 2)
        'App\Services\Tiers\DbTierGate',            // read-only (added PR 3)
        'App\Services\Tiers\TeaserGate',            // read-only (added PR 7)
        'App\Http\Resources\TierConfigurationResource', // read-only (added PR 4)
        'Database\Seeders\TierConfigurationSeeder',
        'Database\Factories\TierConfigurationFactory',
        'App\Models\\',
    ]);
