<?php

declare(strict_types=1);

use App\Services\Coordination\PlanSources\Adapters\SavingsRecommendationAdapter;

it('maps an emergency-fund rec with a definition_key to the key as type and Warning category', function (): void {
    $rec = [
        'priority' => 1,
        'category' => 'Emergency Fund',
        'title' => 'Build your emergency fund',
        'description' => 'Your emergency fund covers less than 1 month of expenses.',
        'action' => 'Set up an automatic transfer to your emergency savings account.',
        'impact' => 'High',
        'scope' => 'portfolio',
        'definition_key' => 'emergency_fund_critical',
        'account_id' => null,
        'account_name' => null,
        'decision_trace' => [['question' => 'runway?', 'passed' => false]],
    ];

    $dto = (new SavingsRecommendationAdapter)->toStrategyRecommendation($rec);

    expect($dto->type)->toBe('emergency_fund_critical')
        ->and($dto->category)->toBe('warning')
        ->and($dto->priority)->toBe('high')
        ->and($dto->title)->toBe('Build your emergency fund')
        ->and($dto->estimatedAnnualTaxSaved)->toBeNull()
        ->and($dto->requiredMonthlyCost)->toBeNull()
        ->and($dto->extra['definition_key'])->toBe('emergency_fund_critical')
        ->and($dto->extra['source_category'])->toBe('Emergency Fund');
});

it('maps a rate-optimisation rec with a definition_key to the key as type and Lifecycle category', function (): void {
    $rec = [
        'priority' => 2,
        'category' => 'Rate Optimisation',
        'title' => 'Switch to a better savings rate',
        'description' => 'Your account could earn more with a higher-interest provider.',
        'action' => 'Compare savings rates and consider switching.',
        'impact' => 'Medium',
        'scope' => 'account',
        'definition_key' => 'rate_below_market',
        'account_id' => 7,
        'account_name' => 'Barclays Easy Access',
    ];

    $dto = (new SavingsRecommendationAdapter)->toStrategyRecommendation($rec);

    expect($dto->type)->toBe('rate_below_market')
        ->and($dto->category)->toBe('lifecycle')
        ->and($dto->priority)->toBe('medium')
        ->and($dto->extra['account_id'])->toBe(7)
        ->and($dto->extra['account_name'])->toBe('Barclays Easy Access')
        ->and($dto->extra['source_category'])->toBe('Rate Optimisation');
});

it('falls back to the curated map when no definition_key is present', function (): void {
    $dto = (new SavingsRecommendationAdapter)->toStrategyRecommendation([
        'category' => 'emergency_fund',
        'title' => 'Build Emergency Fund',
        'description' => 'You need a larger emergency fund.',
        'impact' => 'High',
    ]);

    expect($dto->type)->toBe('build_emergency_fund')
        ->and($dto->category)->toBe('warning')
        ->and($dto->priority)->toBe('high')
        ->and($dto->requiredMonthlyCost)->toBeNull();
});

it('falls back to a slugified type when the category is unmapped and has no definition_key', function (): void {
    $dto = (new SavingsRecommendationAdapter)->toStrategyRecommendation([
        'category' => 'Spouse Optimisation',
        'title' => 'Optimise spousal ISA allocation',
        'description' => 'Consider rebalancing ISA contributions between partners.',
        'impact' => 'Low',
    ]);

    expect($dto->type)->toBe('spouse_optimisation')
        ->and($dto->category)->toBe('lifecycle')
        ->and($dto->priority)->toBe('low');
});
