<?php

declare(strict_types=1);

use App\Models\DCPension;
use App\Models\RetirementProfile;
use App\Models\User;
use Database\Seeders\TaxConfigurationSeeder;

beforeEach(function () {
    $this->seed(TaxConfigurationSeeder::class);
});

describe('Decumulation API', function () {
    describe('GET /api/retirement/decumulation-analysis', function () {
        it('requires authentication', function () {
            $response = $this->getJson('/api/retirement/decumulation-analysis');

            $response->assertUnauthorized();
        });

        it('returns correct structure for user with pension data', function () {
            $user = User::factory()->create();
            RetirementProfile::factory()->create([
                'user_id' => $user->id,
                'current_age' => 60,
                'target_retirement_age' => 67,
                'target_retirement_income' => 30000,
                'life_expectancy' => 85,
            ]);
            DCPension::factory()->create([
                'user_id' => $user->id,
                'current_fund_value' => 300000,
            ]);

            $response = $this->actingAs($user)->getJson('/api/retirement/decumulation-analysis');

            $response->assertOk()
                ->assertJsonStructure([
                    'success',
                    'message',
                    'data' => [
                        'withdrawal_rates' => [
                            'scenarios',
                            'recommended_rate',
                        ],
                        'annuity_vs_drawdown' => [
                            'annuity',
                            'drawdown',
                            'recommendation',
                        ],
                        'pcls_strategy' => [
                            'pension_value',
                            'pcls_amount',
                            'remaining_pot',
                        ],
                        'income_phasing' => [
                            'phasing_strategy',
                            'tax_efficiency_tips',
                        ],
                        'context' => [
                            'current_age',
                            'retirement_age',
                            'years_to_retirement',
                            'total_dc_value',
                        ],
                    ],
                ])
                ->assertJson(['success' => true]);
        });

        it('returns failure when no retirement profile exists', function () {
            $user = User::factory()->create();

            $response = $this->actingAs($user)->getJson('/api/retirement/decumulation-analysis');

            $response->assertOk()
                ->assertJson([
                    'success' => false,
                ]);
        });

        it('returns failure when no DC pension value exists', function () {
            $user = User::factory()->create();
            RetirementProfile::factory()->create([
                'user_id' => $user->id,
                'current_age' => 60,
                'target_retirement_age' => 67,
            ]);
            DCPension::factory()->create([
                'user_id' => $user->id,
                'current_fund_value' => 0,
            ]);

            $response = $this->actingAs($user)->getJson('/api/retirement/decumulation-analysis');

            $response->assertOk()
                ->assertJson([
                    'success' => false,
                ]);
        });

        it('allows preview user access for GET request', function () {
            $user = User::factory()->create(['is_preview_user' => true]);
            RetirementProfile::factory()->create([
                'user_id' => $user->id,
                'current_age' => 62,
                'target_retirement_age' => 67,
                'life_expectancy' => 85,
            ]);
            DCPension::factory()->create([
                'user_id' => $user->id,
                'current_fund_value' => 200000,
            ]);

            $response = $this->actingAs($user)->getJson('/api/retirement/decumulation-analysis');

            $response->assertOk()
                ->assertJson(['success' => true]);
        });
    });
});
