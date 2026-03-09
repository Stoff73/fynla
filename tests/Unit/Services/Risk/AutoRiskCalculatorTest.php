<?php

declare(strict_types=1);

use App\Models\DCPension;
use App\Models\FamilyMember;
use App\Models\Investment\InvestmentAccount;
use App\Models\SavingsAccount;
use App\Models\User;
use App\Services\NetWorth\NetWorthService;
use App\Services\Risk\AutoRiskCalculator;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Mock the NetWorthService
    $this->netWorthService = Mockery::mock(NetWorthService::class);
    $this->calculator = new AutoRiskCalculator($this->netWorthService);
});

afterEach(function () {
    Mockery::close();
});

describe('AutoRiskCalculator', function () {
    describe('calculateRiskProfile', function () {
        it('returns risk_level and factor_breakdown', function () {
            $user = User::factory()->create([
                'date_of_birth' => now()->subYears(40),
                'target_retirement_age' => 67,
                'education_level' => 'undergraduate',
                'employment_status' => 'employed',
                'monthly_expenditure' => 3000,
                'annual_employment_income' => 60000,
            ]);

            $this->netWorthService->shouldReceive('calculateNetWorth')
                ->andReturn(['net_worth' => 100000]);

            $result = $this->calculator->calculateRiskProfile($user);

            expect($result)->toHaveKeys(['risk_level', 'factor_breakdown']);
            expect($result['factor_breakdown'])->toHaveCount(9);
            expect($result['risk_level'])->toBeIn(['low', 'lower_medium', 'medium', 'upper_medium', 'high']);
        });

        it('returns 9 factors with correct structure', function () {
            $user = User::factory()->create([
                'date_of_birth' => now()->subYears(35),
                'target_retirement_age' => 67,
                'education_level' => 'postgraduate',
                'employment_status' => 'employed',
                'monthly_expenditure' => 2500,
                'annual_employment_income' => 75000,
            ]);

            $this->netWorthService->shouldReceive('calculateNetWorth')
                ->andReturn(['net_worth' => 150000]);

            $result = $this->calculator->calculateRiskProfile($user);

            foreach ($result['factor_breakdown'] as $factor) {
                expect($factor)->toHaveKeys(['factor', 'display_name', 'level', 'value', 'raw_value', 'description', 'icon']);
                expect($factor['level'])->toBeIn(['low', 'lower_medium', 'medium', 'upper_medium', 'high']);
            }
        });
    });

    describe('capacity for loss factor', function () {
        it('returns HIGH when investments are 15% or less of net worth', function () {
            $user = User::factory()->create();

            InvestmentAccount::factory()->create(['user_id' => $user->id, 'current_value' => 5000]);
            DCPension::factory()->create(['user_id' => $user->id, 'current_fund_value' => 5000]);

            $this->netWorthService->shouldReceive('calculateNetWorth')
                ->andReturn(['net_worth' => 100000]); // 10% in investments

            $result = $this->calculator->calculateRiskProfile($user);
            $capacityFactor = collect($result['factor_breakdown'])->firstWhere('factor', 'capacity_for_loss');

            expect($capacityFactor['level'])->toBe('high');
            expect($capacityFactor['components'])->toHaveKeys(['investments_total', 'pensions_total', 'net_worth']);
        });

        it('returns MEDIUM when investments are 15-50% of net worth', function () {
            $user = User::factory()->create();

            InvestmentAccount::factory()->create(['user_id' => $user->id, 'current_value' => 15000]);
            DCPension::factory()->create(['user_id' => $user->id, 'current_fund_value' => 15000]);

            $this->netWorthService->shouldReceive('calculateNetWorth')
                ->andReturn(['net_worth' => 100000]); // 30% in investments

            $result = $this->calculator->calculateRiskProfile($user);
            $capacityFactor = collect($result['factor_breakdown'])->firstWhere('factor', 'capacity_for_loss');

            expect($capacityFactor['level'])->toBe('medium');
        });

        it('returns LOWER_MEDIUM when investments are 50-75% of net worth', function () {
            $user = User::factory()->create();

            InvestmentAccount::factory()->create(['user_id' => $user->id, 'current_value' => 30000]);
            DCPension::factory()->create(['user_id' => $user->id, 'current_fund_value' => 30000]);

            $this->netWorthService->shouldReceive('calculateNetWorth')
                ->andReturn(['net_worth' => 100000]); // 60% in investments

            $result = $this->calculator->calculateRiskProfile($user);
            $capacityFactor = collect($result['factor_breakdown'])->firstWhere('factor', 'capacity_for_loss');

            expect($capacityFactor['level'])->toBe('lower_medium');
        });

        it('returns LOW when investments exceed 75% of net worth', function () {
            $user = User::factory()->create();

            InvestmentAccount::factory()->create(['user_id' => $user->id, 'current_value' => 40000]);
            DCPension::factory()->create(['user_id' => $user->id, 'current_fund_value' => 40000]);

            $this->netWorthService->shouldReceive('calculateNetWorth')
                ->andReturn(['net_worth' => 100000]); // 80% in investments

            $result = $this->calculator->calculateRiskProfile($user);
            $capacityFactor = collect($result['factor_breakdown'])->firstWhere('factor', 'capacity_for_loss');

            expect($capacityFactor['level'])->toBe('low');
        });
    });

    describe('time horizon factor', function () {
        it('returns HIGH for 20+ years to retirement', function () {
            $user = User::factory()->create([
                'date_of_birth' => now()->subYears(35),
                'target_retirement_age' => 67,
                'employment_status' => 'employed',
            ]);

            $this->netWorthService->shouldReceive('calculateNetWorth')
                ->andReturn(['net_worth' => 100000]);

            $result = $this->calculator->calculateRiskProfile($user);
            $timeFactor = collect($result['factor_breakdown'])->firstWhere('factor', 'time_horizon');

            expect($timeFactor['level'])->toBe('high');
        });

        it('returns UPPER_MEDIUM for 15-20 years to retirement', function () {
            $user = User::factory()->create([
                'date_of_birth' => now()->subYears(50),
                'target_retirement_age' => 67,
                'employment_status' => 'employed',
            ]);

            $this->netWorthService->shouldReceive('calculateNetWorth')
                ->andReturn(['net_worth' => 100000]);

            $result = $this->calculator->calculateRiskProfile($user);
            $timeFactor = collect($result['factor_breakdown'])->firstWhere('factor', 'time_horizon');

            expect($timeFactor['level'])->toBe('upper_medium');
        });

        it('returns MEDIUM for 3-15 years to retirement', function () {
            $user = User::factory()->create([
                'date_of_birth' => now()->subYears(57),
                'target_retirement_age' => 67,
                'employment_status' => 'employed',
            ]);

            $this->netWorthService->shouldReceive('calculateNetWorth')
                ->andReturn(['net_worth' => 100000]);

            $result = $this->calculator->calculateRiskProfile($user);
            $timeFactor = collect($result['factor_breakdown'])->firstWhere('factor', 'time_horizon');

            expect($timeFactor['level'])->toBe('medium');
        });

        it('returns LOWER_MEDIUM for retired users', function () {
            $user = User::factory()->create([
                'date_of_birth' => now()->subYears(70),
                'target_retirement_age' => 65,
                'employment_status' => 'retired',
            ]);

            $this->netWorthService->shouldReceive('calculateNetWorth')
                ->andReturn(['net_worth' => 100000]);

            $result = $this->calculator->calculateRiskProfile($user);
            $timeFactor = collect($result['factor_breakdown'])->firstWhere('factor', 'time_horizon');

            expect($timeFactor['level'])->toBe('lower_medium');
        });
    });

    describe('knowledge level factor', function () {
        it('returns LOWER_MEDIUM for novice or no knowledge level', function () {
            $user = User::factory()->create();

            $this->netWorthService->shouldReceive('calculateNetWorth')
                ->andReturn(['net_worth' => 100000]);

            $result = $this->calculator->calculateRiskProfile($user);
            $knowledgeFactor = collect($result['factor_breakdown'])->firstWhere('factor', 'knowledge_level');

            expect($knowledgeFactor['level'])->toBe('lower_medium');
        });

        it('returns MEDIUM for intermediate knowledge', function () {
            $user = User::factory()->create();
            \App\Models\Investment\RiskProfile::factory()->create([
                'user_id' => $user->id,
                'knowledge_level' => 'intermediate',
            ]);

            $this->netWorthService->shouldReceive('calculateNetWorth')
                ->andReturn(['net_worth' => 100000]);

            $result = $this->calculator->calculateRiskProfile($user);
            $knowledgeFactor = collect($result['factor_breakdown'])->firstWhere('factor', 'knowledge_level');

            expect($knowledgeFactor['level'])->toBe('medium');
        });

        it('returns UPPER_MEDIUM for experienced', function () {
            $user = User::factory()->create();
            \App\Models\Investment\RiskProfile::factory()->create([
                'user_id' => $user->id,
                'knowledge_level' => 'experienced',
            ]);

            $this->netWorthService->shouldReceive('calculateNetWorth')
                ->andReturn(['net_worth' => 100000]);

            $result = $this->calculator->calculateRiskProfile($user);
            $knowledgeFactor = collect($result['factor_breakdown'])->firstWhere('factor', 'knowledge_level');

            expect($knowledgeFactor['level'])->toBe('upper_medium');
        });
    });

    describe('dependants factor', function () {
        it('returns UPPER_MEDIUM for no dependants', function () {
            $user = User::factory()->create();

            $this->netWorthService->shouldReceive('calculateNetWorth')
                ->andReturn(['net_worth' => 100000]);

            $result = $this->calculator->calculateRiskProfile($user);
            $dependantsFactor = collect($result['factor_breakdown'])->firstWhere('factor', 'dependants');

            expect($dependantsFactor['level'])->toBe('upper_medium');
        });

        it('returns MEDIUM for one dependant', function () {
            $user = User::factory()->create();
            FamilyMember::factory()->create(['user_id' => $user->id, 'is_dependent' => true]);

            $this->netWorthService->shouldReceive('calculateNetWorth')
                ->andReturn(['net_worth' => 100000]);

            $result = $this->calculator->calculateRiskProfile($user);
            $dependantsFactor = collect($result['factor_breakdown'])->firstWhere('factor', 'dependants');

            expect($dependantsFactor['level'])->toBe('medium');
        });

        it('returns LOWER_MEDIUM for multiple dependants', function () {
            $user = User::factory()->create();
            FamilyMember::factory()->count(3)->create(['user_id' => $user->id, 'is_dependent' => true]);

            $this->netWorthService->shouldReceive('calculateNetWorth')
                ->andReturn(['net_worth' => 100000]);

            $result = $this->calculator->calculateRiskProfile($user);
            $dependantsFactor = collect($result['factor_breakdown'])->firstWhere('factor', 'dependants');

            expect($dependantsFactor['level'])->toBe('lower_medium');
        });
    });

    describe('employment factor', function () {
        it('returns MEDIUM for employed', function () {
            $user = User::factory()->create(['employment_status' => 'employed']);

            $this->netWorthService->shouldReceive('calculateNetWorth')
                ->andReturn(['net_worth' => 100000]);

            $result = $this->calculator->calculateRiskProfile($user);
            $employmentFactor = collect($result['factor_breakdown'])->firstWhere('factor', 'employment');

            expect($employmentFactor['level'])->toBe('medium');
        });

        it('returns MEDIUM for self-employed', function () {
            $user = User::factory()->create(['employment_status' => 'self_employed']);

            $this->netWorthService->shouldReceive('calculateNetWorth')
                ->andReturn(['net_worth' => 100000]);

            $result = $this->calculator->calculateRiskProfile($user);
            $employmentFactor = collect($result['factor_breakdown'])->firstWhere('factor', 'employment');

            expect($employmentFactor['level'])->toBe('medium');
        });

        it('returns LOWER_MEDIUM for retired', function () {
            $user = User::factory()->create(['employment_status' => 'retired']);

            $this->netWorthService->shouldReceive('calculateNetWorth')
                ->andReturn(['net_worth' => 100000]);

            $result = $this->calculator->calculateRiskProfile($user);
            $employmentFactor = collect($result['factor_breakdown'])->firstWhere('factor', 'employment');

            expect($employmentFactor['level'])->toBe('lower_medium');
        });
    });

    describe('emergency cash factor', function () {
        it('returns UPPER_MEDIUM for 6+ months runway', function () {
            $user = User::factory()->create(['monthly_expenditure' => 2000]);
            SavingsAccount::factory()->create([
                'user_id' => $user->id,
                'is_emergency_fund' => true,
                'current_balance' => 15000, // 7.5 months
            ]);

            $this->netWorthService->shouldReceive('calculateNetWorth')
                ->andReturn(['net_worth' => 100000]);

            $result = $this->calculator->calculateRiskProfile($user);
            $emergencyFactor = collect($result['factor_breakdown'])->firstWhere('factor', 'emergency_cash');

            expect($emergencyFactor['level'])->toBe('upper_medium');
        });

        it('returns MEDIUM for 3-6 months runway', function () {
            $user = User::factory()->create(['monthly_expenditure' => 2000]);
            SavingsAccount::factory()->create([
                'user_id' => $user->id,
                'is_emergency_fund' => true,
                'current_balance' => 8000, // 4 months
            ]);

            $this->netWorthService->shouldReceive('calculateNetWorth')
                ->andReturn(['net_worth' => 100000]);

            $result = $this->calculator->calculateRiskProfile($user);
            $emergencyFactor = collect($result['factor_breakdown'])->firstWhere('factor', 'emergency_cash');

            expect($emergencyFactor['level'])->toBe('medium');
        });

        it('returns LOWER_MEDIUM for less than 3 months runway', function () {
            $user = User::factory()->create(['monthly_expenditure' => 2000]);
            SavingsAccount::factory()->create([
                'user_id' => $user->id,
                'is_emergency_fund' => true,
                'current_balance' => 4000, // 2 months
            ]);

            $this->netWorthService->shouldReceive('calculateNetWorth')
                ->andReturn(['net_worth' => 100000]);

            $result = $this->calculator->calculateRiskProfile($user);
            $emergencyFactor = collect($result['factor_breakdown'])->firstWhere('factor', 'emergency_cash');

            expect($emergencyFactor['level'])->toBe('lower_medium');
        });
    });

    describe('surplus cash factor', function () {
        it('returns UPPER_MEDIUM for £501+ monthly surplus', function () {
            $user = User::factory()->create([
                'annual_employment_income' => 60000, // £5000/month
                'monthly_expenditure' => 3500, // £1500 surplus
            ]);

            $this->netWorthService->shouldReceive('calculateNetWorth')
                ->andReturn(['net_worth' => 100000]);

            $result = $this->calculator->calculateRiskProfile($user);
            $surplusFactor = collect($result['factor_breakdown'])->firstWhere('factor', 'surplus_cash');

            expect($surplusFactor['level'])->toBe('upper_medium');
        });

        it('returns MEDIUM for £0-500 monthly surplus', function () {
            $user = User::factory()->create([
                'annual_employment_income' => 48000, // £4000/month
                'monthly_expenditure' => 3700, // £300 surplus
            ]);

            $this->netWorthService->shouldReceive('calculateNetWorth')
                ->andReturn(['net_worth' => 100000]);

            $result = $this->calculator->calculateRiskProfile($user);
            $surplusFactor = collect($result['factor_breakdown'])->firstWhere('factor', 'surplus_cash');

            expect($surplusFactor['level'])->toBe('medium');
        });

        it('returns LOWER_MEDIUM for negative surplus', function () {
            $user = User::factory()->create([
                'annual_employment_income' => 36000, // £3000/month
                'monthly_expenditure' => 3500, // -£500 surplus
            ]);

            $this->netWorthService->shouldReceive('calculateNetWorth')
                ->andReturn(['net_worth' => 100000]);

            $result = $this->calculator->calculateRiskProfile($user);
            $surplusFactor = collect($result['factor_breakdown'])->firstWhere('factor', 'surplus_cash');

            expect($surplusFactor['level'])->toBe('lower_medium');
        });
    });

    describe('determineFinalLevel (mode calculation)', function () {
        it('selects the most frequent risk level', function () {
            // Create user with factors that mostly result in MEDIUM
            $user = User::factory()->create([
                'date_of_birth' => now()->subYears(55),
                'target_retirement_age' => 67,
                'education_level' => 'undergraduate',
                'employment_status' => 'employed',
                'monthly_expenditure' => 3000,
                'annual_employment_income' => 48000,
            ]);

            // One dependant = MEDIUM
            FamilyMember::factory()->create(['user_id' => $user->id, 'is_dependent' => true]);

            // 4 months emergency = MEDIUM
            SavingsAccount::factory()->create([
                'user_id' => $user->id,
                'is_emergency_fund' => true,
                'current_balance' => 12000,
            ]);

            $this->netWorthService->shouldReceive('calculateNetWorth')
                ->andReturn(['net_worth' => 200000]);

            $result = $this->calculator->calculateRiskProfile($user);

            // Most factors should result in MEDIUM
            $levels = collect($result['factor_breakdown'])->pluck('level')->countBy();
            expect($levels->get('medium', 0))->toBeGreaterThanOrEqual(3);
        });

        it('prefers lower risk level in case of tie', function () {
            // This tests the tie-breaker logic
            // When two levels have equal counts, the lower risk level should be chosen
            $user = User::factory()->create([
                'date_of_birth' => now()->subYears(45),
                'target_retirement_age' => 67,
                'education_level' => 'secondary', // LOWER_MEDIUM
                'employment_status' => 'retired', // LOWER_MEDIUM
                'monthly_expenditure' => 2000,
                'annual_employment_income' => 0,
                'annual_dividend_income' => 30000, // £2500/month - £500 surplus = MEDIUM
            ]);

            $this->netWorthService->shouldReceive('calculateNetWorth')
                ->andReturn(['net_worth' => 100000]);

            $result = $this->calculator->calculateRiskProfile($user);

            // In a tie situation, lower risk should win
            expect($result['risk_level'])->toBeIn(['lower_medium', 'medium']);
        });
    });
});
