<?php

declare(strict_types=1);

use App\Models\Goal;
use App\Models\User;
use App\Services\Coordination\RecommendationsAggregatorService;

it('does not emit estate recommendations when estate KYC is unmet', function () {
    $user = User::factory()->create([
        'is_preview_user' => false,
        'date_of_birth' => '2001-01-01',
    ]);

    $recs = app(RecommendationsAggregatorService::class)->aggregateRecommendations($user->id);

    $estate = array_filter($recs, fn ($r) => ($r['module'] ?? '') === 'estate');
    expect($estate)->toBeEmpty();
});

it('tags each recommendation with the gate-satisfied module only', function () {
    $user = User::factory()->create(['is_preview_user' => false]);

    // Opening the goals gate requires at least one goal (PrerequisiteGateService::canAnalyseGoals).
    // Every other module's gate stays closed for this bare user, so only goals recommendations
    // should be emitted — proving the gate-tagging contract.
    Goal::factory()->create(['user_id' => $user->id]);

    $recs = app(RecommendationsAggregatorService::class)->aggregateRecommendations($user->id);

    expect($recs)->not->toBeEmpty();

    foreach ($recs as $r) {
        expect($r)->toHaveKeys(['module', 'recommendation_text', 'priority_score']);
    }

    $modules = array_unique(array_map(fn ($r) => $r['module'] ?? '', $recs));
    expect($modules)->toContain('goals')
        ->and($modules)->toEqual(['goals']);
});
