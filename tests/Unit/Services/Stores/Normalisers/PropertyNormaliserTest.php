<?php

declare(strict_types=1);

use App\Services\Stores\Normalisers\PropertyNormaliser;

it('fromForm strips mortgage_* keys and defaults ownership_percentage to 100 for individual', function () {
    $normaliser = new PropertyNormaliser;
    $canonical = $normaliser->fromForm([
        'property_type' => 'main_residence',
        'ownership_type' => 'individual',
        'current_value' => 500000,
        'mortgage_lender_name' => 'Halifax',
        'mortgage_monthly_payment' => 1200,
        'mortgage_type' => 'repayment',
    ]);

    expect($canonical)->toHaveKey('property_type', 'main_residence');
    expect($canonical)->toHaveKey('ownership_type', 'individual');
    expect($canonical)->toHaveKey('ownership_percentage', 100.00);
    expect($canonical)->toHaveKey('current_value', 500000);
    expect($canonical)->not->toHaveKey('mortgage_lender_name');
    expect($canonical)->not->toHaveKey('mortgage_monthly_payment');
    expect($canonical)->not->toHaveKey('mortgage_type');
});

it('fromForm preserves tenants_in_common ownership_type (property-only)', function () {
    $normaliser = new PropertyNormaliser;
    $canonical = $normaliser->fromForm([
        'ownership_type' => 'tenants_in_common',
        'ownership_percentage' => 60,
        'joint_owner_name' => 'Jane Doe',
    ]);

    expect($canonical['ownership_type'])->toBe('tenants_in_common');
    expect($canonical['ownership_percentage'])->toBe(60);
});

it('fromFyn maps address shorthand to address_line_1 and is_joint to ownership_type', function () {
    $normaliser = new PropertyNormaliser;
    $canonical = $normaliser->fromFyn([
        'address' => '10 Downing Street',
        'value' => 5_000_000,
        'is_joint' => true,
        'property_type' => 'main_residence',
    ]);

    expect($canonical['address_line_1'])->toBe('10 Downing Street');
    expect($canonical['current_value'])->toBe(5_000_000.0);
    expect($canonical['ownership_type'])->toBe('joint');
    expect($canonical['joint_ownership_type'])->toBe('joint_tenancy');
});

it('fromUpload defaults to individual ownership at 100% when extraction omits ownership', function () {
    $normaliser = new PropertyNormaliser;
    $canonical = $normaliser->fromUpload([
        'address_line_1' => '5 Acacia Avenue',
        'current_value' => 350000,
        'property_type' => 'main_residence',
    ]);

    expect($canonical['ownership_type'])->toBe('individual');
    expect($canonical['ownership_percentage'])->toBe(100.00);
});

it('fromFyn preserves trust ownership with trust_name and trust_id', function () {
    $normaliser = new PropertyNormaliser;
    $canonical = $normaliser->fromFyn([
        'address' => 'Trust House',
        'ownership_type' => 'trust',
        'trust_name' => 'Smith Family Trust',
    ]);

    expect($canonical['ownership_type'])->toBe('trust');
    expect($canonical['trust_name'])->toBe('Smith Family Trust');
    expect($canonical['ownership_percentage'])->toBe(100.00);
});

// PR 2 regression coverage — the array_key_exists guards on property_type /
// joint_ownership_type / tenure_type. Pre-PR-2 the normaliser unconditionally
// wrote null for missing keys, which breaches properties.tenure_type
// NOT NULL DEFAULT 'freehold'. Lock the guard so a "tidy-up" can't regress it.

it('fromForm omits tenure_type when input omits it (NOT NULL DEFAULT breaches if written as null)', function () {
    $normaliser = new PropertyNormaliser;
    $canonical = $normaliser->fromForm([
        'property_type' => 'main_residence',
        'ownership_type' => 'individual',
        'current_value' => 500000,
    ]);

    expect($canonical)->not->toHaveKey('tenure_type');
});

it('fromForm omits joint_ownership_type when input omits it', function () {
    $normaliser = new PropertyNormaliser;
    $canonical = $normaliser->fromForm([
        'property_type' => 'main_residence',
        'ownership_type' => 'individual',
    ]);

    expect($canonical)->not->toHaveKey('joint_ownership_type');
});

it('fromForm omits property_type when input omits it', function () {
    $normaliser = new PropertyNormaliser;
    $canonical = $normaliser->fromForm([
        'ownership_type' => 'individual',
        'current_value' => 350000,
    ]);

    expect($canonical)->not->toHaveKey('property_type');
});

it('fromFyn omits tenure_type when input omits it', function () {
    $normaliser = new PropertyNormaliser;
    $canonical = $normaliser->fromFyn([
        'address' => '5 Acacia Avenue',
        'property_type' => 'main_residence',
    ]);

    expect($canonical)->not->toHaveKey('tenure_type');
});

it('fromUpload omits tenure_type when extraction omits it', function () {
    $normaliser = new PropertyNormaliser;
    $canonical = $normaliser->fromUpload([
        'address_line_1' => '5 Acacia Avenue',
        'current_value' => 350000,
        'property_type' => 'main_residence',
    ]);

    expect($canonical)->not->toHaveKey('tenure_type');
});
