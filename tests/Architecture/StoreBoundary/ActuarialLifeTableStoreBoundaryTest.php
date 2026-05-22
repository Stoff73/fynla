<?php

declare(strict_types=1);

/**
 * SP1 Pass 2, R3: only ActuarialLifeTableStore (and explicitly allowlisted
 * call sites) may mutate the ActuarialLifeTable model. Hard CI failure per
 * spec §14.1.
 *
 * Allowlist for this PR (will shrink further as subsequent PRs migrate each site):
 *   - App\Services\Stores\ActuarialLifeTableStore (the store itself)
 *   - App\Http\Controllers\Api\Admin\ActuarialLifeTableController (R3.2;
 *     reads via $store->findEloquent(), never touches model directly to mutate)
 *   - Database\Factories\ActuarialLifeTableFactory (test fixtures only — spec §14.2 permanent allowlist)
 *   - Database\Seeders\ActuarialLifeTablesSeeder (migrated in PR R3.4)
 *
 * Estate read consumers (TrustService, FutureValueCalculator,
 * ComprehensiveEstatePlanService) all migrated to forCohort() in PR R3.3
 * and are no longer permitted to import the model.
 *
 * R3.5 will lock the final state down to [Store, Factory] only.
 */
arch('only ActuarialLifeTableStore mutates ActuarialLifeTable')
    ->expect('App\Models\ActuarialLifeTable')
    ->toOnlyBeUsedIn([
        'App\Services\Stores\ActuarialLifeTableStore',
        'App\Http\Controllers\Api\Admin\ActuarialLifeTableController',
        'Database\Factories\ActuarialLifeTableFactory',
        'Database\Seeders\ActuarialLifeTablesSeeder',
    ]);
