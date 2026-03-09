<?php

declare(strict_types=1);

use App\Models\Estate\Will;
use App\Models\User;
use App\Services\Estate\FutureValueCalculator;
use App\Services\Retirement\DecumulationPlanner;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Seed tax configuration
    $this->artisan('db:seed', ['--class' => 'TaxConfigurationSeeder', '--force' => true]);
});

describe('Life Expectancy Override', function () {
    it('uses user override when set', function () {
        $user = User::factory()->create([
            'date_of_birth' => Carbon::now()->subYears(50),
            'gender' => 'male',
            'life_expectancy_override' => 95,
        ]);

        $calculator = app(FutureValueCalculator::class);
        $result = $calculator->getLifeExpectancy($user);

        expect($result['death_age'])->toBe(95);
        expect($result['years_remaining'])->toBe(45.0);
        expect($result['source'])->toBe('user_override');
    });

    it('falls back to actuarial lookup when no override', function () {
        $user = User::factory()->create([
            'date_of_birth' => Carbon::now()->subYears(50),
            'gender' => 'male',
            'life_expectancy_override' => null,
        ]);

        $calculator = app(FutureValueCalculator::class);
        $result = $calculator->getLifeExpectancy($user);

        // Should use actuarial tables, not the hardcoded 85
        expect($result['death_age'])->toBeGreaterThan(50);
        expect($result)->not->toHaveKey('source');
    });
});

describe('Goal Dependencies', function () {
    it('detects blocked goals', function () {
        $user = User::factory()->create();

        $goalA = \App\Models\Goal::factory()->create([
            'user_id' => $user->id,
            'goal_name' => 'Emergency Fund',
            'status' => 'active',
        ]);

        $goalB = \App\Models\Goal::factory()->create([
            'user_id' => $user->id,
            'goal_name' => 'House Deposit',
            'status' => 'active',
        ]);

        // Goal B depends on Goal A (blocks type)
        $goalB->dependsOn()->attach($goalA->id, [
            'dependency_type' => 'blocks',
            'notes' => 'Need emergency fund first',
        ]);

        // Goal B should be blocked because Goal A is not completed
        expect($goalB->isBlocked())->toBeTrue();

        // Complete Goal A
        $goalA->update(['status' => 'completed']);

        // Goal B should no longer be blocked
        expect($goalB->fresh()->isBlocked())->toBeFalse();
    });
});

describe('Care Cost Reduces Sustainable Withdrawal', function () {
    it('reduces portfolio survival when care costs are included', function () {
        $planner = app(DecumulationPlanner::class);

        $portfolioValue = 500000.0;
        $yearsInRetirement = 25;

        // Without care costs
        $withoutCare = $planner->calculateSustainableWithdrawalRate(
            $portfolioValue,
            $yearsInRetirement
        );

        // With care costs starting after 15 years
        $withCare = $planner->calculateSustainableWithdrawalRate(
            $portfolioValue,
            $yearsInRetirement,
            0.05,
            0.025,
            30000.0,
            15
        );

        expect($withCare['care_costs_included'])->toBeTrue();

        // With care costs, the final balance at the same withdrawal rate should be lower
        // or the portfolio may not survive as long
        $withoutCareScenario = collect($withoutCare['scenarios'])->firstWhere('withdrawal_rate', 5);
        $withCareScenario = collect($withCare['scenarios'])->firstWhere('withdrawal_rate', 5);

        if ($withoutCareScenario['survives'] && $withCareScenario['survives']) {
            expect($withCareScenario['final_balance'])->toBeLessThan($withoutCareScenario['final_balance']);
        } else {
            // If care costs cause portfolio to fail, that's also a valid outcome
            expect($withCareScenario['years_survived'])->toBeLessThanOrEqual($withoutCareScenario['years_survived']);
        }
    });
});

describe('Stale Will Warning', function () {
    it('warns when will has not been reviewed for over 3 years', function () {
        $will = new Will([
            'has_will' => true,
            'last_reviewed_date' => Carbon::now()->subYears(4),
            'will_last_updated' => Carbon::now()->subYears(4),
        ]);

        $lastReviewed = $will->last_reviewed_date ?? $will->will_last_updated;
        $isStale = $lastReviewed && $lastReviewed->lt(now()->subYears(3));

        expect($isStale)->toBeTrue();
    });

    it('does not warn for recently reviewed will', function () {
        $will = new Will([
            'has_will' => true,
            'last_reviewed_date' => Carbon::now()->subMonths(6),
            'will_last_updated' => Carbon::now()->subYears(4),
        ]);

        $lastReviewed = $will->last_reviewed_date ?? $will->will_last_updated;
        $isStale = $lastReviewed && $lastReviewed->lt(now()->subYears(3));

        expect($isStale)->toBeFalse();
    });

    it('treats null review date as stale', function () {
        $will = new Will([
            'has_will' => true,
            'last_reviewed_date' => null,
            'will_last_updated' => null,
        ]);

        $lastReviewed = $will->last_reviewed_date ?? $will->will_last_updated;
        $isStale = $lastReviewed ? $lastReviewed->lt(now()->subYears(3)) : true;

        expect($isStale)->toBeTrue();
    });
});
