<?php

declare(strict_types=1);

use App\Models\Estate\Trust;
use App\Models\Household;
use App\Models\TaxConfiguration;
use App\Models\User;
use App\Services\Trust\IHTPeriodicChargeCalculator;
use Carbon\Carbon;

beforeEach(function () {
    // Ensure active tax configuration exists
    if (! TaxConfiguration::where('is_active', true)->exists()) {
        TaxConfiguration::factory()->create(['is_active' => true]);
    }

    $taxConfig = app(\App\Services\TaxConfigService::class);
    $this->calculator = new IHTPeriodicChargeCalculator($taxConfig);
    $this->user = User::factory()->create();
    $this->household = Household::factory()->create();
});

it('calculates periodic charge for relevant property trust at 10 year anniversary', function () {
    $trust = Trust::factory()->create([
        'user_id' => $this->user->id,
        'household_id' => $this->household->id,
        'trust_type' => 'discretionary',
        'is_relevant_property_trust' => true,
        'trust_creation_date' => Carbon::now()->subYears(10),
        'current_value' => 500000,
        'total_asset_value' => 500000,
    ]);

    $result = $this->calculator->calculatePeriodicCharge($trust);

    expect($result['charge_applicable'])->toBeTrue();
    expect((float) $result['trust_value'])->toBe(500000.0);
    expect((float) $result['nil_rate_band'])->toBe(325000.0);
    expect((float) $result['chargeable_value'])->toBe(175000.0); // 500k - 325k
    expect($result['periodic_charge_rate'])->toBe(0.06);
    expect((float) $result['charge_amount'])->toBe(10500.0); // 175k * 6%
    expect($result['years_since_creation'])->toBe(10);
});

it('does not apply periodic charge to non-relevant property trusts', function () {
    $trust = Trust::factory()->create([
        'user_id' => $this->user->id,
        'household_id' => $this->household->id,
        'trust_type' => 'bare',
        'is_relevant_property_trust' => false,
        'trust_creation_date' => Carbon::now()->subYears(10),
        'current_value' => 500000,
    ]);

    $result = $this->calculator->calculatePeriodicCharge($trust);

    expect($result['charge_applicable'])->toBeFalse();
    expect($result['reason'])->toBe('Not a relevant property trust');
    expect($result['charge_amount'])->toBe(0);
});

it('calculates no charge when trust value is below NRB', function () {
    $trust = Trust::factory()->create([
        'user_id' => $this->user->id,
        'household_id' => $this->household->id,
        'trust_type' => 'discretionary',
        'is_relevant_property_trust' => true,
        'trust_creation_date' => Carbon::now()->subYears(10),
        'current_value' => 200000, // Below £325k NRB
        'total_asset_value' => 200000,
    ]);

    $result = $this->calculator->calculatePeriodicCharge($trust);

    expect($result['charge_applicable'])->toBeTrue();
    expect((float) $result['chargeable_value'])->toBe(0.0);
    expect((float) $result['charge_amount'])->toBe(0.0);
});

it('does not apply periodic charge before 10 year anniversary', function () {
    $trust = Trust::factory()->create([
        'user_id' => $this->user->id,
        'household_id' => $this->household->id,
        'trust_type' => 'discretionary',
        'is_relevant_property_trust' => true,
        'trust_creation_date' => Carbon::now()->subYears(7),
        'current_value' => 500000,
    ]);

    $result = $this->calculator->calculatePeriodicCharge($trust);

    expect($result['charge_applicable'])->toBeFalse();
    expect($result['reason'])->toContain('Next periodic charge due on');
    expect($result['charge_amount'])->toBe(0);
    expect($result)->toHaveKey('next_charge_date');
    expect($result['years_until_next_charge'])->toBeGreaterThan(0);
    expect($result['years_until_next_charge'])->toBeLessThanOrEqual(3);
});

it('calculates periodic charge at 20 year anniversary', function () {
    $trust = Trust::factory()->create([
        'user_id' => $this->user->id,
        'household_id' => $this->household->id,
        'trust_type' => 'discretionary',
        'is_relevant_property_trust' => true,
        'trust_creation_date' => Carbon::now()->subYears(20),
        'current_value' => 1000000,
        'total_asset_value' => 1000000,
    ]);

    $result = $this->calculator->calculatePeriodicCharge($trust);

    expect($result['charge_applicable'])->toBeTrue();
    expect((float) $result['chargeable_value'])->toBe(675000.0); // 1m - 325k
    expect((float) $result['charge_amount'])->toBe(40500.0); // 675k * 6%
    expect($result['years_since_creation'])->toBe(20);
});

it('calculates exit charge for relevant property trust', function () {
    $trust = Trust::factory()->create([
        'user_id' => $this->user->id,
        'household_id' => $this->household->id,
        'trust_type' => 'discretionary',
        'is_relevant_property_trust' => true,
        'trust_creation_date' => Carbon::now()->subYears(5),
        'current_value' => 800000,
        'total_asset_value' => 800000,
        'last_periodic_charge_date' => null,
    ]);

    $assetValue = 100000;
    $exitDate = Carbon::now();

    $result = $this->calculator->calculateExitCharge($trust, $assetValue, $exitDate);

    expect($result['charge_applicable'])->toBeTrue();
    expect((float) $result['asset_value'])->toBe(100000.0);
    expect((float) $result['trust_value'])->toBe(800000.0);
    expect((float) $result['chargeable_value'])->toBe(475000.0); // 800k - 325k
    expect($result)->toHaveKey('quarters_since_last_charge');
    expect($result)->toHaveKey('effective_rate');
    expect($result)->toHaveKey('charge_amount');
});

it('does not apply exit charge to non-relevant property trusts', function () {
    $trust = Trust::factory()->create([
        'user_id' => $this->user->id,
        'household_id' => $this->household->id,
        'trust_type' => 'bare',
        'is_relevant_property_trust' => false,
        'trust_creation_date' => Carbon::now()->subYears(5),
        'current_value' => 500000,
    ]);

    $result = $this->calculator->calculateExitCharge($trust, 100000, Carbon::now());

    expect($result['charge_applicable'])->toBeFalse();
    expect($result['reason'])->toBe('Not a relevant property trust');
    expect($result['charge_amount'])->toBe(0);
});

it('caps exit charge at 6 percent of asset value', function () {
    $trust = Trust::factory()->create([
        'user_id' => $this->user->id,
        'household_id' => $this->household->id,
        'trust_type' => 'discretionary',
        'is_relevant_property_trust' => true,
        'trust_creation_date' => Carbon::now()->subYears(9)->subMonths(11),
        'current_value' => 5000000,
        'total_asset_value' => 5000000,
        'last_periodic_charge_date' => null,
    ]);

    $assetValue = 100000;
    $exitDate = Carbon::now();

    $result = $this->calculator->calculateExitCharge($trust, $assetValue, $exitDate);

    expect($result['charge_applicable'])->toBeTrue();
    // Exit charge should be capped at 6% of asset value
    expect((float) $result['charge_amount'])->toBeLessThanOrEqual(6000.0); // 100k * 6%
});

it('calculates entry charge for asset above NRB', function () {
    $assetValue = 500000;

    $result = $this->calculator->calculateEntryCharge($assetValue);

    expect($result['charge_applicable'])->toBeTrue();
    expect((float) $result['asset_value'])->toBe(500000.0);
    expect((float) $result['nil_rate_band'])->toBe(325000.0);
    expect((float) $result['chargeable_value'])->toBe(175000.0);
    expect($result['entry_charge_rate'])->toBe(0.20);
    expect((float) $result['charge_amount'])->toBe(35000.0); // (500k - 325k) * 20%
});

it('calculates no entry charge for asset below NRB', function () {
    $assetValue = 200000;

    $result = $this->calculator->calculateEntryCharge($assetValue);

    expect($result['charge_applicable'])->toBeFalse();
    expect((float) $result['chargeable_value'])->toBe(0.0);
    expect((float) $result['charge_amount'])->toBe(0.0);
});

it('gets upcoming charges for relevant property trusts', function () {
    // Trust with charge due in 6 months
    Trust::factory()->create([
        'user_id' => $this->user->id,
        'household_id' => $this->household->id,
        'trust_name' => 'Trust A',
        'trust_type' => 'discretionary',
        'is_relevant_property_trust' => true,
        'is_active' => true,
        'trust_creation_date' => Carbon::now()->subYears(9)->subMonths(6),
        'current_value' => 600000,
        'total_asset_value' => 600000,
    ]);

    // Trust with charge not due for 3 years
    Trust::factory()->create([
        'user_id' => $this->user->id,
        'household_id' => $this->household->id,
        'trust_name' => 'Trust B',
        'trust_type' => 'discretionary',
        'is_relevant_property_trust' => true,
        'is_active' => true,
        'trust_creation_date' => Carbon::now()->subYears(7),
        'current_value' => 400000,
    ]);

    // Non-RPT trust (should be excluded)
    Trust::factory()->create([
        'user_id' => $this->user->id,
        'household_id' => $this->household->id,
        'trust_type' => 'bare',
        'is_relevant_property_trust' => false,
        'is_active' => true,
        'trust_creation_date' => Carbon::now()->subYears(9)->subMonths(6),
        'current_value' => 500000,
    ]);

    $upcomingCharges = $this->calculator->getUpcomingCharges($this->user->id, 12);

    expect($upcomingCharges)->toHaveCount(1);
    expect($upcomingCharges[0]['trust_name'])->toBe('Trust A');
    expect($upcomingCharges[0])->toHaveKeys(['trust_id', 'trust_name', 'charge_date', 'months_until_charge', 'estimated_charge', 'trust_value']);
});

it('sorts upcoming charges by charge date', function () {
    // Trust with charge due in 3 months
    Trust::factory()->create([
        'user_id' => $this->user->id,
        'household_id' => $this->household->id,
        'trust_name' => 'Trust Later',
        'trust_type' => 'discretionary',
        'is_relevant_property_trust' => true,
        'is_active' => true,
        'trust_creation_date' => Carbon::now()->subYears(9)->subMonths(9),
        'current_value' => 500000,
        'total_asset_value' => 500000,
    ]);

    // Trust with charge due in 1 month
    Trust::factory()->create([
        'user_id' => $this->user->id,
        'household_id' => $this->household->id,
        'trust_name' => 'Trust Earlier',
        'trust_type' => 'discretionary',
        'is_relevant_property_trust' => true,
        'is_active' => true,
        'trust_creation_date' => Carbon::now()->subYears(9)->subMonths(11),
        'current_value' => 400000,
        'total_asset_value' => 400000,
    ]);

    $upcomingCharges = $this->calculator->getUpcomingCharges($this->user->id, 12);

    expect($upcomingCharges)->toHaveCount(2);
    expect($upcomingCharges[0]['trust_name'])->toBe('Trust Earlier');
    expect($upcomingCharges[1]['trust_name'])->toBe('Trust Later');
});

it('calculates tax return due dates correctly', function () {
    $trust = Trust::factory()->create([
        'user_id' => $this->user->id,
        'household_id' => $this->household->id,
        'trust_creation_date' => Carbon::now()->subYears(5),
    ]);

    $result = $this->calculator->calculateTaxReturnDueDates($trust);

    expect($result)->toHaveKeys(['tax_year_end', 'return_due_date', 'days_until_due', 'is_overdue']);
    expect($result['tax_year_end'])->toBeInstanceOf(Carbon::class);
    expect($result['return_due_date'])->toBeInstanceOf(Carbon::class);

    // Tax year ends April 5
    expect($result['tax_year_end']->month)->toBe(4);
    expect($result['tax_year_end']->day)->toBe(5);

    // Return due January 31 following tax year end
    expect($result['return_due_date']->month)->toBe(1);
    expect($result['return_due_date']->day)->toBe(31);

    expect($result['is_overdue'])->toBeIn([true, false]);
});

it('uses total_asset_value field when available for periodic charge', function () {
    $trust = Trust::factory()->create([
        'user_id' => $this->user->id,
        'household_id' => $this->household->id,
        'trust_type' => 'discretionary',
        'is_relevant_property_trust' => true,
        'trust_creation_date' => Carbon::now()->subYears(10),
        'current_value' => 300000,
        'total_asset_value' => 600000, // This should be used
    ]);

    $result = $this->calculator->calculatePeriodicCharge($trust);

    expect((float) $result['trust_value'])->toBe(600000.0);
    expect((float) $result['chargeable_value'])->toBe(275000.0); // 600k - 325k
    expect((float) $result['charge_amount'])->toBe(16500.0); // 275k * 6%
});

it('falls back to current_value when total_asset_value is null', function () {
    $trust = Trust::factory()->create([
        'user_id' => $this->user->id,
        'household_id' => $this->household->id,
        'trust_type' => 'discretionary',
        'is_relevant_property_trust' => true,
        'trust_creation_date' => Carbon::now()->subYears(10),
        'current_value' => 500000,
        'total_asset_value' => null,
    ]);

    $result = $this->calculator->calculatePeriodicCharge($trust);

    expect((float) $result['trust_value'])->toBe(500000.0);
});

it('recognizes accumulation and maintenance trusts as relevant property trusts', function () {
    $trust = Trust::factory()->create([
        'user_id' => $this->user->id,
        'household_id' => $this->household->id,
        'trust_type' => 'accumulation_maintenance',
        'is_relevant_property_trust' => false, // Even if flag is false
        'trust_creation_date' => Carbon::now()->subYears(10),
        'current_value' => 500000,
        'total_asset_value' => 500000,
    ]);

    $result = $this->calculator->calculatePeriodicCharge($trust);

    // Should still apply charge because trust_type is accumulation_maintenance
    expect($result['charge_applicable'])->toBeTrue();
    expect((float) $result['charge_amount'])->toBeGreaterThan(0.0);
});

it('calculates quarters since last charge correctly for exit charge', function () {
    $trust = Trust::factory()->create([
        'user_id' => $this->user->id,
        'household_id' => $this->household->id,
        'trust_type' => 'discretionary',
        'is_relevant_property_trust' => true,
        'trust_creation_date' => Carbon::now()->subYears(7),
        'current_value' => 800000,
        'total_asset_value' => 800000,
        'last_periodic_charge_date' => Carbon::now()->subYears(2), // Last charge 2 years ago
    ]);

    $assetValue = 100000;
    $exitDate = Carbon::now();

    $result = $this->calculator->calculateExitCharge($trust, $assetValue, $exitDate);

    expect($result['charge_applicable'])->toBeTrue();
    expect($result['quarters_since_last_charge'])->toBe(8); // 2 years = 8 quarters
});
