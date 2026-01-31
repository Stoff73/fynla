<?php

declare(strict_types=1);

use App\Models\CriticalIllnessPolicy;
use App\Models\DCPension;
use App\Models\Estate\Liability;
use App\Models\IncomeProtectionPolicy;
use App\Models\LifeInsurancePolicy;
use App\Models\Property;
use App\Models\TaxConfiguration;
use App\Models\User;
use App\Services\Benefits\ChildBenefitService;
use App\Services\Shared\CrossModuleAssetAggregator;
use App\Services\TaxConfigService;
use App\Services\UKTaxCalculator;
use App\Services\UserProfile\UserProfileService;

beforeEach(function () {
    // Ensure active tax configuration exists
    if (! TaxConfiguration::where('is_active', true)->exists()) {
        TaxConfiguration::factory()->create(['is_active' => true]);
    }

    $this->assetAggregator = new CrossModuleAssetAggregator;
    $taxConfigService = app(TaxConfigService::class);
    $this->taxCalculator = new UKTaxCalculator($taxConfigService);
    $this->childBenefitService = new ChildBenefitService($taxConfigService);
    $this->service = new UserProfileService($this->assetAggregator, $this->taxCalculator, $this->childBenefitService);
    $this->user = User::factory()->create();
});

// =============================================================================
// BASIC FUNCTIONALITY TESTS
// =============================================================================

test('returns empty commitments for user with no assets', function () {
    $result = $this->service->getFinancialCommitments($this->user);

    expect($result['commitments'])->toBeArray()
        ->and($result['commitments']['retirement'])->toBeEmpty()
        ->and($result['commitments']['properties'])->toBeEmpty()
        ->and($result['commitments']['protection'])->toBeEmpty()
        ->and($result['commitments']['liabilities'])->toBeEmpty()
        ->and($result['totals']['total'])->toBe(0);
});

test('includes structure with all commitment types', function () {
    $result = $this->service->getFinancialCommitments($this->user);

    expect($result)->toHaveKeys(['commitments', 'totals'])
        ->and($result['commitments'])->toHaveKeys(['retirement', 'properties', 'protection', 'liabilities'])
        ->and($result['totals'])->toHaveKeys(['retirement', 'properties', 'protection', 'liabilities', 'total']);
});

// =============================================================================
// DC PENSION CONTRIBUTION TESTS
// =============================================================================

test('calculates individual DC pension contribution correctly', function () {
    DCPension::factory()->create([
        'user_id' => $this->user->id,
        'scheme_name' => 'Workplace Pension',
        'monthly_contribution_amount' => 300.00,
    ]);

    $result = $this->service->getFinancialCommitments($this->user);

    expect($result['commitments']['retirement'])->toHaveCount(1)
        ->and($result['commitments']['retirement'][0])->toMatchArray([
            'name' => 'Workplace Pension',
            'monthly_amount' => 300.00,
            'is_joint' => false,
        ])
        ->and($result['totals']['retirement'])->toBe(300.00)
        ->and($result['totals']['total'])->toBe(300.00);
});

test('DC pensions are always individual (never joint)', function () {
    $spouse = User::factory()->create();
    $this->user->update(['spouse_id' => $spouse->id]);

    DCPension::factory()->create([
        'user_id' => $this->user->id,
        'scheme_name' => 'User Pension',
        'monthly_contribution_amount' => 600.00,
    ]);

    $result = $this->service->getFinancialCommitments($this->user);

    // DC pensions are always individual - full amount, not split
    expect($result['commitments']['retirement'])->toHaveCount(1)
        ->and($result['commitments']['retirement'][0])->toMatchArray([
            'name' => 'User Pension',
            'monthly_amount' => 600.00, // Full amount - DC pensions are individual
            'is_joint' => false,
        ])
        ->and($result['totals']['retirement'])->toBe(600.00);
});

test('excludes DC pensions with zero contributions', function () {
    DCPension::factory()->create([
        'user_id' => $this->user->id,
        'scheme_name' => 'Inactive Pension',
        'monthly_contribution_amount' => 0.00,
    ]);

    $result = $this->service->getFinancialCommitments($this->user);

    expect($result['commitments']['retirement'])->toBeEmpty()
        ->and($result['totals']['retirement'])->toBe(0);
});

test('handles multiple DC pensions correctly', function () {
    DCPension::factory()->create([
        'user_id' => $this->user->id,
        'scheme_name' => 'Workplace Pension',
        'monthly_contribution_amount' => 300.00,
    ]);

    DCPension::factory()->create([
        'user_id' => $this->user->id,
        'scheme_name' => 'SIPP',
        'monthly_contribution_amount' => 500.00,
    ]);

    $result = $this->service->getFinancialCommitments($this->user);

    expect($result['commitments']['retirement'])->toHaveCount(2)
        ->and($result['totals']['retirement'])->toBe(800.00);
});

// =============================================================================
// PROPERTY EXPENSE TESTS
// =============================================================================

test('calculates individual property expenses correctly', function () {
    Property::factory()->create([
        'user_id' => $this->user->id,
        'address_line_1' => '15 Amherst Place',
        'ownership_type' => 'individual',
        'monthly_council_tax' => 200.00,
        'monthly_gas' => 80.00,
        'monthly_electricity' => 95.00,
        'monthly_water' => 40.00,
        'monthly_building_insurance' => 50.00,
        'monthly_contents_insurance' => 30.00,
        'monthly_service_charge' => 0.00,
        'monthly_maintenance_reserve' => 0.00,
        'other_monthly_costs' => 0.00,
    ]);

    // Create mortgage for the property
    \App\Models\Mortgage::factory()->create([
        'property_id' => Property::first()->id,
        'user_id' => $this->user->id,
        'monthly_payment' => 450.00,
    ]);

    $result = $this->service->getFinancialCommitments($this->user);

    expect($result['commitments']['properties'])->toHaveCount(1)
        ->and($result['commitments']['properties'][0])->toMatchArray([
            'name' => '15 Amherst Place',
            'monthly_amount' => 945.00, // 450 + 200 + 80 + 95 + 40 + 50 + 30
            'is_joint' => false,
        ])
        // Breakdown only includes keys with non-zero values
        ->and($result['commitments']['properties'][0]['breakdown'])->toHaveKey('mortgage', 450.00)
        ->and($result['commitments']['properties'][0]['breakdown'])->toHaveKey('council_tax', 200.00)
        ->and($result['commitments']['properties'][0]['breakdown'])->toHaveKey('utilities', 215.00)
        ->and($result['commitments']['properties'][0]['breakdown'])->toHaveKey('insurance', 80.00)
        ->and($result['totals']['properties'])->toBe(945.00);
});

test('joint property expenses use database values directly (already users share)', function () {
    $spouse = User::factory()->create();
    $this->user->update(['spouse_id' => $spouse->id]);

    // Database values ARE ALREADY the user's share - no splitting at service level
    // User owns 50% of joint property, so council_tax 200 is their share
    Property::factory()->create([
        'user_id' => $this->user->id,
        'address_line_1' => 'Joint Property',
        'ownership_type' => 'joint',
        'ownership_percentage' => 50.00,
        'joint_owner_id' => $spouse->id,
        'monthly_council_tax' => 200.00, // User's 50% share stored directly
        'monthly_gas' => 0.00,
        'monthly_electricity' => 0.00,
        'monthly_water' => 0.00,
        'monthly_building_insurance' => 0.00,
        'monthly_contents_insurance' => 0.00,
    ]);

    \App\Models\Mortgage::factory()->create([
        'property_id' => Property::first()->id,
        'user_id' => $this->user->id,
        'monthly_payment' => 450.00, // User's 50% share stored directly
        'ownership_type' => 'joint',
    ]);

    $result = $this->service->getFinancialCommitments($this->user);

    // Service uses values directly from database (already user's share)
    // User's share: 450 (mortgage) + 200 (council tax) = 650
    expect($result['commitments']['properties'])->toHaveCount(1)
        ->and($result['commitments']['properties'][0])->toMatchArray([
            'monthly_amount' => 650.00, // Values from DB are already user's share
            'is_joint' => true,
        ])
        ->and($result['totals']['properties'])->toBe(650.00);
});

test('handles property without mortgage', function () {
    Property::factory()->create([
        'user_id' => $this->user->id,
        'address_line_1' => 'Unmortgaged Property',
        'ownership_type' => 'individual',
        'monthly_council_tax' => 150.00,
        'monthly_gas' => 60.00,
        'monthly_electricity' => 70.00,
        'monthly_water' => 30.00,
    ]);

    $result = $this->service->getFinancialCommitments($this->user);

    expect($result['commitments']['properties'])->toHaveCount(1)
        ->and($result['commitments']['properties'][0])->toMatchArray([
            'monthly_amount' => 310.00, // Just utilities and council tax
        ])
        // Breakdown won't have 'mortgage' key when no mortgage exists
        ->and($result['commitments']['properties'][0]['breakdown'])->not->toHaveKey('mortgage');
});

test('uses first mortgage on property (service takes first mortgage only)', function () {
    $property = Property::factory()->create([
        'user_id' => $this->user->id,
        'address_line_1' => 'Property with Mortgage',
        'monthly_council_tax' => 200.00,
    ]);

    // Service only takes first mortgage
    \App\Models\Mortgage::factory()->create([
        'property_id' => $property->id,
        'user_id' => $this->user->id,
        'monthly_payment' => 800.00,
    ]);

    $result = $this->service->getFinancialCommitments($this->user);

    // Service uses first mortgage only (model casts to decimal string)
    expect((float) $result['commitments']['properties'][0]['breakdown']['mortgage'])->toBe(800.00)
        ->and($result['commitments']['properties'][0]['monthly_amount'])->toBe(1000.00); // 800 + 200 council tax
});

// =============================================================================
// LIFE INSURANCE PREMIUM TESTS
// =============================================================================

test('converts monthly life insurance premium correctly', function () {
    LifeInsurancePolicy::factory()->create([
        'user_id' => $this->user->id,
        'premium_amount' => 150.00,
        'premium_frequency' => 'monthly',
    ]);

    $result = $this->service->getFinancialCommitments($this->user);

    expect($result['commitments']['protection'])->toHaveCount(1)
        ->and($result['commitments']['protection'][0])->toMatchArray([
            'name' => 'Life Insurance', // Fallback name used
            'type' => 'life_insurance',
            'monthly_amount' => 150.00,
            'is_joint' => false,
        ])
        ->and($result['totals']['protection'])->toBe(150.00);
});

test('converts quarterly premium to monthly', function () {
    LifeInsurancePolicy::factory()->create([
        'user_id' => $this->user->id,
        'premium_amount' => 450.00,
        'premium_frequency' => 'quarterly',
    ]);

    $result = $this->service->getFinancialCommitments($this->user);

    expect($result['commitments']['protection'][0]['monthly_amount'])->toBe(150.00); // 450 / 3
});

test('converts annual premium to monthly', function () {
    LifeInsurancePolicy::factory()->create([
        'user_id' => $this->user->id,
        'premium_amount' => 1800.00,
        'premium_frequency' => 'annually',
    ]);

    $result = $this->service->getFinancialCommitments($this->user);

    expect($result['commitments']['protection'][0]['monthly_amount'])->toBe(150.00); // 1800 / 12
});

test('life insurance is always individual (never joint)', function () {
    $spouse = User::factory()->create();
    $this->user->update(['spouse_id' => $spouse->id]);

    LifeInsurancePolicy::factory()->create([
        'user_id' => $this->user->id,
        'premium_amount' => 300.00,
        'premium_frequency' => 'monthly',
    ]);

    $result = $this->service->getFinancialCommitments($this->user);

    // Life insurance is always individual - full premium amount
    expect($result['commitments']['protection'][0])->toMatchArray([
        'monthly_amount' => 300.00, // Full amount - protection is individual
        'is_joint' => false,
    ]);
});

// =============================================================================
// CRITICAL ILLNESS PREMIUM TESTS
// =============================================================================

test('calculates critical illness premium correctly', function () {
    CriticalIllnessPolicy::factory()->create([
        'user_id' => $this->user->id,
        'premium_amount' => 80.00,
        'premium_frequency' => 'monthly',
    ]);

    $result = $this->service->getFinancialCommitments($this->user);

    expect($result['commitments']['protection'])->toHaveCount(1)
        ->and($result['commitments']['protection'][0])->toMatchArray([
            'name' => 'Critical Illness', // Fallback name used
            'type' => 'critical_illness',
            'monthly_amount' => 80.00,
        ]);
});

// =============================================================================
// INCOME PROTECTION PREMIUM TESTS
// =============================================================================

test('calculates income protection premium correctly', function () {
    IncomeProtectionPolicy::factory()->create([
        'user_id' => $this->user->id,
        'premium_amount' => 120.00,
        'premium_frequency' => 'monthly',
    ]);

    $result = $this->service->getFinancialCommitments($this->user);

    expect($result['commitments']['protection'])->toHaveCount(1)
        ->and($result['commitments']['protection'][0])->toMatchArray([
            'name' => 'Income Protection', // Fallback name used
            'type' => 'income_protection',
            'monthly_amount' => 120.00,
        ]);
});

test('aggregates multiple protection policies', function () {
    LifeInsurancePolicy::factory()->create([
        'user_id' => $this->user->id,
        'premium_amount' => 150.00,
        'premium_frequency' => 'monthly',
    ]);

    CriticalIllnessPolicy::factory()->create([
        'user_id' => $this->user->id,
        'premium_amount' => 80.00,
        'premium_frequency' => 'monthly',
    ]);

    IncomeProtectionPolicy::factory()->create([
        'user_id' => $this->user->id,
        'premium_amount' => 120.00,
        'premium_frequency' => 'monthly',
    ]);

    $result = $this->service->getFinancialCommitments($this->user);

    expect($result['commitments']['protection'])->toHaveCount(3)
        ->and($result['totals']['protection'])->toBe(350.00);
});

// =============================================================================
// LIABILITY REPAYMENT TESTS
// =============================================================================

test('calculates individual liability repayment correctly', function () {
    Liability::factory()->create([
        'user_id' => $this->user->id,
        'liability_name' => 'Personal Loan',
        'liability_type' => 'personal_loan',
        'current_balance' => 10000.00,
        'monthly_payment' => 250.00,
    ]);

    $result = $this->service->getFinancialCommitments($this->user);

    expect($result['commitments']['liabilities'])->toHaveCount(1)
        ->and($result['commitments']['liabilities'][0])->toMatchArray([
            'name' => 'Personal Loan',
            'monthly_amount' => 250.00,
            'is_joint' => false,
        ])
        ->and($result['totals']['liabilities'])->toBe(250.00);
});

test('splits joint liability repayment 50/50', function () {
    $spouse = User::factory()->create();
    $this->user->update(['spouse_id' => $spouse->id]);

    Liability::factory()->create([
        'user_id' => $this->user->id,
        'liability_name' => 'Joint Car Loan',
        'liability_type' => 'personal_loan', // Use valid type from enum
        'ownership_type' => 'joint',
        'current_balance' => 15000.00,
        'monthly_payment' => 400.00,
    ]);

    $result = $this->service->getFinancialCommitments($this->user);

    // Service DOES split joint liabilities by 50%
    expect($result['commitments']['liabilities'][0])->toMatchArray([
        'monthly_amount' => 200.00, // 50% of 400
        'is_joint' => true,
    ]);
});

// =============================================================================
// COMPREHENSIVE INTEGRATION TESTS
// =============================================================================

test('calculates total commitments across all categories', function () {
    // DC Pension
    DCPension::factory()->create([
        'user_id' => $this->user->id,
        'monthly_contribution_amount' => 300.00,
    ]);

    // Property
    $property = Property::factory()->create([
        'user_id' => $this->user->id,
        'monthly_council_tax' => 200.00,
    ]);
    \App\Models\Mortgage::factory()->create([
        'property_id' => $property->id,
        'user_id' => $this->user->id,
        'monthly_payment' => 800.00,
    ]);

    // Life Insurance
    LifeInsurancePolicy::factory()->create([
        'user_id' => $this->user->id,
        'premium_amount' => 150.00,
        'premium_frequency' => 'monthly',
    ]);

    // Liability (non-mortgage - mortgages are excluded from liabilities)
    Liability::factory()->create([
        'user_id' => $this->user->id,
        'liability_type' => 'personal_loan',
        'monthly_payment' => 250.00,
    ]);

    $result = $this->service->getFinancialCommitments($this->user);

    expect($result['totals']['retirement'])->toBe(300.00)
        ->and($result['totals']['properties'])->toBe(1000.00)
        ->and($result['totals']['protection'])->toBe(150.00)
        ->and($result['totals']['liabilities'])->toBe(250.00)
        ->and($result['totals']['total'])->toBe(1700.00);
});

test('joint property uses database values directly (already users share)', function () {
    $spouse = User::factory()->create();
    $this->user->update(['spouse_id' => $spouse->id]);

    // Database values ARE ALREADY user's share - no splitting at service level
    $property = Property::factory()->create([
        'user_id' => $this->user->id,
        'ownership_type' => 'joint',
        'ownership_percentage' => 50.00,
        'joint_owner_id' => $spouse->id,
        'monthly_council_tax' => 200.00, // User's 50% share stored directly
    ]);
    \App\Models\Mortgage::factory()->create([
        'property_id' => $property->id,
        'user_id' => $this->user->id,
        'ownership_type' => 'joint',
        'monthly_payment' => 800.00, // User's 50% share stored directly
    ]);

    $userCommitments = $this->service->getFinancialCommitments($this->user);

    // Service uses values directly from database (already user's share)
    // User's share: 800 (mortgage) + 200 (council tax) = 1000
    expect($userCommitments['commitments']['properties'][0])->toMatchArray([
        'monthly_amount' => 1000.00,
        'is_joint' => true,
    ])
        ->and($userCommitments['totals']['total'])->toBe(1000.00);
});

test('handles mixed individual and joint commitments correctly', function () {
    $spouse = User::factory()->create();
    $this->user->update(['spouse_id' => $spouse->id]);

    // User's individual DC pension (always individual)
    DCPension::factory()->create([
        'user_id' => $this->user->id,
        'monthly_contribution_amount' => 300.00,
    ]);

    // Joint property - values stored as user's share
    $property = Property::factory()->create([
        'user_id' => $this->user->id,
        'ownership_type' => 'joint',
        'ownership_percentage' => 50.00,
        'joint_owner_id' => $spouse->id,
        'monthly_council_tax' => 200.00, // User's 50% share
    ]);
    \App\Models\Mortgage::factory()->create([
        'property_id' => $property->id,
        'user_id' => $this->user->id,
        'ownership_type' => 'joint',
        'monthly_payment' => 800.00, // User's 50% share
    ]);

    // User's individual liability
    Liability::factory()->create([
        'user_id' => $this->user->id,
        'liability_type' => 'personal_loan',
        'ownership_type' => 'individual',
        'monthly_payment' => 200.00,
    ]);

    $result = $this->service->getFinancialCommitments($this->user);

    // Breakdown:
    // - Individual pension: 300 (full amount)
    // - Joint property: 1000 (values already user's share in DB)
    // - Individual liability: 200 (full amount)
    // Total: 1500

    expect($result['totals']['retirement'])->toBe(300.00)
        ->and($result['totals']['properties'])->toBe(1000.00)
        ->and($result['totals']['liabilities'])->toBe(200.00)
        ->and($result['totals']['total'])->toBe(1500.00);

    // Verify is_joint flags
    expect($result['commitments']['retirement'][0]['is_joint'])->toBeFalse()
        ->and($result['commitments']['properties'][0]['is_joint'])->toBeTrue()
        ->and($result['commitments']['liabilities'][0]['is_joint'])->toBeFalse();
});

// =============================================================================
// EDGE CASE TESTS
// =============================================================================

test('handles null monthly payment values gracefully', function () {
    Liability::factory()->create([
        'user_id' => $this->user->id,
        'liability_name' => 'Credit Card',
        'liability_type' => 'credit_card',
        'monthly_payment' => null, // No fixed payment
    ]);

    $result = $this->service->getFinancialCommitments($this->user);

    // Should not include liabilities without monthly payment
    expect($result['commitments']['liabilities'])->toBeEmpty()
        ->and($result['totals']['liabilities'])->toBe(0);
});

test('handles zero monthly payment values', function () {
    DCPension::factory()->create([
        'user_id' => $this->user->id,
        'monthly_contribution_amount' => 0.00,
    ]);

    $result = $this->service->getFinancialCommitments($this->user);

    // Should not include commitments with zero amount
    expect($result['commitments']['retirement'])->toBeEmpty();
});

test('excludes properties with zero total costs', function () {
    Property::factory()->create([
        'user_id' => $this->user->id,
        'monthly_council_tax' => 0.00,
        'monthly_gas' => 0.00,
        'monthly_electricity' => 0.00,
        'monthly_water' => 0.00,
    ]);
    // No mortgage

    $result = $this->service->getFinancialCommitments($this->user);

    expect($result['commitments']['properties'])->toBeEmpty()
        ->and($result['totals']['properties'])->toBe(0);
});

test('rounds monetary values correctly', function () {
    LifeInsurancePolicy::factory()->create([
        'user_id' => $this->user->id,
        'premium_amount' => 123.456, // Should round
        'premium_frequency' => 'monthly',
    ]);

    $result = $this->service->getFinancialCommitments($this->user);

    // Model casts premium_amount to decimal:2, so verify rounding is correct
    // Database stores as 123.46 (rounded), so service returns this value
    $monthlyAmount = $result['commitments']['protection'][0]['monthly_amount'];
    expect(is_numeric($monthlyAmount))->toBeTrue()
        ->and((float) $monthlyAmount)->toBe(123.46);
});
