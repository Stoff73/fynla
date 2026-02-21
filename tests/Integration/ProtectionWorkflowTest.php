<?php

declare(strict_types=1);

use App\Models\CriticalIllnessPolicy;
use App\Models\DisabilityPolicy;
use App\Models\IncomeProtectionPolicy;
use App\Models\LifeInsurancePolicy;
use App\Models\ProtectionProfile;
use App\Models\SicknessIllnessPolicy;
use App\Models\User;

describe('Protection Workflow Integration', function () {
    it('completes full protection planning journey', function () {
        // Step 1: Create a new user
        $user = User::factory()->create([
            'first_name' => 'Integration',
            'surname' => 'Test User',
            'email' => 'integration@test.com',
            'date_of_birth' => now()->subYears(35),
        ]);

        // Step 2: User creates protection profile
        $profileData = [
            'annual_income' => 60000,
            'monthly_expenditure' => 3500,
            'mortgage_balance' => 300000,
            'other_debts' => 20000,
            'number_of_dependents' => 2,
            'dependents_ages' => [5, 8],
            'retirement_age' => 67,
            'occupation' => 'Software Engineer',
            'smoker_status' => false,
            'health_status' => 'good',
        ];

        $profileResponse = $this->actingAs($user)->postJson('/api/protection/profile', $profileData);
        $profileResponse->assertStatus(201);

        // Verify profile was created
        $this->assertDatabaseHas('protection_profiles', [
            'user_id' => $user->id,
            'annual_income' => 60000,
        ]);

        // Step 3: User adds life insurance policy
        $lifeData = [
            'policy_type' => 'term',
            'provider' => 'Aviva',
            'policy_number' => 'LI123456',
            'sum_assured' => 400000,
            'premium_amount' => 75,
            'premium_frequency' => 'monthly',
            'policy_start_date' => now()->subYears(3)->format('Y-m-d'),
            'policy_term_years' => 25,
            'in_trust' => true,
        ];

        $lifeResponse = $this->actingAs($user)->postJson('/api/protection/policies/life', $lifeData);
        $lifeResponse->assertStatus(201);

        // Step 4: User adds critical illness policy
        $criticalData = [
            'policy_type' => 'standalone',
            'provider' => 'Legal & General',
            'sum_assured' => 150000,
            'premium_amount' => 85,
            'premium_frequency' => 'monthly',
            'policy_start_date' => now()->subYears(2)->subMonths(6)->format('Y-m-d'),
            'policy_term_years' => 20,
        ];

        $criticalResponse = $this->actingAs($user)->postJson('/api/protection/policies/critical-illness', $criticalData);
        $criticalResponse->assertStatus(201);

        // Step 5: User adds income protection policy
        $incomeData = [
            'provider' => 'Royal London',
            'benefit_amount' => 3000,
            'benefit_frequency' => 'monthly',
            'deferred_period_weeks' => 13,
            'benefit_period_months' => 24,
            'premium_amount' => 55,
            'premium_frequency' => 'monthly',
            'policy_start_date' => now()->subYears(3)->addMonths(2)->format('Y-m-d'),
        ];

        $incomeResponse = $this->actingAs($user)->postJson('/api/protection/policies/income-protection', $incomeData);
        $incomeResponse->assertStatus(201);

        // Step 6: User retrieves all protection data
        $indexResponse = $this->actingAs($user)->getJson('/api/protection');
        $indexResponse->assertStatus(200)
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

        // Verify all policies are returned
        $indexData = $indexResponse->json('data');
        expect($indexData['policies']['life_insurance'])->toHaveCount(1);
        expect($indexData['policies']['critical_illness'])->toHaveCount(1);
        expect($indexData['policies']['income_protection'])->toHaveCount(1);
        expect($indexData['policies']['disability'])->toHaveCount(0);
        expect($indexData['policies']['sickness_illness'])->toHaveCount(0);

        // Step 7: User runs protection analysis
        $analysisResponse = $this->actingAs($user)->postJson('/api/protection/analyze');
        $analysisResponse->assertStatus(200)
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

        $analysisData = $analysisResponse->json('data');

        // Verify analysis contains expected data
        expect($analysisData['adequacy_score'])->toBeGreaterThan(0);
        expect($analysisData['recommendations'])->toBeArray();
        expect($analysisData['scenarios'])->toHaveKey('death');
        expect($analysisData['scenarios'])->toHaveKey('critical_illness');
        expect($analysisData['scenarios'])->toHaveKey('disability');

        // Step 8: User updates life insurance policy
        $lifePolicy = LifeInsurancePolicy::where('user_id', $user->id)->first();
        $updateResponse = $this->actingAs($user)->putJson(
            "/api/protection/policies/life/{$lifePolicy->id}",
            ['sum_assured' => 500000]
        );
        $updateResponse->assertStatus(200);

        $this->assertDatabaseHas('life_insurance_policies', [
            'id' => $lifePolicy->id,
            'sum_assured' => 500000,
        ]);

        // Step 9: User adds disability policy
        $disabilityData = [
            'provider' => 'Vitality',
            'benefit_amount' => 2500,
            'benefit_frequency' => 'monthly',
            'deferred_period_weeks' => 8,
            'premium_amount' => 45,
            'premium_frequency' => 'monthly',
            'policy_start_date' => now()->subYears(2)->format('Y-m-d'),
            'coverage_type' => 'accident_and_sickness',
        ];

        $disabilityResponse = $this->actingAs($user)->postJson('/api/protection/policies/disability', $disabilityData);
        $disabilityResponse->assertStatus(201);

        // Verify disability policy was created in database
        $this->assertDatabaseHas('disability_policies', [
            'user_id' => $user->id,
            'provider' => 'Vitality',
        ]);

        // Step 11: User deletes critical illness policy
        $criticalPolicy = CriticalIllnessPolicy::where('user_id', $user->id)->first();
        $deleteResponse = $this->actingAs($user)->deleteJson("/api/protection/policies/critical-illness/{$criticalPolicy->id}");
        $deleteResponse->assertStatus(200);

        // Step 12: Final verification - policies exist in database
        $this->assertDatabaseHas('life_insurance_policies', ['user_id' => $user->id]);
        $this->assertDatabaseHas('income_protection_policies', ['user_id' => $user->id]);
        $this->assertDatabaseHas('disability_policies', ['user_id' => $user->id]);
        $this->assertDatabaseMissing('critical_illness_policies', ['id' => $criticalPolicy->id]); // Deleted
    });

    it('handles multiple users with isolated data', function () {
        // Create two users
        $user1 = User::factory()->create(['email' => 'user1@test.com']);
        $user2 = User::factory()->create(['email' => 'user2@test.com']);

        // User 1 creates profile and policy
        $profile1 = ProtectionProfile::factory()->create(['user_id' => $user1->id]);
        $policy1 = LifeInsurancePolicy::factory()->create([
            'user_id' => $user1->id,
            'sum_assured' => 300000,
        ]);

        // User 2 creates profile and policy
        $profile2 = ProtectionProfile::factory()->create(['user_id' => $user2->id]);
        $policy2 = LifeInsurancePolicy::factory()->create([
            'user_id' => $user2->id,
            'sum_assured' => 600000,
        ]);

        // User 1 can only see their data
        $user1Response = $this->actingAs($user1)->getJson('/api/protection');
        $user1Response->assertStatus(200);
        $user1Data = $user1Response->json('data');
        expect($user1Data['policies']['life_insurance'])->toHaveCount(1);
        expect((float) $user1Data['policies']['life_insurance'][0]['sum_assured'])->toBe(300000.0);

        // User 2 can only see their data
        $user2Response = $this->actingAs($user2)->getJson('/api/protection');
        $user2Response->assertStatus(200);
        $user2Data = $user2Response->json('data');
        expect($user2Data['policies']['life_insurance'])->toHaveCount(1);
        expect((float) $user2Data['policies']['life_insurance'][0]['sum_assured'])->toBe(600000.0);

        // User 1 cannot delete User 2's policy
        $deleteResponse = $this->actingAs($user1)->deleteJson("/api/protection/policies/life/{$policy2->id}");
        $deleteResponse->assertStatus(404);
        $this->assertDatabaseHas('life_insurance_policies', ['id' => $policy2->id]);
    });

    it('validates required data before analysis', function () {
        // User without profile cannot run analysis
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson('/api/protection/analyze');
        $response->assertStatus(200)
            ->assertJson(['success' => false]);
    });

    it('handles comprehensive policy portfolio', function () {
        $user = User::factory()->create(['date_of_birth' => now()->subYears(40)]);

        // Create profile
        $profile = ProtectionProfile::factory()->create([
            'user_id' => $user->id,
            'annual_income' => 80000,
            'monthly_expenditure' => 4500,
            'mortgage_balance' => 400000,
            'other_debts' => 30000,
            'number_of_dependents' => 3,
            'dependents_ages' => [3, 7, 12],
        ]);

        // Create all policy types
        LifeInsurancePolicy::factory()->create(['user_id' => $user->id, 'sum_assured' => 600000]);
        CriticalIllnessPolicy::factory()->create(['user_id' => $user->id, 'sum_assured' => 200000]);
        IncomeProtectionPolicy::factory()->create(['user_id' => $user->id, 'benefit_amount' => 4000]);
        DisabilityPolicy::factory()->create(['user_id' => $user->id, 'benefit_amount' => 3000]);
        SicknessIllnessPolicy::factory()->create(['user_id' => $user->id, 'benefit_amount' => 50000]);

        // Retrieve all data
        $response = $this->actingAs($user)->getJson('/api/protection');
        $response->assertStatus(200);

        $data = $response->json('data');
        expect($data['policies']['life_insurance'])->toHaveCount(1);
        expect($data['policies']['critical_illness'])->toHaveCount(1);
        expect($data['policies']['income_protection'])->toHaveCount(1);
        expect($data['policies']['disability'])->toHaveCount(1);
        expect($data['policies']['sickness_illness'])->toHaveCount(1);

        // Run comprehensive analysis
        $analysisResponse = $this->actingAs($user)->postJson('/api/protection/analyze');
        $analysisResponse->assertStatus(200);

        $analysisData = $analysisResponse->json('data');
        expect($analysisData['adequacy_score'])->toBeGreaterThan(0);
        expect($analysisData['gaps'])->toHaveKey('gaps_by_category');
        expect($analysisData['gaps']['gaps_by_category'])->toHaveKeys([
            'human_capital_gap',
            'debt_protection_gap',
            'education_funding_gap',
            'income_protection_gap',
        ]);
    });

    it('handles profile updates and re-analysis', function () {
        $user = User::factory()->create(['date_of_birth' => now()->subYears(30)]);

        // Create initial profile
        $profile = ProtectionProfile::factory()->create([
            'user_id' => $user->id,
            'annual_income' => 40000,
            'number_of_dependents' => 0,
        ]);

        LifeInsurancePolicy::factory()->create([
            'user_id' => $user->id,
            'sum_assured' => 200000,
        ]);

        // First analysis
        $firstAnalysis = $this->actingAs($user)->postJson('/api/protection/analyze');
        $firstAnalysis->assertStatus(200);
        $firstScore = $firstAnalysis->json('data.adequacy_score');

        // Update profile (life changes: married, children, higher income)
        $updateResponse = $this->actingAs($user)->postJson('/api/protection/profile', [
            'annual_income' => 70000,
            'monthly_expenditure' => 4000,
            'mortgage_balance' => 350000,
            'other_debts' => 15000,
            'number_of_dependents' => 2,
            'dependents_ages' => [1, 3],
            'retirement_age' => 67,
            'occupation' => 'Senior Software Engineer',
            'smoker_status' => false,
            'health_status' => 'good',
        ]);
        $updateResponse->assertStatus(201);

        // Second analysis (should reflect changed circumstances)
        $secondAnalysis = $this->actingAs($user)->postJson('/api/protection/analyze');
        $secondAnalysis->assertStatus(200);
        $secondScore = $secondAnalysis->json('data.adequacy_score');

        // Score should be different due to changed circumstances
        // (Higher income and more dependents likely decrease adequacy with same coverage)
        expect($secondScore)->not->toBe($firstScore);
    });
});
