<?php

declare(strict_types=1);

use App\Models\DCPension;
use App\Models\User;
use App\Services\Retirement\PensionProjector;
use App\Services\Retirement\RetirementIncomePosition;
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
function affordWith(?float $net): void
{
    $mock = Mockery::mock(PensionAffordability::class);
    $mock->shouldReceive('moneyThisYear')->andReturn($net);
    app()->instance(PensionAffordability::class, $mock);
}

function analysisFor(float $gap, int $years = 20, float $remaining = 60000): array
{
    return [
        'summary' => [
            'income_gap' => $gap, 'target_retirement_income' => 30000, 'target_retirement_age' => 65,
            'projected_retirement_income' => 30000 - $gap, 'years_to_retirement' => $years,
        ],
        'annual_allowance' => ['remaining_allowance' => $remaining, 'carry_forward_available' => 0, 'total_contributions' => 4400],
    ];
}

it('states the page\'s own shortfall, target and projection', function () {
    affordWith(null);
    $position = app(RetirementIncomePosition::class)->for($this->user, analysisFor(8000));

    expect($position['shortfall'])->toBe(8000.0)
        ->and($position['target_income'])->toBe(30000.0)
        ->and($position['projected_income'])->toBe(22000.0)
        ->and($position['target_age'])->toBe(65);
});

it('returns nothing when the projection meets the target', function () {
    affordWith(null);
    expect(app(RetirementIncomePosition::class)->for($this->user, analysisFor(0)))->toBeNull();
});

it('works out the contribution that buys the shortfall back, by inverting the projection', function () {
    affordWith(null);
    $position = app(RetirementIncomePosition::class)->for($this->user, analysisFor(8000));
    $projector = app(PensionProjector::class);

    // What the contribution adds to the pot, turned into income at the same rate, is the shortfall.
    $income = $projector->potFromExtraContribution($this->user->id, $position['needed_monthly'] * 12, 20) * $projector->safeWithdrawalRate();
    expect($income)->toEqualWithDelta(8000.0, 1.0);

    // And it moves with the shortfall.
    $double = app(RetirementIncomePosition::class)->for($this->user, analysisFor(16000));
    expect($double['needed_monthly'])->toEqualWithDelta($position['needed_monthly'] * 2, 0.02);
});

it('offers no amount to pay until spending is recorded', function () {
    affordWith(null);
    $position = app(RetirementIncomePosition::class)->for($this->user, analysisFor(8000));

    expect($position['affordability_known'])->toBeFalse()
        ->and($position['payable_monthly'])->toBeNull()
        ->and($position['closes_gap'])->toBeFalse();
});

it('caps what can be paid at what the household can afford', function () {
    affordWith(1200.0); // £100 a month net
    $position = app(RetirementIncomePosition::class)->for($this->user, analysisFor(8000));
    $basic = 0.20;

    expect($position['payable_monthly'])->toEqualWithDelta(100 / (1 - $basic), 0.01)
        ->and($position['payable_net_monthly'])->toEqualWithDelta(100.0, 0.01)
        ->and($position['closes_gap'])->toBeFalse();
});

it('caps what can be paid at relevant UK earnings (FA 2004 s190)', function () {
    $this->user->update(['annual_employment_income' => 9000]);
    affordWith(100000.0);
    $position = app(RetirementIncomePosition::class)->for($this->user->fresh(), analysisFor(20000, 20, 59460));

    expect($position['limit_monthly'])->toEqualWithDelta(750.0, 0.01)
        ->and($position['payable_monthly'])->toBeLessThanOrEqual(750.0)
        ->and($position['payable_monthly'])->toBeGreaterThan(0.0);
});

it('floors the cap at the basic amount for someone with no earnings', function () {
    $this->user->update(['annual_employment_income' => 0]);
    affordWith(100000.0);
    $position = app(RetirementIncomePosition::class)->for($this->user->fresh(), analysisFor(20000, 20, 58800));

    expect($position['limit_monthly'])->toEqualWithDelta(300.0, 0.01);
});

it('caps at the Money Purchase Annual Allowance once a pension is flexibly accessed', function () {
    DCPension::create([
        'user_id' => $this->user->id, 'scheme_name' => 'Old SIPP', 'scheme_type' => 'sipp', 'pension_type' => 'sipp',
        'current_fund_value' => 20000, 'has_flexibly_accessed' => true,
    ]);
    affordWith(100000.0);
    $position = app(RetirementIncomePosition::class)->for($this->user->fresh(), analysisFor(20000));

    // £10,000 less the £4,400 already paid in.
    expect($position['limit_monthly'])->toEqualWithDelta(5600 / 12, 0.01);
});

it('gives the retirement age that closes what the affordable payment cannot', function () {
    affordWith(1200.0);
    $position = app(RetirementIncomePosition::class)->for($this->user, analysisFor(3000));

    expect($position['closes_gap'])->toBeFalse();
    if ($position['age_to_close'] !== null) {
        expect($position['age_to_close'])->toBeGreaterThan(65)->toBeLessThanOrEqual(75);
    } else {
        expect($position['short_at_last_age'])->toBeGreaterThan(0.0);
    }
});

it('closes the gap when the household can afford the whole contribution', function () {
    affordWith(100000.0);
    $position = app(RetirementIncomePosition::class)->for($this->user, analysisFor(2000));

    expect($position['closes_gap'])->toBeTrue()
        ->and($position['payable_monthly'])->toEqualWithDelta($position['needed_monthly'], 0.01)
        ->and($position['age_to_close'])->toBeNull();
});
