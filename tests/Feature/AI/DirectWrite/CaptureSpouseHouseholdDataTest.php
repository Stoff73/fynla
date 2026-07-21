<?php

declare(strict_types=1);

use App\Agents\CoordinatingAgent;
use App\Models\TaxStrategyHouseholdInput;
use App\Models\User;
use Database\Seeders\TaxConfigurationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(TaxConfigurationSeeder::class);
});

it('creates household_inputs row with working-spouse fields', function () {
    $user = User::factory()->create([
        'is_preview_user' => false,
        'marital_status' => 'married',
        'household_calculation_mode' => 'dual_earner',
    ]);

    $result = app(CoordinatingAgent::class)->executeTool('capture_spouse_household_data', [
        'spouse_annual_income' => 45000,
        'spouse_employment_status' => 'full_time',
        'spouse_isa_balance' => 12000,
        'spouse_psa_band' => 'basic',
        'spouse_unrealised_gains' => 0,
        'spouse_annual_dividends' => 0,
        'spouse_pension_input_annual' => 3600,
    ], $user);

    expect($result['onboarding_capture'] ?? false)->toBeTrue()
        ->and($result['field_group'])->toBe('campaign_spouse_household');
    $row = TaxStrategyHouseholdInput::where('user_id', $user->id)->first();
    expect($row)->not->toBeNull();
    expect((float) $row->spouse_annual_income)->toBe(45000.0);
    expect((float) $row->spouse_isa_balance)->toBe(12000.0);
    expect($row->spouse_employment_status)->toBe('full_time');
    expect($row->spouse_psa_band)->toBe('basic');
});

it('updates the existing row on a second call (updateOrCreate)', function () {
    $user = User::factory()->create([
        'is_preview_user' => false,
        'household_calculation_mode' => 'dual_earner',
    ]);

    app(CoordinatingAgent::class)->executeTool('capture_spouse_household_data', [
        'spouse_annual_income' => 30000,
    ], $user);
    app(CoordinatingAgent::class)->executeTool('capture_spouse_household_data', [
        'spouse_annual_income' => 35000,
    ], $user);

    expect(TaxStrategyHouseholdInput::where('user_id', $user->id)->count())->toBe(1);
    expect((float) TaxStrategyHouseholdInput::where('user_id', $user->id)->first()->spouse_annual_income)
        ->toBe(35000.0);
});

it('derives the spouse tax band from captured income instead of trusting model text', function () {
    $user = User::factory()->create([
        'is_preview_user' => false,
        'household_calculation_mode' => 'dual_earner',
    ]);

    $result = app(CoordinatingAgent::class)->executeTool('capture_spouse_household_data', [
        'spouse_annual_income' => 80000,
        'spouse_psa_band' => 'basic',
    ], $user);

    expect($result['onboarding_capture'] ?? false)->toBeTrue();
    expect(TaxStrategyHouseholdInput::where('user_id', $user->id)->value('spouse_psa_band'))
        ->toBe('higher');
});

it('includes captured spouse dividends when deriving the spouse tax band', function () {
    $user = User::factory()->create([
        'is_preview_user' => false,
        'household_calculation_mode' => 'dual_earner',
    ]);

    app(CoordinatingAgent::class)->executeTool('capture_spouse_household_data', [
        'spouse_annual_income' => 45000,
        'spouse_annual_dividends' => 10000,
        'spouse_psa_band' => 'basic',
    ], $user);

    expect(TaxStrategyHouseholdInput::where('user_id', $user->id)->value('spouse_psa_band'))
        ->toBe('higher');
});

it('rejects invalid spouse money and employment values without creating a row', function () {
    $user = User::factory()->create([
        'is_preview_user' => false,
        'household_calculation_mode' => 'dual_earner',
    ]);

    $result = app(CoordinatingAgent::class)->executeTool('capture_spouse_household_data', [
        'spouse_annual_income' => -1,
        'spouse_employment_status' => 'definitely_employed',
    ], $user);

    expect($result['error'] ?? false)->toBeTrue()
        ->and($result['error_type'] ?? null)->toBe('validation_failed')
        ->and(TaxStrategyHouseholdInput::where('user_id', $user->id)->exists())->toBeFalse();
});
