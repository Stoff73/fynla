<?php

declare(strict_types=1);

use App\Models\Goal;
use App\Models\SavingsAccount;
use App\Models\User;
use App\Services\Plans\SavingsPlanService;
use Database\Seeders\SavingsActionDefinitionSeeder;
use Database\Seeders\TaxConfigurationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(TaxConfigurationSeeder::class);
    $this->seed(SavingsActionDefinitionSeeder::class);
});

/**
 * fyn-wiring Batch A deleted the plan's separate goal evaluation (goals are seeded
 * rows evaluated by the one service now) but left `'goals' => $goals` in the
 * response with nothing assigning it. Under Laravel's error handler that is an
 * ErrorException, so the whole plan failed. The `goals` block is the linked /
 * unlinked goal list the plan has always carried, and it must still be there.
 */
it('builds the savings plan with the linked and unlinked goal lists', function () {
    $user = User::factory()->create([
        'employment_status' => 'employed',
        'annual_employment_income' => 45000,
        'monthly_expenditure' => 2000,
        'date_of_birth' => '1985-01-01',
        'marital_status' => 'single',
    ]);
    SavingsAccount::factory()->create(['user_id' => $user->id, 'current_balance' => 4000, 'interest_rate' => 1.5]);
    Goal::factory()->create(['user_id' => $user->id, 'assigned_module' => 'savings', 'goal_name' => 'House Deposit']);

    $plan = app(SavingsPlanService::class)->generatePlan($user->id);

    expect($plan['success'])->toBeTrue()
        ->and($plan['goals'])->toHaveKeys(['linked', 'unlinked'])
        ->and(collect($plan['goals']['unlinked'])->pluck('name'))->toContain('House Deposit')
        ->and($plan['actions'])->not->toBeEmpty();
});
