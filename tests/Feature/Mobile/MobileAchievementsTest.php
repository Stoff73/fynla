<?php

declare(strict_types=1);

use App\Models\PointAward;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

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
