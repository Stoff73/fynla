<?php

declare(strict_types=1);

use App\Agents\RetirementAgent;
use App\Models\User;
use App\Services\Coordination\RecommendationsAggregatorService;
use App\Services\PrerequisiteGateService;
use Database\Seeders\RetirementActionDefinitionSeeder;

/**
 * Phase 5.1 — non-tax modules flow through ComposedModulePlanService in the
 * RecommendationsAggregatorService when the composed_module_plans flag is on,
 * gaining catalogue metadata (claim_tier / sequence_position) exactly as tax
 * already does. The raw per-agent path is the rollback branch when the flag
 * is off. Retirement is used as the representative module: a deterministic
 * agent recommendation (category 'Contribution_increase') maps via the adapter
 * to the seeded 'increase_pension_contribution' strategy_type, whose catalogue
 * row carries claim_tier 'mechanical'.
 */
beforeEach(function () {
    $this->seed(RetirementActionDefinitionSeeder::class);

    // A retirement agent that deterministically fires one recommendation whose
    // category maps (via RetirementRecommendationAdapter) to the seeded
    // 'increase_pension_contribution' strategy_type. Bound as a container
    // instance so BOTH the raw per-agent block (constructor-injected agent) and
    // the composed source (resolves its agent from the container) use it.
    $agent = Mockery::mock(RetirementAgent::class);
    $agent->shouldReceive('analyze')->andReturn(['data' => []]);
    $agent->shouldReceive('generateRecommendations')->andReturn(['recommendations' => [[
        'title' => 'Increase your pension contribution',
        'description' => 'You have unused annual allowance this year.',
        'category' => 'Contribution_increase',
        'impact' => 'high',
        'available_monthly_headroom' => 250,
    ]]]);
    app()->instance(RetirementAgent::class, $agent);

    // Open only the retirement gate so the assertion is isolated to the
    // retirement composed path (no other module contributes).
    $gate = Mockery::mock(PrerequisiteGateService::class);
    $gate->shouldReceive('enforce')->andReturnUsing(
        fn (string $module) => ['can_proceed' => $module === 'retirement']
    );
    app()->instance(PrerequisiteGateService::class, $gate);

    $this->user = User::factory()->create(['is_preview_user' => false]);
});

afterEach(function () {
    Mockery::close();
});

it('routes a non-tax module through the composed plan when the flag is on, attaching catalogue metadata', function () {
    config(['coordination.composed_module_plans' => true]);

    $recs = app(RecommendationsAggregatorService::class)->aggregateRecommendations($this->user->id);

    $rec = collect($recs)->firstWhere('recommendation_id', 'retirement_increase_pension_contribution');

    expect($rec)->not->toBeNull()
        ->and($rec['module'])->toBe('retirement')
        ->and($rec['claim_tier'])->toBe('mechanical')
        ->and($rec['sequence_position'])->toBeInt()
        ->and($rec['recommendation_text'])->toContain('Increase your pension contribution');
});

it('falls back to the raw per-agent path when the flag is off (no catalogue metadata)', function () {
    config(['coordination.composed_module_plans' => false]);

    $recs = app(RecommendationsAggregatorService::class)->aggregateRecommendations($this->user->id);

    $retRecs = array_values(array_filter($recs, fn ($r) => $r['module'] === 'retirement'));

    expect($retRecs)->not->toBeEmpty();
    foreach ($retRecs as $r) {
        expect($r['claim_tier'])->toBeNull();
    }
});
