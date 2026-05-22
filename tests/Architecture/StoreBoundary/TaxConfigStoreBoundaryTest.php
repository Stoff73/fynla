<?php

declare(strict_types=1);

/**
 * SP1 Pass 2, R1: only TaxConfigStore (and explicitly allowlisted call sites)
 * may mutate the TaxConfiguration model. Hard CI failure per spec §14.1.
 *
 * Allowlist (final R1 lock-down):
 *   - App\Services\Stores\TaxConfigStore             (the store itself)
 *   - App\Models\TaxConfigurationAudit               (belongsTo relation — permanent; spec §14.2 model-on-model is OK)
 *   - Database\Factories\TaxConfigurationFactory     (test fixtures only — permanent; spec §14.2)
 *
 * Migration history (R1 track, all merged to dev):
 * - PR R1.1 (#364): introduce TaxConfigStore + arch boundary + normaliser + tests.
 * - PR R1.2 (#365): TaxSettingsController writes routed via store; removed from allowlist.
 * - PR R1.3 (#366): TaxConfigurationSeeder writes via $store->create/update with IngestSource::SEEDER plus setActive for the target year; removed from allowlist.
 * - PR R1.4 (#367): TaxConfigService internal reads via $store->activeConfig(); removed from allowlist (dead getModel() also dropped).
 *
 * R1 boundary is now LOCKED. Adding a new direct-model consumer requires
 * adding it to this allowlist with justification, or routing through the
 * store (preferred). PR R1.5 (B2 admin-edit gap fix) is browser-blocked and
 * stays parked until CSJ runs the R1.0 audit.
 */
arch('only TaxConfigStore mutates TaxConfiguration')
    ->expect('App\Models\TaxConfiguration')
    ->toOnlyBeUsedIn([
        'App\Services\Stores\TaxConfigStore',
        'App\Models\TaxConfigurationAudit',
        'Database\Factories\TaxConfigurationFactory',
    ]);
