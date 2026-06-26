<?php

declare(strict_types=1);

use App\Models\RecommendationTracking;
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
        'actions_completed', 'actions_total', 'actions_in_level', 'actions_for_next',
    ]);
});

it('derives the actions-complete running tally from tracking + the open list', function () {
    $user = User::factory()->create(['is_preview_user' => false]);
    UserGamification::create(['user_id' => $user->id, 'total_points' => 0, 'level' => 1]);

    // Two banked (completed) recommendations — counted toward the wheel even
    // though they're no longer shown in the list (replaced by the next-best).
    foreach (['rec_a', 'rec_b'] as $rid) {
        RecommendationTracking::create([
            'user_id' => $user->id,
            'recommendation_id' => $rid,
            'module' => 'savings',
            'recommendation_text' => 'Banked '.$rid,
            'status' => 'completed',
            'completed_at' => now(),
        ]);
    }

    // Four open actions still shown in the list.
    $openActions = [['done' => false], ['done' => false], ['done' => false], ['done' => false]];

    $result = app(MobileLevelService::class)->levelFor($user->id, $openActions);

    // X = banked completions (tracked); Y = banked + open shown.
    expect($result['actions_completed'])->toBe(2);
    expect($result['actions_total'])->toBe(6);
    expect($result['actions_in_level'])->toBe(2);
    expect($result['actions_for_next'])->toBe(6);
});
