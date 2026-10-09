<?php

declare(strict_types=1);

use App\Models\DCPension;
use App\Models\User;
use App\Services\Retirement\RequiredCapitalCalculator;
use App\Services\TaxConfigService;
use Database\Seeders\TaxConfigurationSeeder;

/**
 * Walk R21 (2026-10-09, Sam): the target worked out for someone who has not
 * set one is a share of income less the pension payments they will stop making
 * in retirement. Only workplace (net pay) payments came off, so Sam's £1,500 a
 * year into a personal pension stayed in: £23,100 = 75% × £30,800.
 *
 * Relief at source comes off gross (FA 2004 s192), as net pay comes off before
 * tax, so both are on the same gross-income basis as the income they leave.
 */
beforeEach(function () {
    $this->seed(TaxConfigurationSeeder::class);
});

function retirementTargetUser(): User
{
    return User::factory()->create([
        'date_of_birth' => '1988-05-14',
        'employment_status' => 'employed',
        'annual_employment_income' => 30000,
        'annual_dividend_income' => 0,
        'annual_interest_income' => 0,
        'annual_other_income' => 0,
        'annual_rental_income' => 0,
        'annual_trust_income' => 0,
        'annual_self_employment_income' => 0,
    ]);
}

it('takes a personal pension payment off the income the target is a share of', function () {
    $user = retirementTargetUser();
    DCPension::factory()->for($user)->create([
        'scheme_type' => 'personal', 'pension_type' => 'personal', 'salary_sacrifice' => false,
        'monthly_contribution_amount' => 125, 'annual_salary' => null,
        'employee_contribution_percent' => null, 'employer_contribution_percent' => null,
    ]);
    $taxConfig = app(TaxConfigService::class);
    $basic = (float) $taxConfig->getPensionAllowances()['tax_relief']['basic_rate'];
    $share = (float) $taxConfig->get('retirement.target_income_percent', 0.75);
    $gross = round(1500 / (1 - $basic), 2);

    $result = app(RequiredCapitalCalculator::class)->calculate($user->id);

    expect($result['income_source'])->toBe('calculated')
        ->and($result['required_income'])->toBe(round((30000 - $gross) * $share, 2));
});

it('still takes a workplace (net pay) payment off once', function () {
    $user = retirementTargetUser();
    DCPension::factory()->for($user)->create([
        'scheme_type' => null, 'pension_type' => 'occupational', 'salary_sacrifice' => false,
        'monthly_contribution_amount' => null, 'annual_salary' => null,
        'employee_contribution_percent' => 5, 'employer_contribution_percent' => 3,
    ]);
    $share = (float) app(TaxConfigService::class)->get('retirement.target_income_percent', 0.75);

    $result = app(RequiredCapitalCalculator::class)->calculate($user->id);

    expect($result['required_income'])->toBe(round((30000 - 1500) * $share, 2));
});
