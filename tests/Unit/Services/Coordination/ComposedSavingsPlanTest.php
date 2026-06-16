<?php

declare(strict_types=1);

use App\Models\SavingsAccount;
use App\Models\User;
use App\Services\Coordination\ComposedModulePlanService;
use App\Services\Coordination\PlanSources\SavingsStrategySource;
use Database\Seeders\SavingsActionDefinitionSeeder;

beforeEach(function () {
    $this->seed(SavingsActionDefinitionSeeder::class);
});

it('exposes the seeded savings strategy rows as metadata', function (): void {
    $rows = app(SavingsStrategySource::class)->metadataRows();

    expect($rows->pluck('strategy_type')->sort()->values()->all())->toBe([
        'build_emergency_fund',
        'move_to_high_interest',
        'regular_savings_habit',
    ]);
});

it('locks every savings strategy whose required_data are unmet for a bare user', function (): void {
    $user = User::factory()->create();

    $plan = app(ComposedModulePlanService::class)
        ->forSource(app(SavingsStrategySource::class), $user);

    $lockedTypes = collect($plan['locked'])->pluck('strategy_type')->sort()->values()->all();

    expect($lockedTypes)->toBe([
        'build_emergency_fund',
        'move_to_high_interest',
        'regular_savings_habit',
    ]);
});

it('unlocks move_to_high_interest once a savings account exists', function (): void {
    $user = User::factory()->create();
    SavingsAccount::factory()->create(['user_id' => $user->id]);

    $plan = app(ComposedModulePlanService::class)
        ->forSource(app(SavingsStrategySource::class), $user->fresh());

    $lockedTypes = collect($plan['locked'])->pluck('strategy_type')->all();

    // savings_balances is now true → move_to_high_interest unlocks.
    expect($lockedTypes)->not->toContain('move_to_high_interest')
        // build_emergency_fund still requires emergency_fund_target_known too.
        ->and($lockedTypes)->toContain('build_emergency_fund')
        // regular_savings_habit is gated on emergency_fund_target_known alone.
        ->and($lockedTypes)->toContain('regular_savings_habit');
});
