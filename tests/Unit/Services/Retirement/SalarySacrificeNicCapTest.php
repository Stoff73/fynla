<?php

declare(strict_types=1);

use App\Models\DCPension;
use App\Models\User;
use App\Services\Retirement\RetirementStrategyService;
use App\Services\Tax\TaxStrategyMath;
use App\Services\UKTaxCalculator;
use Carbon\Carbon;
use Database\Seeders\TaxConfigurationSeeder;

/**
 * What an extra employee pension contribution costs in take-home pay: the
 * contribution less the Income Tax it saves, less — under salary sacrifice —
 * the employee National Insurance it saves. From 6 April 2029 only the first
 * £2,000 a year of sacrifice is free of National Insurance (National Insurance
 * Contributions (Employer Pensions Contributions) Act 2026; CSJ 2026-09-28).
 * Cap and date from TaxConfigService.
 *
 * Replaces tests that pinned the old model: every contribution "zero-cost"
 * before the cap, and the excess "via relief at source" after it.
 */
beforeEach(function () {
    $this->seed(TaxConfigurationSeeder::class);
    $this->service = app(RetirementStrategyService::class);
    $this->method = (new ReflectionClass(RetirementStrategyService::class))->getMethod('calculateNetCostOfContribution');
    $this->method->setAccessible(true);
    $this->ni = fn (float $pay): float => (float) app(UKTaxCalculator::class)->calculateNetIncome($pay)['breakdown']['class_1_ni'];
});

afterEach(function () {
    Carbon::setTestNow();
});

function sacrificer(float $pay, float $employeePercent): array
{
    $user = User::factory()->create(['annual_employment_income' => $pay]);
    $pension = DCPension::create(['user_id' => $user->id, 'scheme_name' => 'Work', 'scheme_type' => 'workplace', 'pension_type' => 'occupational',
        'current_fund_value' => 10000, 'annual_salary' => $pay, 'employee_contribution_percent' => $employeePercent, 'salary_sacrifice' => true]);

    return [$user, $pension];
}

it('never treats a contribution as free: without salary sacrifice it costs the contribution less the tax it saves', function () {
    Carbon::setTestNow('2028-12-31');
    $user = User::factory()->create(['annual_employment_income' => 30000]);

    $cost = $this->method->invoke($this->service, 1000.0, $user, null);

    expect($cost)->toBe(1000.0 - app(TaxStrategyMath::class)->pensionContributionSaving($user, 1000.0))
        ->and($cost)->toBeGreaterThan(0.0);
});

it('takes National Insurance off too under salary sacrifice, before the cap', function () {
    Carbon::setTestNow('2028-12-31');
    [$user, $pension] = sacrificer(30000, 5);
    $taxSaved = app(TaxStrategyMath::class)->pensionContributionSaving($user, 1000.0);
    $niSaved = ($this->ni)(30000) - ($this->ni)(29000);

    expect($this->method->invoke($this->service, 1000.0, $user, $pension))->toBe(1000.0 - $taxSaved - $niSaved);
});

it('stops the National Insurance saving at the cap from 6 April 2029', function () {
    Carbon::setTestNow('2029-04-06');
    // 10% of £30,000 = £3,000 already sacrificed: above the £2,000 cap, so an
    // extra £1,000 saves no National Insurance.
    [$user, $pension] = sacrificer(30000, 10);
    $taxSaved = app(TaxStrategyMath::class)->pensionContributionSaving($user, 1000.0);

    expect($this->method->invoke($this->service, 1000.0, $user, $pension))->toBe(1000.0 - $taxSaved);
});

it('saves National Insurance only on what is left under the cap', function () {
    Carbon::setTestNow('2029-04-06');
    // 5% of £30,000 = £1,500 already sacrificed: £500 left under the cap.
    [$user, $pension] = sacrificer(30000, 5);
    $taxSaved = app(TaxStrategyMath::class)->pensionContributionSaving($user, 1000.0);
    $niSaved = ($this->ni)(30000) - ($this->ni)(29500);

    expect($this->method->invoke($this->service, 1000.0, $user, $pension))->toBe(1000.0 - $taxSaved - $niSaved);
});
