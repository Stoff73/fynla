<?php

declare(strict_types=1);

use App\Agents\SavingsAgent;
use App\Models\SavingsAccount;
use App\Models\SavingsGoal;
use App\Models\User;
use App\Services\Savings\EmergencyFundCalculator;
use App\Services\Savings\GoalProgressCalculator;
use App\Services\Savings\ISATracker;
use App\Services\Savings\LiquidityAnalyzer;
use App\Services\Savings\RateComparator;
use App\Services\Savings\SavingsDataReadinessService;

beforeEach(function () {
    $this->emergencyFundCalculator = Mockery::mock(EmergencyFundCalculator::class);
    $this->isaTracker = Mockery::mock(ISATracker::class);
    $this->goalProgressCalculator = Mockery::mock(GoalProgressCalculator::class);
    $this->liquidityAnalyzer = Mockery::mock(LiquidityAnalyzer::class);
    $this->rateComparator = Mockery::mock(RateComparator::class);
    $this->readinessService = Mockery::mock(SavingsDataReadinessService::class);
    $this->readinessService->shouldReceive('assess')->andReturn([
        'can_proceed' => true,
        'blocking' => [],
        'warnings' => [],
        'info' => [],
    ])->byDefault();

    $this->agent = new SavingsAgent(
        $this->emergencyFundCalculator,
        $this->isaTracker,
        $this->goalProgressCalculator,
        $this->liquidityAnalyzer,
        $this->rateComparator,
        $this->readinessService
    );
});

afterEach(function () {
    Mockery::close();
});

describe('analyze', function () {
    it('returns expected structure', function () {
        $user = User::factory()->create(['monthly_expenditure' => 2000]);
        $account = SavingsAccount::factory()->create([
            'user_id' => $user->id,
            'current_balance' => 12000,
            'interest_rate' => 0.04,
            'access_type' => 'immediate',
        ]);
        $goal = SavingsGoal::factory()->create([
            'user_id' => $user->id,
            'goal_name' => 'Holiday Fund',
            'target_amount' => 5000,
            'current_saved' => 2000,
            'target_date' => now()->addMonths(12),
            'priority' => 'medium',
        ]);

        $this->emergencyFundCalculator
            ->shouldReceive('calculateRunway')
            ->once()
            ->andReturn(6.0);

        $this->emergencyFundCalculator
            ->shouldReceive('calculateAdequacy')
            ->once()
            ->andReturn([
                'runway' => 6.0,
                'target' => 6,
                'adequacy_score' => 100.0,
                'shortfall' => 0.0,
            ]);

        $this->emergencyFundCalculator
            ->shouldReceive('categorizeAdequacy')
            ->once()
            ->andReturn('Excellent');

        $this->isaTracker
            ->shouldReceive('getCurrentTaxYear')
            ->once()
            ->andReturn('2025/26');

        $this->isaTracker
            ->shouldReceive('getISAAllowanceStatus')
            ->once()
            ->andReturn([
                'cash_isa_used' => 5000.0,
                'stocks_shares_isa_used' => 0.0,
                'lisa_used' => 0.0,
                'total_used' => 5000.0,
                'total_allowance' => 20000.0,
                'remaining' => 15000.0,
                'percentage_used' => 25.0,
            ]);

        $this->liquidityAnalyzer
            ->shouldReceive('categorizeLiquidity')
            ->once()
            ->andReturn([
                'immediate' => collect([$account]),
                'short_notice' => collect(),
                'fixed_term' => collect(),
            ]);

        $this->liquidityAnalyzer
            ->shouldReceive('getLiquiditySummary')
            ->once()
            ->andReturn([
                'total_liquid' => 12000.0,
                'total_short_notice' => 0.0,
                'total_fixed' => 0.0,
                'liquid_percent' => 100.0,
                'risk_level' => 'Low',
            ]);

        $this->liquidityAnalyzer
            ->shouldReceive('buildLiquidityLadder')
            ->once()
            ->andReturn([]);

        $this->rateComparator
            ->shouldReceive('compareToMarketRates')
            ->twice()
            ->andReturn([
                'account_rate' => 0.04,
                'market_rate' => 0.045,
                'difference' => -0.005,
                'is_competitive' => true,
                'category' => 'Fair',
            ]);

        $this->rateComparator
            ->shouldReceive('calculateInterestDifference')
            ->once()
            ->andReturn(60.0);

        $this->goalProgressCalculator
            ->shouldReceive('calculateProgress')
            ->once()
            ->andReturn([
                'months_remaining' => 12,
                'shortfall' => 3000.0,
                'required_monthly_savings' => 250.0,
                'progress_percent' => 40.0,
                'on_track' => false,
            ]);

        $this->goalProgressCalculator
            ->shouldReceive('prioritizeGoals')
            ->once()
            ->andReturn(new \Illuminate\Database\Eloquent\Collection([$goal]));

        $result = $this->agent->analyze($user->id);

        expect($result)->toHaveKeys([
            'summary',
            'emergency_fund',
            'isa_allowance',
            'liquidity',
            'rate_comparisons',
            'goals',
        ]);

        expect($result['summary'])->toHaveKeys([
            'total_savings',
            'total_accounts',
            'total_goals',
            'monthly_expenditure',
        ]);

        expect($result['emergency_fund'])->toHaveKeys([
            'runway_months',
            'adequacy',
            'category',
            'recommendation',
        ]);

        expect($result['goals'])->toHaveKeys(['progress', 'prioritized']);
    });

    it('handles user with no savings accounts', function () {
        $user = User::factory()->create(['monthly_expenditure' => 2000]);

        $this->emergencyFundCalculator
            ->shouldReceive('calculateRunway')
            ->once()
            ->with(0.0, 2000.0)
            ->andReturn(0.0);

        $this->emergencyFundCalculator
            ->shouldReceive('calculateAdequacy')
            ->once()
            ->andReturn([
                'runway' => 0.0,
                'target' => 6,
                'adequacy_score' => 0.0,
                'shortfall' => 6.0,
            ]);

        $this->emergencyFundCalculator
            ->shouldReceive('categorizeAdequacy')
            ->once()
            ->andReturn('Critical');

        $this->isaTracker
            ->shouldReceive('getCurrentTaxYear')
            ->once()
            ->andReturn('2025/26');

        $this->isaTracker
            ->shouldReceive('getISAAllowanceStatus')
            ->once()
            ->andReturn([
                'cash_isa_used' => 0.0,
                'stocks_shares_isa_used' => 0.0,
                'lisa_used' => 0.0,
                'total_used' => 0.0,
                'total_allowance' => 20000.0,
                'remaining' => 20000.0,
                'percentage_used' => 0.0,
            ]);

        $this->liquidityAnalyzer
            ->shouldReceive('categorizeLiquidity')
            ->once()
            ->andReturn([
                'immediate' => collect(),
                'short_notice' => collect(),
                'fixed_term' => collect(),
            ]);

        $this->liquidityAnalyzer
            ->shouldReceive('getLiquiditySummary')
            ->once()
            ->andReturn([
                'total_liquid' => 0.0,
                'total_short_notice' => 0.0,
                'total_fixed' => 0.0,
                'liquid_percent' => 0.0,
                'risk_level' => 'High',
            ]);

        $this->liquidityAnalyzer
            ->shouldReceive('buildLiquidityLadder')
            ->once()
            ->andReturn([]);

        $this->goalProgressCalculator
            ->shouldReceive('prioritizeGoals')
            ->once()
            ->andReturn(new \Illuminate\Database\Eloquent\Collection);

        $result = $this->agent->analyze($user->id);

        expect($result['summary']['total_savings'])->toBe(0.0);
        expect($result['summary']['total_accounts'])->toBe(0);
        expect($result['summary']['total_goals'])->toBe(0);
        expect($result['emergency_fund']['category'])->toBe('Critical');
    });

    it('calculates totals correctly', function () {
        $user = User::factory()->create(['monthly_expenditure' => 3000]);

        SavingsAccount::factory()->create([
            'user_id' => $user->id,
            'current_balance' => 5000,
            'interest_rate' => 0.03,
            'access_type' => 'immediate',
        ]);

        SavingsAccount::factory()->create([
            'user_id' => $user->id,
            'current_balance' => 10000,
            'interest_rate' => 0.05,
            'access_type' => 'notice',
        ]);

        SavingsGoal::factory()->count(2)->create(['user_id' => $user->id]);

        $this->emergencyFundCalculator
            ->shouldReceive('calculateRunway')
            ->once()
            ->with(15000.0, 3000.0)
            ->andReturn(5.0);

        $this->emergencyFundCalculator
            ->shouldReceive('calculateAdequacy')
            ->once()
            ->andReturn([
                'runway' => 5.0,
                'target' => 6,
                'adequacy_score' => 83.33,
                'shortfall' => 1.0,
            ]);

        $this->emergencyFundCalculator
            ->shouldReceive('categorizeAdequacy')
            ->once()
            ->andReturn('Good');

        $this->isaTracker
            ->shouldReceive('getCurrentTaxYear')
            ->once()
            ->andReturn('2025/26');

        $this->isaTracker
            ->shouldReceive('getISAAllowanceStatus')
            ->once()
            ->andReturn([
                'cash_isa_used' => 0.0,
                'stocks_shares_isa_used' => 0.0,
                'lisa_used' => 0.0,
                'total_used' => 0.0,
                'total_allowance' => 20000.0,
                'remaining' => 20000.0,
                'percentage_used' => 0.0,
            ]);

        $this->liquidityAnalyzer
            ->shouldReceive('categorizeLiquidity')
            ->once()
            ->andReturnUsing(fn ($accounts) => [
                'immediate' => $accounts->where('access_type', 'immediate'),
                'short_notice' => $accounts->where('access_type', 'notice'),
                'fixed_term' => collect(),
            ]);

        $this->liquidityAnalyzer
            ->shouldReceive('getLiquiditySummary')
            ->once()
            ->andReturn([
                'total_liquid' => 5000.0,
                'total_short_notice' => 10000.0,
                'total_fixed' => 0.0,
                'liquid_percent' => 100.0,
                'risk_level' => 'Low',
            ]);

        $this->liquidityAnalyzer
            ->shouldReceive('buildLiquidityLadder')
            ->once()
            ->andReturn([]);

        $this->rateComparator
            ->shouldReceive('compareToMarketRates')
            ->times(4)
            ->andReturn([
                'account_rate' => 0.04,
                'market_rate' => 0.045,
                'difference' => -0.005,
                'is_competitive' => true,
                'category' => 'Fair',
            ]);

        $this->rateComparator
            ->shouldReceive('calculateInterestDifference')
            ->twice()
            ->andReturn(50.0);

        $this->goalProgressCalculator
            ->shouldReceive('calculateProgress')
            ->twice()
            ->andReturn([
                'months_remaining' => 12,
                'shortfall' => 3000.0,
                'required_monthly_savings' => 250.0,
                'progress_percent' => 40.0,
                'on_track' => false,
            ]);

        $this->goalProgressCalculator
            ->shouldReceive('prioritizeGoals')
            ->once()
            ->andReturnUsing(fn ($goals) => $goals);

        $result = $this->agent->analyze($user->id);

        expect($result['summary']['total_savings'])->toBe(15000.0);
        expect($result['summary']['total_accounts'])->toBe(2);
        expect($result['summary']['total_goals'])->toBe(2);
        expect($result['summary']['monthly_expenditure'])->toBe(3000.0);
    });
});

describe('generateRecommendations', function () {
    it('generates emergency fund recommendation when adequacy < 100', function () {
        $this->emergencyFundCalculator
            ->shouldReceive('calculateMonthlyTopUp')
            ->once()
            ->andReturn(500.0);

        $analysisData = [
            'summary' => ['monthly_expenditure' => 2000.0],
            'emergency_fund' => [
                'runway_months' => 3.0,
                'adequacy' => [
                    'adequacy_score' => 50.0,
                    'shortfall' => 3.0,
                ],
            ],
            'isa_allowance' => ['remaining' => 0.0],
            'rate_comparisons' => [],
            'liquidity' => ['summary' => ['risk_level' => 'Low']],
        ];

        $result = $this->agent->generateRecommendations($analysisData);

        expect($result)->toBeArray();
        expect($result)->not->toBeEmpty();

        $emergencyFundRec = collect($result)->first(fn ($r) => $r['category'] === 'emergency_fund');
        expect($emergencyFundRec)->not->toBeNull();
        expect($emergencyFundRec['priority'])->toBe('high');
        expect($emergencyFundRec['title'])->toBe('Build Emergency Fund');
        expect($emergencyFundRec['description'])->toContain('3.0 months');
    });

    it('generates ISA recommendation when remaining allowance > 0', function () {
        $this->isaTracker
            ->shouldReceive('getCurrentTaxYear')
            ->once()
            ->andReturn('2025/26');

        $analysisData = [
            'summary' => ['monthly_expenditure' => 2000.0],
            'emergency_fund' => [
                'runway_months' => 6.0,
                'adequacy' => [
                    'adequacy_score' => 100.0,
                    'shortfall' => 0.0,
                ],
            ],
            'isa_allowance' => ['remaining' => 15000.0],
            'rate_comparisons' => [],
            'liquidity' => ['summary' => ['risk_level' => 'Low']],
        ];

        $result = $this->agent->generateRecommendations($analysisData);

        expect($result)->toBeArray();

        $isaRec = collect($result)->first(fn ($r) => $r['category'] === 'isa_allowance');
        expect($isaRec)->not->toBeNull();
        expect($isaRec['priority'])->toBe('medium');
        expect($isaRec['title'])->toBe('Use ISA Allowance');
        expect($isaRec['description'])->toContain('15,000');
        expect($isaRec['description'])->toContain('2025/26');
    });

    it('generates rate improvement recommendations', function () {
        $analysisData = [
            'summary' => ['monthly_expenditure' => 2000.0],
            'emergency_fund' => [
                'runway_months' => 6.0,
                'adequacy' => [
                    'adequacy_score' => 100.0,
                    'shortfall' => 0.0,
                ],
            ],
            'isa_allowance' => ['remaining' => 0.0],
            'rate_comparisons' => [
                [
                    'account_id' => 1,
                    'institution' => 'Old Bank',
                    'comparison' => [
                        'account_rate' => 0.02,
                        'market_rate' => 0.045,
                        'difference' => -0.025,
                        'is_competitive' => false,
                        'category' => 'Poor',
                    ],
                    'potential_gain' => 250.0,
                ],
            ],
            'liquidity' => ['summary' => ['risk_level' => 'Low']],
        ];

        $result = $this->agent->generateRecommendations($analysisData);

        expect($result)->toBeArray();

        $rateRec = collect($result)->first(fn ($r) => $r['category'] === 'rate_improvement');
        expect($rateRec)->not->toBeNull();
        expect($rateRec['priority'])->toBe('medium');
        expect($rateRec['title'])->toBe('Switch to Better Rate');
        expect($rateRec['description'])->toContain('Old Bank');
        expect($rateRec['description'])->toContain('250');
    });

    it('generates liquidity recommendations', function () {
        $analysisData = [
            'summary' => ['monthly_expenditure' => 2000.0],
            'emergency_fund' => [
                'runway_months' => 6.0,
                'adequacy' => [
                    'adequacy_score' => 100.0,
                    'shortfall' => 0.0,
                ],
            ],
            'isa_allowance' => ['remaining' => 0.0],
            'rate_comparisons' => [],
            'liquidity' => ['summary' => ['risk_level' => 'High']],
        ];

        $result = $this->agent->generateRecommendations($analysisData);

        expect($result)->toBeArray();

        $liquidityRec = collect($result)->first(fn ($r) => $r['category'] === 'liquidity');
        expect($liquidityRec)->not->toBeNull();
        expect($liquidityRec['priority'])->toBe('high');
        expect($liquidityRec['title'])->toBe('Improve Liquidity');
        expect($liquidityRec['description'])->toContain('fixed-term accounts');
    });
});

describe('buildScenarios', function () {
    it('builds increased_monthly_savings scenario', function () {
        $user = User::factory()->create();

        $parameters = [
            'increased_monthly_savings' => 500,
            'interest_rate' => 0.04,
            'years' => 5,
        ];

        $result = $this->agent->buildScenarios($user->id, $parameters);

        expect($result)->toHaveKey('increased_savings');
        expect($result['increased_savings'])->toHaveKeys(['name', 'parameters', 'result']);
        expect($result['increased_savings']['name'])->toBe('Increased Monthly Savings');
        expect($result['increased_savings']['parameters']['monthly_contribution'])->toBe(500);
        expect($result['increased_savings']['parameters']['interest_rate'])->toBe(0.04);
        expect($result['increased_savings']['parameters']['years'])->toBe(5);
        expect($result['increased_savings']['result'])->toHaveKeys([
            'final_amount',
            'total_contributed',
            'interest_earned',
        ]);
        expect($result['increased_savings']['result']['total_contributed'])->toBe(30000.0);
        expect($result['increased_savings']['result']['final_amount'])->toBeGreaterThan(30000.0);
    });

    it('builds goal_achievement scenario', function () {
        $user = User::factory()->create();
        $goal = SavingsGoal::factory()->create([
            'user_id' => $user->id,
            'goal_name' => 'New Car',
            'target_amount' => 20000,
            'current_saved' => 5000,
            'target_date' => now()->addMonths(24),
        ]);

        $this->goalProgressCalculator
            ->shouldReceive('projectGoalAchievement')
            ->once()
            ->with(
                Mockery::on(fn ($g) => $g->id === $goal->id),
                500.0,
                0.04
            )
            ->andReturn([
                'projected_final_amount' => 18500.0,
                'projected_completion_date' => now()->addMonths(30)->format('Y-m-d'),
                'will_meet_goal' => false,
            ]);

        $parameters = [
            'goal_id' => $goal->id,
            'monthly_contribution' => 500,
            'interest_rate' => 0.04,
        ];

        $result = $this->agent->buildScenarios($user->id, $parameters);

        expect($result)->toHaveKey('goal_achievement');
        expect($result['goal_achievement'])->toHaveKeys(['name', 'goal', 'parameters', 'result']);
        expect($result['goal_achievement']['name'])->toBe('Goal Achievement Projection');
        expect($result['goal_achievement']['goal'])->toBe('New Car');
        expect($result['goal_achievement']['result']['will_meet_goal'])->toBeFalse();
    });

    it('handles missing goal gracefully', function () {
        $user = User::factory()->create();

        $parameters = [
            'goal_id' => 99999, // Non-existent goal
            'monthly_contribution' => 500,
            'interest_rate' => 0.04,
        ];

        $result = $this->agent->buildScenarios($user->id, $parameters);

        expect($result)->not->toHaveKey('goal_achievement');
    });

    it('uses default values when optional parameters are missing', function () {
        $user = User::factory()->create();

        $parameters = [
            'increased_monthly_savings' => 300,
            // interest_rate and years should default to 0.04 and 5
        ];

        $result = $this->agent->buildScenarios($user->id, $parameters);

        expect($result)->toHaveKey('increased_savings');
        expect($result['increased_savings']['parameters']['interest_rate'])->toBe(0.04);
        expect($result['increased_savings']['parameters']['years'])->toBe(5);
    });
});
