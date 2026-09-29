<?php

declare(strict_types=1);

use App\Models\DCPension;
use App\Models\User;
use App\Services\Retirement\AnnualAllowanceChecker;
use App\Services\Tax\IncomeDefinitionsService;
use App\Services\Tax\TaxStrategyMath;
use App\Services\TaxConfigService;
use App\Services\UserProfile\UserProfileService;
use Database\Seeders\TaxConfigurationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

/*
 * Pension contributions captured by onboarding (percentages with no
 * annual_salary; personal pensions as monthly_contribution_amount) must reach
 * every income definition. Sources:
 * - Net pay: FA 2004 s193(2), deducted from employment income, so it reduces
 *   net income (ITA 2007 s23 Step 2).
 * - Relief at source: FA 2004 s192. It does not reduce net income; its gross
 *   amount is deducted at adjusted net income (ITA 2007 s58 Step 3) and at
 *   threshold income (FA 2004 s228ZA(5)(c)), and it extends the basic and
 *   higher rate limits (s192(4)).
 * - Adjusted income: FA 2004 s228ZA(4), net income plus net-pay contributions
 *   plus employer contributions.
 * https://www.legislation.gov.uk/ukpga/2004/12/section/192
 * https://www.legislation.gov.uk/ukpga/2004/12/section/193
 * https://www.legislation.gov.uk/ukpga/2004/12/section/228ZA
 * https://www.legislation.gov.uk/ukpga/2007/3/section/58
 */
uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(TaxConfigurationSeeder::class);
});

function onboardingPensionUser(): User
{
    return User::factory()->create([
        'employment_status' => 'employed',
        'annual_employment_income' => 60000,
        'annual_dividend_income' => 0,
        'annual_interest_income' => 0,
        'annual_other_income' => 0,
        'annual_rental_income' => 0,
        'annual_trust_income' => 0,
        'annual_self_employment_income' => 0,
    ]);
}

it('deducts a form-captured workplace (net pay) contribution from net income', function () {
    $user = onboardingPensionUser();
    DCPension::factory()->for($user)->create([
        'scheme_type' => null, 'pension_type' => 'occupational', 'salary_sacrifice' => false,
        'monthly_contribution_amount' => null, 'annual_salary' => null,
        'employee_contribution_percent' => 5, 'employer_contribution_percent' => 3,
    ]);

    $d = app(IncomeDefinitionsService::class)->calculate($user->id);

    expect($d['deductions']['employee_pension_contributions'])->toBe(3000.0)
        ->and($d['net_income'])->toBe(57000.0)
        ->and($d['adjusted_net_income'])->toBe(57000.0)
        ->and($d['threshold_income'])->toBe(57000.0)
        ->and($d['adjusted_income'])->toBe(61800.0);
});

it('deducts a personal pension (relief at source) gross at adjusted net income and threshold income only', function () {
    $user = onboardingPensionUser();
    DCPension::factory()->for($user)->create([
        'scheme_type' => 'personal', 'pension_type' => 'personal', 'salary_sacrifice' => false,
        'monthly_contribution_amount' => 200, 'annual_salary' => null,
        'employee_contribution_percent' => null, 'employer_contribution_percent' => null,
    ]);
    $basic = (float) app(TaxConfigService::class)->getPensionAllowances()['tax_relief']['basic_rate'];
    $gross = round(2400 / (1 - $basic), 2);

    $d = app(IncomeDefinitionsService::class)->calculate($user->id);

    expect($d['net_income'])->toBe(60000.0)
        ->and($d['deductions']['relief_at_source_gross'])->toBe($gross)
        ->and($d['adjusted_net_income'])->toBe(round(60000 - $gross, 2))
        ->and($d['threshold_income'])->toBe(round(60000 - $gross, 2))
        ->and($d['adjusted_income'])->toBe(60000.0);
});

it('bands the tax strategies on net income with relief-at-source extending the bands', function () {
    $netPay = onboardingPensionUser();
    DCPension::factory()->for($netPay)->create([
        'scheme_type' => null, 'pension_type' => 'occupational', 'salary_sacrifice' => false,
        'monthly_contribution_amount' => null, 'annual_salary' => null,
        'employee_contribution_percent' => 5, 'employer_contribution_percent' => 0,
    ]);
    $ras = onboardingPensionUser();
    DCPension::factory()->for($ras)->create([
        'scheme_type' => 'personal', 'pension_type' => 'personal', 'salary_sacrifice' => false,
        'monthly_contribution_amount' => 200, 'annual_salary' => null,
        'employee_contribution_percent' => null, 'employer_contribution_percent' => null,
    ]);
    $math = app(TaxStrategyMath::class);
    $higher = $math->bandThresholds()['higher'];
    $basic = (float) app(TaxConfigService::class)->getPensionAllowances()['tax_relief']['basic_rate'];

    expect($math->taxableIncomeFor($netPay))->toBe(57000.0)
        ->and($math->bandThresholdsFor($ras)['higher'])->toBe(round($higher + 2400 / (1 - $basic), 2));
});

it('counts a form-captured workplace contribution on the income tab', function () {
    $user = onboardingPensionUser();
    DCPension::factory()->for($user)->create([
        'scheme_type' => null, 'pension_type' => 'occupational', 'salary_sacrifice' => false,
        'monthly_contribution_amount' => null, 'annual_salary' => null,
        'employee_contribution_percent' => 5, 'employer_contribution_percent' => 3,
    ]);

    $profile = app(UserProfileService::class)->getCompleteProfile($user->fresh());

    expect((float) $profile['income_occupation']['annual_pension_contributions'])->toBe(3000.0);
});

it('counts the Annual Allowance used as the pension input amount, relief at source gross (FA 2004 s233)', function () {
    $user = onboardingPensionUser();
    DCPension::factory()->for($user)->create([
        'scheme_type' => null, 'pension_type' => 'occupational', 'salary_sacrifice' => false,
        'monthly_contribution_amount' => null, 'annual_salary' => null,
        'employee_contribution_percent' => 5, 'employer_contribution_percent' => 3,
    ]);
    DCPension::factory()->for($user)->create([
        'scheme_type' => 'personal', 'pension_type' => 'personal', 'salary_sacrifice' => false,
        'monthly_contribution_amount' => 200, 'annual_salary' => null,
        'employee_contribution_percent' => null, 'employer_contribution_percent' => null,
    ]);
    $basic = (float) app(TaxConfigService::class)->getPensionAllowances()['tax_relief']['basic_rate'];

    // 3,000 net pay + 1,800 employer + 2,400 paid net grossed to 3,000. The old
    // sum read the personal pension at the 2,400 the member paid.
    expect(app(TaxStrategyMath::class)->estimatePensionContributionThisYear($user, null))
        ->toBe(round(3000 + 1800 + 2400 / (1 - $basic), 2));
});

it('gives the Retirement Annual Allowance check the same pension input amount as Tax Strategy', function () {
    $user = onboardingPensionUser();
    DCPension::factory()->for($user)->create([
        'scheme_type' => null, 'pension_type' => 'occupational', 'salary_sacrifice' => false,
        'monthly_contribution_amount' => null, 'annual_salary' => null,
        'employee_contribution_percent' => 5, 'employer_contribution_percent' => 3,
    ]);
    DCPension::factory()->for($user)->create([
        'scheme_type' => 'personal', 'pension_type' => 'personal', 'salary_sacrifice' => false,
        'has_flexibly_accessed' => false,
        'monthly_contribution_amount' => 200, 'annual_salary' => null,
        'employee_contribution_percent' => null, 'employer_contribution_percent' => null,
    ]);
    $taxYear = app(TaxConfigService::class)->getTaxYear();

    $checked = app(AnnualAllowanceChecker::class)->checkAnnualAllowance($user->id, $taxYear);

    // Before: the checker read the blank scheme salary (workplace £0) and the
    // personal pension net (£2,400).
    expect($checked['total_contributions'])
        ->toBe(app(TaxStrategyMath::class)->estimatePensionContributionThisYear($user, null))
        ->and($checked['total_contributions'])->toBeGreaterThan(7000.0);
});

it('publishes each pension\'s part of the pension input amount, so Fyn can explain the total', function () {
    // The walk household behind "Ask Fyn about this" (2026-09-26): Fyn read the
    // £7,800 total with no per-scheme parts and left the personal pension out.
    $user = onboardingPensionUser();
    $nest = DCPension::factory()->for($user)->create([
        'scheme_name' => 'Nest', 'scheme_type' => null, 'pension_type' => 'occupational', 'salary_sacrifice' => false,
        'monthly_contribution_amount' => null, 'annual_salary' => null,
        'employee_contribution_percent' => 5, 'employer_contribution_percent' => 3,
    ]);
    $vanguard = DCPension::factory()->for($user)->create([
        'scheme_name' => 'Vanguard', 'scheme_type' => 'personal', 'pension_type' => 'personal', 'salary_sacrifice' => false,
        'monthly_contribution_amount' => 200, 'annual_salary' => null,
        'employee_contribution_percent' => null, 'employer_contribution_percent' => null,
    ]);
    DCPension::factory()->for($user)->create([
        'scheme_name' => 'Old job', 'scheme_type' => 'workplace', 'pension_type' => 'occupational', 'salary_sacrifice' => false,
        'monthly_contribution_amount' => null, 'annual_salary' => null,
        'employee_contribution_percent' => null, 'employer_contribution_percent' => null,
    ]);
    $basic = (float) app(TaxConfigService::class)->getPensionAllowances()['tax_relief']['basic_rate'];
    $gross = round(2400 / (1 - $basic), 2);

    $d = app(IncomeDefinitionsService::class)->calculate($user->id);

    // A pension nothing is paid into this year has no part (the "Old job" scheme).
    expect($d['pension_input_breakdown'])->toBe([
        [
            'pension_id' => $nest->id, 'scheme_name' => 'Nest', 'relief' => 'net_pay',
            'member_pays' => 3000.0, 'tax_relief_added' => 0.0, 'employer_pays' => 1800.0, 'pension_input' => 4800.0,
        ],
        [
            'pension_id' => $vanguard->id, 'scheme_name' => 'Vanguard', 'relief' => 'relief_at_source',
            'member_pays' => 2400.0, 'tax_relief_added' => round($gross - 2400, 2), 'employer_pays' => 0.0, 'pension_input' => $gross,
        ],
    ])
        ->and(round(array_sum(array_column($d['pension_input_breakdown'], 'pension_input')), 2))->toBe($d['pension_input_amount']);
});

it('shows salary-sacrificed pay as the employer\'s in its pension\'s part (W-0204)', function () {
    $user = onboardingPensionUser();
    $pension = DCPension::factory()->for($user)->create([
        'scheme_name' => 'Acme', 'scheme_type' => 'workplace', 'pension_type' => 'occupational', 'salary_sacrifice' => true,
        'monthly_contribution_amount' => null, 'annual_salary' => null,
        'employee_contribution_percent' => 5, 'employer_contribution_percent' => 3,
    ]);

    $d = app(IncomeDefinitionsService::class)->calculate($user->id);

    expect($d['pension_input_breakdown'])->toBe([[
        'pension_id' => $pension->id, 'scheme_name' => 'Acme', 'relief' => 'salary_sacrifice',
        'member_pays' => 0.0, 'tax_relief_added' => 0.0, 'employer_pays' => 4800.0, 'pension_input' => 4800.0,
    ]])->and($d['pension_input_amount'])->toBe(4800.0);
});
