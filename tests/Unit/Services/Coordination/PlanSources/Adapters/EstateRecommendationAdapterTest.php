<?php

declare(strict_types=1);

use App\Services\Coordination\PlanSources\Adapters\EstateRecommendationAdapter;

it('maps a no-will rec to the definition_key type with Warning category', function (): void {
    $rec = [
        'priority' => 1,
        'category' => 'Will',
        'title' => 'No Will in Place',
        'description' => 'There is no will recorded for this estate.',
        'action' => 'Arrange a will with a solicitor.',
        'impact' => 'Critical',
        'scope' => 'portfolio',
        'definition_key' => 'no_will',
    ];

    $dto = (new EstateRecommendationAdapter)->toStrategyRecommendation($rec);

    expect($dto->type)->toBe('no_will')
        ->and($dto->category)->toBe('warning')
        ->and($dto->priority)->toBe('high')
        ->and($dto->title)->toBe('No Will in Place')
        ->and($dto->estimatedAnnualTaxSaved)->toBeNull()
        ->and($dto->requiredMonthlyCost)->toBeNull()
        ->and($dto->requiredLumpSum)->toBeNull()
        ->and($dto->requiresAdvice)->toBeTrue()
        ->and($dto->extra['definition_key'])->toBe('no_will')
        ->and($dto->extra['source_category'])->toBe('Will');
});

it('maps an IHT-exceeds-NRB rec carrying estimated_impact to Warning category with iht_saving in extra', function (): void {
    $rec = [
        'priority' => 2,
        'category' => 'Inheritance Tax',
        'title' => 'Estate Value Exceeds Nil-Rate Band',
        'description' => 'Your estate may be subject to Inheritance Tax.',
        'action' => 'Review estate planning strategies.',
        'impact' => 'High',
        'scope' => 'portfolio',
        'definition_key' => 'iht_exceeds_nrb',
        'estimated_impact' => 60000.0,
    ];

    $dto = (new EstateRecommendationAdapter)->toStrategyRecommendation($rec);

    expect($dto->type)->toBe('iht_exceeds_nrb')
        ->and($dto->category)->toBe('warning')
        ->and($dto->priority)->toBe('high')
        ->and($dto->estimatedAnnualTaxSaved)->toBeNull()
        ->and($dto->extra['estimated_iht_saving'])->toBe(60000.0)
        ->and($dto->requiresAdvice)->toBeFalse();
});

it('maps an LPA rec to lifecycle category with requiresAdvice false', function (): void {
    $rec = [
        'priority' => 3,
        'category' => 'Lasting Power of Attorney',
        'title' => 'No Lasting Power of Attorney (Financial)',
        'description' => 'No financial LPA is recorded.',
        'action' => 'Set up a Property and Financial Affairs LPA.',
        'impact' => 'High',
        'scope' => 'portfolio',
        'definition_key' => 'no_lpa',
    ];

    $dto = (new EstateRecommendationAdapter)->toStrategyRecommendation($rec);

    expect($dto->type)->toBe('no_lpa')
        ->and($dto->category)->toBe('lifecycle')
        ->and($dto->priority)->toBe('high')
        ->and($dto->requiresAdvice)->toBeFalse();
});

it('maps a trust planning rec to lifecycle category with requiresAdvice true', function (): void {
    $rec = [
        'priority' => 4,
        'category' => 'Trust',
        'title' => 'Place Life Policies in Trust',
        'description' => 'Policies not in trust add to the taxable estate.',
        'action' => 'Contact your provider to place policies in trust.',
        'impact' => 'Medium',
        'scope' => 'portfolio',
        'definition_key' => 'policy_not_in_trust',
    ];

    $dto = (new EstateRecommendationAdapter)->toStrategyRecommendation($rec);

    expect($dto->type)->toBe('policy_not_in_trust')
        ->and($dto->category)->toBe('lifecycle')
        ->and($dto->priority)->toBe('medium')
        ->and($dto->requiresAdvice)->toBeTrue();
});

it('falls back to category slug when definition_key is absent', function (): void {
    $dto = (new EstateRecommendationAdapter)->toStrategyRecommendation([
        'category' => 'Beneficiaries',
        'title' => 'Review Beneficiary Designations',
        'description' => 'Review your beneficiary nominations.',
        'impact' => 'Low',
    ]);

    expect($dto->type)->toBe('register_lpa')
        ->and($dto->priority)->toBe('low')
        ->and($dto->extra)->not->toHaveKey('definition_key');
});

it('falls back to slugified type when category is entirely unmapped', function (): void {
    $dto = (new EstateRecommendationAdapter)->toStrategyRecommendation([
        'category' => 'Pension Amendment',
        'title' => 'Review pension nominations',
        'description' => 'Ensure pension nominations are current.',
        'impact' => 'Medium',
    ]);

    expect($dto->type)->toBe('pension_amendment')
        ->and($dto->category)->toBe('lifecycle');
});
