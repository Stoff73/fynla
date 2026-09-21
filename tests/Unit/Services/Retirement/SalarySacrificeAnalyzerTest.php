<?php

declare(strict_types=1);

use App\Models\DCPension;
use App\Models\User;
use App\Services\Retirement\SalarySacrificeAnalyzer;
use Database\Seeders\TaxConfigurationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(fn () => $this->seed(TaxConfigurationSeeder::class));

it('names the cap year from config, not a literal', function () {
    $analyser = app(SalarySacrificeAnalyzer::class);
    $method = new ReflectionMethod($analyser, 'calculateNISavings');
    $method->setAccessible(true);

    $ni = $method->invoke($analyser, 5000.0);

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
