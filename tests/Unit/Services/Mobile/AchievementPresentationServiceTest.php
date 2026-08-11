<?php

declare(strict_types=1);

use App\Models\PointAward;
use App\Models\User;
use App\Models\UserGamification;
use App\Models\UserLevelCrossing;
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

it('derives a level achievement timestamp from the immutable crossing award', function () {
    $user = User::factory()->create();
    $beforeCrossing = Carbon::parse('2026-08-01 09:00:00');
    $crossedAt = Carbon::parse('2026-08-02 09:00:00');
    $laterAt = Carbon::parse('2026-08-03 09:00:00');

    $first = PointAward::create([
        'user_id' => $user->id,
        'source_type' => 'data',
        'dedup_key' => 'data:before-level',
        'points' => 40,
        'meta' => [],
    ]);
    $first->forceFill(['created_at' => $beforeCrossing, 'updated_at' => $beforeCrossing])->saveQuietly();
    $crossing = PointAward::create([
        'user_id' => $user->id,
        'source_type' => 'milestone',
        'dedup_key' => 'milestone:level-crossing',
        'points' => 20,
        'meta' => [],
    ]);
    $crossing->forceFill(['created_at' => $crossedAt, 'updated_at' => $crossedAt])->saveQuietly();
    $later = PointAward::create([
        'user_id' => $user->id,
        'source_type' => 'login',
        'dedup_key' => 'login:2026-08-03',
        'points' => 5,
        'meta' => [],
    ]);
    $later->forceFill(['created_at' => $laterAt, 'updated_at' => $laterAt])->saveQuietly();
    UserLevelCrossing::create([
        'user_id' => $user->id,
        'level' => 2,
        'point_award_id' => $crossing->id,
        'reached_at' => $crossedAt,
    ]);
    UserGamification::create([
        'user_id' => $user->id,
        'total_points' => 65,
        'level' => 2,
    ])->forceFill(['updated_at' => $laterAt])->saveQuietly();

    $level = collect(app(AchievementPresentationService::class)->badges($user))->firstWhere('key', 'level');

    expect($level)->toMatchArray([
        'earned' => true,
        'earned_at' => $crossedAt->toIso8601String(),
        'provenance' => [
            'kind' => 'point_award',
            'event' => 'milestone:level-crossing',
            'occurred_at' => $crossedAt->toIso8601String(),
        ],
    ]);
});

it('uses a single indexed level-crossing lookup instead of scanning the point-award ledger', function () {
    $user = User::factory()->create();
    $crossing = PointAward::create([
        'user_id' => $user->id,
        'source_type' => 'milestone',
        'dedup_key' => 'milestone:crossing',
        'points' => 50,
    ]);
    UserGamification::create(['user_id' => $user->id, 'total_points' => 250, 'level' => 4]);
    UserLevelCrossing::create([
        'user_id' => $user->id,
        'level' => 4,
        'point_award_id' => $crossing->id,
        'reached_at' => $crossing->created_at,
    ]);
    foreach (range(1, 200) as $index) {
        PointAward::create([
            'user_id' => $user->id,
            'source_type' => 'login',
            'dedup_key' => "login:{$index}",
            'points' => 1,
            'meta' => [],
        ]);
    }
    $queries = [];
    \Illuminate\Support\Facades\DB::listen(function ($query) use (&$queries): void {
        if (str_contains($query->sql, 'user_level_crossings') || str_contains($query->sql, 'point_awards')) {
            $queries[] = $query->sql;
        }
    });

    $level = collect(app(AchievementPresentationService::class)->badges($user))->firstWhere('key', 'level');

    expect($level['provenance']['event'])->toBe('milestone:crossing')
        ->and(collect($queries)->first(fn (string $sql): bool => str_contains($sql, 'user_level_crossings')))->toContain('limit 1')
        ->and(collect($queries)->join(' '))->not->toContain('OVER (ORDER BY');
});
