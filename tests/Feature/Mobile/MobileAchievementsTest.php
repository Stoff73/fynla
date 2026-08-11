<?php

declare(strict_types=1);

use App\Models\PointAward;
use App\Models\User;
use App\Models\UserMilestone;
use Database\Seeders\TaxConfigurationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
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
