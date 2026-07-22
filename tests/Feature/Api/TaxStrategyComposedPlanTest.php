<?php

declare(strict_types=1);

use App\Models\User;
use Database\Seeders\TaxActionDefinitionSeeder;
use Database\Seeders\TaxConfigurationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(TaxConfigurationSeeder::class);
    $this->seed(TaxActionDefinitionSeeder::class);
});

it('returns the composed plan alongside the existing payload', function () {
    $user = User::factory()->create([
        'date_of_birth' => '1982-02-19',
        'employment_status' => 'full_time',
        'annual_employment_income' => 110000,
        'monthly_expenditure' => 3000,
        'marital_status' => 'married',
    ]);
    Sanctum::actingAs($user);

    $response = $this->getJson('/api/tax-strategy');

    $response->assertOk()
        ->assertJsonStructure([
            'data' => [
                'composed_plan' => ['items', 'combined_annual_saving', 'locked'],
            ],
        ]);
});

it('keeps every pre-existing top-level response key', function () {
    // Pre-existing top-level keys under `data` (from TaxStrategyOutputDTO::toArray()):
    //   tax_year, calculation_mode, user_allowances, spouse_allowances,
    //   recommendations, delta_vs_baseline
    // This test is the additive guarantee — all must remain after composed_plan is added.
    $user = User::factory()->create([
        'annual_employment_income' => 50000,
        'household_calculation_mode' => 'single',
    ]);
    Sanctum::actingAs($user);

    $response = $this->getJson('/api/tax-strategy');

    $response->assertOk()
        ->assertJsonStructure([
            'data' => [
                'tax_year',
                'calculation_mode',
                'user_allowances',
                'recommendations',
                'delta_vs_baseline',
                'composed_plan',
            ],
        ]);

    // spouse_allowances is present but nullable for single users
    expect($response->json('data'))->toHaveKey('spouse_allowances');
});
