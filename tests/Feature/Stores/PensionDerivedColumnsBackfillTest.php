<?php

declare(strict_types=1);

use App\Models\DBPension;
use App\Models\DCPension;
use App\Models\StatePension;
use App\Models\User;
use Database\Seeders\TaxConfigurationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(TaxConfigurationSeeder::class);
});

it('pensions:backfill-derived populates DC derived columns on legacy rows', function () {
    $user = User::factory()->create(['date_of_birth' => now()->subYears(40)]);
    // forceCreate to write a row without invoking the PensionStore recalc path
    $pension = DCPension::forceCreate([
        'user_id' => $user->id,
        'scheme_name' => 'Legacy',
        'current_fund_value' => 30000,
        'expected_return_percent' => 5,
        'retirement_age' => 65,
    ]);

    expect($pension->current_fund_value_gbp)->toBeNull();

    $this->artisan('pensions:backfill-derived')->assertSuccessful();

    $pension->refresh();
    expect((float) $pension->current_fund_value_gbp)->toBe(30000.00)
        ->and($pension->current_fund_value_gbp_calculated_at)->not->toBeNull()
        ->and($pension->years_to_drawdown)->toBe(25);
});

it('pensions:backfill-derived populates DB derived columns', function () {
    $user = User::factory()->create();
    $pension = DBPension::forceCreate([
        'user_id' => $user->id,
        'scheme_name' => 'Legacy DB',
        'scheme_type' => 'final_salary',
        'accrued_annual_pension' => 8000,
        'spouse_pension_percent' => 50,
    ]);

    $this->artisan('pensions:backfill-derived')->assertSuccessful();

    $pension->refresh();
    expect((float) $pension->projected_annual_pension_at_nra_gbp)->toBe(8000.00)
        ->and((float) $pension->spouse_pension_projected_gbp)->toBe(4000.00);
});

it('pensions:backfill-derived populates State derived columns', function () {
    $user = User::factory()->create(['date_of_birth' => now()->subYears(60)]);
    $state = StatePension::forceCreate([
        'user_id' => $user->id,
        'ni_years_completed' => 30,
        'ni_years_required' => 35,
        'state_pension_forecast_annual' => 10000,
        'state_pension_age' => 67,
    ]);

    $this->artisan('pensions:backfill-derived')->assertSuccessful();

    $state->refresh();
    expect((float) $state->state_pension_forecast_annual_gbp)->toBe(10000.00)
        ->and((float) $state->ni_completion_pct)->toBeGreaterThan(85.0)
        ->and((float) $state->ni_completion_pct)->toBeLessThan(86.0)
        ->and($state->years_to_state_pension_age)->toBe(7);
});
