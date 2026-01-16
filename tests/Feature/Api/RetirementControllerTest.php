<?php

declare(strict_types=1);

use App\Models\DBPension;
use App\Models\DCPension;
use App\Models\Household;
use App\Models\User;
use Database\Seeders\TaxConfigurationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(TaxConfigurationSeeder::class);

    $this->household = Household::factory()->create();

    $this->user = User::factory()->create([
        'household_id' => $this->household->id,
        'date_of_birth' => now()->subYears(45),
        'target_retirement_age' => 65,
        'annual_employment_income' => 60000,
    ]);

    $this->actingAs($this->user, 'sanctum');
});

describe('GET /api/retirement', function () {
    test('returns retirement dashboard data', function () {
        DCPension::factory()->create([
            'user_id' => $this->user->id,
            'scheme_name' => 'Workplace Pension',
            'current_fund_value' => 150000,
        ]);

        $response = $this->getJson('/api/retirement');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data',
            ]);

        expect($response->json('success'))->toBe(true);
    });

    test('returns empty data when no pensions exist', function () {
        $response = $this->getJson('/api/retirement');

        $response->assertStatus(200);
        expect($response->json('success'))->toBe(true);
    });
});

describe('GET /api/retirement/dc-pensions', function () {
    test('returns all DC pensions for user', function () {
        DCPension::factory()->count(2)->create([
            'user_id' => $this->user->id,
        ]);

        $response = $this->getJson('/api/retirement/dc-pensions');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data',
            ]);

        expect($response->json('data'))->toHaveCount(2);
    });
});

describe('POST /api/retirement/dc-pensions', function () {
    test('creates a new DC pension', function () {
        $data = [
            'scheme_name' => 'New Workplace Pension',
            'provider' => 'Scottish Widows',
            'current_fund_value' => 50000,
            'monthly_contribution_amount' => 500,
            'employer_contribution_amount' => 300,
            'pension_type' => 'workplace',
        ];

        $response = $this->postJson('/api/retirement/dc-pensions', $data);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
            ]);

        $this->assertDatabaseHas('dc_pensions', [
            'user_id' => $this->user->id,
            'scheme_name' => 'New Workplace Pension',
        ]);
    });

    test('validates required fields', function () {
        $response = $this->postJson('/api/retirement/dc-pensions', []);

        $response->assertStatus(422);
    });
});

describe('GET /api/retirement/dc-pensions/{id}', function () {
    test('returns a specific DC pension', function () {
        $pension = DCPension::factory()->create([
            'user_id' => $this->user->id,
            'scheme_name' => 'Test Pension',
        ]);

        $response = $this->getJson("/api/retirement/dc-pensions/{$pension->id}");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ]);

        expect($response->json('data.scheme_name'))->toBe('Test Pension');
    });

    test('returns 404 for non-existent pension', function () {
        $response = $this->getJson('/api/retirement/dc-pensions/99999');

        $response->assertStatus(404);
    });
});

describe('PUT /api/retirement/dc-pensions/{id}', function () {
    test('updates a DC pension', function () {
        $pension = DCPension::factory()->create([
            'user_id' => $this->user->id,
            'scheme_name' => 'Original Name',
            'current_fund_value' => 50000,
        ]);

        $response = $this->putJson("/api/retirement/dc-pensions/{$pension->id}", [
            'scheme_name' => 'Updated Name',
            'current_fund_value' => 55000,
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ]);

        $this->assertDatabaseHas('dc_pensions', [
            'id' => $pension->id,
            'scheme_name' => 'Updated Name',
        ]);
    });
});

describe('DELETE /api/retirement/dc-pensions/{id}', function () {
    test('deletes a DC pension', function () {
        $pension = DCPension::factory()->create([
            'user_id' => $this->user->id,
        ]);

        $response = $this->deleteJson("/api/retirement/dc-pensions/{$pension->id}");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ]);

        $this->assertDatabaseMissing('dc_pensions', [
            'id' => $pension->id,
        ]);
    });
});

describe('GET /api/retirement/db-pensions', function () {
    test('returns all DB pensions for user', function () {
        DBPension::factory()->count(2)->create([
            'user_id' => $this->user->id,
        ]);

        $response = $this->getJson('/api/retirement/db-pensions');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data',
            ]);

        expect($response->json('data'))->toHaveCount(2);
    });
});

describe('POST /api/retirement/db-pensions', function () {
    test('creates a new DB pension', function () {
        $data = [
            'scheme_name' => 'NHS Pension',
            'accrued_annual_pension' => 15000,
            'normal_pension_age' => 67,
            'pension_type' => 'public_sector',
        ];

        $response = $this->postJson('/api/retirement/db-pensions', $data);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
            ]);

        $this->assertDatabaseHas('db_pensions', [
            'user_id' => $this->user->id,
            'scheme_name' => 'NHS Pension',
        ]);
    });
});

describe('GET /api/retirement/projections', function () {
    test('returns retirement projections', function () {
        DCPension::factory()->create([
            'user_id' => $this->user->id,
            'current_fund_value' => 100000,
        ]);

        $response = $this->getJson('/api/retirement/projections');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data',
            ]);
    });
});

describe('GET /api/retirement/state-pension', function () {
    test('returns state pension information', function () {
        $response = $this->getJson('/api/retirement/state-pension');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data',
            ]);
    });
});
