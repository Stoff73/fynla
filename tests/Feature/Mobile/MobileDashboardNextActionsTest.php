<?php

declare(strict_types=1);

use App\Constants\GateRoutes;
use App\Models\User;
use App\Services\Coordination\RecommendationsAggregatorService;
use Laravel\Sanctum\Sanctum;
use Mockery\MockInterface;

it('returns a unified next_actions list of at most four items', function () {
    $user = User::factory()->create(['is_preview_user' => false]);
    Sanctum::actingAs($user);

    $res = $this->getJson('/api/v1/mobile/dashboard');

    $res->assertOk();
    $actions = $res->json('data.next_actions');
    expect($actions)->toBeArray()
        ->and(count($actions))->toBeLessThanOrEqual(4);
    $res->assertJsonPath('data.level.actions_total', count($actions));
});

it('returns focus_areas with a Top card whose actions equal next_actions', function () {
    $user = User::factory()->create(['is_preview_user' => false]);
    Sanctum::actingAs($user);

    $res = $this->getJson('/api/v1/mobile/dashboard');

    $res->assertOk();
    $areas = $res->json('data.focus_areas');
    expect($areas)->toBeArray()
        ->and($areas[0]['key'])->toBe('top');

    // The Top card's actions ARE the unified next_actions list (no duplication).
    expect($res->json('data.next_actions'))->toEqual($areas[0]['actions']);
});

it('returns typed semantic recommendation destinations and a safe general fallback', function () {
    $this->mock(
        RecommendationsAggregatorService::class,
        function (MockInterface $mock): void {
            $mock->shouldReceive('aggregateRecommendations')->andReturn([
                [
                    'recommendation_id' => 'retirement_semantic_route',
                    'module' => 'retirement',
                    'recommendation_text' => 'Review your retirement contribution',
                    'category' => 'retirement',
                    'potential_benefit' => 1_000_000,
                    'priority_score' => 100,
                ],
                [
                    'recommendation_id' => 'general_semantic_route',
                    'module' => 'general',
                    'recommendation_text' => 'Review your overall position',
                    'category' => 'recommended',
                    'potential_benefit' => 999_999,
                    'priority_score' => 99,
                ],
            ]);
        },
    );

    $user = User::factory()->create([
        'is_preview_user' => false,
        'onboarding_completed' => true,
    ]);
    Sanctum::actingAs($user);

    $response = $this->getJson('/api/v1/mobile/dashboard')->assertOk();
    $actions = collect($response->json('data.next_actions'));

    expect($actions->firstWhere('id', 'retirement_semantic_route')['action'])->toBe([
        'kind' => 'navigate',
        'payload' => '/retirement',
        'destination' => [
            'screen' => GateRoutes::RETIREMENT,
            'params' => [],
            'fallback' => GateRoutes::DASHBOARD,
        ],
    ])->and($actions->firstWhere('id', 'general_semantic_route')['action'])->toBe([
        'kind' => 'navigate',
        'payload' => '/net-worth',
        'destination' => [
            'screen' => GateRoutes::NET_WORTH,
            'params' => [],
            'fallback' => GateRoutes::DASHBOARD,
        ],
    ]);
});
