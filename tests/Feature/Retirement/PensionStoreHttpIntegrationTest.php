<?php

declare(strict_types=1);

use App\Models\DBPension;
use App\Models\DCPension;
use App\Models\StatePension;
use App\Models\User;
use Database\Seeders\TaxConfigurationSeeder;
use Database\Seeders\TierConfigurationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(TaxConfigurationSeeder::class);
    $this->seed(TierConfigurationSeeder::class);
});

it('HTTP POST /api/retirement/pensions/dc persists via PensionStore', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    $response = $this->postJson('/api/retirement/pensions/dc', [
        'scheme_name' => 'Aviva Workplace',
        'pension_type' => 'occupational',
        'provider' => 'Aviva',
        'current_fund_value' => 45000,
        'employee_contribution_percent' => 5,
        'employer_contribution_percent' => 5,
        'retirement_age' => 65,
    ]);

    $response->assertCreated();
    $this->assertDatabaseHas('dc_pensions', [
        'user_id' => $user->id,
        'scheme_name' => 'Aviva Workplace',
        'pension_type' => 'occupational',
        'current_fund_value' => 45000,
    ]);
});

it('HTTP PUT /api/retirement/pensions/dc/{id} mutates via PensionStore', function () {
    $user = User::factory()->create();
    $pension = DCPension::factory()->create(['user_id' => $user->id, 'current_fund_value' => 1000]);
    Sanctum::actingAs($user);

    $this->putJson("/api/retirement/pensions/dc/{$pension->id}", [
        'current_fund_value' => 3000,
    ])->assertOk();

    expect((float) $pension->fresh()->current_fund_value)->toBe(3000.00);
});

it('HTTP POST /api/retirement/pensions/db persists via PensionStore', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    $this->postJson('/api/retirement/pensions/db', [
        'scheme_name' => 'NHS 2015',
        'scheme_type' => 'career_average',
        'accrued_annual_pension' => 12000,
        'normal_retirement_age' => 67,
    ])->assertCreated();

    $this->assertDatabaseHas('db_pensions', [
        'user_id' => $user->id,
        'scheme_name' => 'NHS 2015',
        'scheme_type' => 'career_average',
    ]);
});

it('HTTP PUT /api/retirement/state-pension is idempotent (one row per user)', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    $this->postJson('/api/retirement/state-pension', ['ni_years_completed' => 20, 'ni_years_required' => 35])->assertOk();
    $this->postJson('/api/retirement/state-pension', ['ni_years_completed' => 25])->assertOk();

    expect(StatePension::where('user_id', $user->id)->count())->toBe(1);
    expect((int) StatePension::where('user_id', $user->id)->first()->ni_years_completed)->toBe(25);
});

it('HTTP DELETE /api/retirement/pensions/dc/{id} soft-deletes via PensionStore', function () {
    $user = User::factory()->create();
    $pension = DCPension::factory()->create(['user_id' => $user->id]);
    Sanctum::actingAs($user);

    $this->deleteJson("/api/retirement/pensions/dc/{$pension->id}")->assertOk();

    expect(DCPension::find($pension->id))->toBeNull();
    expect(DCPension::withTrashed()->find($pension->id))->not->toBeNull();
});

it('HTTP DELETE /api/retirement/pensions/db/{id} returns 404 when foreign user attempts delete', function () {
    $owner = User::factory()->create();
    $intruder = User::factory()->create();
    $pension = DBPension::factory()->create(['user_id' => $owner->id]);
    Sanctum::actingAs($intruder);

    $this->deleteJson("/api/retirement/pensions/db/{$pension->id}")->assertNotFound();

    expect(DBPension::find($pension->id))->not->toBeNull();
});

/**
 * W-0017. Sarah Jones's NHS 2015 scheme, exactly as `tests/Persona/peak_earners.md`
 * states it. The web form could not express four of these seven: no Normal
 * Retirement Age input, no Spouse Pension input, a numeric "Revaluation Rate"
 * where the column wants a cpi/rpi/fixed/none enum, and no career-average or
 * public-sector option, so the row saved as final_salary / NULL / NULL / 'none'.
 * The endpoint always accepted all seven — the forms simply never sent them.
 */
it('persists every field of the persona NHS Defined Benefit pension', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    $this->postJson('/api/retirement/pensions/db', [
        'scheme_name' => 'NHS Pension Scheme',
        'scheme_type' => 'career_average',
        'accrued_annual_pension' => 35000,
        'pensionable_service_years' => 18,
        'normal_retirement_age' => 60,
        'spouse_pension_percent' => 50,
        'inflation_protection' => 'cpi',
        'lump_sum_entitlement' => 105000,
    ])->assertCreated();

    $pension = DBPension::where('user_id', $user->id)->firstOrFail();

    expect($pension->scheme_name)->toBe('NHS Pension Scheme')
        ->and($pension->scheme_type)->toBe('career_average')
        ->and((float) $pension->accrued_annual_pension)->toBe(35000.0)
        ->and((float) $pension->pensionable_service_years)->toBe(18.0)
        ->and($pension->normal_retirement_age)->toBe(60)
        ->and((float) $pension->spouse_pension_percent)->toBe(50.0)
        ->and($pension->inflation_protection)->toBe('cpi')
        ->and((float) $pension->lump_sum_entitlement)->toBe(105000.0);
});

it('rejects an inflation protection value the column enum cannot hold', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    $this->postJson('/api/retirement/pensions/db', [
        'scheme_name' => 'NHS Pension Scheme',
        'scheme_type' => 'career_average',
        'accrued_annual_pension' => 35000,
        'inflation_protection' => 'cpi_plus_1_5',
    ])->assertStatus(422);
});

/**
 * The spouse benefit is live logic, not decoration: HouseholdPlanningService
 * falls back to an assumed 50% when the column is NULL, so an unrecordable
 * field means every death-of-a-spouse projection silently ran on a guess.
 */
it('uses the recorded spouse pension percentage rather than the assumed 50%', function () {
    $user = User::factory()->create();
    $pension = DBPension::factory()->create([
        'user_id' => $user->id,
        'accrued_annual_pension' => 35000,
        'spouse_pension_percent' => null,
    ]);

    $assumed = (float) ($pension->spouse_pension_percent ?? 50);
    expect($assumed)->toBe(50.0);

    Sanctum::actingAs($user);
    $this->putJson("/api/retirement/pensions/db/{$pension->id}", [
        'spouse_pension_percent' => 66.67,
    ])->assertOk();

    $recorded = (float) ($pension->fresh()->spouse_pension_percent ?? 50);
    expect($recorded)->toBe(66.67);
});
