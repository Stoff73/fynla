<?php

declare(strict_types=1);

use App\Services\Coordination\PlanSources\Adapters\InvestmentRecommendationAdapter;

it('uses definition_key as type and maps a rebalancing rec to lifecycle category', function (): void {
    $rec = [
        'priority' => 1,
        'category' => 'Rebalancing',
        'title' => 'Rebalance your portfolio to target allocation',
        'description' => 'Your portfolio has drifted from the target allocation set by your risk profile.',
        'action' => 'Review and rebalance your holdings.',
        'impact' => 'High',
        'scope' => 'portfolio',
        'definition_key' => 'rebalance_to_target',
        'estimated_impact' => 2500.0,
        'decision_trace' => [['question' => 'q', 'passed' => true]],
    ];

    $dto = (new InvestmentRecommendationAdapter)->toStrategyRecommendation($rec);

    expect($dto->type)->toBe('rebalance_to_target')
        ->and($dto->category)->toBe('lifecycle')
        ->and($dto->priority)->toBe('high')
        ->and($dto->title)->toBe('Rebalance your portfolio to target allocation')
        ->and($dto->estimatedAnnualTaxSaved)->toBeNull()
        ->and($dto->requiredMonthlyCost)->toBeNull()
        ->and($dto->requiredLumpSum)->toBe(2500.0)
        ->and($dto->extra['definition_key'])->toBe('rebalance_to_target')
        ->and($dto->extra['source_category'])->toBe('Rebalancing');
});

it('maps a risk profile rec to warning category via curated map when no definition_key', function (): void {
    $dto = (new InvestmentRecommendationAdapter)->toStrategyRecommendation([
        'category' => 'Risk Profile',
        'title' => 'Provide your investment preferences',
        'description' => 'No risk profile set.',
        'impact' => 'High',
    ]);

    expect($dto->type)->toBe('set_risk_profile')
        ->and($dto->category)->toBe('warning')
        ->and($dto->priority)->toBe('high')
        ->and($dto->requiredMonthlyCost)->toBeNull()
        ->and($dto->requiredLumpSum)->toBeNull();
});

it('falls back to a slugified type when category is unmapped and definition_key is absent', function (): void {
    $dto = (new InvestmentRecommendationAdapter)->toStrategyRecommendation([
        'category' => 'Tax Loss Harvesting',
        'title' => 'Crystallise losses to offset gains',
        'description' => 'You have harvesting opportunities.',
        'impact' => 'Medium',
    ]);

    expect($dto->type)->toBe('tax_loss_harvesting')
        ->and($dto->category)->toBe('lifecycle')
        ->and($dto->priority)->toBe('medium')
        ->and($dto->requiredLumpSum)->toBeNull();
});

it('prefers definition_key over the category map', function (): void {
    $dto = (new InvestmentRecommendationAdapter)->toStrategyRecommendation([
        'category' => 'Rebalancing',
        'definition_key' => 'allocation_needs_rebalancing',
        'title' => 'Rebalance',
        'description' => '',
        'impact' => 'Low',
    ]);

    // definition_key takes precedence over the curated category map
    expect($dto->type)->toBe('allocation_needs_rebalancing')
        ->and($dto->priority)->toBe('low');
});

it('omits account_id and account_name from extra when not present', function (): void {
    $dto = (new InvestmentRecommendationAdapter)->toStrategyRecommendation([
        'category' => 'Rebalancing',
        'definition_key' => 'rebalance_to_target',
        'title' => 'Rebalance',
        'description' => '',
        'impact' => 'Medium',
    ]);

    expect(array_key_exists('account_id', $dto->extra))->toBeFalse()
        ->and(array_key_exists('account_name', $dto->extra))->toBeFalse();
});
