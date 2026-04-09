<?php

declare(strict_types=1);

use App\Models\ProtectionProfile;
use App\Models\User;
use App\Services\Protection\ScenarioBuilder;
use App\Services\TaxConfigService;

beforeEach(function () {
    $mockTaxConfig = Mockery::mock(TaxConfigService::class);
    $mockTaxConfig->shouldReceive('get')
        ->with('protection.withdrawal_rates.scenario', Mockery::any())
        ->andReturn(0.03);
    $mockTaxConfig->shouldReceive('get')
        ->with('protection.premium_factors.base_rate', Mockery::any())
        ->andReturn(0.50);

    $this->builder = new ScenarioBuilder($mockTaxConfig);
});

afterEach(function () {
    Mockery::close();
});

describe('modelDeathScenario', function () {
    it('calculates death scenario with sufficient coverage', function () {
        $user = User::factory()->create();
        $profile = ProtectionProfile::factory()->create([
            'user_id' => $user->id,
            'monthly_expenditure' => 3000,
            'mortgage_balance' => 200000,
            'other_debts' => 25000,
        ]);

        $coverage = [
            'life_coverage' => 500000,
        ];

        $result = $this->builder->modelDeathScenario($profile, $coverage);

        expect($result)->toHaveKeys([
            'scenario_type',
            'payout',
            'debt_clearance',
            'remaining_funds',
            'monthly_income_potential',
            'months_of_support',
            'adequacy',
            'insights',
        ]);
        expect($result['scenario_type'])->toBe('Death');
        expect($result['payout'])->toEqual(500000);
        expect($result['debt_clearance'])->toEqual(225000);
        expect($result['remaining_funds'])->toEqual(275000);
    });

    it('handles insufficient coverage scenario', function () {
        $user = User::factory()->create();
        $profile = ProtectionProfile::factory()->create([
            'user_id' => $user->id,
            'monthly_expenditure' => 3000,
            'mortgage_balance' => 300000,
            'other_debts' => 50000,
        ]);

        $coverage = [
            'life_coverage' => 100000,
        ];

        $result = $this->builder->modelDeathScenario($profile, $coverage);

        expect($result['remaining_funds'])->toEqual(0);
        expect($result['adequacy'])->toBe('Poor');
    });
});

describe('modelCriticalIllnessScenario', function () {
    it('calculates critical illness scenario', function () {
        $user = User::factory()->create();
        $profile = ProtectionProfile::factory()->create([
            'user_id' => $user->id,
            'monthly_expenditure' => 3000,
        ]);

        $coverage = [
            'critical_illness_coverage' => 150000,
        ];

        $result = $this->builder->modelCriticalIllnessScenario($profile, $coverage);

        expect($result)->toHaveKeys([
            'scenario_type',
            'payout',
            'immediate_needs',
            'remaining_funds',
            'months_of_support',
            'adequacy',
            'insights',
        ]);
        expect($result['scenario_type'])->toBe('Critical Illness');
        expect($result['payout'])->toEqual(150000);
        expect($result['immediate_needs'])->toEqual(18000);
    });

    it('handles zero coverage', function () {
        $user = User::factory()->create();
        $profile = ProtectionProfile::factory()->create([
            'user_id' => $user->id,
            'monthly_expenditure' => 3000,
        ]);

        $coverage = [
            'critical_illness_coverage' => 0,
        ];

        $result = $this->builder->modelCriticalIllnessScenario($profile, $coverage);

        expect($result['payout'])->toEqual(0);
        expect($result['adequacy'])->toBe('Poor');
    });
});

describe('modelDisabilityScenario', function () {
    it('calculates disability scenario with good coverage', function () {
        $user = User::factory()->create();
        $profile = ProtectionProfile::factory()->create([
            'user_id' => $user->id,
            'annual_income' => 50000,
            'monthly_expenditure' => 3000,
        ]);

        $coverage = [
            'income_protection_coverage' => 30000, // 60% of income
        ];

        $result = $this->builder->modelDisabilityScenario($profile, $coverage);

        expect($result)->toHaveKeys([
            'scenario_type',
            'annual_benefit',
            'monthly_benefit',
            'monthly_expenditure',
            'monthly_shortfall',
            'income_replacement_ratio',
            'adequacy',
            'insights',
        ]);
        expect($result['scenario_type'])->toBe('Disability');
        expect($result['annual_benefit'])->toEqual(30000);
        expect($result['income_replacement_ratio'])->toEqual(60.0);
        expect($result['adequacy'])->toBe('Excellent');
    });

    it('calculates shortfall when coverage is insufficient', function () {
        $user = User::factory()->create();
        $profile = ProtectionProfile::factory()->create([
            'user_id' => $user->id,
            'annual_income' => 50000,
            'monthly_expenditure' => 3000,
        ]);

        $coverage = [
            'income_protection_coverage' => 24000, // 48% of income, 2000/month
        ];

        $result = $this->builder->modelDisabilityScenario($profile, $coverage);

        expect($result['monthly_benefit'])->toEqual(2000);
        expect($result['monthly_shortfall'])->toEqual(1000);
    });
});

describe('modelPremiumChangeScenario', function () {
    it('calculates premium increase for additional coverage', function () {
        $coverage = [
            'total_coverage' => 300000,
        ];

        $newCoverage = 500000;

        $result = $this->builder->modelPremiumChangeScenario($coverage, $newCoverage);

        expect($result)->toHaveKeys([
            'scenario_type',
            'current_coverage',
            'new_coverage',
            'coverage_increase',
            'coverage_increase_percent',
            'estimated_monthly_premium_increase',
            'estimated_annual_premium_increase',
        ]);
        expect($result['scenario_type'])->toBe('Premium Change');
        expect($result['current_coverage'])->toEqual(300000);
        expect($result['new_coverage'])->toEqual(500000);
        expect($result['coverage_increase'])->toEqual(200000);
    });

    it('handles zero current coverage', function () {
        $coverage = [
            'total_coverage' => 0,
        ];

        $newCoverage = 250000;

        $result = $this->builder->modelPremiumChangeScenario($coverage, $newCoverage);

        expect($result['coverage_increase'])->toEqual(250000);
        expect($result['coverage_increase_percent'])->toEqual(0);
    });
});
