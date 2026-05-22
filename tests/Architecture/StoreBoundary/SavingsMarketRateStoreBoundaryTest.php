<?php

declare(strict_types=1);

/**
 * SP1 Pass 2, R4: only SavingsMarketRateStore (and explicitly allowlisted
 * call sites) may mutate the SavingsMarketRate model. Hard CI failure per
 * spec §14.1.
 *
 * Allowlist for this PR (will shrink as subsequent PRs migrate each site):
 *   - App\Services\Stores\SavingsMarketRateStore (the store itself)
 *   - App\Services\Savings\RateComparator (read consumer — never mutates;
 *     here until PR R4.3 migrates it to store reads)
 *   - Database\Seeders\SavingsMarketRatesSeeder (migrated in PR R4.4)
 *   - App\Http\Controllers\Api\Admin\SavingsMarketRateController (added in PR R4.2)
 */
arch('only SavingsMarketRateStore mutates SavingsMarketRate')
    ->expect('App\Models\SavingsMarketRate')
    ->toOnlyBeUsedIn([
        'App\Services\Stores\SavingsMarketRateStore',
        'App\Services\Savings\RateComparator',
        'Database\Seeders\SavingsMarketRatesSeeder',
        'App\Http\Controllers\Api\Admin\SavingsMarketRateController',
    ]);
