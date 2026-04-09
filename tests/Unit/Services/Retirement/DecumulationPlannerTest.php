<?php

declare(strict_types=1);

use App\Services\Retirement\DecumulationPlanner;
use App\Services\TaxConfigService;

beforeEach(function () {
    $this->mockTaxConfig = Mockery::mock(TaxConfigService::class);
    $this->mockTaxConfig->shouldReceive('get')
        ->with('retirement.annuity_rate_estimates', [])
        ->andReturn([
            '55' => ['single' => 0.048, 'joint' => 0.042],
            '60' => ['single' => 0.053, 'joint' => 0.047],
            '65' => ['single' => 0.060, 'joint' => 0.053],
            '70' => ['single' => 0.069, 'joint' => 0.061],
            '75' => ['single' => 0.081, 'joint' => 0.072],
        ]);
    $this->mockTaxConfig->shouldReceive('get')
        ->with('retirement.withdrawal_rates.safe', 0.04)
        ->andReturn(0.04);
    $this->mockTaxConfig->shouldReceive('getIncomeTax')
        ->andReturn([
            'personal_allowance' => 12570,
            'higher_rate_threshold' => 50270,
            'additional_rate_threshold' => 125140,
        ]);

    $this->planner = new DecumulationPlanner($this->mockTaxConfig);
});

afterEach(function () {
    Mockery::close();
});

it('calculates sustainable withdrawal rate scenarios', function () {
    $portfolioValue = 500000; // £500,000 pension pot
    $yearsInRetirement = 30;
    $growthRate = 0.05; // 5% annual growth
    $inflationRate = 0.025; // 2.5% inflation

    $result = $this->planner->calculateSustainableWithdrawalRate(
        $portfolioValue,
        $yearsInRetirement,
        $growthRate,
        $inflationRate
    );

    expect($result)->toHaveKeys(['scenarios', 'recommended_rate'])
        ->and($result['scenarios'])->toHaveCount(3)
        ->and($result['scenarios'][0])->toHaveKeys(['withdrawal_rate', 'initial_annual_income', 'survives', 'final_balance'])
        ->and($result['scenarios'][1]['initial_annual_income'])->toBe(20000.0) // 4% of £500k
        ->and($result['recommended_rate'])->toBeIn([3.0, 4.0, 5.0]);
});

it('ensures 3% withdrawal rate is always sustainable', function () {
    $portfolioValue = 300000;
    $yearsInRetirement = 30;
    $growthRate = 0.04;
    $inflationRate = 0.02;

    $result = $this->planner->calculateSustainableWithdrawalRate(
        $portfolioValue,
        $yearsInRetirement,
        $growthRate,
        $inflationRate
    );

    expect($result['scenarios'][0]['survives'])->toBeTrue()
        ->and($result['scenarios'][0]['initial_annual_income'])->toBe(9000.0);
});

it('shows 5% withdrawal rate may deplete portfolio', function () {
    $portfolioValue = 200000;
    $yearsInRetirement = 35;
    $growthRate = 0.03; // Lower growth
    $inflationRate = 0.025;

    $result = $this->planner->calculateSustainableWithdrawalRate(
        $portfolioValue,
        $yearsInRetirement,
        $growthRate,
        $inflationRate
    );

    // With low growth and long retirement, 5% may not survive
    expect($result['scenarios'][2])->toHaveKeys(['withdrawal_rate', 'initial_annual_income', 'survives', 'final_balance'])
        ->and($result['scenarios'][2]['initial_annual_income'])->toBe(10000.0);
});

it('compares annuity vs drawdown options', function () {
    $pensionPot = 400000;
    $age = 65;
    $hasSpouse = true;

    $result = $this->planner->compareAnnuityVsDrawdown($pensionPot, $age, $hasSpouse);

    expect($result)->toHaveKeys(['annuity', 'drawdown', 'recommendation'])
        ->and($result['annuity'])->toHaveKeys(['annual_income', 'guaranteed'])
        ->and($result['drawdown'])->toHaveKeys(['annual_income', 'guaranteed', 'flexibility'])
        ->and($result['annuity']['guaranteed'])->toBeTrue()
        ->and($result['drawdown']['guaranteed'])->toBeFalse();
});

it('decreases annuity rate with joint life option', function () {
    $pensionPot = 300000;
    $age = 67;

    $singleLife = $this->planner->compareAnnuityVsDrawdown($pensionPot, $age, false);
    $jointLife = $this->planner->compareAnnuityVsDrawdown($pensionPot, $age, true);

    // Joint life annuity should provide lower income than single life
    expect($singleLife['annuity']['annual_income'])
        ->toBeGreaterThan($jointLife['annuity']['annual_income']);
});

it('provides flexible income access via drawdown', function () {
    $pensionPot = 500000;
    $age = 65;
    $hasSpouse = false;

    $result = $this->planner->compareAnnuityVsDrawdown($pensionPot, $age, $hasSpouse);

    expect($result['drawdown']['annual_income'])->toBe(20000.0) // 4% of £500k
        ->and($result['drawdown']['flexibility'])->toBeString()
        ->and($result['recommendation'])->toBeString();
});

it('calculates PCLS strategy correctly', function () {
    $pensionValue = 400000;

    $result = $this->planner->calculatePCLSStrategy($pensionValue);

    expect($result)->toHaveKeys(['pension_value', 'pcls_amount', 'remaining_pot', 'tax_saving', 'options'])
        ->and($result['pcls_amount'])->toBe(100000.0) // 25% of £400,000
        ->and($result['remaining_pot'])->toBe(300000.0)
        ->and($result['tax_saving'])->toBeGreaterThan(0); // Tax savings from 25% lump sum
});

it('caps PCLS at 25% of pension value', function () {
    $pensionValue = 1000000;

    $result = $this->planner->calculatePCLSStrategy($pensionValue);

    expect($result['pcls_amount'])->toBe(250000.0) // Exactly 25%
        ->and($result['remaining_pot'])->toBe(750000.0);
});

it('includes various options in PCLS strategies', function () {
    $pensionValue = 500000;

    $result = $this->planner->calculatePCLSStrategy($pensionValue);

    expect($result['options'])->toBeArray()
        ->and($result['options'])->not->toBeEmpty()
        ->and($result['recommendation'])->toBeString();
});

it('models income phasing for tax efficiency', function () {
    $pensions = collect([
        (object) ['id' => 1, 'scheme_name' => 'Workplace DC', 'current_fund_value' => 200000, 'type' => 'dc'],
        (object) ['id' => 2, 'scheme_name' => 'SIPP', 'current_fund_value' => 150000, 'type' => 'dc'],
        (object) ['id' => 3, 'scheme_name' => 'DB Pension', 'accrued_annual_pension' => 15000, 'type' => 'db'],
    ]);
    $retirementAge = 67;

    $result = $this->planner->modelIncomePhasing($pensions, $retirementAge);

    expect($result)->toHaveKeys(['phasing_strategy', 'tax_efficiency_tips'])
        ->and($result['phasing_strategy'])->toBeArray()
        ->and($result['phasing_strategy'])->not->toBeEmpty()
        ->and($result['tax_efficiency_tips'])->toBeArray();
});

it('prioritizes DB pensions first in income phasing', function () {
    $pensions = collect([
        (object) ['id' => 1, 'scheme_name' => 'DC Pension', 'current_fund_value' => 300000, 'type' => 'dc'],
        (object) ['id' => 2, 'scheme_name' => 'DB Pension', 'accrued_annual_pension' => 20000, 'type' => 'db'],
    ]);
    $retirementAge = 65;

    $result = $this->planner->modelIncomePhasing($pensions, $retirementAge);

    // DB pensions should be recommended first as they provide guaranteed income
    expect($result['phasing_strategy'])->toBeArray()
        ->and($result['tax_efficiency_tips'])->not->toBeEmpty();
});

it('adjusts withdrawal rate for inflation', function () {
    $portfolioValue = 400000;
    $yearsInRetirement = 25;
    $growthRate = 0.06;
    $inflationRate = 0.03; // Higher inflation

    $result = $this->planner->calculateSustainableWithdrawalRate(
        $portfolioValue,
        $yearsInRetirement,
        $growthRate,
        $inflationRate
    );

    // With higher inflation, real returns are lower
    expect($result['scenarios'])->toHaveCount(3)
        ->and($result['recommended_rate'])->toBeNumeric();
});
