<?php

declare(strict_types=1);

use App\Models\Estate\Asset;
use App\Models\FamilyMember;
use App\Models\ProtectionProfile;
use App\Models\User;
use Database\Seeders\TaxConfigurationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(TaxConfigurationSeeder::class);
});

describe('Profile Completeness API', function () {
    it('requires authentication', function () {
        $response = $this->getJson('/api/user/profile/completeness');

        $response->assertStatus(401);
    });

    it('returns completeness data for authenticated user', function () {
        $user = User::factory()->create([
            'marital_status' => 'single',
            'domicile_status' => 'uk_domiciled',
            'country_of_birth' => 'United Kingdom',
            'annual_employment_income' => 50000,
        ]);

        $response = $this->actingAs($user)->getJson('/api/user/profile/completeness');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'completeness_score',
                    'is_complete',
                    'missing_fields',
                    'all_checks',
                    'recommendations',
                    'is_married',
                ],
            ]);

        expect($response->json('success'))->toBeTrue();
        expect($response->json('data.completeness_score'))->toBeInt();
        expect($response->json('data.is_complete'))->toBeBool();
        expect($response->json('data.is_married'))->toBeFalse();
    });

    it('returns correct structure for married user', function () {
        $spouse = User::factory()->create();
        $user = User::factory()->create([
            'marital_status' => 'married',
            'spouse_id' => $spouse->id,
            'domicile_status' => 'uk_domiciled',
            'country_of_birth' => 'United Kingdom',
            'annual_employment_income' => 50000,
        ]);

        $response = $this->actingAs($user)->getJson('/api/user/profile/completeness');

        $response->assertStatus(200);
        expect($response->json('data.is_married'))->toBeTrue();
    });

    it('identifies missing spouse link for married user', function () {
        $user = User::factory()->create([
            'marital_status' => 'married',
            'spouse_id' => null,
            'annual_employment_income' => 50000,
        ]);

        $response = $this->actingAs($user)->getJson('/api/user/profile/completeness');

        $response->assertStatus(200);
        $data = $response->json('data');

        expect($data['completeness_score'])->toBeLessThan(100);
        expect($data['is_complete'])->toBeFalse();
        expect($data['missing_fields'])->toHaveKey('spouse_linked');
        expect($data['recommendations'])->toContain('Link your spouse account for accurate joint financial planning');
    });

    it('identifies missing income', function () {
        $user = User::factory()->create([
            'marital_status' => 'single',
            'annual_employment_income' => 0,
            'annual_self_employment_income' => 0,
            'annual_rental_income' => 0,
            'annual_dividend_income' => 0,
            'annual_other_income' => 0,
        ]);

        $response = $this->actingAs($user)->getJson('/api/user/profile/completeness');

        $response->assertStatus(200);
        $data = $response->json('data');

        expect($data['missing_fields'])->toHaveKey('income');
        expect($data['missing_fields']['income']['priority'])->toBe('high');
    });

    it('identifies missing assets', function () {
        $user = User::factory()->create([
            'marital_status' => 'single',
            'annual_employment_income' => 50000,
        ]);

        $response = $this->actingAs($user)->getJson('/api/user/profile/completeness');

        $response->assertStatus(200);
        $data = $response->json('data');

        expect($data['missing_fields'])->toHaveKey('assets');
        expect($data['missing_fields']['assets']['priority'])->toBe('high');
    });

    it('identifies missing protection plans', function () {
        $user = User::factory()->create([
            'marital_status' => 'single',
            'annual_employment_income' => 50000,
        ]);

        Asset::create([
            'user_id' => $user->id,
            'asset_name' => 'Property',
            'asset_type' => 'property',
            'current_value' => 300000,
            'valuation_date' => now(),
        ]);

        $response = $this->actingAs($user)->getJson('/api/user/profile/completeness');

        $response->assertStatus(200);
        $data = $response->json('data');

        expect($data['missing_fields'])->toHaveKey('protection_plans');
        expect($data['missing_fields']['protection_plans']['priority'])->toBe('high');
    });

    it('shows higher completeness for user with more data', function () {
        $spouse = User::factory()->create();
        $user = User::factory()->create([
            'marital_status' => 'married',
            'spouse_id' => $spouse->id,
            'domicile_status' => 'uk_domiciled',
            'country_of_birth' => 'United Kingdom',
            'annual_employment_income' => 50000,
        ]);

        FamilyMember::create([
            'user_id' => $user->id,
            'name' => 'Child',
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

        $response = $this->actingAs($user)->getJson('/api/user/profile/completeness');

        $response->assertStatus(200);
        $data = $response->json('data');

        // Should have decent completeness with all this data
        expect($data['completeness_score'])->toBeGreaterThanOrEqual(40);
    });

    it('caches completeness results', function () {
        $user = User::factory()->create([
            'marital_status' => 'single',
            'annual_employment_income' => 50000,
        ]);

        Cache::shouldReceive('remember')
            ->once()
            ->with("profile_completeness_{$user->id}", 86400, \Closure::class)
            ->andReturn([
                'completeness_score' => 50,
                'is_complete' => false,
                'missing_fields' => [],
                'all_checks' => [],
                'recommendations' => [],
                'is_married' => false,
            ]);

        $response = $this->actingAs($user)->getJson('/api/user/profile/completeness');

        $response->assertStatus(200);
    });

    it('returns all checks including filled ones', function () {
        $user = User::factory()->create([
            'marital_status' => 'single',
            'domicile_status' => 'uk_domiciled',
            'country_of_birth' => 'United Kingdom',
            'annual_employment_income' => 50000,
        ]);

        $response = $this->actingAs($user)->getJson('/api/user/profile/completeness');

        $response->assertStatus(200);
        $data = $response->json('data');

        // all_checks should include both filled and unfilled checks
        expect($data['all_checks'])->toBeArray();
        expect(count($data['all_checks']))->toBeGreaterThan(0);

        // Each check should have required structure
        foreach ($data['all_checks'] as $check) {
            expect($check)->toHaveKeys(['required', 'filled', 'message', 'priority', 'link']);
        }
    });

    it('generates appropriate recommendations based on missing fields', function () {
        $user = User::factory()->create([
            'marital_status' => 'married',
            'spouse_id' => null, // Missing spouse
            'domicile_status' => null, // Missing domicile
            'annual_employment_income' => 0, // Missing income
        ]);

        $response = $this->actingAs($user)->getJson('/api/user/profile/completeness');

        $response->assertStatus(200);
        $data = $response->json('data');

        expect($data['recommendations'])->toBeArray();
        expect(count($data['recommendations']))->toBeGreaterThan(0);

        // Should include high priority items
        expect($data['recommendations'])->toContain('Link your spouse account for accurate joint financial planning');
        expect($data['recommendations'])->toContain('Add your income details for protection needs calculation');
    });

    it('handles widowed user as single user', function () {
        $user = User::factory()->create([
            'marital_status' => 'widowed',
            'spouse_id' => null,
            'annual_employment_income' => 50000,
        ]);

        $response = $this->actingAs($user)->getJson('/api/user/profile/completeness');

        $response->assertStatus(200);
        $data = $response->json('data');

        expect($data['is_married'])->toBeFalse();
        expect($data['missing_fields'])->not->toHaveKey('spouse_linked');
    });

    it('handles divorced user as single user', function () {
        $user = User::factory()->create([
            'marital_status' => 'divorced',
            'spouse_id' => null,
            'annual_employment_income' => 50000,
        ]);

        $response = $this->actingAs($user)->getJson('/api/user/profile/completeness');

        $response->assertStatus(200);
        $data = $response->json('data');

        expect($data['is_married'])->toBeFalse();
        expect($data['missing_fields'])->not->toHaveKey('spouse_linked');
    });
});
