<?php

declare(strict_types=1);

/**
 * SP1 Pass 2, R2: only CurrencyRateStore (and explicitly allowlisted call sites)
 * may mutate the CurrencyRate model. Hard CI failure per spec §14.1.
 *
 * Allowlist for this PR (will shrink as subsequent PRs migrate each site):
 *   - App\Services\Stores\CurrencyRateStore (the store itself)
 *   - App\Http\Controllers\Api\Admin\CurrencyRateController (R2.3;
 *     reads via $store->findEloquent(), never touches model directly to mutate)
 *   - Database\Factories\CurrencyRateFactory (test fixtures only — spec §14.2 permanent allowlist)
 *   - Database\Seeders\CurrencyRatesSeeder (migrates to the store in PR R2.4)
 *
 * R2.5 will lock down to [Store, Factory] only.
 */
arch('only CurrencyRateStore mutates CurrencyRate')
    ->expect('App\Models\CurrencyRate')
    ->toOnlyBeUsedIn([
        'App\Services\Stores\CurrencyRateStore',
        'App\Http\Controllers\Api\Admin\CurrencyRateController',
        'Database\Factories\CurrencyRateFactory',
        'Database\Seeders\CurrencyRatesSeeder',
    ]);
