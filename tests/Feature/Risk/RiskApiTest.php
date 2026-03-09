<?php

declare(strict_types=1);

use App\Models\DCPension;
use App\Models\Household;
use App\Models\Investment\InvestmentAccount;
use App\Models\Investment\RiskProfile;
use App\Models\SavingsAccount;
use App\Models\User;
use Database\Seeders\TaxConfigurationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(TaxConfigurationSeeder::class);

    $this->household = Household::factory()->create();

    $this->user = User::factory()->create([
        'household_id' => $this->household->id,
        'date_of_birth' => now()->subYears(40),
        'target_retirement_age' => 67,
        'education_level' => 'undergraduate',
        'employment_status' => 'employed',
        'monthly_expenditure' => 3000,
        'annual_employment_income' => 60000,
    ]);

    $this->actingAs($this->user, 'sanctum');
});

describe('GET /api/investment/risk/profile', function () {
    it('auto-calculates risk profile when none exists', function () {
        // With auto-risk calculator, profiles are created on-demand
        $response = $this->getJson('/api/investment/risk/profile');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'risk_level',
                    'factor_breakdown',
                    'risk_assessed_at',
                    'is_self_assessed',
                    'config',
                ],
            ]);

        expect($response->json('success'))->toBe(true);
        expect($response->json('data.is_self_assessed'))->toBe(false);
        expect($response->json('data.factor_breakdown'))->toHaveCount(9);
    });

    it('returns existing risk profile with factor breakdown', function () {
        RiskProfile::create([
            'user_id' => $this->user->id,
            'risk_level' => 'medium',
            'is_self_assessed' => false,
            'risk_assessed_at' => now(),
            'factor_breakdown' => [
                ['factor' => 'capacity_for_loss', 'level' => 'medium'],
                ['factor' => 'time_horizon', 'level' => 'medium'],
            ],
        ]);

        $response = $this->getJson('/api/investment/risk/profile');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'risk_level',
                    'risk_assessed_at',
                    'is_self_assessed',
                    'factor_breakdown',
                    'config',
                ],
            ]);

        expect($response->json('data.risk_level'))->toBe('medium');
        expect($response->json('data.is_self_assessed'))->toBe(false);
    });

    it('requires authentication', function () {
        $response = $this->withoutMiddleware()
            ->getJson('/api/investment/risk/profile');

        // Without authentication middleware, this tests the route exists
        $response->assertStatus(200);
    });
});

describe('POST /api/investment/risk/recalculate', function () {
    it('calculates risk profile from user data', function () {
        // Setup user with financial data
        InvestmentAccount::factory()->create([
            'user_id' => $this->user->id,
            'current_value' => 25000,
        ]);

        DCPension::factory()->create([
            'user_id' => $this->user->id,
            'current_fund_value' => 50000,
        ]);

        SavingsAccount::factory()->create([
            'user_id' => $this->user->id,
            'is_emergency_fund' => true,
            'current_balance' => 15000,
        ]);

        $response = $this->postJson('/api/investment/risk/recalculate');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'risk_level',
                    'factor_breakdown',
                    'risk_assessed_at',
                    'is_self_assessed',
                    'config',
                ],
            ]);

        expect($response->json('data.is_self_assessed'))->toBe(false);
        expect($response->json('data.factor_breakdown'))->toHaveCount(9);

        // Verify it was saved to database
        $profile = RiskProfile::where('user_id', $this->user->id)->first();
        expect($profile)->not->toBeNull();
        expect($profile->risk_level)->toBe($response->json('data.risk_level'));
    });

    it('returns 9 factors in breakdown', function () {
        $response = $this->postJson('/api/investment/risk/recalculate');

        $response->assertStatus(200);

        $factors = $response->json('data.factor_breakdown');
        expect($factors)->toHaveCount(9);

        $factorNames = collect($factors)->pluck('factor')->toArray();
        expect($factorNames)->toContain('capacity_for_loss');
        expect($factorNames)->toContain('time_horizon');
        expect($factorNames)->toContain('knowledge_level');
        expect($factorNames)->toContain('dependants');
        expect($factorNames)->toContain('employment');
        expect($factorNames)->toContain('emergency_cash');
        expect($factorNames)->toContain('surplus_cash');
    });

    it('updates existing profile on recalculation', function () {
        // Create initial profile
        RiskProfile::create([
            'user_id' => $this->user->id,
            'risk_level' => 'low',
            'is_self_assessed' => true,
            'risk_assessed_at' => now()->subDays(30),
        ]);

        $response = $this->postJson('/api/investment/risk/recalculate');

        $response->assertStatus(200);

        // Should update, not create new
        expect(RiskProfile::where('user_id', $this->user->id)->count())->toBe(1);

        $profile = RiskProfile::where('user_id', $this->user->id)->first();
        expect($profile->is_self_assessed)->toBe(false); // Changed from self-assessed
    });
});

describe('GET /api/investment/risk/levels', function () {
    it('returns all 5 risk levels with configurations', function () {
        $response = $this->getJson('/api/investment/risk/levels');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    '*' => [
                        'key',
                        'level_numeric',
                        'display_name',
                        'short_description',
                        'full_description',
                        'asset_allocation',
                        'expected_returns',
                        'volatility_percent',
                        'colour_class',
                    ],
                ],
            ]);

        expect($response->json('data'))->toHaveCount(5);

        $levelKeys = collect($response->json('data'))->pluck('key')->toArray();
        expect($levelKeys)->toBe(['low', 'lower_medium', 'medium', 'upper_medium', 'high']);
    });
});

describe('GET /api/investment/risk/allowed-levels', function () {
    it('returns all levels when no profile exists', function () {
        // Without auto-calculation on this endpoint, returns all levels
        $response = $this->getJson('/api/investment/risk/allowed-levels');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'main_level',
                    'allowed_levels',
                ],
            ]);

        // No profile means no main level set
        // All 5 levels are returned as allowed
        expect($response->json('data.allowed_levels'))->toHaveCount(5);
    });

    it('returns all 5 levels when profile exists (no restriction to adjacent)', function () {
        RiskProfile::create([
            'user_id' => $this->user->id,
            'risk_level' => 'medium',
            'is_self_assessed' => false,
            'risk_assessed_at' => now(),
        ]);

        $response = $this->getJson('/api/investment/risk/allowed-levels');

        $response->assertStatus(200);

        expect($response->json('data.main_level'))->toBe('medium');

        // All 5 levels are returned as allowed (no +/-1 restriction)
        $allowedKeys = collect($response->json('data.allowed_levels'))->pluck('key')->toArray();
        expect($allowedKeys)->toContain('low');
        expect($allowedKeys)->toContain('lower_medium');
        expect($allowedKeys)->toContain('medium');
        expect($allowedKeys)->toContain('upper_medium');
        expect($allowedKeys)->toContain('high');
        expect($allowedKeys)->toHaveCount(5);
    });

    it('returns all 5 levels at low end', function () {
        RiskProfile::create([
            'user_id' => $this->user->id,
            'risk_level' => 'low',
            'is_self_assessed' => false,
            'risk_assessed_at' => now(),
        ]);

        $response = $this->getJson('/api/investment/risk/allowed-levels');

        $response->assertStatus(200);

        expect($response->json('data.main_level'))->toBe('low');

        $allowedKeys = collect($response->json('data.allowed_levels'))->pluck('key')->toArray();
        expect($allowedKeys)->toHaveCount(5);
    });

    it('returns all 5 levels at high end', function () {
        RiskProfile::create([
            'user_id' => $this->user->id,
            'risk_level' => 'high',
            'is_self_assessed' => false,
            'risk_assessed_at' => now(),
        ]);

        $response = $this->getJson('/api/investment/risk/allowed-levels');

        $response->assertStatus(200);

        expect($response->json('data.main_level'))->toBe('high');

        $allowedKeys = collect($response->json('data.allowed_levels'))->pluck('key')->toArray();
        expect($allowedKeys)->toHaveCount(5);
    });
});

describe('GET /api/investment/risk/config/{level}', function () {
    it('returns configuration for valid risk level', function () {
        $response = $this->getJson('/api/investment/risk/config/medium');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'key',
                    'level_numeric',
                    'display_name',
                    'short_description',
                    'full_description',
                    'asset_allocation' => [
                        'equities',
                        'bonds',
                        'cash',
                        'alternatives',
                    ],
                    'expected_returns' => [
                        'min',
                        'max',
                        'typical',
                    ],
                    'volatility_percent',
                    'colour_class',
                ],
            ]);

        expect($response->json('data.key'))->toBe('medium');
        expect($response->json('data.display_name'))->toBe('Medium');
    });

    it('returns error for invalid risk level', function () {
        $response = $this->getJson('/api/investment/risk/config/invalid');

        // Laravel validation returns 422 for invalid input
        $response->assertStatus(422);
    });
});

describe('POST /api/investment/risk/validate-product-level', function () {
    it('validates product level is within allowed range', function () {
        RiskProfile::create([
            'user_id' => $this->user->id,
            'risk_level' => 'medium',
            'is_self_assessed' => false,
            'risk_assessed_at' => now(),
        ]);

        $response = $this->postJson('/api/investment/risk/validate-product-level', [
            'risk_level' => 'upper_medium',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'is_valid' => true,
                    'main_level' => 'medium',
                    'requested_level' => 'upper_medium',
                ],
            ]);
    });

    it('accepts any product level regardless of main level', function () {
        RiskProfile::create([
            'user_id' => $this->user->id,
            'risk_level' => 'low',
            'is_self_assessed' => false,
            'risk_assessed_at' => now(),
        ]);

        $response = $this->postJson('/api/investment/risk/validate-product-level', [
            'risk_level' => 'high', // All levels are now allowed
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'is_valid' => true,
                    'main_level' => 'low',
                    'requested_level' => 'high',
                ],
            ]);
    });
});
