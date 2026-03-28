<?php

declare(strict_types=1);

use App\Models\Estate\Asset;
use App\Models\FamilyMember;
use App\Models\ProtectionProfile;
use App\Models\User;
use App\Services\UserProfile\ProfileCompletenessChecker;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->checker = new ProfileCompletenessChecker;
});

describe('ProfileCompletenessChecker - Married Users', function () {
    it('returns 100% score for married user with complete profile', function () {
        // Create spouse first
        $spouse = User::factory()->create();

        // Create married user with complete profile (now includes expenditure fields)
        $user = User::factory()->create([
            'marital_status' => 'married',
            'spouse_id' => $spouse->id,
            'domicile_status' => 'uk_domiciled',
            'country_of_birth' => 'United Kingdom',
            'annual_employment_income' => 50000,
            'monthly_expenditure' => 3000,
            'annual_expenditure' => 36000,
            'liabilities_reviewed' => true,
        ]);

        // Create dependant
        FamilyMember::factory()->create([
            'user_id' => $user->id,
            'name' => 'Child',
            'first_name' => 'Test',
            'last_name' => 'Child',
            'relationship' => 'child',
            'date_of_birth' => now()->subYears(10),
            'is_dependent' => true,
        ]);

        // Create asset
        Asset::create([
            'user_id' => $user->id,
            'asset_name' => 'Property',
            'asset_type' => 'property',
            'current_value' => 300000,
            'valuation_date' => now(),
        ]);

        // Create protection profile and policies
        ProtectionProfile::create([
            'user_id' => $user->id,
            'annual_income' => 50000,
            'monthly_expenditure' => 3000,
            'number_of_dependents' => 1,
        ]);

        // Add at least one policy for protection_plans check
        \App\Models\LifeInsurancePolicy::create([
            'user_id' => $user->id,
            'provider' => 'Test Provider',
            'policy_number' => 'TEST123',
            'policy_type' => 'term',
            'sum_assured' => 100000,
            'premium_amount' => 50,
            'premium_frequency' => 'monthly',
            'policy_start_date' => now()->subYears(1),
            'policy_term_years' => 10,
            'in_trust' => false,
        ]);

        $result = $this->checker->checkCompleteness($user);

        // Now should achieve 100% completeness with expenditure fields
        expect($result['completeness_score'])->toBe(100.0);
        expect($result['is_complete'])->toBeTrue();
        expect($result['is_married'])->toBeTrue();
        // All critical fields should be filled
        expect($result['missing_fields'])->toBeEmpty();
    });

    it('identifies missing spouse link for married user', function () {
        $user = User::factory()->create([
            'marital_status' => 'married',
            'spouse_id' => null, // Missing spouse link
            'domicile_status' => 'uk_domiciled',
            'country_of_birth' => 'United Kingdom',
            'annual_employment_income' => 50000,
        ]);

        FamilyMember::factory()->create([
            'user_id' => $user->id,
            'name' => 'Child',
            'first_name' => 'Test',
            'last_name' => 'Child',
            'relationship' => 'child',
            'date_of_birth' => now()->subYears(10),
            'is_dependent' => true,
        ]);

        Asset::create([
            'user_id' => $user->id,
            'asset_name' => 'Property',
            'asset_type' => 'property',
            'current_value' => 300000,
            'valuation_date' => now(),
        ]);

        ProtectionProfile::create([
            'user_id' => $user->id,
            'annual_income' => 50000,
            'monthly_expenditure' => 3000,
            'number_of_dependents' => 1,
        ]);

        $result = $this->checker->checkCompleteness($user);

        expect($result['completeness_score'])->toBeLessThan(100);
        expect($result['is_complete'])->toBeFalse();
        expect($result['missing_fields'])->toHaveKey('spouse_linked');
        expect($result['missing_fields']['spouse_linked']['priority'])->toBe('high');
        expect($result['recommendations'])->toContain('Link your spouse account for accurate joint financial planning');
    });

    it('identifies missing dependants for single user', function () {
        $user = User::factory()->create([
            'marital_status' => 'single',
            'spouse_id' => null,
            'domicile_status' => 'uk_domiciled',
            'country_of_birth' => 'United Kingdom',
            'annual_employment_income' => 50000,
        ]);

        // No dependants created, no spouse linked

        Asset::create([
            'user_id' => $user->id,
            'asset_name' => 'Property',
            'asset_type' => 'property',
            'current_value' => 300000,
            'valuation_date' => now(),
        ]);

        ProtectionProfile::create([
            'user_id' => $user->id,
            'annual_income' => 50000,
            'monthly_expenditure' => 3000,
            'number_of_dependents' => 0,
        ]);

        $result = $this->checker->checkCompleteness($user);

        expect($result['completeness_score'])->toBeLessThan(100);
        expect($result['missing_fields'])->toHaveKey('dependants');
        expect($result['missing_fields']['dependants']['priority'])->toBe('high');
    });

    it('does not include domicile info in completeness checks', function () {
        $spouse = User::factory()->create();

        $user = User::factory()->create([
            'marital_status' => 'married',
            'spouse_id' => $spouse->id,
            'domicile_status' => null,
            'country_of_birth' => null,
            'annual_employment_income' => 50000,
        ]);

        $result = $this->checker->checkCompleteness($user);

        expect($result['missing_fields'])->not->toHaveKey('domicile_info');
        expect($result['all_checks'])->not->toHaveKey('domicile_info');
    });
});

describe('ProfileCompletenessChecker - Single Users', function () {
    it('returns 100% score for single user with complete profile', function () {
        // Create single user with complete profile (now includes expenditure fields)
        $user = User::factory()->create([
            'marital_status' => 'single',
            'domicile_status' => 'uk_domiciled',
            'country_of_birth' => 'United Kingdom',
            'annual_employment_income' => 50000,
            'monthly_expenditure' => 2500,
            'annual_expenditure' => 30000,
            'liabilities_reviewed' => true,
        ]);

        FamilyMember::factory()->create([
            'user_id' => $user->id,
            'name' => 'Child',
            'first_name' => 'Test',
            'last_name' => 'Child',
            'relationship' => 'child',
            'date_of_birth' => now()->subYears(10),
            'is_dependent' => true,
        ]);

        Asset::create([
            'user_id' => $user->id,
            'asset_name' => 'Property',
            'asset_type' => 'property',
            'current_value' => 300000,
            'valuation_date' => now(),
        ]);

        ProtectionProfile::create([
            'user_id' => $user->id,
            'annual_income' => 50000,
            'monthly_expenditure' => 3000,
            'number_of_dependents' => 1,
        ]);

        // Add at least one policy for protection_plans check
        \App\Models\LifeInsurancePolicy::create([
            'user_id' => $user->id,
            'provider' => 'Test Provider',
            'policy_number' => 'TEST123',
            'policy_type' => 'term',
            'sum_assured' => 100000,
            'premium_amount' => 50,
            'premium_frequency' => 'monthly',
            'policy_start_date' => now()->subYears(1),
            'policy_term_years' => 10,
            'in_trust' => false,
        ]);

        $result = $this->checker->checkCompleteness($user);

        // Now should achieve 100% completeness with expenditure fields
        expect($result['completeness_score'])->toBe(100.0);
        expect($result['is_complete'])->toBeTrue();
        expect($result['is_married'])->toBeFalse();
        expect($result['missing_fields'])->toBeEmpty();
    });

    it('identifies missing income for single user', function () {
        $user = User::factory()->create([
            'marital_status' => 'single',
            'domicile_status' => 'uk_domiciled',
            'country_of_birth' => 'United Kingdom',
            'annual_employment_income' => 0,
            'annual_self_employment_income' => 0,
            'annual_rental_income' => 0,
            'annual_dividend_income' => 0,
            'annual_other_income' => 0,
        ]);

        FamilyMember::factory()->create([
            'user_id' => $user->id,
            'name' => 'Child',
            'first_name' => 'Test',
            'last_name' => 'Child',
            'relationship' => 'child',
            'date_of_birth' => now()->subYears(10),
            'is_dependent' => true,
        ]);

        Asset::create([
            'user_id' => $user->id,
            'asset_name' => 'Property',
            'asset_type' => 'property',
            'current_value' => 300000,
            'valuation_date' => now(),
        ]);

        ProtectionProfile::create([
            'user_id' => $user->id,
            'annual_income' => 50000,
            'monthly_expenditure' => 3000,
            'number_of_dependents' => 1,
        ]);

        $result = $this->checker->checkCompleteness($user);

        expect($result['completeness_score'])->toBeLessThan(100);
        expect($result['missing_fields'])->toHaveKey('income');
        expect($result['missing_fields']['income']['priority'])->toBe('high');
    });

    it('identifies missing assets for single user', function () {
        $user = User::factory()->create([
            'marital_status' => 'single',
            'domicile_status' => 'uk_domiciled',
            'country_of_birth' => 'United Kingdom',
            'annual_employment_income' => 50000,
        ]);

        FamilyMember::factory()->create([
            'user_id' => $user->id,
            'name' => 'Child',
            'first_name' => 'Test',
            'last_name' => 'Child',
            'relationship' => 'child',
            'date_of_birth' => now()->subYears(10),
            'is_dependent' => true,
        ]);

        // No assets created

        ProtectionProfile::create([
            'user_id' => $user->id,
            'annual_income' => 50000,
            'monthly_expenditure' => 3000,
            'number_of_dependents' => 1,
        ]);

        $result = $this->checker->checkCompleteness($user);

        expect($result['completeness_score'])->toBeLessThan(100);
        expect($result['missing_fields'])->toHaveKey('assets');
        expect($result['missing_fields']['assets']['priority'])->toBe('high');
    });

    it('identifies missing protection plans for single user', function () {
        $user = User::factory()->create([
            'marital_status' => 'single',
            'domicile_status' => 'uk_domiciled',
            'country_of_birth' => 'United Kingdom',
            'annual_employment_income' => 50000,
        ]);

        FamilyMember::factory()->create([
            'user_id' => $user->id,
            'name' => 'Child',
            'first_name' => 'Test',
            'last_name' => 'Child',
            'relationship' => 'child',
            'date_of_birth' => now()->subYears(10),
            'is_dependent' => true,
        ]);

        Asset::create([
            'user_id' => $user->id,
            'asset_name' => 'Property',
            'asset_type' => 'property',
            'current_value' => 300000,
            'valuation_date' => now(),
        ]);

        // No protection profile created

        $result = $this->checker->checkCompleteness($user);

        expect($result['completeness_score'])->toBeLessThan(100);
        expect($result['missing_fields'])->toHaveKey('protection_plans');
        expect($result['missing_fields']['protection_plans']['priority'])->toBe('high');
    });
});

describe('ProfileCompletenessChecker - Edge Cases', function () {
    it('handles user with null values gracefully', function () {
        $user = User::factory()->create([
            'marital_status' => null,
            'domicile_status' => null,
            'country_of_birth' => null,
            'annual_employment_income' => null,
        ]);

        $result = $this->checker->checkCompleteness($user);

        expect($result)->toHaveKey('completeness_score');
        expect($result)->toHaveKey('is_complete');
        expect($result)->toHaveKey('missing_fields');
        expect($result['completeness_score'])->toBeLessThan(50);
    });

    it('treats widowed user as single user', function () {
        $user = User::factory()->create([
            'marital_status' => 'widowed',
            'domicile_status' => 'uk_domiciled',
            'country_of_birth' => 'United Kingdom',
            'annual_employment_income' => 50000,
        ]);

        FamilyMember::factory()->create([
            'user_id' => $user->id,
            'name' => 'Child',
            'first_name' => 'Test',
            'last_name' => 'Child',
            'relationship' => 'child',
            'date_of_birth' => now()->subYears(10),
            'is_dependent' => true,
        ]);

        Asset::create([
            'user_id' => $user->id,
            'asset_name' => 'Property',
            'asset_type' => 'property',
            'current_value' => 300000,
            'valuation_date' => now(),
        ]);

        ProtectionProfile::create([
            'user_id' => $user->id,
            'annual_income' => 50000,
            'monthly_expenditure' => 3000,
            'number_of_dependents' => 1,
        ]);

        $result = $this->checker->checkCompleteness($user);

        expect($result['is_married'])->toBeFalse();
        expect($result['missing_fields'])->not->toHaveKey('spouse_linked');
    });

    it('calculates correct score with multiple missing fields', function () {
        $user = User::factory()->create([
            'marital_status' => 'married',
            'spouse_id' => null, // Missing
            'domicile_status' => null, // Missing
            'country_of_birth' => null, // Missing
            'annual_employment_income' => 0, // Missing
        ]);

        // No dependants, assets, or protection profile

        $result = $this->checker->checkCompleteness($user);

        // Should have many missing fields
        expect($result['completeness_score'])->toBeLessThan(30);
        expect($result['missing_fields'])->not->toBeEmpty();
        expect(count($result['missing_fields']))->toBeGreaterThan(3);
    });

    it('generates appropriate recommendations for critical completeness', function () {
        $user = User::factory()->create([
            'marital_status' => 'married',
            'spouse_id' => null,
            'domicile_status' => null,
            'country_of_birth' => null,
            'annual_employment_income' => 0,
        ]);

        $result = $this->checker->checkCompleteness($user);

        expect($result['recommendations'])->toBeArray();
        expect($result['recommendations'])->not->toBeEmpty();
        expect($result['completeness_score'])->toBeLessThan(50);
    });
});
