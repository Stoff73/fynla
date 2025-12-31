<?php

use App\Models\DBPension;
use App\Models\DCPension;
use App\Models\RetirementProfile;
use App\Models\StatePension;
use App\Models\User;
use Database\Seeders\TaxConfigurationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(TaxConfigurationSeeder::class);
});

// Authenticated Tests
describe('Retirement Index Endpoint (Authenticated)', function () {
    beforeEach(function () {
        $this->user = User::factory()->create();
        $this->actingAs($this->user, 'sanctum');
    });

    test('GET /api/retirement returns all retirement data for authenticated user', function () {
        // Create test data
        DCPension::factory()->create(['user_id' => $this->user->id]);
        DBPension::factory()->create(['user_id' => $this->user->id]);
        StatePension::factory()->create(['user_id' => $this->user->id]);
        RetirementProfile::factory()->create(['user_id' => $this->user->id]);

        $response = $this->getJson('/api/retirement');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'dc_pensions',
                    'db_pensions',
                    'state_pension',
                    'profile',
                ],
            ]);
    });

    test('GET /api/retirement returns empty arrays when no data exists', function () {
        $response = $this->getJson('/api/retirement');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'dc_pensions' => [],
                    'db_pensions' => [],
                ],
            ]);
    });
});

describe('Retirement Analysis Endpoint (Authenticated)', function () {
    beforeEach(function () {
        $this->user = User::factory()->create();
        $this->actingAs($this->user, 'sanctum');
    });

    test('POST /api/retirement/analyze performs retirement analysis', function () {
        DCPension::factory()->create([
            'user_id' => $this->user->id,
            'current_fund_value' => 100000,
            'monthly_contribution_amount' => 500,
        ]);

        RetirementProfile::factory()->create([
            'user_id' => $this->user->id,
            'current_age' => 45,
            'target_retirement_age' => 67,
            'target_retirement_income' => 30000,
        ]);

        $response = $this->postJson('/api/retirement/analyze', [
            'growth_rate' => 0.05,
            'inflation_rate' => 0.025,
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'projected_income',
                    'income_gap',
                    'recommendations',
                ],
            ]);
    });

});

describe('Annual Allowance Endpoint (Authenticated)', function () {
    beforeEach(function () {
        $this->user = User::factory()->create();
        $this->actingAs($this->user, 'sanctum');
    });

    test('GET /api/retirement/annual-allowance/{taxYear} returns allowance information', function () {
        $response = $this->getJson('/api/retirement/annual-allowance/2024-25');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'tax_year',
                    'standard_allowance',
                    'available_allowance',
                    'carry_forward',
                ],
            ]);
    });

    test('GET /api/retirement/annual-allowance/{taxYear} calculates tapering for high earners', function () {
        // Create high income scenario
        RetirementProfile::factory()->create([
            'user_id' => $this->user->id,
            'current_annual_salary' => 250000, // High earner
        ]);

        $response = $this->getJson('/api/retirement/annual-allowance/2024-25');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ]);
    });
});

describe('Recommendations Endpoint (Authenticated)', function () {
    beforeEach(function () {
        $this->user = User::factory()->create();
        $this->actingAs($this->user, 'sanctum');
    });

    test('GET /api/retirement/recommendations returns personalized recommendations', function () {
        DCPension::factory()->create(['user_id' => $this->user->id]);
        RetirementProfile::factory()->create(['user_id' => $this->user->id]);

        $response = $this->getJson('/api/retirement/recommendations');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'recommendations',
                ],
            ]);
    });
});

describe('Scenarios Endpoint (Authenticated)', function () {
    beforeEach(function () {
        $this->user = User::factory()->create();
        $this->actingAs($this->user, 'sanctum');
    });

    test('POST /api/retirement/scenarios runs what-if scenarios', function () {
        DCPension::factory()->create([
            'user_id' => $this->user->id,
            'current_fund_value' => 150000,
        ]);

        RetirementProfile::factory()->create([
            'user_id' => $this->user->id,
            'current_age' => 45,
            'target_retirement_age' => 67,
        ]);

        $response = $this->postJson('/api/retirement/scenarios', [
            'scenario_type' => 'contribution_increase',
            'additional_contribution' => 200,
            'years_to_retirement' => 20,
            'growth_rate' => 0.05,
        ]);

        $response->assertStatus(200)
            ->assertJson(['success' => true])
            ->assertJsonStructure([
                'success',
                'data',
            ]);
    });
});

describe('DC Pension CRUD Endpoints (Authenticated)', function () {
    beforeEach(function () {
        $this->user = User::factory()->create();
        $this->actingAs($this->user, 'sanctum');
    });

    test('POST /api/retirement/pensions/dc creates DC pension', function () {
        $pensionData = [
            'scheme_name' => 'Workplace Pension',
            'scheme_type' => 'workplace',
            'provider' => 'Aviva',
            'member_number' => 'WP123456',
            'current_fund_value' => 50000,
            'employee_contribution_percent' => 5,
            'employer_contribution_percent' => 3,
            'monthly_contribution_amount' => 400,
            'annual_salary' => 60000, // REQUIRED for percentage-based contributions
            'investment_strategy' => 'Balanced Growth',
            'platform_fee_percent' => 0.75,
            'retirement_age' => 67,
        ];

        $response = $this->postJson('/api/retirement/pensions/dc', $pensionData);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
            ])
            ->assertJsonPath('data.scheme_name', 'Workplace Pension');

        $this->assertDatabaseHas('dc_pensions', [
            'user_id' => $this->user->id,
            'scheme_name' => 'Workplace Pension',
            'current_fund_value' => 50000,
        ]);
    });

    test('PUT /api/retirement/pensions/dc/{id} updates DC pension', function () {
        $pension = DCPension::factory()->create([
            'user_id' => $this->user->id,
            'current_fund_value' => 50000,
        ]);

        $response = $this->putJson("/api/retirement/pensions/dc/{$pension->id}", [
            'scheme_name' => $pension->scheme_name,
            'scheme_type' => $pension->scheme_type,
            'pension_type' => $pension->pension_type ?? 'occupational',
            'provider' => $pension->provider,
            'current_fund_value' => 60000,
            'retirement_age' => 67,
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.current_fund_value', '60000.00');

        $this->assertDatabaseHas('dc_pensions', [
            'id' => $pension->id,
            'current_fund_value' => 60000,
        ]);
    });

    test('DELETE /api/retirement/pensions/dc/{id} deletes DC pension', function () {
        $pension = DCPension::factory()->create(['user_id' => $this->user->id]);

        $response = $this->deleteJson("/api/retirement/pensions/dc/{$pension->id}");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'DC pension deleted successfully',
            ]);

        $this->assertDatabaseMissing('dc_pensions', [
            'id' => $pension->id,
        ]);
    });

    test('user cannot update another users DC pension', function () {
        $otherUser = User::factory()->create();
        $pension = DCPension::factory()->create(['user_id' => $otherUser->id]);

        $response = $this->putJson("/api/retirement/pensions/dc/{$pension->id}", [
            'scheme_name' => 'Updated Name',
            'pension_type' => 'occupational',
            'current_fund_value' => 100000,
        ]);

        $response->assertStatus(403);
    });

    test('user cannot delete another users DC pension', function () {
        $otherUser = User::factory()->create();
        $pension = DCPension::factory()->create(['user_id' => $otherUser->id]);

        $response = $this->deleteJson("/api/retirement/pensions/dc/{$pension->id}");

        $response->assertStatus(403);
    });
});

describe('DB Pension CRUD Endpoints (Authenticated)', function () {
    beforeEach(function () {
        $this->user = User::factory()->create();
        $this->actingAs($this->user, 'sanctum');
    });

    test('POST /api/retirement/pensions/db creates DB pension', function () {
        $pensionData = [
            'scheme_name' => 'NHS Pension',
            'scheme_type' => 'public_sector',
            'accrued_annual_pension' => 15000,
            'pensionable_service_years' => 20,
            'pensionable_salary' => 45000,
            'normal_retirement_age' => 67,
            'revaluation_method' => 'CPI',
            'spouse_pension_percent' => 50,
            'lump_sum_entitlement' => 45000,
            'inflation_protection' => 'cpi',
        ];

        $response = $this->postJson('/api/retirement/pensions/db', $pensionData);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
            ])
            ->assertJsonPath('data.scheme_name', 'NHS Pension');

        $this->assertDatabaseHas('db_pensions', [
            'user_id' => $this->user->id,
            'scheme_name' => 'NHS Pension',
            'accrued_annual_pension' => 15000,
        ]);
    });

    test('PUT /api/retirement/pensions/db/{id} updates DB pension', function () {
        $pension = DBPension::factory()->create([
            'user_id' => $this->user->id,
            'accrued_annual_pension' => 10000,
        ]);

        $response = $this->putJson("/api/retirement/pensions/db/{$pension->id}", [
            'scheme_name' => $pension->scheme_name,
            'scheme_type' => $pension->scheme_type,
            'accrued_annual_pension' => 12000,
            'pensionable_service_years' => $pension->pensionable_service_years,
            'normal_retirement_age' => 67,
            'inflation_protection' => 'cpi',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.accrued_annual_pension', '12000.00');
    });

    test('DELETE /api/retirement/pensions/db/{id} deletes DB pension', function () {
        $pension = DBPension::factory()->create(['user_id' => $this->user->id]);

        $response = $this->deleteJson("/api/retirement/pensions/db/{$pension->id}");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ]);

        $this->assertDatabaseMissing('db_pensions', [
            'id' => $pension->id,
        ]);
    });

    test('user cannot access another users DB pension', function () {
        $otherUser = User::factory()->create();
        $pension = DBPension::factory()->create(['user_id' => $otherUser->id]);

        $response = $this->deleteJson("/api/retirement/pensions/db/{$pension->id}");

        $response->assertStatus(403);
    });
});

describe('State Pension Endpoint (Authenticated)', function () {
    beforeEach(function () {
        $this->user = User::factory()->create();
        $this->actingAs($this->user, 'sanctum');
    });

    test('POST /api/retirement/state-pension updates state pension', function () {
        StatePension::factory()->create([
            'user_id' => $this->user->id,
            'ni_years_completed' => 25,
        ]);

        $response = $this->postJson('/api/retirement/state-pension', [
            'ni_years_completed' => 30,
            'ni_years_required' => 35,
            'state_pension_age' => 67,
        ]);

        $response->assertStatus(200)
            ->assertJson(['success' => true]);

        $this->assertDatabaseHas('state_pensions', [
            'user_id' => $this->user->id,
            'ni_years_completed' => 30,
        ]);
    });

    test('POST /api/retirement/state-pension creates record if none exists', function () {
        $response = $this->postJson('/api/retirement/state-pension', [
            'ni_years_completed' => 20,
            'ni_years_required' => 35,
            'state_pension_age' => 67,
        ]);

        $response->assertStatus(200)
            ->assertJson(['success' => true]);

        $this->assertDatabaseHas('state_pensions', [
            'user_id' => $this->user->id,
            'ni_years_completed' => 20,
        ]);
    });

    test('POST /api/retirement/state-pension validates input', function () {
        $response = $this->postJson('/api/retirement/state-pension', [
            'ni_years_completed' => 'invalid',
        ]);

        $response->assertStatus(422);
    });
});

// Unauthenticated Tests (No beforeEach authentication)
describe('Retirement API Authentication Requirements', function () {
    test('all endpoints require authentication', function () {
        $endpoints = [
            ['GET', '/api/retirement'],
            ['POST', '/api/retirement/analyze'],
            ['GET', '/api/retirement/recommendations'],
            ['POST', '/api/retirement/scenarios'],
            ['GET', '/api/retirement/annual-allowance/2024-25'],
            ['POST', '/api/retirement/pensions/dc'],
            ['POST', '/api/retirement/pensions/db'],
            ['POST', '/api/retirement/state-pension'],
        ];

        foreach ($endpoints as [$method, $endpoint]) {
            $response = $this->json($method, $endpoint);
            expect($response->status())->toBe(401, "Endpoint $method $endpoint should require authentication");
        }
    });
});

// Authorization Tests (Authenticated but checking cross-user access)
describe('Retirement API Authorization Checks', function () {
    beforeEach(function () {
        $this->user = User::factory()->create();
        $this->actingAs($this->user, 'sanctum');
    });

    test('users cannot access other users data', function () {
        $otherUser = User::factory()->create();

        $dcPension = DCPension::factory()->create(['user_id' => $otherUser->id]);
        $dbPension = DBPension::factory()->create(['user_id' => $otherUser->id]);

        // Try to update other user's pensions (with required fields)
        $this->putJson("/api/retirement/pensions/dc/{$dcPension->id}", [
            'scheme_name' => 'Test Scheme',
            'pension_type' => 'occupational',
            'current_fund_value' => 999999,
        ])->assertStatus(403);

        $this->deleteJson("/api/retirement/pensions/dc/{$dcPension->id}")
            ->assertStatus(403);

        $this->deleteJson("/api/retirement/pensions/db/{$dbPension->id}")
            ->assertStatus(403);
    });
});
