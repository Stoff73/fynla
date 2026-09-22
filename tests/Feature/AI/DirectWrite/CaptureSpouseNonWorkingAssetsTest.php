<?php

declare(strict_types=1);

use App\Agents\CoordinatingAgent;
use App\Models\TaxStrategyHouseholdInput;
use App\Models\User;
use App\Services\TaxConfigService;
use Database\Seeders\TaxConfigurationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(TaxConfigurationSeeder::class);
});

it('creates household_inputs row with non-working-spouse fields', function () {
    $user = User::factory()->create([
        'is_preview_user' => false,
        'marital_status' => 'married',
        'household_calculation_mode' => 'single_earner_couple',
        'marriage_allowance_eligible' => true,
    ]);

    $result = app(CoordinatingAgent::class)->executeTool('capture_spouse_non_working_assets', [
        'spouse_existing_isa_balance' => 5000,
        'spouse_existing_savings_balance' => 0,
        'spouse_existing_investment_balance' => 0,
        'spouse_existing_dividend_holdings_value' => 0,
    ], $user);

    expect($result['onboarding_capture'] ?? false)->toBeTrue()
        ->and($result['field_group'])->toBe('campaign_spouse_non_working_assets');
    $row = TaxStrategyHouseholdInput::where('user_id', $user->id)->first();
    expect($row)->not->toBeNull();
    expect((float) $row->spouse_existing_isa_balance)->toBe(5000.0);
    expect((float) $row->spouse_existing_savings_balance)->toBe(0.0);
    // Working-spouse fields untouched
    expect($row->spouse_annual_income)->toBeNull();
});

it('accepts the all-zero case (spouse has no standalone assets)', function () {
    $user = User::factory()->create([
        'is_preview_user' => false,
        'household_calculation_mode' => 'single_earner_couple',
    ]);

    $result = app(CoordinatingAgent::class)->executeTool('capture_spouse_non_working_assets', [
        'spouse_existing_isa_balance' => 0,
        'spouse_existing_savings_balance' => 0,
        'spouse_existing_investment_balance' => 0,
        'spouse_existing_dividend_holdings_value' => 0,
    ], $user);

    expect($result['onboarding_capture'] ?? false)->toBeTrue();
    expect(TaxStrategyHouseholdInput::where('user_id', $user->id)->count())->toBe(1);
});

it('rejects invalid non-working spouse balances without creating a row', function () {
    $user = User::factory()->create([
        'is_preview_user' => false,
        'household_calculation_mode' => 'single_earner_couple',
    ]);

    $result = app(CoordinatingAgent::class)->executeTool('capture_spouse_non_working_assets', [
        'spouse_existing_isa_balance' => -500,
    ], $user);

    expect($result['error'] ?? false)->toBeTrue()
        ->and($result['error_type'] ?? null)->toBe('validation_failed')
        ->and(TaxStrategyHouseholdInput::where('user_id', $user->id)->exists())->toBeFalse();
});

it('turns the non-earner maximum yes into the configured net contribution and stores dividends', function () {
    $user = User::factory()->create(['is_preview_user' => false, 'household_calculation_mode' => 'single_earner_couple']);
    $pension = app(TaxConfigService::class)->getPensionAllowances();
    $expected = round($pension['relevant_earnings_minimum'] * (1 - $pension['tax_relief']['basic_rate']), 2);

    $result = app(CoordinatingAgent::class)->executeTool('capture_spouse_non_working_assets', [
        'spouse_existing_pension_balance' => 30000,
        'spouse_annual_dividends' => 1500,
        'spouse_pays_non_earner_maximum' => 'yes',
    ], $user);

    expect($result['onboarding_capture'] ?? false)->toBeTrue();
    $row = TaxStrategyHouseholdInput::where('user_id', $user->id)->first();
    expect((float) $row->spouse_pension_input_annual)->toBe($expected)
        ->and($expected)->toBeGreaterThan(0.0)
        ->and((float) $row->spouse_annual_dividends)->toBe(1500.0);

    app(CoordinatingAgent::class)->executeTool('capture_spouse_non_working_assets', ['spouse_pays_non_earner_maximum' => 'no'], $user);
    expect((float) $row->fresh()->spouse_pension_input_annual)->toBe(0.0);
});
