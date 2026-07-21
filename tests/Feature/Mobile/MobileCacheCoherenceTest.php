<?php

declare(strict_types=1);

use App\Agents\CoordinatingAgent;
use App\Services\Cache\CacheInvalidationService;
use Illuminate\Support\Facades\Cache;

/*
 * SP3 /m Phase 3 — cache coherence. The mobile dashboard, module-summary and
 * gamification-level caches must be invalidated whenever a user's data changes,
 * via BOTH write paths:
 *   - web controllers → CacheInvalidationService::invalidateForUser
 *   - Fyn capture     → CoordinatingAgent::invalidateUserCache (the only /m writer)
 */

$mobileModules = ['protection', 'savings', 'investment', 'retirement', 'estate', 'goals', 'tax'];

it('CacheInvalidationService clears mobile dashboard, all module summaries and the level cache', function () use ($mobileModules) {
    $userId = 919191;
    Cache::put("mobile_dashboard_{$userId}", ['x' => 1], 600);
    Cache::put("mobile_level_actions_{$userId}", 7, 600);
    foreach ($mobileModules as $m) {
        Cache::put("mobile_module_{$m}_{$userId}", ['x' => 1], 600);
    }

    app(CacheInvalidationService::class)->invalidateForUser($userId);

    expect(Cache::has("mobile_dashboard_{$userId}"))->toBeFalse()
        ->and(Cache::has("mobile_level_actions_{$userId}"))->toBeFalse();
    foreach ($mobileModules as $m) {
        expect(Cache::has("mobile_module_{$m}_{$userId}"))->toBeFalse();
    }
});

it('invalidateForUserAndSpouse clears both users mobile dashboards', function () {
    $userId = 919192;
    $spouseId = 919193;
    Cache::put("mobile_dashboard_{$userId}", ['x' => 1], 600);
    Cache::put("mobile_dashboard_{$spouseId}", ['x' => 1], 600);

    app(CacheInvalidationService::class)->invalidateForUserAndSpouse($userId, $spouseId);

    expect(Cache::has("mobile_dashboard_{$userId}"))->toBeFalse()
        ->and(Cache::has("mobile_dashboard_{$spouseId}"))->toBeFalse();
});

it('CoordinatingAgent::invalidateUserCache clears the mobile caches (Fyn write path)', function () {
    $userId = 919194;
    Cache::put("mobile_dashboard_{$userId}", ['x' => 1], 600);
    Cache::put("mobile_module_savings_{$userId}", ['x' => 1], 600);
    Cache::put("mobile_level_actions_{$userId}", 3, 600);

    app(CoordinatingAgent::class)->invalidateUserCache($userId);

    expect(Cache::has("mobile_dashboard_{$userId}"))->toBeFalse()
        ->and(Cache::has("mobile_module_savings_{$userId}"))->toBeFalse()
        ->and(Cache::has("mobile_level_actions_{$userId}"))->toBeFalse();
});
