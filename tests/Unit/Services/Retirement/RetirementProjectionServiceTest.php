<?php

declare(strict_types=1);

use App\Models\DBPension;
use App\Models\DCPension;
use App\Models\StatePension;
use App\Models\User;
use App\Services\Goals\LifeEventCashFlowService;
use App\Services\Investment\MonteCarloSimulator;
use App\Services\Retirement\RequiredCapitalCalculator;
use App\Services\Retirement\RetirementProjectionService;
use App\Services\Risk\RiskPreferenceService;
use App\Services\TaxConfigService;
use Carbon\Carbon;

beforeEach(function () {
    // Create test user
    $this->user = User::factory()->create([
        'date_of_birth' => Carbon::now()->subYears(45),
        'marital_status' => 'single',
        'target_retirement_age' => 65,
    ]);

    // Mock dependencies
    $this->mockRiskService = Mockery::mock(RiskPreferenceService::class);
    $this->mockRiskService->shouldReceive('getReturnParameters')
        ->andReturn([
            'expected_return_typical' => 7.0,
            'expected_return_min' => 4.0,
            'volatility' => 15.0,
        ]);
    $this->mockRiskService->shouldReceive('getRiskProfile')
        ->andReturn(['risk_level' => 'medium']);

    $this->mockSimulator = Mockery::mock(MonteCarloSimulator::class);
    $this->mockSimulator->shouldReceive('simulate')
        ->andReturn([
            'year_by_year' => [
                [
                    'year' => 1,
                    'percentiles' => [
                        ['percentile' => '10th', 'value' => 105000],
                        ['percentile' => '25th', 'value' => 110000],
                        ['percentile' => '50th', 'value' => 120000],
                        ['percentile' => '75th', 'value' => 130000],
                        ['percentile' => '90th', 'value' => 140000],
                    ],
                ],
                [
                    'year' => 20,
                    'percentiles' => [
                        ['percentile' => '10th', 'value' => 300000],
                        ['percentile' => '25th', 'value' => 350000],
                        ['percentile' => '50th', 'value' => 450000],
                        ['percentile' => '75th', 'value' => 550000],
                        ['percentile' => '90th', 'value' => 650000],
                    ],
                ],
            ],
        ]);

    $this->mockRequiredCapitalCalculator = Mockery::mock(RequiredCapitalCalculator::class);
    $this->mockRequiredCapitalCalculator->shouldReceive('calculate')
        ->andReturn([
            'required_income' => 30000.0,
            'required_capital_at_retirement' => 600000.0,
            'required_capital_today' => 400000.0,
            'assumptions' => [],
            'retirement_info' => [],
            'year_by_year' => [],
        ]);

    $this->mockLifeEventCashFlowService = Mockery::mock(LifeEventCashFlowService::class);
    $this->mockLifeEventCashFlowService->shouldReceive('buildCashFlowMap')->andReturn([]);
    $this->mockLifeEventCashFlowService->shouldReceive('buildDrawdownCashFlowMap')->andReturn([]);
    $this->mockLifeEventCashFlowService->shouldReceive('getEventHash')->andReturn('noevents');
    $this->mockLifeEventCashFlowService->shouldReceive('getAppliedEvents')->andReturn([]);

    $this->mockTaxConfig = Mockery::mock(TaxConfigService::class);
    $this->mockTaxConfig->shouldReceive('get')->with('retirement.withdrawal_rates.sustainable', 0.047)->andReturn(0.047);
    $this->mockTaxConfig->shouldReceive('get')->with('retirement.projection_end_age', 100)->andReturn(100);
    $this->mockTaxConfig->shouldReceive('get')->with('retirement.monte_carlo_iterations', 1000)->andReturn(1000);
    $this->mockTaxConfig->shouldReceive('get')->with('assumptions.inflation', 0.025)->andReturn(0.025);
    $this->mockTaxConfig->shouldReceive('get')->with('retirement.target_income_percent', 0.75)->andReturn(0.75);

    $this->service = new RetirementProjectionService(
        $this->mockSimulator,
        $this->mockRiskService,
        $this->mockTaxConfig,
        $this->mockLifeEventCashFlowService,
        app(\App\Services\Cache\CacheInvalidationService::class),
        $this->mockRequiredCapitalCalculator
    );
});

describe('projectPensionPot', function () {
    it('projects DC pension pot correctly', function () {
        // Create DC pension for user
        DCPension::factory()->create([
            'user_id' => $this->user->id,
            'current_fund_value' => 100000,
            'monthly_contribution_amount' => 500,
        ]);

        $this->user->load('dcPensions');

        $result = $this->service->projectPensionPot($this->user);

        expect($result)->toHaveKeys([
            'current_value',
            'monthly_contribution',
            'risk_level',
            'expected_return',
            'volatility',
            'years_to_retirement',
            'retirement_age',
            'current_age',
            'percentile_20_at_retirement',
            'median_at_retirement',
            'year_by_year',
            'dc_pension_count',
        ])
            ->and($result['current_value'])->toBe(100000.0)
            ->and($result['monthly_contribution'])->toBe(500.0)
            ->and($result['retirement_age'])->toBe(65)
            ->and($result['current_age'])->toBe(45)
            ->and($result['years_to_retirement'])->toBe(20)
            ->and($result['dc_pension_count'])->toBe(1);
    });

    it('handles multiple DC pensions', function () {
        DCPension::factory()->create([
            'user_id' => $this->user->id,
            'current_fund_value' => 75000,
            'monthly_contribution_amount' => 400,
        ]);
        DCPension::factory()->create([
            'user_id' => $this->user->id,
            'current_fund_value' => 50000,
            'monthly_contribution_amount' => 300,
        ]);

        $this->user->load('dcPensions');

        $result = $this->service->projectPensionPot($this->user);

        expect($result['current_value'])->toBe(125000.0)
            ->and($result['monthly_contribution'])->toBe(700.0)
            ->and($result['dc_pension_count'])->toBe(2);
    });

    it('uses percentage-based contributions for occupational pensions', function () {
        DCPension::factory()->create([
            'user_id' => $this->user->id,
            'current_fund_value' => 80000,
            'annual_salary' => 60000,
            'employee_contribution_percent' => 5,
            'employer_contribution_percent' => 3,
            'monthly_contribution_amount' => null,
        ]);

        $this->user->load('dcPensions');

        $result = $this->service->projectPensionPot($this->user);

        // 5% + 3% of £60,000 = £4,800/year = £400/month
        expect($result['monthly_contribution'])->toBe(400.0);
    });

    it('handles zero current age gracefully', function () {
        $this->user->date_of_birth = null;
        $this->user->save();

        DCPension::factory()->create([
            'user_id' => $this->user->id,
            'current_fund_value' => 50000,
            'monthly_contribution_amount' => 200,
        ]);

        $this->user->load('dcPensions');

        $result = $this->service->projectPensionPot($this->user);

        // Default age of 40 assumed when no DOB
        expect($result['current_age'])->toBe(40)
            ->and($result['years_to_retirement'])->toBe(25);
    });
});

describe('projectIncomeDrawdown', function () {
    it('calculates income drawdown projections', function () {
        DCPension::factory()->create([
            'user_id' => $this->user->id,
            'current_fund_value' => 500000,
            'monthly_contribution_amount' => 0,
        ]);

        $this->user->load(['dcPensions', 'dbPensions', 'statePension']);

        $potProjection = [
            'retirement_age' => 65,
            'percentile_20_at_retirement' => 500000,
            'risk_level' => 'medium',
        ];

        $result = $this->service->projectIncomeDrawdown($this->user, $potProjection);

        expect($result)->toHaveKeys([
            'starting_pot',
            'target_income',
            'current_net_income',
            'retirement_age',
            'withdrawal_rate',
            'inflation_rate',
            'growth_rate',
            'on_track_status',
            'probability',
            'fund_depletion_age',
            'years_funded',
            'guaranteed_income',
            'yearly_income',
        ])
            ->and($result['starting_pot'])->toBe(500000.0)
            ->and($result['withdrawal_rate'])->toBe(4.7)
            ->and($result['inflation_rate'])->toBe(2.5)
            ->and($result['yearly_income'])->toBeArray()
            ->and($result['yearly_income'])->not->toBeEmpty();
    });

    it('includes DB pension income in projections', function () {
        DCPension::factory()->create([
            'user_id' => $this->user->id,
            'current_fund_value' => 200000,
            'monthly_contribution_amount' => 0,
        ]);

        DBPension::factory()->create([
            'user_id' => $this->user->id,
            'accrued_annual_pension' => 15000,
        ]);

        $this->user->load(['dcPensions', 'dbPensions', 'statePension']);

        $potProjection = [
            'retirement_age' => 65,
            'percentile_20_at_retirement' => 200000,
            'risk_level' => 'medium',
        ];

        $result = $this->service->projectIncomeDrawdown($this->user, $potProjection);

        expect($result['guaranteed_income']['db_pensions'])->toBe(15000.0);
    });

    it('includes state pension income in projections', function () {
        DCPension::factory()->create([
            'user_id' => $this->user->id,
            'current_fund_value' => 200000,
            'monthly_contribution_amount' => 0,
        ]);

        StatePension::factory()->create([
            'user_id' => $this->user->id,
            'state_pension_forecast_annual' => 11502,
            'state_pension_age' => 67,
        ]);

        $this->user->load(['dcPensions', 'dbPensions', 'statePension']);

        $potProjection = [
            'retirement_age' => 65,
            'percentile_20_at_retirement' => 200000,
            'risk_level' => 'medium',
        ];

        $result = $this->service->projectIncomeDrawdown($this->user, $potProjection);

        expect($result['guaranteed_income']['state_pension'])->toBe(11502.0);
    });
});

describe('on-track status calculation', function () {
    it('returns Excellent for high income coverage', function () {
        // Create setup that will generate high income
        DCPension::factory()->create([
            'user_id' => $this->user->id,
            'current_fund_value' => 1000000,
            'monthly_contribution_amount' => 0,
        ]);

        DBPension::factory()->create([
            'user_id' => $this->user->id,
            'accrued_annual_pension' => 20000,
        ]);

        $this->user->load(['dcPensions', 'dbPensions', 'statePension']);

        $potProjection = [
            'retirement_age' => 65,
            'percentile_20_at_retirement' => 1000000,
            'risk_level' => 'medium',
        ];

        $result = $this->service->projectIncomeDrawdown($this->user, $potProjection);

        // £1M pot with 4.7% drawdown = £47,000 + £20,000 DB = £67,000
        // Target = 75% of £40,000 net income = £30,000
        // Income ratio = 67000/30000 = 223% -> Excellent
        expect($result['on_track_status'])->toBe('Excellent')
            ->and($result['probability'])->toBeGreaterThanOrEqual(90);
    });

    it('returns Off Track for low income coverage', function () {
        DCPension::factory()->create([
            'user_id' => $this->user->id,
            'current_fund_value' => 50000,
            'monthly_contribution_amount' => 0,
        ]);

        $this->user->load(['dcPensions', 'dbPensions', 'statePension']);

        $potProjection = [
            'retirement_age' => 65,
            'percentile_20_at_retirement' => 50000,
            'risk_level' => 'medium',
        ];

        $result = $this->service->projectIncomeDrawdown($this->user, $potProjection);

        // £50k pot with 4.7% drawdown = £2,350
        // Target = 75% of £40,000 net income = £30,000
        // Income ratio = 2350/30000 = 7.8% -> Critical or Off Track
        expect($result['on_track_status'])->toBeIn(['Off Track', 'Significantly Off Track', 'Critical'])
            ->and($result['probability'])->toBeLessThan(50);
    });
});

describe('projectTargetIncomeDrawdown', function () {
    it('draws target income until fund depletes', function () {
        DCPension::factory()->create([
            'user_id' => $this->user->id,
            'current_fund_value' => 300000,
            'monthly_contribution_amount' => 0,
        ]);

        $this->user->load(['dcPensions', 'dbPensions', 'statePension']);

        $potProjection = [
            'retirement_age' => 65,
            'percentile_20_at_retirement' => 300000,
            'risk_level' => 'medium',
        ];

        $result = $this->service->projectTargetIncomeDrawdown($this->user, $potProjection);

        expect($result)->toHaveKeys([
            'starting_pot',
            'target_income',
            'retirement_age',
            'inflation_rate',
            'growth_rate',
            'fund_depletion_age',
            'years_funded',
            'guaranteed_income',
            'yearly_income',
        ])
            ->and($result['starting_pot'])->toBe(300000.0)
            ->and($result['yearly_income'])->toBeArray();
    });

    it('tracks fund depletion age correctly', function () {
        DCPension::factory()->create([
            'user_id' => $this->user->id,
            'current_fund_value' => 100000,
            'monthly_contribution_amount' => 0,
        ]);

        $this->user->load(['dcPensions', 'dbPensions', 'statePension']);

        $potProjection = [
            'retirement_age' => 65,
            'percentile_20_at_retirement' => 100000,
            'risk_level' => 'medium',
        ];

        $result = $this->service->projectTargetIncomeDrawdown($this->user, $potProjection);

        // Small pot should deplete before age 100
        if ($result['fund_depletion_age'] !== null) {
            expect($result['fund_depletion_age'])->toBeGreaterThan(65)
                ->and($result['fund_depletion_age'])->toBeLessThanOrEqual(100);
        }

        expect($result['years_funded'])->toBeGreaterThan(0);
    });
});

describe('getProjections', function () {
    it('returns complete projection data', function () {
        DCPension::factory()->create([
            'user_id' => $this->user->id,
            'current_fund_value' => 200000,
            'monthly_contribution_amount' => 500,
        ]);

        $result = $this->service->getProjections($this->user->id);

        expect($result)->toHaveKeys([
            'pension_pot_projection',
            'income_drawdown',
            'target_income_drawdown',
        ])
            ->and($result['pension_pot_projection'])->toBeArray()
            ->and($result['income_drawdown'])->toBeArray()
            ->and($result['target_income_drawdown'])->toBeArray();
    });
});

afterEach(function () {
    Mockery::close();
});
