<?php

declare(strict_types=1);

use App\Models\LifeInsurancePolicy;
use App\Models\ProtectionProfile;
use App\Models\User;
use Database\Seeders\TaxConfigurationSeeder;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->seed(TaxConfigurationSeeder::class);
});

it('publishes server-calculated protection gaps with inputs assumptions explanations and policy references', function () {
    $user = User::factory()->create([
        'date_of_birth' => now()->subYears(40),
        'annual_employment_income' => 60000,
        'annual_self_employment_income' => 0,
        'annual_rental_income' => 0,
        'annual_dividend_income' => 0,
        'annual_other_income' => 0,
    ]);
    ProtectionProfile::factory()->create([
        'user_id' => $user->id,
        'annual_income' => 60000,
        'monthly_expenditure' => 2500,
        'mortgage_balance' => 140000,
        'other_debts' => 10000,
        'number_of_dependents' => 1,
        'dependents_ages' => [10],
    ]);
    $policy = LifeInsurancePolicy::factory()->create([
        'user_id' => $user->id,
        'provider' => 'Canonical Life',
        'sum_assured' => 100000,
    ]);
    Sanctum::actingAs($user);

    $presentation = $this->getJson('/api/protection')
        ->assertOk()
        ->assertJsonPath('success', true)
        ->json('data.coverage_gaps');

    $debt = collect($presentation['categories'])->firstWhere('key', 'debt_protection');
    $income = collect($presentation['categories'])->firstWhere('key', 'income_protection');

    expect($presentation['contract_version'])->toBe('protection_gap_v1')
        ->and($presentation)->toHaveKeys(['totals', 'categories', 'calculated_at'])
        ->and($debt)->toMatchArray([
            'label' => 'Debt protection',
            'need' => 150000,
            'cover' => 100000,
            'shortfall' => 50000,
            'status' => 'gap',
        ])
        ->and($debt['severity'])->toBeIn(['low', 'medium', 'high'])
        ->and($debt['explanation'])->toContain('debt')
        ->and($debt['inputs'])->toHaveKeys(['mortgage_balance', 'other_debts'])
        ->and($debt['assumptions'])->not->toBeEmpty()
        ->and($debt['relevant_policies'][0])->toMatchArray([
            'id' => $policy->id,
            'type' => 'life_insurance',
            'provider' => 'Canonical Life',
            'cover' => 100000,
        ])
        ->and($income)->toHaveKeys(['need', 'cover', 'shortfall', 'inputs', 'assumptions', 'explanation'])
        ->and(collect($presentation['categories'])->whereIn('key', [
            'human_capital',
            'debt_protection',
            'final_expenses',
            'education_funding',
        ])->sum('need'))->toEqual($presentation['totals']['need']);
});
