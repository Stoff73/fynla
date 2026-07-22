<?php

declare(strict_types=1);

use App\Models\RecommendationTracking;
use App\Models\User;
use App\Models\UserGamification;

beforeEach(fn () => $this->user = User::factory()->create(['is_preview_user' => false]));

it('awards points when a recommendation is marked completed', function () {
    $rec = RecommendationTracking::create([
        'user_id' => $this->user->id,
        'recommendation_id' => 'rec-abc',
        'module' => 'savings',
        'recommendation_text' => 'Top up your ISA',
        'priority_score' => 80,
        'timeline' => 'short_term',
        'status' => 'pending',
    ]);

    $rec->markAsCompleted();

    expect(UserGamification::where('user_id', $this->user->id)->value('total_points'))->toBe(25);
});

it('awards once even if completed twice', function () {
    $rec = RecommendationTracking::create([
        'user_id' => $this->user->id,
        'recommendation_id' => 'rec-xyz',
        'module' => 'savings',
        'recommendation_text' => 'x',
        'priority_score' => 50,
        'timeline' => 'short_term',
        'status' => 'completed',
        'completed_at' => now(),
    ]);
    $rec->markAsCompleted(); // again

    expect(UserGamification::where('user_id', $this->user->id)->value('total_points'))->toBe(25);
});
