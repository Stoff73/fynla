<?php

declare(strict_types=1);

use App\Agents\CoordinatingAgent;
use App\Models\TaxStrategyHouseholdInput;
use App\Models\User;
use Database\Seeders\TaxConfigurationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * Live prod 2026-09-11: "an Aviva pension with 75680 in it and she contributes
 * 500 per month" — the contributions landed, the pot had no field and was
 * dropped. The spouse step now records the pot the spouse already holds.
 */
it('records the spouse pension pot alongside the yearly contributions', function (): void {
    $this->seed(TaxConfigurationSeeder::class);
    $user = User::factory()->create(['is_preview_user' => false, 'marital_status' => 'married']);

    $result = app(CoordinatingAgent::class)->executeTool('capture_spouse_household_data', [
        'spouse_annual_income' => 65000,
        'spouse_isa_balance' => 6700,
        'spouse_pension_input_annual' => 6000,
        'spouse_existing_pension_balance' => 75680,
    ], $user);

    expect($result['onboarding_capture'] ?? false)->toBeTrue();
    $row = TaxStrategyHouseholdInput::where('user_id', $user->id)->firstOrFail();
    expect((float) $row->spouse_existing_pension_balance)->toBe(75680.0)
        ->and((float) $row->spouse_pension_input_annual)->toBe(6000.0)
        ->and((float) $row->spouse_isa_balance)->toBe(6700.0);
});
