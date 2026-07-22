<?php

declare(strict_types=1);

use App\Services\Coordination\PlanSources\Adapters\ProtectionRecommendationAdapter;

it('maps action to title and rationale to description', function (): void {
    $rec = [
        'priority' => 1,
        'category' => 'Life Insurance',
        'action' => 'Increase life insurance coverage',
        'rationale' => 'Current coverage falls short by £150,000.00. This gap could leave your dependants financially vulnerable.',
        'impact' => 'High',
        'estimated_cost' => 45.50,
    ];

    $dto = (new ProtectionRecommendationAdapter)->toStrategyRecommendation($rec);

    expect($dto->title)->toBe('Increase life insurance coverage')
        ->and($dto->description)->toBe('Current coverage falls short by £150,000.00. This gap could leave your dependants financially vulnerable.');
});

it('maps Life Insurance category to the curated strategy_type', function (): void {
    $rec = [
        'priority' => 1,
        'category' => 'Life Insurance',
        'action' => 'Increase life insurance coverage',
        'rationale' => 'Gap exists.',
        'impact' => 'High',
        'estimated_cost' => 45.50,
    ];

    $dto = (new ProtectionRecommendationAdapter)->toStrategyRecommendation($rec);

    expect($dto->type)->toBe('protection_life_cover_gap');
});

it('maps int priority 1 to high', function (): void {
    $dto = (new ProtectionRecommendationAdapter)->toStrategyRecommendation([
        'priority' => 1,
        'category' => 'Life Insurance',
        'action' => 'Add cover',
        'rationale' => 'Gap detected.',
        'impact' => 'High',
    ]);

    expect($dto->priority)->toBe('high');
});

it('maps int priority 2 to high', function (): void {
    $dto = (new ProtectionRecommendationAdapter)->toStrategyRecommendation([
        'priority' => 2,
        'category' => 'Critical Illness',
        'action' => 'Consider critical illness cover',
        'rationale' => 'No CI policy in place.',
        'impact' => 'Medium',
    ]);

    expect($dto->priority)->toBe('high');
});

it('maps int priority 3 to medium', function (): void {
    $dto = (new ProtectionRecommendationAdapter)->toStrategyRecommendation([
        'priority' => 3,
        'category' => 'Life Insurance',
        'action' => 'Consider family income benefit',
        'rationale' => 'Education gap exists.',
        'impact' => 'Medium',
    ]);

    expect($dto->priority)->toBe('medium');
});

it('maps int priority 4 to low', function (): void {
    $dto = (new ProtectionRecommendationAdapter)->toStrategyRecommendation([
        'priority' => 4,
        'category' => 'Trust Planning',
        'action' => 'Place policies in trust',
        'rationale' => 'Policies not in trust may attract inheritance tax.',
        'impact' => 'Medium',
    ]);

    expect($dto->priority)->toBe('low');
});

it('maps int priority 5 to low', function (): void {
    $dto = (new ProtectionRecommendationAdapter)->toStrategyRecommendation([
        'priority' => 5,
        'category' => 'Policy Optimisation',
        'action' => 'Review and optimise existing policies',
        'rationale' => 'Premiums exceed 5% of income.',
        'impact' => 'Low',
    ]);

    expect($dto->priority)->toBe('low');
});

it('carries estimated_cost in extra as estimated_premium', function (): void {
    $rec = [
        'priority' => 1,
        'category' => 'Life Insurance',
        'action' => 'Increase life insurance coverage',
        'rationale' => 'Gap exists.',
        'impact' => 'High',
        'estimated_cost' => 45.50,
    ];

    $dto = (new ProtectionRecommendationAdapter)->toStrategyRecommendation($rec);

    expect($dto->extra['estimated_premium'])->toBe(45.50)
        ->and($dto->requiredMonthlyCost)->toBeNull();
});

it('sets requiresAdvice to true', function (): void {
    $dto = (new ProtectionRecommendationAdapter)->toStrategyRecommendation([
        'priority' => 1,
        'category' => 'Life Insurance',
        'action' => 'Add cover',
        'rationale' => 'Gap exists.',
        'impact' => 'High',
    ]);

    expect($dto->requiresAdvice)->toBeTrue();
});

it('assigns Warning category to gap recs (Life Insurance)', function (): void {
    $dto = (new ProtectionRecommendationAdapter)->toStrategyRecommendation([
        'priority' => 1,
        'category' => 'Life Insurance',
        'action' => 'Add cover',
        'rationale' => 'Gap exists.',
        'impact' => 'High',
    ]);

    expect($dto->category)->toBe('warning');
});

it('assigns Warning category to Income Protection gap recs', function (): void {
    $dto = (new ProtectionRecommendationAdapter)->toStrategyRecommendation([
        'priority' => 2,
        'category' => 'Income Protection',
        'action' => 'Add income protection',
        'rationale' => 'No cover in place.',
        'impact' => 'High',
    ]);

    expect($dto->category)->toBe('warning');
});

it('assigns Lifecycle category to Trust Planning recs', function (): void {
    $dto = (new ProtectionRecommendationAdapter)->toStrategyRecommendation([
        'priority' => 4,
        'category' => 'Trust Planning',
        'action' => 'Place policies in trust',
        'rationale' => 'Policies not in trust.',
        'impact' => 'Medium',
    ]);

    expect($dto->category)->toBe('lifecycle');
});

it('assigns Lifecycle category to Policy Optimisation recs', function (): void {
    $dto = (new ProtectionRecommendationAdapter)->toStrategyRecommendation([
        'priority' => 5,
        'category' => 'Policy Optimisation',
        'action' => 'Review policies',
        'rationale' => 'Premiums high.',
        'impact' => 'Low',
    ]);

    expect($dto->category)->toBe('lifecycle');
});

it('carries source_category and impact in extra', function (): void {
    $dto = (new ProtectionRecommendationAdapter)->toStrategyRecommendation([
        'priority' => 1,
        'category' => 'Life Insurance',
        'action' => 'Add cover',
        'rationale' => 'Gap exists.',
        'impact' => 'High',
    ]);

    expect($dto->extra['source_category'])->toBe('Life Insurance')
        ->and($dto->extra['impact'])->toBe('High');
});

it('falls back to a slugified type for unmapped categories', function (): void {
    $dto = (new ProtectionRecommendationAdapter)->toStrategyRecommendation([
        'priority' => 3,
        'category' => 'Employer Benefits',
        'action' => 'Review employer cover',
        'rationale' => 'Employer DIS reliance.',
        'impact' => 'Medium',
    ]);

    expect($dto->type)->toBe('employer_benefits')
        ->and($dto->priority)->toBe('medium');
});

it('falls back to impact string priority when int priority is absent', function (): void {
    $dto = (new ProtectionRecommendationAdapter)->toStrategyRecommendation([
        'category' => 'Income Protection',
        'action' => 'Add income protection',
        'rationale' => 'No cover.',
        'impact' => 'High',
    ]);

    expect($dto->priority)->toBe('high');
});

it('sets estimated_annual_tax_saved to null', function (): void {
    $dto = (new ProtectionRecommendationAdapter)->toStrategyRecommendation([
        'priority' => 1,
        'category' => 'Life Insurance',
        'action' => 'Add cover',
        'rationale' => 'Gap.',
        'impact' => 'High',
    ]);

    expect($dto->estimatedAnnualTaxSaved)->toBeNull();
});
