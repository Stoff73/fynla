<?php

declare(strict_types=1);

use App\Models\PointAward;
use App\Models\Goal;
use App\Models\User;
use App\Models\UserMilestone;
use Database\Seeders\TaxConfigurationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(TaxConfigurationSeeder::class);
});

it('returns achievements, completed actions, and milestones', function () {
    // WP-4 — the `next` list is gone from this payload: actions live on the
    // dashboard; this page is what the user has DONE and earned.
    $user = User::factory()->create(['is_preview_user' => false]);
    Sanctum::actingAs($user);

    $res = $this->getJson('/api/v1/mobile/achievements');

    $res->assertOk()
        ->assertJsonStructure(['success', 'data' => ['achievements', 'completed', 'milestones']])
        ->assertJsonMissingPath('data.next');
});

it('badges are fixed goals with grant dates, not live counters', function () {
    $user = User::factory()->create(['is_preview_user' => false]);
    Sanctum::actingAs($user);

    // A broken streak (current run 1 day) must NOT un-earn the badge once
    // the 3-day award has been banked; the actioned badge is a fixed title.
    PointAward::create([
        'user_id' => $user->id,
        'source_type' => 'streak',
        'dedup_key' => 'streak:3:2026-06-01',
        'points' => 15,
        'meta' => [],
    ]);

    $res = $this->getJson('/api/v1/mobile/achievements')->assertOk();
    $badges = collect($res->json('data.achievements'));

    $streak = $badges->firstWhere('key', 'streak');
    expect($streak['title'])->toBe('3-day check-in streak')
        ->and($streak['earned'])->toBeTrue()
        ->and($streak['earned_at'])->not->toBeNull();

    $recs = $badges->firstWhere('key', 'recs_actioned');
    expect($recs['title'])->toBe('First action completed')
        ->and($recs['earned'])->toBeFalse();
});

it('earns a data badge using the real award category key', function () {
    $user = User::factory()->create(['is_preview_user' => false]);
    Sanctum::actingAs($user);

    PointAward::create([
        'user_id' => $user->id,
        'source_type' => 'data',
        'dedup_key' => 'data:savings_account:first',
        'points' => 20,
        'meta' => [],
    ]);

    $res = $this->getJson('/api/v1/mobile/achievements');

    $res->assertOk();

    $badge = collect($res->json('data.achievements'))->firstWhere('key', 'data_savings_account');

    expect($badge)->not->toBeNull()
        ->and($badge['earned'])->toBeTrue();
});

it('presents only the authenticated user ledger with provenance and legacy fields', function () {
    $user = User::factory()->create(['is_preview_user' => false]);
    $otherUser = User::factory()->create(['is_preview_user' => false]);
    $earnedAt = Carbon::parse('2026-08-01 10:00:00');
    $reachedAt = Carbon::parse('2026-08-02 11:00:00');

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
    UserMilestone::create([
        'user_id' => $user->id,
        'milestone_type' => 'net_worth',
        'reference_id' => null,
        'threshold' => 10000,
        'achieved_at' => $reachedAt,
    ]);
    UserMilestone::create([
        'user_id' => $otherUser->id,
        'milestone_type' => 'net_worth',
        'reference_id' => null,
        'threshold' => 25000,
        'achieved_at' => $reachedAt,
    ]);

    Sanctum::actingAs($user);

    $data = $this->getJson('/api/v1/mobile/achievements')->assertOk()->json('data');
    $badge = collect($data['achievements'])->firstWhere('key', 'data_savings_account');
    $milestone = collect($data['milestones'])->firstWhere('key', 'net_worth:0:10000');

    expect($badge)->toMatchArray([
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
        ->and(collect($data['achievements'])->firstWhere('key', 'data_pension')['earned'])->toBeFalse()
        ->and($milestone)->toMatchArray([
            'achieved' => true,
            'state' => 'earned',
            'achieved_at' => $reachedAt->toIso8601String(),
            'provenance' => [
                'kind' => 'user_milestone',
                'event' => 'net_worth:0:10000',
                'occurred_at' => $reachedAt->toIso8601String(),
            ],
            'progress' => null,
            'next_action' => null,
        ])
        ->and(collect($data['milestones'])->pluck('key')->all())->not->toContain('net_worth:0:25000');
});

it('keeps legacy milestones complete while canonical milestones are paginated and retrievable', function () {
    $user = User::factory()->create(['is_preview_user' => false]);
    Sanctum::actingAs($user);

    foreach (range(1, 105) as $threshold) {
        UserMilestone::create([
            'user_id' => $user->id,
            'milestone_type' => 'anniversary',
            'reference_id' => null,
            'threshold' => $threshold,
            'achieved_at' => now()->subDays($threshold),
        ]);
    }

    $legacy = $this->getJson('/api/v1/mobile/achievements')->assertOk()->json('data');
    $canonical = $this->getJson('/api/v1/mobile/achievements/v2')->assertOk()->json('data');
    $secondPage = $this->getJson('/api/v1/mobile/achievements/v2/milestones?cursor='.urlencode($canonical['next_cursor']))->assertOk()->json('data');
    $thirdPage = $this->getJson('/api/v1/mobile/achievements/v2/milestones?cursor='.urlencode($secondPage['next_cursor']))->assertOk()->json('data');

    expect($legacy['milestones'])->toHaveCount(105)
        ->and($canonical)->toMatchArray([
            'milestones_total' => 105,
            'per_page' => 50,
        ])
        ->and($canonical['milestones'])->toHaveCount(50)
        ->and($canonical['next_cursor'])->not->toBeNull()
        ->and($secondPage)->toMatchArray([
            'milestones_total' => 105,
            'per_page' => 50,
        ])
        ->and($secondPage['milestones'])->toHaveCount(50)
        ->and($secondPage['next_cursor'])->not->toBeNull()
        ->and($thirdPage)->toMatchArray([
            'milestones_total' => 105,
            'per_page' => 50,
        ])
        ->and($thirdPage['milestones'])->toHaveCount(5)
        ->and($thirdPage['next_cursor'])->toBeNull();
});

it('uses an achieved-at and id cursor so inserted milestones do not shift canonical continuation', function () {
    $user = User::factory()->create(['is_preview_user' => false]);
    Sanctum::actingAs($user);
    foreach (range(1, 55) as $threshold) {
        UserMilestone::create([
            'user_id' => $user->id,
            'milestone_type' => 'anniversary',
            'reference_id' => null,
            'threshold' => $threshold,
            'achieved_at' => now()->subDays($threshold),
        ]);
    }

    $first = $this->getJson('/api/v1/mobile/achievements/v2')->assertOk()->json('data');
    UserMilestone::create([
        'user_id' => $user->id,
        'milestone_type' => 'anniversary',
        'reference_id' => null,
        'threshold' => 99,
        'achieved_at' => now(),
    ]);
    $second = $this->getJson('/api/v1/mobile/achievements/v2/milestones?cursor='.urlencode($first['next_cursor']))->assertOk()->json('data');

    expect($first['next_cursor'])->not->toBeNull()
        ->and($second['milestones'])->toHaveCount(5)
        ->and(array_intersect(array_column($first['milestones'], 'key'), array_column($second['milestones'], 'key')))->toBe([]);
});

it('uses a generic title when a goal milestone references another users goal', function () {
    $user = User::factory()->create(['is_preview_user' => false]);
    $otherUser = User::factory()->create(['is_preview_user' => false]);
    $privateGoal = Goal::factory()->create(['user_id' => $otherUser->id, 'goal_name' => 'Private retirement plan']);
    UserMilestone::create([
        'user_id' => $user->id,
        'milestone_type' => 'goal',
        'reference_id' => $privateGoal->id,
        'threshold' => 25,
        'achieved_at' => now(),
    ]);
    Sanctum::actingAs($user);

    $milestone = collect($this->getJson('/api/v1/mobile/achievements')->assertOk()->json('data.milestones'))->firstWhere('key', 'goal:'.$privateGoal->id.':25');

    expect($milestone['title'])->toContain('your goal')
        ->and($milestone['title'])->not->toContain('Private retirement plan');
});

it('batch-loads canonical goal milestone titles instead of querying each milestone', function () {
    $user = User::factory()->create(['is_preview_user' => false]);
    foreach (range(1, 50) as $index) {
        $goal = Goal::factory()->create(['user_id' => $user->id, 'goal_name' => "Goal {$index}"]);
        UserMilestone::create([
            'user_id' => $user->id,
            'milestone_type' => 'goal',
            'reference_id' => $goal->id,
            'threshold' => 25,
            'achieved_at' => now()->subSeconds($index),
        ]);
    }
    Sanctum::actingAs($user);
    $goalQueries = [];
    DB::listen(function ($query) use (&$goalQueries): void {
        if (str_contains($query->sql, 'from `goals`')) {
            $goalQueries[] = $query->sql;
        }
    });

    $data = $this->getJson('/api/v1/mobile/achievements/v2')->assertOk()->json('data');

    expect($data['milestones'])->toHaveCount(50)
        ->and($goalQueries)->toHaveCount(2)
        ->and($data['milestones'][0]['title'])->not->toContain('your goal');
});
