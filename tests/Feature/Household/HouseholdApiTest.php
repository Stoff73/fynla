<?php

declare(strict_types=1);

use App\Models\User;

beforeEach(function () {
    // Seed tax configuration
    $this->seed(\Database\Seeders\TaxConfigurationSeeder::class);

    $this->user = User::factory()->create([
        'first_name' => 'James',
        'surname' => 'Carter',
        'marital_status' => 'married',
        'annual_employment_income' => 85000,
    ]);

    $this->spouse = User::factory()->create([
        'first_name' => 'Emily',
        'surname' => 'Carter',
        'marital_status' => 'married',
        'annual_employment_income' => 32000,
    ]);

    $this->user->spouse_id = $this->spouse->id;
    $this->user->save();
    $this->spouse->spouse_id = $this->user->id;
    $this->spouse->save();

    $this->singleUser = User::factory()->create([
        'first_name' => 'John',
        'surname' => 'Morgan',
        'marital_status' => 'single',
    ]);
});

describe('Household API', function () {
    describe('GET /api/household/net-worth', function () {
        it('requires authentication', function () {
            $this->getJson('/api/household/net-worth')
                ->assertStatus(401);
        });

        it('returns data for married user with data sharing', function () {
            $response = $this->actingAs($this->user)
                ->getJson('/api/household/net-worth');

            $response->assertStatus(200)
                ->assertJsonStructure([
                    'success',
                    'message',
                    'data' => [
                        'total_assets',
                        'total_liabilities',
                        'net_worth',
                        'user_share',
                        'spouse_share',
                        'has_spouse',
                        'breakdown_by_type',
                    ],
                ])
                ->assertJson(['success' => true]);
        });

        it('returns individual data for single user without 404', function () {
            $response = $this->actingAs($this->singleUser)
                ->getJson('/api/household/net-worth');

            $response->assertStatus(200)
                ->assertJson([
                    'success' => true,
                    'data' => [
                        'has_spouse' => false,
                        'spouse_share' => 0,
                    ],
                ]);
        });
    });

    describe('GET /api/household/optimisations', function () {
        it('requires authentication', function () {
            $this->getJson('/api/household/optimisations')
                ->assertStatus(401);
        });

        it('returns optimisations for married user', function () {
            $response = $this->actingAs($this->user)
                ->getJson('/api/household/optimisations');

            $response->assertStatus(200)
                ->assertJson(['success' => true])
                ->assertJsonStructure([
                    'success',
                    'message',
                    'data',
                ]);
        });

        it('returns empty data for single user without 404', function () {
            $response = $this->actingAs($this->singleUser)
                ->getJson('/api/household/optimisations');

            $response->assertStatus(200)
                ->assertJson([
                    'success' => true,
                    'data' => [],
                ]);
        });
    });

    describe('GET /api/household/death-scenario', function () {
        it('requires authentication', function () {
            $this->getJson('/api/household/death-scenario')
                ->assertStatus(401);
        });

        it('accepts spouse parameter', function () {
            $response = $this->actingAs($this->user)
                ->getJson('/api/household/death-scenario?spouse=partner');

            $response->assertStatus(200)
                ->assertJson(['success' => true])
                ->assertJsonPath('data.deceased_name', 'Emily');
        });

        it('defaults to primary spouse', function () {
            $response = $this->actingAs($this->user)
                ->getJson('/api/household/death-scenario');

            $response->assertStatus(200)
                ->assertJson(['success' => true])
                ->assertJsonPath('data.deceased_name', 'James');
        });

        it('rejects invalid spouse parameter', function () {
            $response = $this->actingAs($this->user)
                ->getJson('/api/household/death-scenario?spouse=invalid');

            $response->assertStatus(400);
        });

        it('returns individual analysis for single user', function () {
            $response = $this->actingAs($this->singleUser)
                ->getJson('/api/household/death-scenario');

            $response->assertStatus(200)
                ->assertJson([
                    'success' => true,
                    'data' => [
                        'scenario' => 'Individual estate analysis',
                    ],
                ]);
        });
    });
});
