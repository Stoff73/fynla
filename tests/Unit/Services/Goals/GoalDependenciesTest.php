<?php

declare(strict_types=1);

use App\Models\Goal;
use App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->create();
});

it('can add a dependency between goals', function () {
    $goalA = Goal::factory()->emergencyFund()->create(['user_id' => $this->user->id]);
    $goalB = Goal::factory()->propertyPurchase()->create(['user_id' => $this->user->id]);

    $goalB->dependsOn()->attach($goalA->id, ['dependency_type' => 'blocks']);

    expect($goalB->dependsOn)->toHaveCount(1);
    expect($goalB->dependsOn->first()->id)->toBe($goalA->id);
    expect($goalB->dependsOn->first()->pivot->dependency_type)->toBe('blocks');
});

it('supports multiple dependencies on a goal', function () {
    $goalA = Goal::factory()->create(['user_id' => $this->user->id]);
    $goalB = Goal::factory()->create(['user_id' => $this->user->id]);
    $goalC = Goal::factory()->create(['user_id' => $this->user->id]);

    $goalC->dependsOn()->attach([
        $goalA->id => ['dependency_type' => 'blocks'],
        $goalB->id => ['dependency_type' => 'funds'],
    ]);

    $goalC->load('dependsOn');
    expect($goalC->dependsOn)->toHaveCount(2);
});

it('shows goals that depend on this goal via dependedOnBy', function () {
    $goalA = Goal::factory()->emergencyFund()->create(['user_id' => $this->user->id]);
    $goalB = Goal::factory()->create(['user_id' => $this->user->id]);

    $goalB->dependsOn()->attach($goalA->id, ['dependency_type' => 'blocks']);

    $goalA->load('dependedOnBy');
    expect($goalA->dependedOnBy)->toHaveCount(1);
    expect($goalA->dependedOnBy->first()->id)->toBe($goalB->id);
});

it('returns blocked true when blocking dependency is incomplete', function () {
    $goalA = Goal::factory()->create([
        'user_id' => $this->user->id,
        'status' => 'active',
    ]);
    $goalB = Goal::factory()->create([
        'user_id' => $this->user->id,
        'status' => 'active',
    ]);

    $goalB->dependsOn()->attach($goalA->id, ['dependency_type' => 'blocks']);

    expect($goalB->isBlocked())->toBeTrue();
});

it('returns blocked false when blocking dependency is completed', function () {
    $goalA = Goal::factory()->completed()->create([
        'user_id' => $this->user->id,
    ]);
    $goalB = Goal::factory()->create([
        'user_id' => $this->user->id,
        'status' => 'active',
    ]);

    $goalB->dependsOn()->attach($goalA->id, ['dependency_type' => 'blocks']);

    expect($goalB->isBlocked())->toBeFalse();
});

it('returns blocked false for prerequisite type dependencies', function () {
    $goalA = Goal::factory()->create([
        'user_id' => $this->user->id,
        'status' => 'active',
    ]);
    $goalB = Goal::factory()->create([
        'user_id' => $this->user->id,
        'status' => 'active',
    ]);

    $goalB->dependsOn()->attach($goalA->id, ['dependency_type' => 'prerequisite']);

    expect($goalB->isBlocked())->toBeFalse();
});

it('returns blocked false when goal has no dependencies', function () {
    $goal = Goal::factory()->create([
        'user_id' => $this->user->id,
        'status' => 'active',
    ]);

    expect($goal->isBlocked())->toBeFalse();
});

it('removes dependencies when a goal is force deleted', function () {
    $goalA = Goal::factory()->create(['user_id' => $this->user->id]);
    $goalB = Goal::factory()->create(['user_id' => $this->user->id]);

    $goalB->dependsOn()->attach($goalA->id, ['dependency_type' => 'blocks']);

    expect(\DB::table('goal_dependencies')->count())->toBe(1);

    $goalA->forceDelete();

    expect(\DB::table('goal_dependencies')->count())->toBe(0);
});

// GoalRiskService tests

it('returns risk parameters for a goal', function () {
    $goal = Goal::factory()->create([
        'user_id' => $this->user->id,
        'risk_preference' => 3,
        'use_global_risk_profile' => false,
    ]);

    $service = app(\App\Services\Goals\GoalRiskService::class);
    $params = $service->getRiskParameters($goal);

    expect($params)->toHaveKeys(['risk_level', 'risk_label', 'expected_return', 'volatility', 'use_global_profile']);
    expect($params['risk_level'])->toBe(3);
    expect($params['risk_label'])->toBe('Balanced');
});

it('defaults to balanced risk when no preference set', function () {
    $goal = Goal::factory()->create([
        'user_id' => $this->user->id,
        'risk_preference' => null,
        'use_global_risk_profile' => false,
    ]);

    $service = app(\App\Services\Goals\GoalRiskService::class);
    $params = $service->getRiskParameters($goal);

    expect($params['risk_level'])->toBe(3);
});

it('clamps risk level to valid range', function () {
    $goal = Goal::factory()->create([
        'user_id' => $this->user->id,
        'risk_preference' => 10,
        'use_global_risk_profile' => false,
    ]);

    $service = app(\App\Services\Goals\GoalRiskService::class);
    $params = $service->getRiskParameters($goal);

    expect($params['risk_level'])->toBe(5);
});

it('returns projections for an investment goal', function () {
    $goal = Goal::factory()->create([
        'user_id' => $this->user->id,
        'target_amount' => 50000,
        'current_amount' => 10000,
        'monthly_contribution' => 500,
        'target_date' => now()->addYears(5),
        'start_date' => now(),
        'risk_preference' => 3,
        'use_global_risk_profile' => false,
    ]);

    $service = app(\App\Services\Goals\GoalRiskService::class);
    $projections = $service->getProjections($goal);

    expect($projections)->toHaveKeys(['risk_parameters', 'projections', 'yearly_projections', 'recommendation']);
    expect($projections['projections'])->toHaveKeys([
        'expected_final_value', 'target_amount', 'probability_of_success',
        'required_monthly_contribution', 'current_monthly_contribution',
    ]);
    expect($projections['projections']['probability_of_success'])->toBeGreaterThan(0);
});

it('returns available risk levels', function () {
    $service = app(\App\Services\Goals\GoalRiskService::class);
    $levels = $service->getAvailableRiskLevels();

    expect($levels)->toHaveCount(5);
    expect($levels[0]['label'])->toBe('Conservative');
    expect($levels[4]['label'])->toBe('Aggressive');
});
