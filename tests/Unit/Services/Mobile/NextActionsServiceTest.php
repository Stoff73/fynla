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

it('marks a recommendation item done when a matching completed tracking row exists', function () {
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

    // Matching completed tracking row drives the done=true branch.
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

    $item = collect($items)->firstWhere('id', $recommendationId);
    expect($item)->not->toBeNull()
        ->and($item['type'])->toBe('recommendation')
        ->and($item['done'])->toBeTrue();
});

afterEach(function () {
    Mockery::close();
});
