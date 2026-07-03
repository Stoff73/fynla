<?php

declare(strict_types=1);

use App\Models\PointAward;
use App\Models\RecommendationTracking;
use App\Models\User;
use App\Services\Gamification\ActivityFeedService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

/**
 * WP-3 — the point_awards ledger translated into a user-facing activity
 * history. Events and dates only — point values are never exposed (Rule 12).
 */
function awardRow(User $user, string $dedupKey, string $sourceType = 'onboarding'): void
{
    PointAward::create([
        'user_id' => $user->id,
        'source_type' => $sourceType,
        'points' => 10,
        'dedup_key' => $dedupKey,
    ]);
}

it('translates the ledger into labelled events, newest first', function () {
    $user = User::factory()->create();

    awardRow($user, 'onboarding:campaign_dob');
    awardRow($user, 'data:savings_account:first', 'data');
    awardRow($user, 'milestone:net_worth:0:10000', 'milestone');
    awardRow($user, 'streak:3:2026-07-01', 'streak');

    $feed = app(ActivityFeedService::class)->feed($user);

    expect(array_column($feed, 'label'))->toBe([
        '3-day check-in streak',
        'Net worth passed £10,000',
        'Added your first savings account',
        'Shared your date of birth',
    ]);

    // Rule 12 — no points anywhere in the payload.
    foreach ($feed as $event) {
        expect($event)->not->toHaveKey('points');
    }
});

it('names completed actions with their real wording', function () {
    $user = User::factory()->create();

    // The observer on status='completed' creates the recommendation:* award —
    // this exercises the REAL completion chain end to end.
    RecommendationTracking::create([
        'user_id' => $user->id,
        'recommendation_id' => 'tax_pa_taper_rescue',
        'module' => 'tax',
        'recommendation_text' => 'Reclaim your Personal Allowance with a pension contribution',
        'status' => 'completed',
        'completed_at' => now(),
    ]);

    $feed = app(ActivityFeedService::class)->feed($user);

    expect($feed[0]['label'])->toBe('Completed: Reclaim your Personal Allowance with a pension contribution');
});

it('hides plumbing events: daily logins and verify steps', function () {
    $user = User::factory()->create();

    awardRow($user, 'login:2026-07-03', 'login');
    awardRow($user, 'onboarding:campaign_verify_announce');
    awardRow($user, 'onboarding:campaign_verify_navigate');
    awardRow($user, 'onboarding:base_work');

    $feed = app(ActivityFeedService::class)->feed($user);

    expect($feed)->toHaveCount(1)
        ->and($feed[0]['label'])->toBe('Told us about your income');
});

it('serves the feed over the API', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user);
    awardRow($user, 'data:pension:first', 'data');

    $this->getJson('/api/gamification/activity')
        ->assertOk()
        ->assertJsonPath('data.0.label', 'Added your first pension');
});
