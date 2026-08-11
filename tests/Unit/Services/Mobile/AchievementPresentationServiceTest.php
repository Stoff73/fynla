<?php

declare(strict_types=1);

use App\Models\PointAward;
use App\Models\User;
use App\Services\Mobile\AchievementPresentationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

uses(RefreshDatabase::class);

it('builds badges from the requesting users award ledger only', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $earnedAt = Carbon::parse('2026-08-01 10:00:00');

    $award = PointAward::create([
        'user_id' => $user->id,
        'source_type' => 'data',
        'dedup_key' => 'data:savings_account:first',
        'points' => 20,
        'meta' => [],
    ]);
    $award->forceFill(['created_at' => $earnedAt, 'updated_at' => $earnedAt])->saveQuietly();
    PointAward::create([
        'user_id' => $otherUser->id,
        'source_type' => 'data',
        'dedup_key' => 'data:pension:first',
        'points' => 20,
        'meta' => [],
    ]);

    $badges = collect(app(AchievementPresentationService::class)->badges($user));
    $savings = $badges->firstWhere('key', 'data_savings_account');
    $pension = $badges->firstWhere('key', 'data_pension');

    expect($savings)->toMatchArray([
        'earned' => true,
        'state' => 'earned',
        'earned_at' => $earnedAt->toIso8601String(),
        'provenance' => [
            'kind' => 'point_award',
            'event' => 'data:savings_account:first',
            'occurred_at' => $earnedAt->toIso8601String(),
        ],
        'progress' => null,
        'next_action' => null,
    ])
        ->and($pension['earned'])->toBeFalse()
        ->and($pension['provenance'])->toBeNull();
});
