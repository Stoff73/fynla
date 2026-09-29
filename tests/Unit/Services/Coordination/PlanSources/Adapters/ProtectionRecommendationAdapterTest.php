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

it('lets the seeded impact label win over int priority 2', function (): void {
    $dto = (new ProtectionRecommendationAdapter)->toStrategyRecommendation([
        'priority' => 2,
        'category' => 'Critical Illness',
        'action' => 'Consider critical illness cover',
        'rationale' => 'No CI policy in place.',
        'impact' => 'Medium',
    ]);

    expect($dto->priority)->toBe('medium');
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

it('lets the seeded impact label win over int priority 4', function (): void {
    $dto = (new ProtectionRecommendationAdapter)->toStrategyRecommendation([
        'priority' => 4,
        'category' => 'Trust Planning',
        'action' => 'Place policies in trust',
        'rationale' => 'Policies not in trust may attract inheritance tax.',
        'impact' => 'Medium',
    ]);

    expect($dto->priority)->toBe('medium');
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

it('types a definition rec by its key and carries its figures and policy to the card', function (): void {
    // Protection cards are the action definitions (CSJ 2026-09-29): the key is the
    // card's type (a stable id and its how-to), the figures fill its steps, and a
    // per-policy rule is scoped to its policy.
    $dto = (new ProtectionRecommendationAdapter)->toStrategyRecommendation([
        'priority' => 3,
        'category' => 'Life Insurance',
        'action' => 'Place your life insurance policy in trust',
        'rationale' => 'Your life insurance policy with Aviva is not held in trust.',
        'impact' => 'Medium',
        'estimated_cost' => 0,
        'definition_key' => 'policy_not_in_trust',
        'policy_id' => 36,
        'figures' => ['provider' => 'Aviva'],
    ]);

    expect($dto->type)->toBe('policy_not_in_trust')
        ->and($dto->extra['definition_key'])->toBe('policy_not_in_trust')
        ->and($dto->extra['policy_id'])->toBe(36)
        ->and($dto->extra['figures'])->toBe(['provider' => 'Aviva']);
});

it('prefers a rec\'s own title and description over its action text', function (): void {
    // The premium rules publish title/description and a separate action sentence;
    // the card's title is the title, never the action.
    $dto = (new ProtectionRecommendationAdapter)->toStrategyRecommendation([
        'priority' => 2,
        'category' => 'Premium Review',
        'title' => 'Protection premiums may be unaffordable',
        'description' => 'Your total annual protection premiums of £9,000 represent 12.0% of your gross income.',
        'action' => 'Urgently review your protection cover.',
        'impact' => 'High',
        'definition_key' => 'premium_affordability_warning',
    ]);

    expect($dto->title)->toBe('Protection premiums may be unaffordable')
        ->and($dto->description)->toStartWith('Your total annual protection premiums');
});
