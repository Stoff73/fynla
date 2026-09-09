<?php

declare(strict_types=1);

use App\Agents\GoalsAgent;
use App\Models\RecommendationTracking;
use App\Models\User;
use App\Services\PrerequisiteGateService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

/**
 * F18 — the aggregator merges recommendation_tracking status onto every rec,
 * so GET /api/recommendations?status= can match something other than pending.
 */
it('filters recommendations by their tracked status', function () {
    $user = User::factory()->create(['onboarding_completed' => true]);
    Sanctum::actingAs($user);

    RecommendationTracking::create([
        'user_id' => $user->id,
        'recommendation_id' => 'goals_done',
        'module' => 'goals',
        'recommendation_text' => 'Done one',
        'priority_score' => 85,
        'timeline' => 'immediate',
        'status' => 'completed',
        'completed_at' => now(),
    ]);

    // Feed the goals engine two recs through the real aggregator path.
    $goals = Mockery::mock(GoalsAgent::class);
    $goals->shouldReceive('analyze')->andReturn(['data' => []]);
    $goals->shouldReceive('generateRecommendations')->andReturn(['recommendations' => [
        ['recommendation_id' => 'goals_done', 'title' => 'Done one', 'priority' => 'high'],
        ['recommendation_id' => 'goals_open', 'title' => 'Open one', 'priority' => 'high'],
    ]]);
    app()->instance(GoalsAgent::class, $goals);
    $gate = Mockery::mock(PrerequisiteGateService::class);
    $gate->shouldReceive('enforce')->andReturnUsing(fn (string $module) => ['can_proceed' => $module === 'goals']);
    app()->instance(PrerequisiteGateService::class, $gate);

    $completed = $this->getJson('/api/recommendations?status=completed')->assertOk()->json('data');
    $pending = $this->getJson('/api/recommendations?status=pending')->assertOk()->json('data');

    expect(array_column($completed, 'recommendation_id'))->toBe(['goals_done'])
        ->and(array_column($pending, 'recommendation_id'))->toBe(['goals_open']);
});
