<?php

declare(strict_types=1);

use App\Models\SavingsGoal;
use App\Services\Savings\GoalProgressCalculator;
use Carbon\Carbon;

beforeEach(function () {
    $this->calculator = new GoalProgressCalculator;
    Carbon::setTestNow(Carbon::create(2025, 1, 1));
});

afterEach(function () {
    Carbon::setTestNow(null);
    Mockery::close();
});

function createGoalMock(array $attributes): SavingsGoal
{
    $goal = Mockery::mock(SavingsGoal::class)->makePartial();
    $goal->shouldReceive('getAttribute')->with('target_date')->andReturn($attributes['target_date']);
    $goal->shouldReceive('getAttribute')->with('target_amount')->andReturn($attributes['target_amount']);
    $goal->shouldReceive('getAttribute')->with('current_saved')->andReturn($attributes['current_saved']);
    $goal->shouldReceive('getAttribute')->with('auto_transfer_amount')->andReturn($attributes['auto_transfer_amount'] ?? null);

    return $goal;
}

describe('GoalProgressCalculator', function () {
    describe('calculateProgress', function () {
        it('calculates progress for a goal in progress', function () {
            $goal = createGoalMock([
                'target_date' => Carbon::create(2025, 7, 1),
                'target_amount' => 6000,
                'current_saved' => 3000,
                'auto_transfer_amount' => 500,
            ]);

            $result = $this->calculator->calculateProgress($goal);

            expect($result['months_remaining'])->toBe(6);
            expect($result['shortfall'])->toBe(3000.0);
            expect($result['required_monthly_savings'])->toBe(500.0);
            expect($result['progress_percent'])->toBe(50.0);
            expect($result['on_track'])->toBeTrue();
        });

        it('returns shortfall as 0 when goal is achieved', function () {
            $goal = createGoalMock([
                'target_date' => Carbon::create(2025, 6, 1),
                'target_amount' => 5000,
                'current_saved' => 5500,
                'auto_transfer_amount' => 200,
            ]);

            $result = $this->calculator->calculateProgress($goal);

            expect($result['shortfall'])->toBe(0.0);
            expect($result['progress_percent'])->toBe(110.0);
        });

        it('handles goal with no months remaining', function () {
            $goal = createGoalMock([
                'target_date' => Carbon::create(2024, 12, 1),
                'target_amount' => 1000,
                'current_saved' => 500,
                'auto_transfer_amount' => null,
            ]);

            $result = $this->calculator->calculateProgress($goal);

            expect($result['months_remaining'])->toBe(0);
            expect($result['shortfall'])->toBe(500.0);
            expect($result['required_monthly_savings'])->toBe(500.0);
            expect($result['on_track'])->toBeFalse();
        });

        it('marks as not on track when auto transfer is insufficient', function () {
            $goal = createGoalMock([
                'target_date' => Carbon::create(2025, 6, 1),
                'target_amount' => 10000,
                'current_saved' => 4000,
                'auto_transfer_amount' => 500,
            ]);

            $result = $this->calculator->calculateProgress($goal);

            expect($result['required_monthly_savings'])->toBe(1200.0);
            expect($result['on_track'])->toBeFalse();
        });
    });

    describe('projectGoalAchievement', function () {
        it('projects future amount with interest', function () {
            $goal = createGoalMock([
                'target_date' => Carbon::create(2025, 7, 1),
                'target_amount' => 2000,
                'current_saved' => 1000,
            ]);

            $result = $this->calculator->projectGoalAchievement($goal, 200, 0.05);

            expect($result['projected_final_amount'])->toBeGreaterThan(2200);
            expect($result['will_meet_goal'])->toBeTrue();
        });

        it('projects future amount without interest', function () {
            $goal = createGoalMock([
                'target_date' => Carbon::create(2025, 7, 1),
                'target_amount' => 2000,
                'current_saved' => 1000,
            ]);

            $result = $this->calculator->projectGoalAchievement($goal, 100, 0);

            expect($result['projected_final_amount'])->toBe(1600.0);
            expect($result['will_meet_goal'])->toBeFalse();
        });

        it('handles zero monthly contribution', function () {
            $goal = createGoalMock([
                'target_date' => Carbon::create(2025, 6, 1),
                'target_amount' => 1000,
                'current_saved' => 500,
            ]);

            $result = $this->calculator->projectGoalAchievement($goal, 0, 0.03);

            expect($result['projected_completion_date'])->toBeNull();
        });
    });
});
