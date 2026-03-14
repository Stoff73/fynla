<?php

declare(strict_types=1);

use App\Models\CriticalIllnessPolicy;
use App\Models\DisabilityPolicy;
use App\Models\LifeInsurancePolicy;
use App\Models\ProtectionProfile;
use App\Models\SicknessIllnessPolicy;
use App\Models\User;
use Database\Seeders\TaxConfigurationSeeder;

beforeEach(function () {
    $this->seed(TaxConfigurationSeeder::class);
});

describe('Protection API - Authentication', function () {
    it('requires authentication for protection index', function () {
        $response = $this->getJson('/api/protection');
        $response->assertStatus(401);
    });

    it('requires authentication for analyze endpoint', function () {
        $response = $this->postJson('/api/protection/analyze');
        $response->assertStatus(401);
    });
});

describe('Protection API - Index', function () {
    it('returns user protection data', function () {
        $user = User::factory()->create();
        $profile = ProtectionProfile::factory()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user)->getJson('/api/protection');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'profile',
                    'policies' => [
                        'life_insurance',
                        'critical_illness',
                        'income_protection',
                        'disability',
                        'sickness_illness',
                    ],
                ],
            ]);
    });
});

describe('Protection Profile', function () {
    it('creates a protection profile', function () {
        $user = User::factory()->create(['date_of_birth' => now()->subYears(35)]);

        $data = [
            'annual_income' => 50000,
            'monthly_expenditure' => 3000,
            'mortgage_balance' => 250000,
            'other_debts' => 25000,
            'number_of_dependents' => 2,
            'dependents_ages' => [5, 10],
            'retirement_age' => 67,
            'occupation' => 'Software Engineer',
            'smoker_status' => false,
            'health_status' => 'good',
        ];

        $response = $this->actingAs($user)->postJson('/api/protection/profile', $data);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'success',
                'message',
                'data',
            ]);

        $this->assertDatabaseHas('protection_profiles', [
            'user_id' => $user->id,
            'annual_income' => 50000,
        ]);
    });
});

describe('Life Insurance Policies', function () {
    it('creates a life insurance policy', function () {
        $user = User::factory()->create();
        ProtectionProfile::factory()->create(['user_id' => $user->id]);

        $data = [
            'policy_type' => 'term',
            'provider' => 'Test Insurance Co',
            'policy_number' => 'LI123456',
            'sum_assured' => 500000,
            'premium_amount' => 50,
            'premium_frequency' => 'monthly',
            'policy_start_date' => '2024-01-01',
            'policy_term_years' => 25,
            'in_trust' => false,
        ];

        $response = $this->actingAs($user)->postJson('/api/protection/policies/life', $data);

        $response->assertStatus(201);
        $this->assertDatabaseHas('life_insurance_policies', [
            'user_id' => $user->id,
            'provider' => 'Test Insurance Co',
        ]);
    });

    it('updates a life insurance policy', function () {
        $user = User::factory()->create();
        $policy = LifeInsurancePolicy::factory()->create(['user_id' => $user->id]);

        $data = ['sum_assured' => 600000];

        $response = $this->actingAs($user)->putJson("/api/protection/policies/life/{$policy->id}", $data);

        $response->assertStatus(200);
        $this->assertDatabaseHas('life_insurance_policies', [
            'id' => $policy->id,
            'sum_assured' => 600000,
        ]);
    });

    it('deletes a life insurance policy', function () {
        $user = User::factory()->create();
        $policy = LifeInsurancePolicy::factory()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user)->deleteJson("/api/protection/policies/life/{$policy->id}");

        $response->assertStatus(200);
        $this->assertSoftDeleted('life_insurance_policies', ['id' => $policy->id]);
    });

    it('prevents access to other users policies', function () {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();
        $policy = LifeInsurancePolicy::factory()->create(['user_id' => $user1->id]);

        $response = $this->actingAs($user2)->deleteJson("/api/protection/policies/life/{$policy->id}");

        $response->assertStatus(404);
        $this->assertDatabaseHas('life_insurance_policies', ['id' => $policy->id]);
    });
});

describe('Critical Illness Policies', function () {
    it('creates a critical illness policy', function () {
        $user = User::factory()->create();

        $data = [
            'policy_type' => 'standalone',
            'provider' => 'Test Insurance Co',
            'sum_assured' => 100000,
            'premium_amount' => 75,
            'premium_frequency' => 'monthly',
            'policy_start_date' => '2024-01-01',
            'policy_term_years' => 20,
        ];

        $response = $this->actingAs($user)->postJson('/api/protection/policies/critical-illness', $data);

        $response->assertStatus(201);
    });

    it('updates a critical illness policy', function () {
        $user = User::factory()->create();
        $policy = CriticalIllnessPolicy::factory()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user)->putJson("/api/protection/policies/critical-illness/{$policy->id}", [
            'sum_assured' => 150000,
        ]);

        $response->assertStatus(200);
    });

    it('deletes a critical illness policy', function () {
        $user = User::factory()->create();
        $policy = CriticalIllnessPolicy::factory()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user)->deleteJson("/api/protection/policies/critical-illness/{$policy->id}");

        $response->assertStatus(200);
    });
});

describe('Income Protection Policies', function () {
    it('creates an income protection policy', function () {
        $user = User::factory()->create();

        $data = [
            'provider' => 'Test Insurance Co',
            'benefit_amount' => 2500,
            'benefit_frequency' => 'monthly',
            'deferred_period_weeks' => 8,
            'benefit_period_months' => 24,
            'premium_amount' => 50,
            'premium_frequency' => 'monthly',
            'policy_start_date' => '2024-01-01',
        ];

        $response = $this->actingAs($user)->postJson('/api/protection/policies/income-protection', $data);

        $response->assertStatus(201);
    });
});

describe('Disability Policies', function () {
    it('creates a disability policy', function () {
        $user = User::factory()->create();

        $data = [
            'provider' => 'Test Insurance Co',
            'benefit_amount' => 2000,
            'benefit_frequency' => 'monthly',
            'deferred_period_weeks' => 4,
            'premium_amount' => 40,
            'premium_frequency' => 'monthly',
            'policy_start_date' => '2024-01-01',
            'coverage_type' => 'accident_and_sickness',
        ];

        $response = $this->actingAs($user)->postJson('/api/protection/policies/disability', $data);

        $response->assertStatus(201);
    });

    it('updates a disability policy', function () {
        $user = User::factory()->create();
        $policy = DisabilityPolicy::factory()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user)->putJson("/api/protection/policies/disability/{$policy->id}", [
            'benefit_amount' => 2500,
        ]);

        $response->assertStatus(200);
    });
});

describe('Sickness/Illness Policies', function () {
    it('creates a sickness/illness policy', function () {
        $user = User::factory()->create();

        $data = [
            'provider' => 'Test Insurance Co',
            'benefit_amount' => 50000,
            'benefit_frequency' => 'lump_sum',
            'premium_amount' => 30,
            'premium_frequency' => 'monthly',
            'policy_start_date' => '2024-01-01',
            'conditions_covered' => ['Cancer', 'Heart Attack'],
        ];

        $response = $this->actingAs($user)->postJson('/api/protection/policies/sickness-illness', $data);

        $response->assertStatus(201);
    });

    it('updates a sickness/illness policy', function () {
        $user = User::factory()->create();
        $policy = SicknessIllnessPolicy::factory()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user)->putJson("/api/protection/policies/sickness-illness/{$policy->id}", [
            'benefit_amount' => 75000,
        ]);

        $response->assertStatus(200);
    });

    it('deletes a sickness/illness policy', function () {
        $user = User::factory()->create();
        $policy = SicknessIllnessPolicy::factory()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user)->deleteJson("/api/protection/policies/sickness-illness/{$policy->id}");

        $response->assertStatus(200);
    });
});

describe('Protection Analysis', function () {
    it('analyzes user protection coverage', function () {
        $user = User::factory()->create(['date_of_birth' => now()->subYears(35)]);
        $profile = ProtectionProfile::factory()->create([
            'user_id' => $user->id,
            'annual_income' => 50000,
            'monthly_expenditure' => 3000,
            'mortgage_balance' => 250000,
            'other_debts' => 25000,
            'number_of_dependents' => 2,
            'dependents_ages' => [5, 10],
            'retirement_age' => 67,
        ]);

        LifeInsurancePolicy::factory()->create([
            'user_id' => $user->id,
            'sum_assured' => 300000,
        ]);

        $response = $this->actingAs($user)->postJson('/api/protection/analyze');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'profile',
                    'needs',
                    'coverage',
                    'gaps',
                    'adequacy_score',
                    'recommendations',
                    'scenarios',
                    'policies',
                ],
            ]);
    });

    it('requires protection profile for analysis', function () {
        $user = User::factory()->create([
            'date_of_birth' => now()->subYears(35),
            'annual_employment_income' => 50000,
        ]);

        $response = $this->actingAs($user)->postJson('/api/protection/analyze');

        $response->assertStatus(200)
            ->assertJson([
                'success' => false,
            ]);
    });
});

describe('Validation', function () {
    it('validates life insurance policy creation', function () {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson('/api/protection/policies/life', [
            'sum_assured' => -1000, // Invalid - must be >= 0
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['sum_assured']);
    });

    it('validates protection profile creation', function () {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson('/api/protection/profile', [
            'annual_income' => 'invalid', // Should be numeric
            'number_of_dependents' => -1, // Should be >= 0
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['annual_income', 'number_of_dependents']);
    });
});
