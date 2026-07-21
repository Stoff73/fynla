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
