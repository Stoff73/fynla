<?php

declare(strict_types=1);

use App\Services\Documents\FieldMappers\PropertyMapper;

it('maps extracted property fields to Property model fields', function () {
    $mapper = new PropertyMapper;

    $extracted = [
        'address' => '14 Oakwood Drive, Manchester',
        'current_value' => '£850,000',
        'property_type' => 'Main Residence',
        'ownership_type' => 'Joint',
        'rental_income_monthly' => null,
        'mortgage_outstanding' => '280000',
    ];

    $mapped = $mapper->map($extracted);

    expect($mapped['address'])->toBe('14 Oakwood Drive, Manchester');
    expect($mapped['current_value'])->toBe(850000.0);
    expect($mapped['property_type'])->toBe('main_residence');
    expect($mapped['ownership_type'])->toBe('joint');
    expect($mapped['mortgage_outstanding'])->toBe(280000.0);
    expect($mapped)->not->toHaveKey('rental_income_monthly');
});

it('validates required property fields', function () {
    $mapper = new PropertyMapper;
    $errors = $mapper->validate(['ownership_type' => 'individual']);
    expect($errors)->toHaveKey('address');
    expect($errors)->toHaveKey('current_value');
});

it('passes validation with required fields present', function () {
    $mapper = new PropertyMapper;
    $errors = $mapper->validate(['address' => '10 High St', 'current_value' => 500000]);
    expect($errors)->toBeEmpty();
});
