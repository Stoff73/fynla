<?php

declare(strict_types=1);

use App\Models\RecommendationTracking;
use App\Models\User;
use App\Models\UserMilestone;
use App\Services\Mobile\MilestoneDetectionService;
use Database\Seeders\TaxConfigurationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

/**
 * WP-5 — journey milestone flavours + self-healing detection. The milestones
 * page previously showed only net-worth/goal crossings, detected exclusively
 * on the /m dashboard read — a user who never opened it earned nothing.
 */
beforeEach(function () {
    $this->seed(TaxConfigurationSeeder::class);
});

it('records the tax-profile milestone once for a completed campaign user', function () {
    $user = User::factory()->create([
        'onboarding_completed' => true,
        'funnel_answers' => ['income' => '50271_100000', 'assets' => ['bank']],
    ]);

    $service = app(MilestoneDetectionService::class);
    $first = $service->detectJourney($user);
    $second = $service->detectJourney($user);

    expect(array_column($first, 'type'))->toContain('campaign')
        ->and($second)->toBe([])
        ->and(UserMilestone::where('user_id', $user->id)->where('milestone_type', 'campaign')->count())->toBe(1);
});

it('does not record the tax-profile milestone mid-onboarding', function () {
    $user = User::factory()->create([
        'onboarding_completed' => false,
        'funnel_answers' => ['income' => '50271_100000'],
    ]);

    expect(app(MilestoneDetectionService::class)->detectJourney($user))->toBe([]);
});

it('records the first-action milestone when a recommendation completes', function () {
    $user = User::factory()->create();
    RecommendationTracking::create([
        'user_id' => $user->id,
        'recommendation_id' => 'tax_x',
        'module' => 'tax',
        'recommendation_text' => 'Do the thing',
        'status' => 'completed',
        'completed_at' => now(),
    ]);

    $new = app(MilestoneDetectionService::class)->detectJourney($user);

    expect(array_column($new, 'type'))->toContain('action');
});

it('records the quantified tax saving once, keeping the first amount', function () {
    $user = User::factory()->create();
    $service = app(MilestoneDetectionService::class);

    $first = $service->detectTaxSavingsIdentified($user, 12000.4);
    $again = $service->detectTaxSavingsIdentified($user, 15000.0);

    expect($first[0]['label'])->toBe('We found £12,000 a year you could save in tax.')
        ->and($again)->toBe([])
        ->and(UserMilestone::where('user_id', $user->id)->where('milestone_type', 'tax_savings')->count())->toBe(1);
});

it('ignores a zero saving', function () {
    $user = User::factory()->create();

    expect(app(MilestoneDetectionService::class)->detectTaxSavingsIdentified($user, 0.0))->toBe([]);
});

it('self-heals the milestones page: journey milestones appear on the progress read', function () {
    $user = User::factory()->create([
        'is_preview_user' => false,
        'onboarding_completed' => true,
        'funnel_answers' => ['assets' => ['bank']],
    ]);
    Sanctum::actingAs($user);

    // The user has NEVER loaded the /m dashboard — the achievements read
    // itself must materialise the journey milestone.
    $res = $this->getJson('/api/v1/mobile/achievements')->assertOk();

    $titles = array_column($res->json('data.milestones'), 'title');
    expect($titles)->toContain('You completed your tax profile.');
});
