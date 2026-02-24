<?php

declare(strict_types=1);

use App\Models\Goal;
use App\Models\GoalContribution;
use App\Models\SavingsAccount;
use App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->create();
});

// SavingsAccountGoalObserver tests

it('creates automatic contribution when linked savings account balance increases', function () {
    $account = SavingsAccount::factory()->create([
        'user_id' => $this->user->id,
        'current_balance' => 5000.00,
    ]);

    $goal = Goal::factory()->emergencyFund()->create([
        'user_id' => $this->user->id,
        'linked_savings_account_id' => $account->id,
        'current_amount' => 5000.00,
        'target_amount' => 10000.00,
    ]);

    $account->update(['current_balance' => 6000.00]);

    $contribution = GoalContribution::where('goal_id', $goal->id)->first();
    expect($contribution)->not->toBeNull();
    expect((float) $contribution->amount)->toBe(1000.00);
    expect($contribution->contribution_type)->toBe('automatic');
    expect((float) $contribution->goal_balance_after)->toBe(6000.00);

    $goal->refresh();
    expect((float) $goal->current_amount)->toBe(6000.00);
});

it('does not create contribution when savings balance decreases', function () {
    $account = SavingsAccount::factory()->create([
        'user_id' => $this->user->id,
        'current_balance' => 5000.00,
    ]);

    $goal = Goal::factory()->emergencyFund()->create([
        'user_id' => $this->user->id,
        'linked_savings_account_id' => $account->id,
        'current_amount' => 5000.00,
    ]);

    $account->update(['current_balance' => 4000.00]);

    expect(GoalContribution::where('goal_id', $goal->id)->count())->toBe(0);
});

it('does not create contribution for non-linked savings goals', function () {
    $account = SavingsAccount::factory()->create([
        'user_id' => $this->user->id,
        'current_balance' => 5000.00,
    ]);

    Goal::factory()->emergencyFund()->create([
        'user_id' => $this->user->id,
        'linked_savings_account_id' => null,
        'current_amount' => 5000.00,
    ]);

    $account->update(['current_balance' => 6000.00]);

    expect(GoalContribution::count())->toBe(0);
});

it('does not create contribution for paused goals on savings change', function () {
    $account = SavingsAccount::factory()->create([
        'user_id' => $this->user->id,
        'current_balance' => 5000.00,
    ]);

    Goal::factory()->emergencyFund()->paused()->create([
        'user_id' => $this->user->id,
        'linked_savings_account_id' => $account->id,
        'current_amount' => 5000.00,
    ]);

    $account->update(['current_balance' => 6000.00]);

    expect(GoalContribution::count())->toBe(0);
});

it('does not create contribution when non-balance savings field changes', function () {
    $account = SavingsAccount::factory()->create([
        'user_id' => $this->user->id,
        'current_balance' => 5000.00,
    ]);

    $goal = Goal::factory()->emergencyFund()->create([
        'user_id' => $this->user->id,
        'linked_savings_account_id' => $account->id,
        'current_amount' => 5000.00,
    ]);

    $account->update(['account_name' => 'Updated Name']);

    expect(GoalContribution::where('goal_id', $goal->id)->count())->toBe(0);
});

// InvestmentAccountGoalObserver tests

it('creates automatic contribution when linked investment account value increases', function () {
    $account = \App\Models\Investment\InvestmentAccount::factory()->create([
        'user_id' => $this->user->id,
        'current_value' => 20000.00,
    ]);

    $goal = Goal::factory()->create([
        'user_id' => $this->user->id,
        'goal_type' => 'wealth_accumulation',
        'assigned_module' => 'investment',
        'linked_investment_account_id' => $account->id,
        'current_amount' => 20000.00,
        'target_amount' => 50000.00,
    ]);

    $account->update(['current_value' => 22000.00]);

    $contribution = GoalContribution::where('goal_id', $goal->id)->first();
    expect($contribution)->not->toBeNull();
    expect((float) $contribution->amount)->toBe(2000.00);
    expect($contribution->contribution_type)->toBe('automatic');
    expect($contribution->streak_qualifying)->toBeFalse();

    $goal->refresh();
    expect((float) $goal->current_amount)->toBe(22000.00);
});

it('does not create contribution when investment value decreases', function () {
    $account = \App\Models\Investment\InvestmentAccount::factory()->create([
        'user_id' => $this->user->id,
        'current_value' => 20000.00,
    ]);

    Goal::factory()->create([
        'user_id' => $this->user->id,
        'linked_investment_account_id' => $account->id,
        'current_amount' => 20000.00,
    ]);

    $account->update(['current_value' => 18000.00]);

    expect(GoalContribution::count())->toBe(0);
});
