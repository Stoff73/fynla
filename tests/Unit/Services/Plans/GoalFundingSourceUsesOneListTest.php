<?php

declare(strict_types=1);

use App\Models\Goal;
use App\Models\SavingsAccount;
use App\Models\User;
use App\Services\Plans\BasePlanService;
use App\Services\Plans\GoalPlanService;

/*
 * Review I4: the goal funding source kept its own account list — it left out
 * accounts where the user is the JOINT owner and read full balances (Rule 6).
 * It now reads FundingAccounts, the one list.
 */
it('offers a joint account the user co-owns, judged on the user share', function () {
    $owner = User::factory()->create();
    $user = User::factory()->create(['monthly_expenditure' => 0]);
    SavingsAccount::factory()->create([
        'user_id' => $owner->id, 'joint_owner_id' => $user->id, 'ownership_type' => 'joint', 'ownership_percentage' => 70,
        'account_name' => 'Shared Saver', 'institution' => 'Shared Saver', 'account_type' => 'easy_access', 'is_isa' => false,
        'current_balance' => 30000, 'interest_rate' => 4,
    ]);
    $goal = Goal::factory()->create(['user_id' => $user->id, 'target_amount' => 5000, 'current_amount' => 0]);

    $source = Closure::bind(fn () => $this->resolveFundingSource($goal), app(GoalPlanService::class), BasePlanService::class)();

    // The user's 30% share is £9,000 — enough for the £5,000 top-up.
    expect($source['name'])->toBe('Shared Saver');
});

it('does not offer a joint account whose user share is too small for the top-up', function () {
    $owner = User::factory()->create();
    $user = User::factory()->create(['monthly_expenditure' => 0]);
    SavingsAccount::factory()->create([
        'user_id' => $owner->id, 'joint_owner_id' => $user->id, 'ownership_type' => 'joint', 'ownership_percentage' => 90,
        'account_name' => 'Shared Saver', 'institution' => 'Shared Saver', 'account_type' => 'easy_access', 'is_isa' => false,
        'current_balance' => 30000, 'interest_rate' => 4,
    ]);
    $goal = Goal::factory()->create(['user_id' => $user->id, 'target_amount' => 5000, 'current_amount' => 0]);

    $source = Closure::bind(fn () => $this->resolveFundingSource($goal), app(GoalPlanService::class), BasePlanService::class)();

    // The user's 10% is £3,000, less than £5,000; the full £30,000 must not count.
    expect($source['name'])->toBeNull();
});
