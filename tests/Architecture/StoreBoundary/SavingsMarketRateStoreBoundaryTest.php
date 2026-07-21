<?php

declare(strict_types=1);

/**
 * SP1 Pass 2, R4: only SavingsMarketRateStore (and explicitly allowlisted
 * call sites) may mutate the SavingsMarketRate model. Hard CI failure per
 * spec §14.1.
 *
 * Allowlist for this PR (will shrink as subsequent PRs migrate each site):
 *   - App\Services\Stores\SavingsMarketRateStore (the store itself)
 *   - Database\Factories\SavingsMarketRateFactory (test fixtures only — spec §14.2 permanent allowlist)
 *
 * RateComparator removed from allowlist in PR R4.3 (now reads via store).
 * SavingsMarketRatesSeeder removed in PR R4.4 (now writes via store).
 * SavingsMarketRateController removed in PR R4.5 (now reads via $store->findEloquent()).
 *
 * R4 boundary is now LOCKED. Adding a new direct-model consumer requires adding it
 * to this allowlist with justification, or routing through the store (preferred).
 */
arch('only SavingsMarketRateStore mutates SavingsMarketRate')
    ->expect('App\Models\SavingsMarketRate')
    ->toOnlyBeUsedIn([
        'App\Services\Stores\SavingsMarketRateStore',
        'Database\Factories\SavingsMarketRateFactory',
    ]);
