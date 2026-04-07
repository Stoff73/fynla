<?php

declare(strict_types=1);

use App\Services\Documents\FieldMappers\ProtectionMapper;

it('maps extracted protection fields to policy model fields', function () {
    $mapper = new ProtectionMapper;

    $extracted = [
        'provider' => 'Aviva',
        'policy_type' => 'Term Life',
        'sum_assured' => '£500,000',
        'premium_amount' => '45.50',
        'premium_frequency' => 'monthly',
        'policy_start_date' => '01/06/2020',
        'term_years' => '25',
    ];

    $mapped = $mapper->map($extracted);

    expect($mapped['provider'])->toBe('Aviva');
    expect($mapped['policy_type'])->toBe('term');
    expect($mapped['sum_assured'])->toBe(500000.0);
    expect($mapped['premium_amount'])->toBe(45.50);
    expect($mapped['premium_frequency'])->toBe('monthly');
    expect($mapped['policy_start_date'])->toBe('2020-06-01');
    expect($mapped['policy_term_years'])->toBe(25);
});

it('detects policy model class from type', function () {
    $mapper = new ProtectionMapper;

    expect($mapper->getModelClassForType('term'))->toBe(\App\Models\LifeInsurancePolicy::class);
    expect($mapper->getModelClassForType('whole_of_life'))->toBe(\App\Models\LifeInsurancePolicy::class);
    expect($mapper->getModelClassForType('critical_illness'))->toBe(\App\Models\CriticalIllnessPolicy::class);
    expect($mapper->getModelClassForType('income_protection'))->toBe(\App\Models\IncomeProtectionPolicy::class);
});

it('validates required protection fields', function () {
    $mapper = new ProtectionMapper;
    $errors = $mapper->validate(['policy_type' => 'term']);
    expect($errors)->toHaveKey('provider');
    expect($errors)->toHaveKey('sum_assured');
});
