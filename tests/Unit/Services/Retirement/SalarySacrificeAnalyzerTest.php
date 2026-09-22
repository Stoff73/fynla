<?php

declare(strict_types=1);

use App\Models\DCPension;
use App\Models\User;
use App\Services\Retirement\SalarySacrificeAnalyzer;
use App\Services\Tax\IncomeDefinitionsService;
use App\Services\Tax\Thresholds\Lines\SalarySacrificeNiCapLine;
use App\Services\Tax\Thresholds\ThresholdContext;
use App\Services\UKTaxCalculator;
use Carbon\Carbon;
use Database\Seeders\TaxConfigurationSeeder;
use Database\Seeders\TierConfigurationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(fn () => $this->seed(TaxConfigurationSeeder::class));

it('names the cap year from config, not a literal', function () {
    $analyser = app(SalarySacrificeAnalyzer::class);
    $method = new ReflectionMethod($analyser, 'calculateNISavings');
    $method->setAccessible(true);

    $ni = $method->invoke($analyser, 5000.0, 60000.0);

    expect($ni['exceeds_nic_cap'])->toBeTrue()
        ->and($ni['nic_cap_effective_year'])->toBe(2027)
        ->and($ni)->not->toHaveKey('post_2029_employee');
});

it('carries the renamed cap keys and the config year through the public analyser output', function () {
    $user = User::factory()->create(['annual_employment_income' => 60000]);
    $pension = DCPension::factory()->for($user)->create([
        'scheme_type' => 'workplace',
        'annual_salary' => 50000,
        'employee_contribution_percent' => 10,   // £5,000/year sacrifice — exceeds the £2,000 cap
        'monthly_contribution_amount' => 0,
    ]);

    $analyser = app(SalarySacrificeAnalyzer::class);
    $result = $analyser->analyzeForPension($user, $pension);

    expect($result['current_employee_contribution'])->toBe(5000.0)
        ->and($result)->toHaveKeys(['post_cap_employee_ni_saving', 'post_cap_total_ni_saving', 'exceeds_nic_cap', 'nic_cap_effective_year'])
        ->and($result['exceeds_nic_cap'])->toBeTrue()
        ->and($result['nic_cap_effective_year'])->toBe(2027);

    $warningText = collect($result['warnings'])->pluck('message')->implode(' ');
    expect($warningText)->toContain('From April 2027');
});

it('prices the employee National Insurance saving through the calculator, not a flat rate', function () {
    $user = User::factory()->create(['annual_employment_income' => 60000]);
    $pension = DCPension::factory()->for($user)->create([
        'scheme_type' => 'workplace',
        'annual_salary' => 50000,
        'employee_contribution_percent' => 10,   // £5,000/year sacrifice
        'monthly_contribution_amount' => 0,
    ]);

    $result = app(SalarySacrificeAnalyzer::class)->analyzeForPension($user, $pension);

    // The whole £5,000 sits above the upper earnings limit, where the employee rate is
    // 2%, so the saving is £100 and not the £400 a flat main rate would claim. Both
    // figures come from the calculator here rather than being written in.
    $calculator = app(UKTaxCalculator::class);
    $classOne = fn (float $pay): float => (float) $calculator->calculateNetIncome($pay)['breakdown']['class_1_ni'];
    $expected = round($classOne(60000.0) - $classOne(55000.0), 2);

    expect($expected)->toBe(100.0)
        ->and($result['employee_ni_saving'])->toBe($expected)
        // Only the first £2,000 stays exempt after the cap.
        ->and($result['post_cap_employee_ni_saving'])->toBe(round($classOne(60000.0) - $classOne(58000.0), 2));
});

it('agrees with the threshold line on what the cap costs a £145,000 sacrificer', function () {
    Carbon::setTestNow('2026-09-21');
    $this->seed(TierConfigurationSeeder::class);

    $user = User::factory()->create(['annual_employment_income' => 145000, 'employment_income_basis' => 'gross']);
    $pension = DCPension::factory()->for($user)->create([
        'scheme_type' => 'workplace',
        'pension_type' => 'occupational',
        'annual_salary' => 145000,
        'employee_contribution_percent' => 20.69,
        'employer_contribution_percent' => 0,
        'monthly_contribution_amount' => 0,
        'salary_sacrifice' => true,
    ]);

    $analysis = app(SalarySacrificeAnalyzer::class)->analyzeForPension($user, $pension);
    $line = app(SalarySacrificeNiCapLine::class)->evaluate(
        new ThresholdContext($user, app(IncomeDefinitionsService::class)->calculate($user->id))
    );

    // Two surfaces, one fact: what the user loses when the cap arrives is the saving
    // they have now less the saving that survives it. Before this they disagreed by
    // a factor of four, because the analyser used the 8% main rate on pay that is
    // taxed at 2%.
    $lost = round($analysis['employee_ni_saving'] - $analysis['post_cap_employee_ni_saving'], 2);

    expect($line)->not->toBeNull()
        ->and($lost)->toBe($line->cost->total())
        ->and($lost)->toBeGreaterThan(0.0);

    Carbon::setTestNow();
});

it('reads a post-sacrifice recorded income back to gross pay, and a gross one down to pay after sacrifice', function () {
    $pension = [
        'scheme_type' => 'workplace',
        'annual_salary' => 50000,
        'employee_contribution_percent' => 10,   // £5,000 sacrificed
        'monthly_contribution_amount' => 0,
        'salary_sacrifice' => true,
    ];
    $net = User::factory()->create(['annual_employment_income' => 50000, 'employment_income_basis' => 'post_sacrifice']);
    DCPension::factory()->for($net)->create($pension);
    $gross = User::factory()->create(['annual_employment_income' => 55000, 'employment_income_basis' => 'gross']);
    DCPension::factory()->for($gross)->create($pension);

    $analyser = app(SalarySacrificeAnalyzer::class);
    $classOne = fn (float $pay) => (float) app(UKTaxCalculator::class)->calculateNetIncome($pay)['breakdown']['class_1_ni'];

    // One household fact recorded two ways lands on one pair of figures.
    foreach ([$net, $gross] as $user) {
        expect($analyser->payBeforeSacrifice($user))->toBe(55000.0)
            ->and($analyser->payAfterSacrifice($user))->toBe(50000.0)
            ->and($analyser->analyzeForPension($user, $user->dcPensions()->first())['employee_ni_saving'])
            ->toBe(round($classOne(55000.0) - $classOne(50000.0), 2));
    }
});
