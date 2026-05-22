<?php

declare(strict_types=1);

/**
 * SP1 Pass 2, R1: only TaxConfigStore (and explicitly allowlisted call sites)
 * may mutate the TaxConfiguration model. Hard CI failure per spec §14.1.
 *
 * Allowlist for this PR (initial — will shrink as subsequent PRs migrate each site):
 *   - App\Services\Stores\TaxConfigStore             (the store itself)
 *   - App\Http\Controllers\Api\TaxSettingsController (removed in PR R1.2)
 *   - App\Services\TaxConfigService                  (read-only via getActive; routed through store in PR R1.4)
 *   - App\Models\TaxConfigurationAudit               (belongsTo relation — permanent; spec §14.2 model-on-model is OK)
 *   - Database\Seeders\TaxConfigurationSeeder        (removed in PR R1.3)
 *   - Database\Factories\TaxConfigurationFactory     (test fixtures — permanent; spec §14.2)
 *
 * Final R1 lock-down (PR R1.6) reduces this allowlist to [TaxConfigStore, TaxConfigurationAudit, TaxConfigurationFactory].
 */
arch('only TaxConfigStore mutates TaxConfiguration')
    ->expect('App\Models\TaxConfiguration')
    ->toOnlyBeUsedIn([
        'App\Services\Stores\TaxConfigStore',
        'App\Http\Controllers\Api\TaxSettingsController',
        'App\Services\TaxConfigService',
        'App\Models\TaxConfigurationAudit',
        'Database\Seeders\TaxConfigurationSeeder',
        'Database\Factories\TaxConfigurationFactory',
    ]);
