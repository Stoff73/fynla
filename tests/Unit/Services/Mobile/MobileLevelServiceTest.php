<?php

declare(strict_types=1);

use App\Models\User;
use App\Models\UserGamification;
use App\Services\Mobile\MobileLevelService;

it('reads the level + progress from the gamification engine', function () {
    $user = User::factory()->create(['is_preview_user' => false]);
    UserGamification::create(['user_id' => $user->id, 'total_points' => 360, 'level' => 5]);

    $result = app(MobileLevelService::class)->levelFor($user->id, []);

    expect($result['level'])->toBe(5);
    expect($result['level_name'])->toBe('Planner');
    expect($result['progress_percent'])->toBeInt();
    expect($result)->toHaveKeys([
        'level', 'level_name', 'next_level_name', 'progress_percent',
        'actions_completed', 'actions_total', 'actions_to_next',
        'actions_in_level', 'actions_for_next',
    ]);
});

it('shows actions-to-next as quarters of the level, capped at 4 (CSJ)', function (int $points, int $expectedToNext, int $expectedInLevel) {
    // Level 3 (Builder) spans 120–220 points, so each 25 points is a quarter.
    $user = User::factory()->create(['is_preview_user' => false]);
    UserGamification::create(['user_id' => $user->id, 'total_points' => $points, 'level' => 3]);

    $result = app(MobileLevelService::class)->levelFor($user->id, []);

    expect($result['actions_to_next'])->toBe($expectedToNext)
        ->and($result['actions_completed'])->toBe($expectedInLevel)
        ->and($result['actions_total'])->toBe(4)
        ->and($result['actions_completed'] + $result['actions_to_next'])->toBe(4);
})->with([
    'start of level (0%)' => [120, 4, 0],
    'a quarter in (25%)' => [145, 3, 1],
    'halfway (50%)' => [170, 2, 2],
    'three quarters (75%)' => [195, 1, 3],
]);

it('shows nothing left to earn at the top level (no next level)', function () {
    $user = User::factory()->create(['is_preview_user' => false]);
    UserGamification::create(['user_id' => $user->id, 'total_points' => 2000, 'level' => 10]);

    $result = app(MobileLevelService::class)->levelFor($user->id, []);

    expect($result['next_level_name'])->toBeNull()
        ->and($result['actions_to_next'])->toBe(0)
        ->and($result['actions_completed'])->toBe(4)
        ->and($result['actions_total'])->toBe(4);
});
