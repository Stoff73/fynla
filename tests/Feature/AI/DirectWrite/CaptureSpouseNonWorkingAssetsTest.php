<?php

declare(strict_types=1);

use App\Agents\CoordinatingAgent;
use App\Models\TaxStrategyHouseholdInput;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(\Database\Seeders\TaxConfigurationSeeder::class);
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
