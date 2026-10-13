<?php

declare(strict_types=1);

use App\Models\Mortgage;
use App\Models\Property;
use App\Models\TaxConfiguration;
use App\Models\User;
use App\Services\Property\PropertyService;
use App\Services\TaxConfigService;
use Database\Seeders\TaxConfigurationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/*
 * T64 (release ac prod walk, 2026-10-13). A buy-to-let with an interest-only
 * mortgage of £180,000 at 5.2% and no monthly payment recorded got a Section 24
 * credit of £0, because the interest was read from the payment alone. On an
 * interest-only loan the payment is the interest: £180,000 × 5.2% = £9,360 a
 * year. Finance costs are not deducted from the rent (ITTOIA 2005 s272A); they
 * reduce the tax at the basic rate (ITA 2007 s399A–s399B): 20% × £9,360 = £1,872.
 * The basic rate is read from the tax configuration (Rule 2), not written in.
 */
beforeEach(function () {
    $this->seed(TaxConfigurationSeeder::class);
    $this->user = User::factory()->create(['is_preview_user' => false]);
    $this->property = Property::factory()->create([
        'user_id' => $this->user->id,
        'property_type' => 'buy_to_let',
        'ownership_type' => 'individual',
        'current_value' => 320000,
        'monthly_rental_income' => 1450,
    ]);
});

function interestOnly(array $overrides = []): Mortgage
{
    return Mortgage::factory()->create(array_merge([
        'user_id' => test()->user->id,
        'property_id' => test()->property->id,
        'ownership_type' => 'individual',
        'mortgage_type' => 'interest_only',
        'outstanding_balance' => 180000,
        'interest_rate' => 5.2,
        'monthly_payment' => null,
    ], $overrides));
}

it('works the interest from the balance and rate when no payment was given', function () {
    interestOnly();

    $position = app(PropertyService::class)->calculateTaxPosition($this->property->fresh('mortgages'), $this->user->id);

    expect($position['monthly_mortgage_interest'])->toBe(780.0)
        ->and($position['section_24_annual_credit'])->toBe(1872.0);
});

it('uses the payment when one was given', function () {
    interestOnly(['monthly_payment' => 800]);

    $position = app(PropertyService::class)->calculateTaxPosition($this->property->fresh('mortgages'), $this->user->id);

    expect($position['monthly_mortgage_interest'])->toBe(800.0)
        ->and($position['section_24_annual_credit'])->toBe(1920.0);
});

it('gives no credit when neither the payment nor the rate is known', function () {
    interestOnly(['interest_rate' => null]);

    $position = app(PropertyService::class)->calculateTaxPosition($this->property->fresh('mortgages'), $this->user->id);

    expect($position['section_24_annual_credit'])->toBe(0.0);
});

it('takes the credit rate from the tax configuration', function () {
    interestOnly(['monthly_payment' => 1000]);
    $config = TaxConfiguration::where('is_active', true)->first();
    $data = $config->config_data;
    $data['income_tax']['bands'][0]['rate'] = 0.25;
    $config->update(['config_data' => $data]);
    app()->forgetInstance(TaxConfigService::class);
    app()->forgetInstance(PropertyService::class);

    $position = app(PropertyService::class)->calculateTaxPosition($this->property->fresh('mortgages'), $this->user->id);

    expect($position['section_24_annual_credit'])->toBe(3000.0);
});
