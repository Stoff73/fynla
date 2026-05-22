<?php

declare(strict_types=1);

/**
 * SP1 Pass 2, R3: only ActuarialLifeTableStore (and explicitly allowlisted
 * call sites) may mutate the ActuarialLifeTable model. Hard CI failure per
 * spec §14.1.
 *
 * Allowlist for this PR (will shrink as subsequent PRs migrate each site):
 *   - App\Services\Stores\ActuarialLifeTableStore (the store itself)
 *   - App\Http\Controllers\Api\Admin\ActuarialLifeTableController (added in R3.2;
 *     reads via $store->findEloquent(), never touches model directly to mutate)
 *   - Database\Factories\ActuarialLifeTableFactory (test fixtures only — spec §14.2 permanent allowlist)
 *   - App\Services\Estate\TrustService (read consumer — never mutates;
 *     here until PR R3.3 migrates it to store reads)
 *   - App\Services\Estate\FutureValueCalculator (read consumer; migrated PR R3.3)
 *   - App\Services\Estate\ComprehensiveEstatePlanService (read consumer; migrated PR R3.3)
 *   - Database\Seeders\ActuarialLifeTablesSeeder (migrated in PR R3.4)
 *
 * R3.5 will lock the final state down to [Store, Factory] only.
 */
arch('only ActuarialLifeTableStore mutates ActuarialLifeTable')
    ->expect('App\Models\ActuarialLifeTable')
    ->toOnlyBeUsedIn([
        'App\Services\Stores\ActuarialLifeTableStore',
        'App\Http\Controllers\Api\Admin\ActuarialLifeTableController',
        'Database\Factories\ActuarialLifeTableFactory',
        'App\Services\Estate\TrustService',
        'App\Services\Estate\FutureValueCalculator',
        'App\Services\Estate\ComprehensiveEstatePlanService',
        'Database\Seeders\ActuarialLifeTablesSeeder',
    ]);
