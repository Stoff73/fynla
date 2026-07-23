<?php

declare(strict_types=1);

use App\Models\RecommendationTracking;
use App\Models\User;
use App\Services\Coordination\RecommendationsAggregatorService;
use App\Services\Mobile\NextActionsService;

it('caps the unified list at four items', function () {
    $user = User::factory()->create(['is_preview_user' => false]);

    $items = app(NextActionsService::class)->build($user->id);

    expect(count($items))->toBeLessThanOrEqual(4);
});

it('emits an unlock item for a gated module carrying a fyn_capture action', function () {
    $user = User::factory()->create(['is_preview_user' => false]);

    $items = app(NextActionsService::class)->build($user->id);

    $unlock = collect($items)->firstWhere('type', 'unlock');
    expect($unlock)->not->toBeNull()
        ->and($unlock['action']['kind'])->toBe('fyn_capture')
        ->and($unlock['done'])->toBeFalse();
});

it('sorts by value descending', function () {
    $user = User::factory()->create(['is_preview_user' => false]);

    $items = app(NextActionsService::class)->build($user->id);
    $values = array_column($items, 'value');
    $sorted = $values;
    rsort($sorted);

    expect($values)->toEqual($sorted);
});

it('excludes a completed recommendation from the list (banked + replaced by next-best)', function () {
    $user = User::factory()->create(['is_preview_user' => false]);

    // Stub the aggregator with one stable recommendation so the id is deterministic
    // (real module recs fall back to uniqid() ids — see formatRecommendations()).
    $recommendationId = 'goals_test_rec_1';

    $aggregator = Mockery::mock(RecommendationsAggregatorService::class);
    $aggregator->shouldReceive('aggregateRecommendations')
        ->with($user->id)
        ->andReturn([[
            'recommendation_id' => $recommendationId,
            'module' => 'goals',
            'recommendation_text' => 'Increase your monthly goal contributions',
            'priority_score' => 70.0,
            'category' => 'goals',
            'potential_benefit' => null,
        ]]);
    app()->instance(RecommendationsAggregatorService::class, $aggregator);

    // A matching completed tracking row removes the rec from the actionable list.
    RecommendationTracking::create([
        'user_id' => $user->id,
        'recommendation_id' => $recommendationId,
        'module' => 'goals',
        'recommendation_text' => 'Increase your monthly goal contributions',
        'priority_score' => 70.0,
        'status' => 'completed',
        'completed_at' => now(),
    ]);

    $items = app(NextActionsService::class)->build($user->id);

    // Completed recs are banked toward the wheel count + replaced, not shown
    // ticked — so the rec is absent from the actionable list (CSJ 4.4).
    $item = collect($items)->firstWhere('id', $recommendationId);
    expect($item)->toBeNull();
});

it('deep-links a tax recommendation to the tax strategy screen', function () {
    $user = User::factory()->create(['is_preview_user' => false]);

    // The aggregator's seventh module — a tax rec must navigate to
    // /tax-strategy, not the /net-worth fallback.
    $aggregator = Mockery::mock(RecommendationsAggregatorService::class);
    $aggregator->shouldReceive('aggregateRecommendations')
        ->with($user->id)
        ->andReturn([[
            'recommendation_id' => 'tax_test_rec_1',
            'module' => 'tax',
            'recommendation_text' => 'Use salary sacrifice for your pension contributions',
            'priority_score' => 80.0,
            'category' => 'tax',
            'potential_benefit' => null,
        ]]);
    app()->instance(RecommendationsAggregatorService::class, $aggregator);

    $items = app(NextActionsService::class)->build($user->id);

    $item = collect($items)->firstWhere('id', 'tax_test_rec_1');
    expect($item)->not->toBeNull()
        ->and($item['action']['kind'])->toBe('navigate')
        ->and($item['action']['payload'])->toBe('/tax-strategy');
});

it('builds focus-area cards: a Top card first, then one per module', function () {
    $user = User::factory()->create(['is_preview_user' => false]);

    $areas = app(NextActionsService::class)->focusAreas($user->id);

    // First card is always "Top actions".
    expect($areas[0]['key'])->toBe('top')
        ->and($areas[0]['label'])->toBe('Top actions')
        ->and($areas[0]['locked'])->toBeFalse();

    // One card per module, in the canonical order.
    $keys = array_column($areas, 'key');
    foreach (['retirement', 'protection', 'savings', 'investment', 'estate', 'goals'] as $module) {
        expect($keys)->toContain($module);
    }

    // A fresh user's modules are KYC-gated → those cards are locked with a single
    // unlock action.
    $estate = collect($areas)->firstWhere('key', 'estate');
    expect($estate['locked'])->toBeTrue()
        ->and($estate['actions'])->toHaveCount(1)
        ->and($estate['actions'][0]['type'])->toBe('unlock');
});

afterEach(function () {
    Mockery::close();
});

it('suppresses unlock items while the user is mid-onboarding — the walk is the action', function () {
    // CSJ 2026-07-23 (live): a brand-new mid-walk user saw four "Unlock X
    // advice — date of birth is required" actions on the dashboard while Fyn
    // was about to ask for exactly that data in the walk. While the director
    // owns the next turn (onboarding_fyn_step non-null), unlock prompts are
    // noise that competes with onboarding — the dashboard's "finish your
    // plan with Fyn" nudge is the one call to action.
    $user = User::factory()->create([
        'is_preview_user' => false,
        'onboarding_completed' => false,
        'onboarding_fyn_step' => 'base_work',
    ]);

    $service = app(NextActionsService::class);

    expect(collect($service->build($user->id))->where('type', 'unlock'))->toBeEmpty();

    // The unified Top card carries no unlock prompts mid-walk; the
    // per-module tab cards keep their true gate state (the level map).
    $areas = collect($service->focusAreas($user->id));
    $top = $areas->firstWhere('key', 'top');
    expect(collect($top['actions'])->where('type', 'unlock'))->toBeEmpty();
    expect($areas->where('locked', true))->not->toBeEmpty();
});

it('shows unlock items again once the walk is over (step nulled)', function () {
    $user = User::factory()->create([
        'is_preview_user' => false,
        'onboarding_completed' => true,
        'onboarding_fyn_step' => null,
    ]);

    $unlocks = collect(app(NextActionsService::class)->build($user->id))->where('type', 'unlock');
    expect($unlocks)->not->toBeEmpty();
});
