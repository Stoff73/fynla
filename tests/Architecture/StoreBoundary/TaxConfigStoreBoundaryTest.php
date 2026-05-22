<?php

declare(strict_types=1);

/**
 * SP1 Pass 2, R1: only TaxConfigStore (and explicitly allowlisted call sites)
 * may mutate the TaxConfiguration model. Hard CI failure per spec §14.1.
 *
 * Allowlist after R1.4 (final lock-down already effectively achieved here —
 * R1.6 confirms and updates this docblock):
 *   - App\Services\Stores\TaxConfigStore             (the store itself — permanent)
 *   - App\Models\TaxConfigurationAudit               (belongsTo relation — permanent; spec §14.2 model-on-model is OK)
 *   - Database\Factories\TaxConfigurationFactory     (test fixtures — permanent; spec §14.2)
 *
 * Migration history:
 * - PR R1.2: TaxSettingsController removed (delegates to store).
 * - PR R1.3: TaxConfigurationSeeder removed (writes via $store with IngestSource::SEEDER + setActive).
 * - PR R1.4: TaxConfigService removed (internal reads via $store->activeConfig(); getModel() dead-code removed).
 */
arch('only TaxConfigStore mutates TaxConfiguration')
    ->expect('App\Models\TaxConfiguration')
    ->toOnlyBeUsedIn([
        'App\Services\Stores\TaxConfigStore',
        'App\Models\TaxConfigurationAudit',
        'Database\Factories\TaxConfigurationFactory',
    ]);
