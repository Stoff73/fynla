<?php

use App\Models\DBPension;
use App\Models\DCPension;
use App\Models\StatePension;
use App\Services\Retirement\PensionProjector;
use App\Services\Risk\RiskPreferenceService;

beforeEach(function () {
    // Get PensionProjector from the service container (with RiskPreferenceService injected)
    $this->projector = app(PensionProjector::class);
});

test('projects DC pension value correctly', function () {
    $pension = new DCPension([
        'current_fund_value' => 100000,
        'monthly_contribution_amount' => 500,
        'platform_fee_percent' => 0.5,
    ]);

    $yearsToRetirement = 20;
    $growthRate = 0.05; // 5%

    $projectedValue = $this->projector->projectDCPension($pension, $yearsToRetirement, $growthRate);

    // Future value of £100,000 at 4.5% (5% - 0.5% fees) for 20 years
    // Plus monthly contributions of £500 * 12 = £6,000 per year
    // Expected: approximately £390,000
    expect($projectedValue)->toBeGreaterThan(300000)
        ->and($projectedValue)->toBeLessThan(450000);
});

test('projects DC pension with zero contributions', function () {
    $pension = new DCPension([
        'current_fund_value' => 50000,
        'monthly_contribution_amount' => 0,
        'platform_fee_percent' => 0.75,
    ]);

    $yearsToRetirement = 15;
    $growthRate = 0.05;

    $projectedValue = $this->projector->projectDCPension($pension, $yearsToRetirement, $growthRate);

    // Future value of £50,000 at 4.25% for 15 years
    // Expected: approximately £93,000
    expect($projectedValue)->toBeGreaterThan(80000)
        ->and($projectedValue)->toBeLessThan(110000);
});

test('projects DB pension correctly', function () {
    $pension = new DBPension([
        'accrued_annual_pension' => 15000,
    ]);

    $projectedIncome = $this->projector->projectDBPension($pension);

    expect($projectedIncome)->toBe(15000.0);
});

test('projects state pension with forecast', function () {
    $statePension = new StatePension([
        'state_pension_forecast_annual' => 11502,
        'ni_years_completed' => 35,
        'ni_years_required' => 35,
    ]);

    $projectedIncome = $this->projector->projectStatePension($statePension);

    expect($projectedIncome)->toBe(11502.0);
});

test('projects state pension without forecast based on NI years', function () {
    $statePension = new StatePension([
        'state_pension_forecast_annual' => null,
        'ni_years_completed' => 20,
        'ni_years_required' => 35,
    ]);

    $projectedIncome = $this->projector->projectStatePension($statePension);

    // 20/35 of full state pension (£11,502)
    // Expected: approximately £6,572
    expect($projectedIncome)->toBeGreaterThan(6000)
        ->and($projectedIncome)->toBeLessThan(7000);
});

test('calculates income replacement ratio correctly', function () {
    $projectedIncome = 30000;
    $currentIncome = 50000;

    $ratio = $this->projector->calculateIncomeReplacementRatio($projectedIncome, $currentIncome);

    expect($ratio)->toBe(60.0);
});

test('handles zero current income for replacement ratio', function () {
    $projectedIncome = 30000;
    $currentIncome = 0;

    $ratio = $this->projector->calculateIncomeReplacementRatio($projectedIncome, $currentIncome);

    expect($ratio)->toBe(0.0);
});
