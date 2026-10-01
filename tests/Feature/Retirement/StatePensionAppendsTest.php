<?php

declare(strict_types=1);

use App\Models\StatePension;
use App\Models\User;
use Database\Seeders\TaxConfigurationSeeder;

/**
 * The figures every surface shows for a State Pension, worked out once on the
 * model (CSJ 2026-10-01: one figure, every surface). Web, /m and iOS used to
 * divide by 52, type in 35 qualifying years and 67 for the age.
 */
beforeEach(function () {
    $this->seed(TaxConfigurationSeeder::class);
});

it('gives the weekly forecast from the annual one', function () {
    $user = User::factory()->create(['date_of_birth' => '1970-01-01']);
    $pension = StatePension::factory()->create([
        'user_id' => $user->id,
        'state_pension_forecast_annual' => 12_548.00,
    ]);

    expect($pension->weekly_forecast)->toBe(241.31);
});

it('gives the qualifying years for the full amount and the years still needed from the record', function () {
    // ni_years_required is NOT NULL (default 35), so the record always carries it.
    $user = User::factory()->create(['date_of_birth' => '1970-01-01']);
    $pension = StatePension::factory()->create([
        'user_id' => $user->id,
        'ni_years_required' => 35,
        'ni_years_completed' => 28,
    ]);

    expect($pension->ni_years_for_full_pension)->toBe(35)
        ->and($pension->ni_years_needed)->toBe(7);
});

it('never gives a negative number of years still needed', function () {
    $user = User::factory()->create(['date_of_birth' => '1970-01-01']);
    $pension = StatePension::factory()->create([
        'user_id' => $user->id,
        'ni_years_required' => 35,
        'ni_years_completed' => 40,
    ]);

    expect($pension->ni_years_needed)->toBe(0);
});

it('keeps the State Pension age on the record over the birth-cohort schedule', function () {
    $user = User::factory()->create(['date_of_birth' => '1995-01-01']);
    $pension = StatePension::factory()->create([
        'user_id' => $user->id,
        'state_pension_age' => 66,
    ]);

    expect($pension->resolved_state_pension_age)->toBe(66);
});

it('resolves the State Pension age from the date of birth when the record has none', function () {
    $user = User::factory()->create(['date_of_birth' => '1995-01-01']);
    $pension = StatePension::factory()->create([
        'user_id' => $user->id,
        'state_pension_age' => null,
    ]);

    // Born 1995: the 2044-2046 cohort, 68 (StatePensionAgeResolverTest).
    expect($pension->resolved_state_pension_age)->toBe(68);
});

/**
 * The accessor used to load `user`, and the resolver loaded `user->statePension`,
 * so serialising the record walked user -> statePension -> user without end
 * (memory exhausted on POST /api/retirement/state-pension).
 */
it('serialises without loading the user onto the record', function () {
    $user = User::factory()->create(['date_of_birth' => '1995-01-01']);
    $pension = StatePension::factory()->create([
        'user_id' => $user->id,
        'state_pension_age' => null,
    ]);

    $array = $pension->fresh()->toArray();

    expect($array['resolved_state_pension_age'])->toBe(68)
        ->and($array)->not->toHaveKey('user');
});
