<?php

declare(strict_types=1);

use App\Models\RecommendationTracking;
use App\Models\User;
use App\Models\UserGamification;
use App\Services\Mobile\MobileLevelService;

it('reads the level + progress from the gamification engine', function () {
    $user = User::factory()->create(['is_preview_user' => false]);
    UserGamification::create(['user_id' => $user->id, 'total_points' => 360, 'level' => 5]);

    $result = app(MobileLevelService::class)->levelFor($user->id);

    expect($result['level'])->toBe(5);
    expect($result['level_name'])->toBe('Planner');
    expect($result['progress_percent'])->toBeInt();
    expect($result)->toHaveKeys([
        'level', 'level_name', 'next_level_name', 'progress_percent',
        'actions_completed', 'actions_total', 'actions_in_level', 'actions_for_next',
    ]);
});

it('feeds the actions-complete heading from recommendation completion', function () {
    $user = User::factory()->create(['is_preview_user' => false]);
    UserGamification::create(['user_id' => $user->id, 'total_points' => 0, 'level' => 1]);

    foreach (['completed', 'completed', 'pending', 'in_progress', 'dismissed'] as $i => $status) {
        RecommendationTracking::create([
            'user_id' => $user->id,
            'recommendation_id' => "rec-{$i}",
            'module' => 'savings',
            'recommendation_text' => 'x',
            'priority_score' => 50,
            'timeline' => 'short_term',
            'status' => $status,
            'completed_at' => $status === 'completed' ? now() : null,
        ]);
    }

    $result = app(MobileLevelService::class)->levelFor($user->id);

    // 2 completed; total = pending + in_progress + completed = 4 (dismissed excluded)
    expect($result['actions_completed'])->toBe(2);
    expect($result['actions_total'])->toBe(4);
    expect($result['actions_in_level'])->toBe(2);  // legacy alias
    expect($result['actions_for_next'])->toBe(4);   // legacy alias
});

it('computes percentile from the points-based level distribution', function () {
    // Three users below, this user above.
    foreach ([1, 1, 2] as $i => $lvl) {
        $u = User::factory()->create(['is_preview_user' => false]);
        UserGamification::create(['user_id' => $u->id, 'total_points' => 0, 'level' => $lvl]);
    }
    $user = User::factory()->create(['is_preview_user' => false]);
    UserGamification::create(['user_id' => $user->id, 'total_points' => 2000, 'level' => 10]);

    $pct = app(MobileLevelService::class)->percentile($user->id);

    expect($pct)->toBeGreaterThanOrEqual(1);
    expect($pct)->toBeLessThanOrEqual(99);
    // All 3 others are strictly below level 10 -> 3/4 = 75%.
    expect($pct)->toBe(75);
});
