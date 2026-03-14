<?php

declare(strict_types=1);

use App\Agents\RetirementAgent;
use App\Models\DCPension;
use App\Models\RetirementProfile;
use App\Models\StatePension;
use App\Models\User;
use Database\Seeders\TaxConfigurationSeeder;

beforeEach(function () {
    $this->seed(TaxConfigurationSeeder::class);
    // Default user attributes to pass retirement data readiness blocking checks
    $this->readyUserAttrs = [
        'date_of_birth' => now()->subYears(60),
        'annual_employment_income' => 50000,
    ];
});

describe('DecumulationPlanner Integration with RetirementAgent', function () {
    it('includes decumulation data when user is within 10 years of retirement', function () {
        $user = User::factory()->create($this->readyUserAttrs);
        RetirementProfile::factory()->create([
            'user_id' => $user->id,
            'current_age' => 60,
            'target_retirement_age' => 67,
            'target_retirement_income' => 30000,
            'life_expectancy' => 85,
        ]);
        DCPension::factory()->create([
            'user_id' => $user->id,
            'current_fund_value' => 300000,
        ]);
        StatePension::factory()->create([
            'user_id' => $user->id,
        ]);

        $agent = app(RetirementAgent::class);
        $result = $agent->analyze($user->id);

        expect($result['success'])->toBeTrue()
            ->and($result['data'])->toHaveKey('decumulation')
            ->and($result['data']['decumulation'])->not->toBeNull()
            ->and($result['data']['decumulation'])->toHaveKeys([
                'withdrawal_rates',
                'annuity_vs_drawdown',
                'pcls_strategy',
                'income_phasing',
            ]);
    });

    it('includes decumulation data when user is already retired', function () {
        $user = User::factory()->create($this->readyUserAttrs);
        RetirementProfile::factory()->create([
            'user_id' => $user->id,
            'current_age' => 68,
            'target_retirement_age' => 67,
            'target_retirement_income' => 25000,
            'life_expectancy' => 85,
        ]);
        DCPension::factory()->create([
            'user_id' => $user->id,
            'current_fund_value' => 250000,
        ]);
        StatePension::factory()->create([
            'user_id' => $user->id,
        ]);

        $agent = app(RetirementAgent::class);
        $result = $agent->analyze($user->id);

        expect($result['success'])->toBeTrue()
            ->and($result['data']['decumulation'])->not->toBeNull()
            ->and($result['data']['decumulation']['withdrawal_rates']['scenarios'])->toHaveCount(3);
    });

    it('excludes decumulation data when user is far from retirement', function () {
        $user = User::factory()->create(array_merge($this->readyUserAttrs, ['date_of_birth' => now()->subYears(30)]));
        RetirementProfile::factory()->create([
            'user_id' => $user->id,
            'current_age' => 30,
            'target_retirement_age' => 67,
            'target_retirement_income' => 35000,
        ]);
        DCPension::factory()->create([
            'user_id' => $user->id,
            'current_fund_value' => 50000,
        ]);
        StatePension::factory()->create([
            'user_id' => $user->id,
        ]);

        $agent = app(RetirementAgent::class);
        $result = $agent->analyze($user->id);

        expect($result['success'])->toBeTrue()
            ->and($result['data']['decumulation'])->toBeNull();
    });

    it('excludes decumulation data when user has no DC pension value', function () {
        $user = User::factory()->create($this->readyUserAttrs);
        RetirementProfile::factory()->create([
            'user_id' => $user->id,
            'current_age' => 62,
            'target_retirement_age' => 67,
            'target_retirement_income' => 30000,
        ]);
        DCPension::factory()->create([
            'user_id' => $user->id,
            'current_fund_value' => 0,
        ]);
        StatePension::factory()->create([
            'user_id' => $user->id,
        ]);

        $agent = app(RetirementAgent::class);
        $result = $agent->analyze($user->id);

        expect($result['success'])->toBeTrue()
            ->and($result['data']['decumulation'])->toBeNull();
    });

    it('calculates safe withdrawal rate within decumulation analysis', function () {
        $user = User::factory()->create($this->readyUserAttrs);
        RetirementProfile::factory()->create([
            'user_id' => $user->id,
            'current_age' => 63,
            'target_retirement_age' => 67,
            'target_retirement_income' => 30000,
            'life_expectancy' => 90,
        ]);
        DCPension::factory()->create([
            'user_id' => $user->id,
            'current_fund_value' => 500000,
        ]);
        StatePension::factory()->create([
            'user_id' => $user->id,
        ]);

        $agent = app(RetirementAgent::class);
        $result = $agent->analyze($user->id);

        $withdrawalRates = $result['data']['decumulation']['withdrawal_rates'];

        expect($withdrawalRates)->toHaveKeys(['scenarios', 'recommended_rate'])
            ->and($withdrawalRates['scenarios'])->toHaveCount(3)
            ->and($withdrawalRates['recommended_rate'])->toBeGreaterThanOrEqual(3.0)
            ->and($withdrawalRates['recommended_rate'])->toBeLessThanOrEqual(5.0);
    });

    it('includes PCLS strategy in decumulation analysis', function () {
        $user = User::factory()->create($this->readyUserAttrs);
        RetirementProfile::factory()->create([
            'user_id' => $user->id,
            'current_age' => 65,
            'target_retirement_age' => 67,
            'target_retirement_income' => 25000,
            'life_expectancy' => 85,
        ]);
        DCPension::factory()->create([
            'user_id' => $user->id,
            'current_fund_value' => 400000,
        ]);
        StatePension::factory()->create([
            'user_id' => $user->id,
        ]);

        $agent = app(RetirementAgent::class);
        $result = $agent->analyze($user->id);

        $pcls = $result['data']['decumulation']['pcls_strategy'];

        expect($pcls)->toHaveKeys(['pension_value', 'pcls_amount', 'remaining_pot', 'tax_saving'])
            ->and($pcls['pcls_amount'])->toBe(100000.0); // 25% of £400k
    });
});
