<?php

declare(strict_types=1);

/**
 * SP1 Pass 2, R3: only ActuarialLifeTableStore (and explicitly allowlisted
 * call sites) may mutate the ActuarialLifeTable model. Hard CI failure per
 * spec §14.1.
 *
 * Allowlist for this PR (will shrink as subsequent PRs migrate each site):
 *   - App\Services\Stores\ActuarialLifeTableStore (the store itself)
 *   - Database\Factories\ActuarialLifeTableFactory (test fixtures only — spec §14.2 permanent allowlist)
 *
 * TrustService removed in PR R3.3 (now reads via $store->forCohort()).
 * FutureValueCalculator removed in PR R3.3 (now reads via $store->forCohort()).
 * ComprehensiveEstatePlanService removed in PR R3.3 (now reads via $store->forCohort()).
 * ActuarialLifeTablesSeeder removed in PR R3.4 (now writes via $store->create/update with IngestSource::SEEDER).
 * ActuarialLifeTableController removed in PR R3.5 (reads return models via $store->findEloquent() — the controller never imports the model directly).
 *
 * R3 boundary is now LOCKED. Adding a new direct-model consumer requires adding it
 * to this allowlist with justification, or routing through the store (preferred).
 */
arch('only ActuarialLifeTableStore mutates ActuarialLifeTable')
    ->expect('App\Models\ActuarialLifeTable')
    ->toOnlyBeUsedIn([
        'App\Services\Stores\ActuarialLifeTableStore',
        'Database\Factories\ActuarialLifeTableFactory',
    ]);
