<?php

declare(strict_types=1);

/**
 * SP1 Pass 2, R2: only CurrencyRateStore (and explicitly allowlisted call sites)
 * may mutate the CurrencyRate model. Hard CI failure per spec §14.1.
 *
 * Allowlist for this PR (final R2 lock-down):
 *   - App\Services\Stores\CurrencyRateStore (the store itself)
 *   - Database\Factories\CurrencyRateFactory (test fixtures only — spec §14.2 permanent allowlist)
 *
 * CurrencyRatesSeeder removed in PR R2.4 (now writes via $store->create/update with IngestSource::SEEDER).
 * CurrencyRateController removed in PR R2.5 (reads return models via $store->findEloquent() — the controller never imports the model directly).
 *
 * R2 boundary is now LOCKED. Adding a new direct-model consumer requires adding it
 * to this allowlist with justification, or routing through the store (preferred).
 */
arch('only CurrencyRateStore mutates CurrencyRate')
    ->expect('App\Models\CurrencyRate')
    ->toOnlyBeUsedIn([
        'App\Services\Stores\CurrencyRateStore',
        'Database\Factories\CurrencyRateFactory',
    ]);
