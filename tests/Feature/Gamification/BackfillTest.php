<?php

declare(strict_types=1);

use App\Models\PointAward;
use App\Models\RecommendationTracking;
use App\Models\SavingsAccount;
use App\Models\User;
use App\Models\UserGamification;

it('backfills data + completed recommendations quietly and idempotently', function () {
    $user = User::factory()->create(['is_preview_user' => false]);
    SavingsAccount::factory()->create(['user_id' => $user->id]);
    RecommendationTracking::create([
        'user_id' => $user->id, 'recommendation_id' => 'r1', 'module' => 'savings',
        'recommendation_text' => 'x', 'priority_score' => 50, 'timeline' => 'short_term',
        'status' => 'completed', 'completed_at' => now(),
    ]);

    // Simulate a pre-launch user: wipe what the live hooks already awarded.
    UserGamification::where('user_id', $user->id)->delete();
    PointAward::where('user_id', $user->id)->delete();

    $this->artisan('gamification:backfill')->assertExitCode(0);

    $g = UserGamification::where('user_id', $user->id)->first();
    // savings first-in-category (20) + recommendation (25) = 45
    expect($g->total_points)->toBe(45);
    // Quiet: no celebration queued.
    expect($g->pending_celebration_level)->toBeNull();

    // Re-run awards nothing more.
    $this->artisan('gamification:backfill')->assertExitCode(0);
    expect(UserGamification::where('user_id', $user->id)->value('total_points'))->toBe(45);
});

it('skips preview users entirely', function () {
    $preview = User::factory()->create(['is_preview_user' => true]);
    SavingsAccount::factory()->create(['user_id' => $preview->id]);
    UserGamification::where('user_id', $preview->id)->delete();

    $this->artisan('gamification:backfill')->assertExitCode(0);

    expect(UserGamification::where('user_id', $preview->id)->exists())->toBeFalse();
});
