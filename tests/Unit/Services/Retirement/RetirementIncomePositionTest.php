<?php

declare(strict_types=1);

use App\Models\DCPension;
use App\Models\User;
use App\Services\Retirement\RetirementIncomePosition;
use App\Services\Retirement\RetirementProjectionContractService;
use App\Services\Tax\PensionAffordability;
use Database\Seeders\TaxConfigurationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(TaxConfigurationSeeder::class);
    $this->user = User::factory()->create([
        'date_of_birth' => now()->subYears(45)->toDateString(),
        'annual_employment_income' => 55000,
        'employment_status' => 'employed',
    ]);
    DCPension::create([
        'user_id' => $this->user->id, 'scheme_name' => 'Workplace', 'scheme_type' => 'workplace',
        'pension_type' => 'occupational', 'employee_contribution_percent' => 5.0, 'employer_contribution_percent' => 3.0,
        'current_fund_value' => 60000, 'annual_salary' => 55000, 'retirement_age' => 65,
    ]);
});

afterEach(function () {
    Mockery::close();
});

/** Affordability as a household with $net a year of spare money (null: spending not recorded). */
function affordWith(?float $net, bool $fromCash = false): void
{
    $mock = Mockery::mock(PensionAffordability::class);
    $mock->shouldReceive('moneyThisYear')->andReturn($net);
    $mock->shouldReceive('fundedFromCash')->andReturn($fromCash);
    app()->instance(PensionAffordability::class, $mock);
}

/** The analysis the card reads: the target, and the allowance position. */
function analysisFor(float $target, int $years = 20, float $remaining = 60000): array
{
    return [
        'summary' => ['target_retirement_income' => $target, 'target_retirement_age' => 65, 'years_to_retirement' => $years],
        'annual_allowance' => ['remaining_allowance' => $remaining, 'carry_forward_available' => 0, 'total_contributions' => 4400],
    ];
}

/** A target far enough above the planning projection to leave a shortfall of $gap. */
function targetShortBy(User $user, float $gap): float
{
    return (float) app(RetirementProjectionContractService::class)->build($user->fresh())['planning_total_at_target_age'] + $gap;
}

it('measures the shortfall against the planning contract /m and iOS show', function () {
    affordWith(null);
    $plan = app(RetirementProjectionContractService::class)->build($this->user->fresh());
    $position = app(RetirementIncomePosition::class)->for($this->user, analysisFor(targetShortBy($this->user, 8000)));

    expect($position['projected_income'])->toEqualWithDelta((float) $plan['planning_total_at_target_age'], 0.01)
        ->and($position['shortfall'])->toEqualWithDelta(8000.0, 0.01)
        ->and($position['target_age'])->toBe(65);
});

it('returns nothing when the projection meets the target', function () {
    affordWith(null);
    expect(app(RetirementIncomePosition::class)->for($this->user, analysisFor(targetShortBy($this->user, -100))))->toBeNull();
});

it('works out the contribution that buys the shortfall back, by inverting the contract', function () {
    affordWith(null);
    $plan = app(RetirementProjectionContractService::class)->build($this->user->fresh());
    $position = app(RetirementIncomePosition::class)->for($this->user, analysisFor(targetShortBy($this->user, 8000)));

    $pot = RetirementProjectionContractService::calculatePlanningValue(
        0.0, $position['needed_monthly'], (float) $plan['assumptions']['net_growth_rate_percent'],
        $position['years_to_retirement'], (int) $plan['assumptions']['compound_periods'],
    );
    expect($pot * (float) $plan['assumptions']['sustainable_withdrawal_rate']['decimal'])->toEqualWithDelta(8000.0, 2.0);

    // And it moves with the shortfall.
    $double = app(RetirementIncomePosition::class)->for($this->user, analysisFor(targetShortBy($this->user, 16000)));
    expect($double['needed_monthly'])->toEqualWithDelta($position['needed_monthly'] * 2, 0.05);
});

it('offers no amount to pay until spending is recorded', function () {
    affordWith(null);
    $position = app(RetirementIncomePosition::class)->for($this->user, analysisFor(targetShortBy($this->user, 8000)));

    expect($position['affordability_known'])->toBeFalse()
        ->and($position['payable_monthly'])->toBeNull()
        ->and($position['closes_gap'])->toBeFalse();
});

it('caps what can be paid at what the household can afford', function () {
    affordWith(1200.0); // £100 a month net
    $position = app(RetirementIncomePosition::class)->for($this->user, analysisFor(targetShortBy($this->user, 8000)));
    $basic = 0.20;

    expect($position['payable_monthly'])->toEqualWithDelta(100 / (1 - $basic), 0.01)
        ->and($position['payable_net_monthly'])->toEqualWithDelta(100.0, 0.01)
        ->and($position['closes_gap'])->toBeFalse();
});

it('caps what can be paid at relevant UK earnings less what the member already pays (FA 2004 s190)', function () {
    $this->user->update(['annual_employment_income' => 9000]);
    DCPension::where('user_id', $this->user->id)->update(['annual_salary' => 9000]);
    affordWith(100000.0);
    $position = app(RetirementIncomePosition::class)->for($this->user->fresh(), analysisFor(targetShortBy($this->user, 20000), 20, 59460));

    // £9,000 of earnings less the 5% (£450) already paid through payroll.
    expect($position['limit_monthly'])->toEqualWithDelta(8550 / 12, 0.01)
        ->and($position['payable_monthly'])->toEqualWithDelta(8550 / 12, 0.01);
});

it('lifts the cap to the basic amount for someone with no earnings who can pay by relief at source', function () {
    $this->user->update(['annual_employment_income' => 0]);
    DCPension::where('user_id', $this->user->id)->delete();
    DCPension::create([
        'user_id' => $this->user->id, 'scheme_name' => 'SIPP', 'scheme_type' => 'sipp', 'pension_type' => 'sipp',
        'current_fund_value' => 20000,
    ]);
    affordWith(100000.0);
    $position = app(RetirementIncomePosition::class)->for($this->user->fresh(), analysisFor(targetShortBy($this->user, 20000), 20, 60000));

    expect($position['limit_monthly'])->toEqualWithDelta(300.0, 0.01);
});

it('leaves nothing under the basic amount once the member already pays it in', function () {
    $this->user->update(['annual_employment_income' => 0]);
    DCPension::where('user_id', $this->user->id)->delete();
    DCPension::create([
        'user_id' => $this->user->id, 'scheme_name' => 'SIPP', 'scheme_type' => 'sipp', 'pension_type' => 'sipp',
        'current_fund_value' => 20000, 'monthly_contribution_amount' => 240, // £2,880 net, £3,600 gross
    ]);
    affordWith(100000.0);
    $position = app(RetirementIncomePosition::class)->for($this->user->fresh(), analysisFor(targetShortBy($this->user, 20000), 20, 56400));

    expect($position['limit_monthly'])->toEqualWithDelta(0.0, 0.01)
        ->and($position['payable_monthly'])->toEqualWithDelta(0.0, 0.01);
});

it('gives no basic-amount lift on a net-pay workplace scheme alone (s191(7))', function () {
    $this->user->update(['annual_employment_income' => 0]);
    DCPension::where('user_id', $this->user->id)->update(['employee_contribution_percent' => 0, 'employer_contribution_percent' => 0, 'annual_salary' => 0]);
    affordWith(100000.0);
    $position = app(RetirementIncomePosition::class)->for($this->user->fresh(), analysisFor(targetShortBy($this->user, 20000), 20, 60000));

    expect($position['limit_monthly'])->toEqualWithDelta(0.0, 0.01);
});

it('spreads savings over the years to retirement for someone paying from cash', function () {
    affordWith(12000.0, fromCash: true);
    $position = app(RetirementIncomePosition::class)->for($this->user, analysisFor(targetShortBy($this->user, 20000), 20));

    // £12,000 of savings, grossed up at the basic rate, over 20 years.
    expect($position['affordable_monthly'])->toEqualWithDelta(12000 / 0.8 / 20 / 12, 0.01);
});

it('caps at the Money Purchase Annual Allowance once a pension is flexibly accessed', function () {
    DCPension::create([
        'user_id' => $this->user->id, 'scheme_name' => 'Old SIPP', 'scheme_type' => 'sipp', 'pension_type' => 'sipp',
        'current_fund_value' => 20000, 'has_flexibly_accessed' => true,
    ]);
    affordWith(100000.0);
    $position = app(RetirementIncomePosition::class)->for($this->user->fresh(), analysisFor(targetShortBy($this->user, 20000)));

    // £10,000 less the £4,400 already paid in.
    expect($position['limit_monthly'])->toEqualWithDelta(5600 / 12, 0.01);
});

it('gives the retirement age that closes what the affordable payment cannot', function () {
    affordWith(1200.0);
    $position = app(RetirementIncomePosition::class)->for($this->user, analysisFor(targetShortBy($this->user, 3000)));

    expect($position['closes_gap'])->toBeFalse();
    if ($position['age_to_close'] !== null) {
        expect($position['age_to_close'])->toBeGreaterThan(65)->toBeLessThanOrEqual(75);
    } else {
        expect($position['short_at_last_age'])->toBeGreaterThan(0.0);
    }
});

it('closes the gap when the household can afford the whole contribution', function () {
    affordWith(100000.0);
    $position = app(RetirementIncomePosition::class)->for($this->user, analysisFor(targetShortBy($this->user, 2000)));

    expect($position['closes_gap'])->toBeTrue()
        ->and($position['payable_monthly'])->toEqualWithDelta($position['needed_monthly'], 0.01)
        ->and($position['age_to_close'])->toBeNull();
});
