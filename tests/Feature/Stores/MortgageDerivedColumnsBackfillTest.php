<?php

declare(strict_types=1);

use App\Models\Mortgage;
use App\Models\Property;
use App\Models\User;
use Database\Seeders\TaxConfigurationSeeder;
use Database\Seeders\TierConfigurationSeeder;
use Illuminate\Support\Facades\Artisan;

beforeEach(function () {
    $this->seed(TaxConfigurationSeeder::class);
    $this->seed(TierConfigurationSeeder::class);
});

it('backfills derived columns for all existing mortgages', function () {
    $user = User::factory()->create();
    $property = Property::factory()->create(['user_id' => $user->id, 'current_value' => 500000]);

    // Create mortgage WITHOUT going through MortgageStore (simulates pre-Pass-5 row)
    $mortgage = Mortgage::create([
        'user_id' => $user->id,
        'property_id' => $property->id,
        'lender_name' => 'Legacy',
        'mortgage_type' => 'repayment',
        'outstanding_balance' => 300000,
        'monthly_payment' => 1800,
        'ownership_type' => 'individual',
        'ownership_percentage' => 100.00,
    ]);

    expect($mortgage->outstanding_balance_gbp)->toBeNull();

    Artisan::call('mortgages:backfill-derived-columns');

    $fresh = $mortgage->fresh();
    expect((float) $fresh->outstanding_balance_gbp)->toEqual(300000.00);
    expect((float) $fresh->current_ltv_pct)->toEqual(60.0);  // 300k / 500k * 100
});

it('reconciles property.outstanding_mortgage from canonical mortgages sum', function () {
    $user = User::factory()->create();
    $property = Property::factory()->create([
        'user_id' => $user->id,
        'current_value' => 500000,
        'outstanding_mortgage' => 9999.99,  // intentionally wrong — pre-Pass-5 drift
    ]);

    Mortgage::create([
        'user_id' => $user->id,
        'property_id' => $property->id,
        'lender_name' => 'Legacy',
        'mortgage_type' => 'repayment',
        'outstanding_balance' => 250000,
        'monthly_payment' => 1500,
        'ownership_type' => 'individual',
        'ownership_percentage' => 100.00,
    ]);

    Artisan::call('properties:backfill-outstanding-mortgage');

    expect((float) $property->fresh()->outstanding_mortgage)->toEqual(250000.00);
});
