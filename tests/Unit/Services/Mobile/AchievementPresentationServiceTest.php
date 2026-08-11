<?php

declare(strict_types=1);

use App\Models\PointAward;
use App\Models\User;
use App\Models\UserGamification;
use App\Models\UserLevelCrossing;
use App\Services\Mobile\AchievementPresentationService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

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
    DB::listen(function ($query) use (&$queries): void {
        if (str_contains($query->sql, 'user_level_crossings') || str_contains($query->sql, 'point_awards')) {
            $queries[] = $query->sql;
        }
    });

    $level = collect(app(AchievementPresentationService::class)->badges($user))->firstWhere('key', 'level');

    expect($level['provenance']['event'])->toBe('milestone:crossing')
        ->and(collect($queries)->first(fn (string $sql): bool => str_contains($sql, 'user_level_crossings')))->toContain('limit 1')
        ->and(collect($queries)->join(' '))->not->toContain('OVER (ORDER BY');
});

it('does not expose a foreign users point-award as level provenance', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $foreignAward = PointAward::create([
        'user_id' => $otherUser->id,
        'source_type' => 'milestone',
        'dedup_key' => 'foreign:award',
        'points' => 50,
        'meta' => [],
    ]);
    UserGamification::create(['user_id' => $user->id, 'total_points' => 50, 'level' => 2]);
    expect(fn () => UserLevelCrossing::create([
        'user_id' => $user->id,
        'level' => 2,
        'point_award_id' => $foreignAward->id,
        'reached_at' => $foreignAward->created_at,
    ]))->toThrow(QueryException::class);

    // Simulate a damaged legacy/import row to prove presentation also
    // enforces ownership if database integrity has been bypassed.
    DB::statement('SET FOREIGN_KEY_CHECKS=0');
    try {
        UserLevelCrossing::create([
            'user_id' => $user->id,
            'level' => 2,
            'point_award_id' => $foreignAward->id,
            'reached_at' => $foreignAward->created_at,
        ]);
    } finally {
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }

    $level = collect(app(AchievementPresentationService::class)->badges($user))->firstWhere('key', 'level');

    expect($level['earned'])->toBeTrue()
        ->and($level['provenance'])->toBeNull();
});

it('backfills every historical crossing per user and remains idempotent', function () {
    $first = User::factory()->create();
    $second = User::factory()->create();
    UserGamification::create(['user_id' => $first->id, 'total_points' => 130, 'level' => 3]);
    UserGamification::create(['user_id' => $second->id, 'total_points' => 220, 'level' => 4]);
    PointAward::create(['user_id' => $first->id, 'source_type' => 'data', 'dedup_key' => 'first:a', 'points' => 40, 'meta' => []]);
    $firstB = PointAward::create(['user_id' => $first->id, 'source_type' => 'data', 'dedup_key' => 'first:b', 'points' => 90, 'meta' => []]);
    $secondA = PointAward::create(['user_id' => $second->id, 'source_type' => 'data', 'dedup_key' => 'second:a', 'points' => 220, 'meta' => []]);
    $firstCrossedAt = Carbon::parse('2026-07-01 10:00:00');
    $secondCrossedAt = Carbon::parse('2026-07-02 11:00:00');
    $firstB->forceFill(['created_at' => $firstCrossedAt, 'updated_at' => $firstCrossedAt])->saveQuietly();
    $secondA->forceFill(['created_at' => $secondCrossedAt, 'updated_at' => $secondCrossedAt])->saveQuietly();

    $migration = require base_path('database/migrations/2026_08_11_090000_create_user_level_crossings_table.php');
    $migration->up();
    $migration->up();

    expect(UserLevelCrossing::orderBy('user_id')->orderBy('level')->get(['user_id', 'level', 'point_award_id'])->map(fn ($row) => $row->only(['user_id', 'level', 'point_award_id']))->all())
        ->toBe([
            ['user_id' => $first->id, 'level' => 2, 'point_award_id' => $firstB->id],
            ['user_id' => $first->id, 'level' => 3, 'point_award_id' => $firstB->id],
            ['user_id' => $second->id, 'level' => 2, 'point_award_id' => $secondA->id],
            ['user_id' => $second->id, 'level' => 3, 'point_award_id' => $secondA->id],
            ['user_id' => $second->id, 'level' => 4, 'point_award_id' => $secondA->id],
        ])
        ->and(UserLevelCrossing::where('user_id', $first->id)->pluck('reached_at')->every(fn ($at) => $at->equalTo($firstCrossedAt)))->toBeTrue()
        ->and(UserLevelCrossing::where('user_id', $second->id)->pluck('reached_at')->every(fn ($at) => $at->equalTo($secondCrossedAt)))->toBeTrue();
});
