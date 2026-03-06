<?php

declare(strict_types=1);

use App\Models\TaxConfiguration;
use App\Services\Estate\FutureValueCalculator;

beforeEach(function () {
    // Ensure active tax configuration exists
    if (! TaxConfiguration::where('is_active', true)->exists()) {
        TaxConfiguration::factory()->create(['is_active' => true]);
    }

    $taxConfig = app(\App\Services\TaxConfigService::class);
    $this->calculator = new FutureValueCalculator($taxConfig);
});

it('calculates future value correctly', function () {
    $presentValue = 100000;
    $growthRate = 0.05; // 5%
    $years = 10;

    $futureValue = $this->calculator->calculateFutureValue($presentValue, $growthRate, $years);

    // FV = 100000 * (1.05)^10 = 162,889.46
    expect($futureValue)->toBeFloat()
        ->and($futureValue)->toBeGreaterThan($presentValue)
        ->and($futureValue)->toBeGreaterThan(162800)
        ->and($futureValue)->toBeLessThan(162900);
});

it('returns present value when zero years', function () {
    $presentValue = 50000;
    $futureValue = $this->calculator->calculateFutureValue($presentValue, 0.05, 0);

    expect($futureValue)->toBe(50000.0);
});

it('calculates portfolio future value', function () {
    $assets = collect([
        (object) ['asset_name' => 'Property', 'asset_type' => 'property', 'current_value' => 300000],
        (object) ['asset_name' => 'Investments', 'asset_type' => 'investment', 'current_value' => 100000],
        (object) ['asset_name' => 'Cash', 'asset_type' => 'cash', 'current_value' => 50000],
    ]);

    $result = $this->calculator->calculatePortfolioFutureValue($assets, 0.05, 10);

    expect($result)->toBeArray()
        ->and($result)->toHaveKeys(['total_current_value', 'total_future_value', 'total_growth', 'asset_projections'])
        ->and($result['total_current_value'])->toBe(450000.0)
        ->and($result['total_future_value'])->toBeGreaterThan(450000.0)
        ->and($result['asset_projections'])->toHaveCount(3);
});

it('calculates portfolio future value with different growth rates by asset type', function () {
    $assets = collect([
        (object) ['asset_name' => 'Property', 'asset_type' => 'property', 'current_value' => 200000],
        (object) ['asset_name' => 'Stocks', 'asset_type' => 'investment', 'current_value' => 100000],
    ]);

    $growthRates = [
        'property' => 0.03, // 3%
        'investment' => 0.07, // 7%
        'default' => 0.05,
    ];

    $result = $this->calculator->calculatePortfolioFutureValueByAssetType($assets, $growthRates, 10);

    expect($result)->toBeArray()
        ->and($result['asset_projections'])->toHaveCount(2);

    // Check that different growth rates were applied
    $propertyProjection = collect($result['asset_projections'])->firstWhere('asset_type', 'property');
    $investmentProjection = collect($result['asset_projections'])->firstWhere('asset_type', 'investment');

    expect($propertyProjection['growth_rate'])->toBe(0.03)
        ->and($investmentProjection['growth_rate'])->toBe(0.07);
});

it('provides default growth rates', function () {
    $rates = $this->calculator->getDefaultGrowthRates();

    expect($rates)->toBeArray()
        ->and($rates)->toHaveKeys(['property', 'investment', 'cash', 'default'])
        ->and($rates['property'])->toBeFloat()
        ->and($rates['investment'])->toBeFloat();
});

it('calculates real future value adjusted for inflation', function () {
    $presentValue = 100000;
    $nominalRate = 0.07; // 7%
    $inflationRate = 0.02; // 2%
    $years = 10;

    $realFV = $this->calculator->calculateRealFutureValue($presentValue, $nominalRate, $inflationRate, $years);

    // Real growth rate = ((1.07)/(1.02)) - 1 ≈ 4.9%
    // Should be less than nominal FV but greater than PV
    $nominalFV = $this->calculator->calculateFutureValue($presentValue, $nominalRate, $years);

    expect($realFV)->toBeFloat()
        ->and($realFV)->toBeGreaterThan($presentValue)
        ->and($realFV)->toBeLessThan($nominalFV);
});

it('projects estate at death', function () {
    $assets = collect([
        (object) ['asset_name' => 'House', 'asset_type' => 'property', 'current_value' => 500000],
        (object) ['asset_name' => 'Pension', 'asset_type' => 'pension', 'current_value' => 200000],
    ]);

    $yearsUntilDeath = 20;

    $projection = $this->calculator->projectEstateAtDeath($assets, $yearsUntilDeath);

    expect($projection)->toBeArray()
        ->and($projection)->toHaveKeys([
            'current_estate_value',
            'projected_estate_value_at_death',
            'projected_growth',
            'years_until_death',
            'asset_projections',
        ])
        ->and($projection['years_until_death'])->toBe(20)
        ->and($projection['projected_estate_value_at_death'])->toBeGreaterThan($projection['current_estate_value']);
});

it('calculates required growth rate to reach target', function () {
    $presentValue = 100000;
    $targetValue = 200000; // Double
    $years = 10;

    $requiredCAGR = $this->calculator->calculateRequiredGrowthRate($presentValue, $targetValue, $years);

    // To double in 10 years: (2)^(1/10) - 1 ≈ 7.18%
    expect($requiredCAGR)->toBeFloat()
        ->and($requiredCAGR)->toBeGreaterThan(0.071)
        ->and($requiredCAGR)->toBeLessThan(0.073);
});
