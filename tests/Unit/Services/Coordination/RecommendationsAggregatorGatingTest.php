<?php

declare(strict_types=1);

use App\Models\Goal;
use App\Models\User;
use App\Services\Coordination\RecommendationsAggregatorService;
use Database\Seeders\TaxConfigurationSeeder;

it('does not emit estate recommendations when estate KYC is unmet', function () {
    $user = User::factory()->create([
        'is_preview_user' => false,
        'date_of_birth' => '2001-01-01',
    ]);

    $recs = app(RecommendationsAggregatorService::class)->aggregateRecommendations($user->id);

    $estate = array_filter($recs, fn ($r) => ($r['module'] ?? '') === 'estate');
    expect($estate)->toBeEmpty();
});

it('assigns the same recommendation_id to the same recommendation across calls', function () {
    $this->seed(TaxConfigurationSeeder::class);

    // date_of_birth + income + expenditure open the savings gate, whose
    // ISA-allowance recommendation is deterministic for a fresh user.
    $user = User::factory()->create([
        'is_preview_user' => false,
        'date_of_birth' => '1980-01-01',
        'employment_status' => 'employed',
        'annual_employment_income' => 40000,
        'monthly_expenditure' => 2000,
    ]);

    $service = app(RecommendationsAggregatorService::class);
    $first = $service->aggregateRecommendations($user->id);
    $second = $service->aggregateRecommendations($user->id);

    $idsByText = fn (array $recs) => collect($recs)
        ->mapWithKeys(fn ($r) => [$r['module'].'|'.$r['recommendation_text'] => $r['recommendation_id']]);

    $firstIds = $idsByText($first);
    $secondIds = $idsByText($second);

    expect($firstIds)->not->toBeEmpty();

    // Tracking (mark-done) and award dedup key on recommendation_id — the same
    // logical recommendation must carry the same id on every request.
    foreach ($firstIds as $key => $id) {
        expect($secondIds[$key] ?? null)->toBe($id, "recommendation_id for [{$key}] changed between calls");
    }
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
