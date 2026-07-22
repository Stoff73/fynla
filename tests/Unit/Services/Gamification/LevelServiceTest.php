<?php

declare(strict_types=1);

use App\Models\User;
use App\Services\Gamification\LevelService;

it('resolves the level from a points total', function () {
    $svc = app(LevelService::class);

    expect($svc->levelForPoints(0))->toBe(1);
    expect($svc->levelForPoints(49))->toBe(1);
    expect($svc->levelForPoints(50))->toBe(2);
    expect($svc->levelForPoints(119))->toBe(2);
    expect($svc->levelForPoints(360))->toBe(5);
    expect($svc->levelForPoints(2000))->toBe(10);
    expect($svc->levelForPoints(999999))->toBe(10); // caps at 10
});

it('names a level', function () {
    $svc = app(LevelService::class);
    expect($svc->levelName(5))->toBe('Planner');
    expect($svc->levelName(10))->toBe('Master');
});

it('computes progress within the current band', function () {
    $svc = app(LevelService::class);
    // 220 = start of L4, 360 = start of L5 -> band size 140. 290 = 70/140 = 50%.
    $p = $svc->progress(290);
    expect($p['level'])->toBe(4);
    expect($p['level_name'])->toBe('Organiser');
    expect($p['next_level_name'])->toBe('Planner');
    expect($p['progress_percent'])->toBe(50);
});

it('reports 100 percent progress and no next level at the cap', function () {
    $svc = app(LevelService::class);
    $p = $svc->progress(2000);
    expect($p['level'])->toBe(10);
    expect($p['next_level_name'])->toBeNull();
    expect($p['progress_percent'])->toBe(100);
});

it('returns action-oriented next steps with no points figure', function () {
    $user = User::factory()->create();
    $svc = app(LevelService::class);
    $actions = $svc->nextActions($user);
    expect($actions)->toBeArray();
    foreach ($actions as $a) {
        expect($a)->toBeString();
        expect(strtolower($a))->not->toContain('point'); // never mention points
    }
});
