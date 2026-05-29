<?php

declare(strict_types=1);

use App\Models\DBPension;
use App\Models\DCPension;
use App\Models\StatePension;
use App\Models\User;
use App\Services\Stores\Recalc\PensionDerivedColumnCalculator;
use Database\Seeders\TaxConfigurationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(TaxConfigurationSeeder::class);
});

it('calculateDc materialises current_fund_value_gbp + annual_contribution + years_to_drawdown', function () {
    $user = User::factory()->create(['date_of_birth' => now()->subYears(45)]);
    $pension = DCPension::factory()->create([
        'user_id' => $user->id,
        'current_fund_value' => 50000,
        'annual_salary' => 60000,
        'employee_contribution_percent' => 5,
        'employer_contribution_percent' => 5,
        'monthly_contribution_amount' => null,
        'expected_return_percent' => 5,
        'retirement_age' => 65,
    ]);

    $derived = app(PensionDerivedColumnCalculator::class)->calculateDc($pension, $user);

    expect($derived['current_fund_value_gbp'])->toBe(50000.00)
        ->and($derived['annual_contribution_gbp'])->toBe(6000.00)
        ->and($derived['years_to_drawdown'])->toBe(20);
});

it('calculateDc projected_value reflects compounded growth + contributions', function () {
    $user = User::factory()->create(['date_of_birth' => now()->subYears(45)]);
    $pension = DCPension::factory()->create([
        'user_id' => $user->id,
        'current_fund_value' => 100000,
        'expected_return_percent' => 5,
        'retirement_age' => 65,
        'monthly_contribution_amount' => 0,
        'employee_contribution_percent' => 0,
        'employer_contribution_percent' => 0,
    ]);

    $derived = app(PensionDerivedColumnCalculator::class)->calculateDc($pension, $user);

    // 100k * 1.05^20 = ~265,329.77
    expect($derived['projected_value_at_retirement_gbp'])->toBeGreaterThan(265000)
        ->and($derived['projected_value_at_retirement_gbp'])->toBeLessThan(266000);
});

it('calculateDc returns null projected when retirement_age or expected_return_percent missing', function () {
    $user = User::factory()->create(['date_of_birth' => now()->subYears(45)]);
    $pension = DCPension::factory()->create([
        'user_id' => $user->id,
        'current_fund_value' => 50000,
        'retirement_age' => null,
        'expected_return_percent' => null,
    ]);

    $derived = app(PensionDerivedColumnCalculator::class)->calculateDc($pension, $user);

    expect($derived['projected_value_at_retirement_gbp'])->toBeNull()
        ->and($derived['years_to_drawdown'])->toBeNull();
});

it('calculateDc tolerates a null user (owner soft-deleted), computing user-independent columns', function () {
    // Orphaned pension: the owner is soft-deleted, so DCPension::with('user')
    // resolves the belongsTo to null while the user_id FK stays satisfied.
    $user = User::factory()->create();
    $pension = DCPension::factory()->create([
        'user_id' => $user->id,
        'current_fund_value' => 50000,
        'monthly_contribution_amount' => 200,
        'expected_return_percent' => 5,
        'retirement_age' => 65,
    ]);

    $derived = app(PensionDerivedColumnCalculator::class)->calculateDc($pension, null);

    // User-independent columns still materialise...
    expect($derived['current_fund_value_gbp'])->toBe(50000.00)
        ->and($derived['annual_contribution_gbp'])->toBe(2400.00)
        // ...age-dependent columns degrade to null without a date_of_birth.
        ->and($derived['years_to_drawdown'])->toBeNull()
        ->and($derived['projected_value_at_retirement_gbp'])->toBeNull();
});

it('calculateState tolerates a null user, leaving years_to_state_pension_age null', function () {
    $user = User::factory()->create();
    $state = StatePension::factory()->create([
        'user_id' => $user->id,
        'ni_years_completed' => 28,
        'ni_years_required' => 35,
        'state_pension_forecast_annual' => 9000,
        'state_pension_age' => 67,
    ]);

    $derived = app(PensionDerivedColumnCalculator::class)->calculateState($state, null);

    expect($derived['state_pension_forecast_annual_gbp'])->toBe(9000.00)
        ->and($derived['ni_completion_pct'])->toBe(80.00)
        ->and($derived['years_to_state_pension_age'])->toBeNull();
});

it('calculateDb tolerates a null user', function () {
    $user = User::factory()->create();
    $pension = DBPension::factory()->create([
        'user_id' => $user->id,
        'accrued_annual_pension' => 12000,
        'spouse_pension_percent' => 50,
    ]);

    $derived = app(PensionDerivedColumnCalculator::class)->calculateDb($pension, null);

    expect($derived['projected_annual_pension_at_nra_gbp'])->toBe(12000.00)
        ->and($derived['spouse_pension_projected_gbp'])->toBe(6000.00);
});

it('calculateDb materialises projected annual pension + spouse share', function () {
    $user = User::factory()->create();
    $pension = DBPension::factory()->create([
        'user_id' => $user->id,
        'accrued_annual_pension' => 12000,
        'spouse_pension_percent' => 50,
    ]);

    $derived = app(PensionDerivedColumnCalculator::class)->calculateDb($pension, $user);

    expect($derived['projected_annual_pension_at_nra_gbp'])->toBe(12000.00)
        ->and($derived['spouse_pension_projected_gbp'])->toBe(6000.00);
});

it('calculateState materialises forecast + completion pct + years to SPA', function () {
    $user = User::factory()->create(['date_of_birth' => now()->subYears(50)]);
    $state = StatePension::factory()->create([
        'user_id' => $user->id,
        'ni_years_completed' => 28,
        'ni_years_required' => 35,
        'state_pension_forecast_annual' => 9000,
        'state_pension_age' => 67,
    ]);

    $derived = app(PensionDerivedColumnCalculator::class)->calculateState($state, $user);

    expect($derived['state_pension_forecast_annual_gbp'])->toBe(9000.00)
        ->and($derived['ni_completion_pct'])->toBe(80.00)
        ->and($derived['years_to_state_pension_age'])->toBe(17);
});
