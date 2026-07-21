<?php

declare(strict_types=1);

use App\Services\Coordination\PlanSources\Adapters\RetirementRecommendationAdapter;

it('maps a contribution rec to the curated strategy_type with cost + lifecycle category', function (): void {
    $rec = [
        'priority' => 1,
        'category' => 'Contribution_increase',
        'title' => 'Increase your pension contribution',
        'description' => 'Raise your monthly contribution to capture the full employer match.',
        'action' => 'Increase by 2%',
        'impact' => 'High',
        'scope' => 'account',
        'account_id' => 42,
        'account_name' => 'Aviva Workplace',
        'available_monthly_headroom' => 180.0,
        'decision_trace' => [['question' => 'q', 'passed' => false]],
    ];

    $dto = (new RetirementRecommendationAdapter)->toStrategyRecommendation($rec);

    expect($dto->type)->toBe('increase_pension_contribution')
        ->and($dto->category)->toBe('lifecycle')
        ->and($dto->priority)->toBe('high')
        ->and($dto->title)->toBe('Increase your pension contribution')
        ->and($dto->estimatedAnnualTaxSaved)->toBeNull()
        ->and($dto->requiredMonthlyCost)->toBe(180.0)
        ->and($dto->extra['account_id'])->toBe(42)
        ->and($dto->extra['source_category'])->toBe('Contribution_increase');
});

it('falls back to a slugified type when the category is unmapped', function (): void {
    $dto = (new RetirementRecommendationAdapter)->toStrategyRecommendation([
        'category' => 'Care Costs',
        'title' => 'Plan for care costs',
        'description' => 'Consider how later-life care will be funded.',
        'impact' => 'Medium',
    ]);

    expect($dto->type)->toBe('care_costs')
        ->and($dto->priority)->toBe('medium')
        ->and($dto->requiredMonthlyCost)->toBeNull();
});
