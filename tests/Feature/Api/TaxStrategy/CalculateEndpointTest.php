<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(\Database\Seeders\TaxConfigurationSeeder::class);
});

it('requires authentication', function () {
    $this->postJson('/api/tax-strategy/calculate', [])->assertStatus(401);
});

it('rejects pension_contribution_percent outside 0-100', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->postJson('/api/tax-strategy/calculate', ['pension_contribution_percent' => 150])
        ->assertStatus(422)
        ->assertJsonValidationErrors('pension_contribution_percent');
});

it('rejects isa_additional_deposit > £20,000', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->postJson('/api/tax-strategy/calculate', ['isa_additional_deposit' => 25000])
        ->assertStatus(422)
        ->assertJsonValidationErrors('isa_additional_deposit');
});

it('returns recalculated grid with override applied', function () {
    $user = User::factory()->create([
        'household_calculation_mode' => 'single',
        'annual_employment_income' => 50000,
    ]);

    $response = $this->actingAs($user)->postJson('/api/tax-strategy/calculate', [
        'pension_contribution_percent' => 10,
        'salary_sacrifice' => true,
    ]);

    $response->assertOk()
        ->assertJsonStructure([
            'data' => [
                'user_allowances',
                'delta_vs_baseline',
            ],
        ]);

    // Pension AA used reflects the 10% override (10% of £50k = £5,000)
    $aa = collect($response->json('data.user_allowances'))->firstWhere('key', 'pension_annual_allowance');
    expect($aa['used'])->toBeGreaterThanOrEqual(5000);
});

it('accepts an empty payload (no overrides) and returns the baseline', function () {
    $user = User::factory()->create(['household_calculation_mode' => 'single']);

    $response = $this->actingAs($user)->postJson('/api/tax-strategy/calculate', []);

    $response->assertOk();
    // Canonical: 6 base allowances + Starting Rate for Savings (factory user
    // has no employment income, so SRS is fully available). Marriage
    // Allowance hidden — factory default marital_status is 'single'.
    expect($response->json('data.user_allowances'))->toHaveCount(7);
});
