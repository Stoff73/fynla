<?php

declare(strict_types=1);

use App\Models\User;
use Database\Seeders\TaxConfigurationSeeder;

beforeEach(function () {
    $this->seed(TaxConfigurationSeeder::class);
});

describe('Tax Optimisation API', function () {
    describe('GET /api/tax/optimisation-analysis', function () {
        it('requires authentication', function () {
            $response = $this->getJson('/api/tax/optimisation-analysis');

            $response->assertUnauthorized();
        });

        it('returns correct structure for authenticated user', function () {
            $user = User::factory()->create([
                'annual_employment_income' => 55000,
            ]);

            $response = $this->actingAs($user)->getJson('/api/tax/optimisation-analysis');

            $response->assertOk()
                ->assertJsonStructure([
                    'success',
                    'data' => [
                        'tax_year',
                        'allowance_usage' => [
                            'isa',
                            'pension_annual_allowance',
                            'capital_gains',
                            'personal_savings_allowance',
                        ],
                        'strategies',
                        'total_estimated_saving',
                        'strategy_count',
                    ],
                ]);
        });

        it('returns strategies with correct fields', function () {
            $user = User::factory()->create([
                'annual_employment_income' => 55000,
            ]);

            $response = $this->actingAs($user)->getJson('/api/tax/optimisation-analysis');
            $data = $response->json('data');

            $response->assertOk();

            if (count($data['strategies']) > 0) {
                $strategy = $data['strategies'][0];
                expect($strategy)->toHaveKeys([
                    'type', 'priority', 'title', 'description',
                    'action', 'estimated_annual_saving', 'rank',
                ]);
            }
        });

        it('allows preview user access', function () {
            $user = User::factory()->create([
                'is_preview_user' => true,
                'annual_employment_income' => 55000,
            ]);

            $response = $this->actingAs($user)->getJson('/api/tax/optimisation-analysis');

            $response->assertOk();
        });
    });

    describe('GET /api/tax/strategies', function () {
        it('requires authentication', function () {
            $response = $this->getJson('/api/tax/strategies');

            $response->assertUnauthorized();
        });

        it('returns strategies-only response', function () {
            $user = User::factory()->create([
                'annual_employment_income' => 55000,
            ]);

            $response = $this->actingAs($user)->getJson('/api/tax/strategies');

            $response->assertOk()
                ->assertJsonStructure([
                    'success',
                    'data' => [
                        'strategies',
                        'total_estimated_saving',
                        'strategy_count',
                    ],
                ]);
        });
    });
});
