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
    it('returns retirement dashboard data', function () {
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

    it('returns empty data when no pensions exist', function () {
        $response = $this->getJson('/api/retirement');

        $response->assertStatus(200);
        expect($response->json('success'))->toBe(true);
    });

    it('includes dc_pensions and db_pensions in response', function () {
        DCPension::factory()->create(['user_id' => $this->user->id]);
        DBPension::factory()->create(['user_id' => $this->user->id]);

        $response = $this->getJson('/api/retirement');

        $response->assertStatus(200);
        expect($response->json('data.dc_pensions'))->toHaveCount(1);
        expect($response->json('data.db_pensions'))->toHaveCount(1);
    });
});

describe('POST /api/retirement/pensions/dc', function () {
    it('creates a new DC pension', function () {
        $data = [
            'scheme_name' => 'New Workplace Pension',
            'provider' => 'Scottish Widows',
            'current_fund_value' => 50000,
            'monthly_contribution_amount' => 500,
            'employer_contribution_amount' => 300,
            'pension_type' => 'occupational',
        ];

        $response = $this->postJson('/api/retirement/pensions/dc', $data);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
            ]);

        $this->assertDatabaseHas('dc_pensions', [
            'user_id' => $this->user->id,
            'scheme_name' => 'New Workplace Pension',
        ]);
    });

    it('accepts empty request with defaults', function () {
        // DC pension allows creation with minimal/no data
        $response = $this->postJson('/api/retirement/pensions/dc', []);

        $response->assertStatus(201);
        expect(\App\Models\DCPension::where('user_id', $this->user->id)->count())->toBe(1);
    });
});

describe('PUT /api/retirement/pensions/dc/{id}', function () {
    it('updates a DC pension', function () {
        $pension = DCPension::factory()->create([
            'user_id' => $this->user->id,
            'scheme_name' => 'Original Name',
            'current_fund_value' => 50000,
        ]);

        $response = $this->putJson("/api/retirement/pensions/dc/{$pension->id}", [
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

describe('DELETE /api/retirement/pensions/dc/{id}', function () {
    it('deletes a DC pension', function () {
        $pension = DCPension::factory()->create([
            'user_id' => $this->user->id,
        ]);

        $response = $this->deleteJson("/api/retirement/pensions/dc/{$pension->id}");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ]);

        $this->assertSoftDeleted('dc_pensions', [
            'id' => $pension->id,
        ]);
    });
});

describe('POST /api/retirement/pensions/db', function () {
    it('creates a new DB pension', function () {
        $data = [
            'scheme_name' => 'NHS Pension',
            'accrued_annual_pension' => 15000,
            'normal_pension_age' => 67,
            'pension_type' => 'public_sector',
        ];

        $response = $this->postJson('/api/retirement/pensions/db', $data);

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
    it('returns retirement projections', function () {
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
