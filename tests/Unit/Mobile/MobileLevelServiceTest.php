<?php

declare(strict_types=1);

use App\Services\Mobile\MobileLevelService;

/**
 * Mobile dashboard gamification — level threshold logic.
 *
 * The goal defines progression as: Level 1→2 needs 3 actions, 2→3 needs 5,
 * 3→4 needs 7, +2 per level, capped at 10 actions for any level. These are
 * pure, data-free assertions on MobileLevelService::actionsForLevel(), placed
 * in tests/Unit so they run WITHOUT a database (the DB-backed level/percentile
 * paths are exercised end-to-end in the browser per
 * docs/mobile/mockup-to-app-workflow.md).
 */
it('uses the goal-defined action thresholds per level', function () {
    $svc = new MobileLevelService();

    expect($svc->actionsForLevel(1))->toBe(3);   // Level 1 → 2
    expect($svc->actionsForLevel(2))->toBe(5);   // Level 2 → 3
    expect($svc->actionsForLevel(3))->toBe(7);   // Level 3 → 4
    expect($svc->actionsForLevel(4))->toBe(9);   // Level 4 → 5
});

it('caps the per-level requirement at 10 actions', function () {
    $svc = new MobileLevelService();

    expect($svc->actionsForLevel(5))->toBe(10);
    expect($svc->actionsForLevel(6))->toBe(10);
    expect($svc->actionsForLevel(20))->toBe(10);
});

it('never returns fewer than 3 actions for the first level', function () {
    $svc = new MobileLevelService();

    expect($svc->actionsForLevel(1))->toBeGreaterThanOrEqual(3);
    expect($svc->actionsForLevel(0))->toBeGreaterThanOrEqual(3);
});
