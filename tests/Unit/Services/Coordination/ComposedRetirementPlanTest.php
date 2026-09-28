<?php

declare(strict_types=1);

use App\Models\DCPension;
use App\Models\SavingsAccount;
use App\Models\User;
use App\Services\Coordination\ComposedModulePlanService;
use App\Services\Coordination\PlanSources\RetirementStrategySource;
use Database\Seeders\RetirementActionDefinitionSeeder;

beforeEach(function () {
    $this->seed(RetirementActionDefinitionSeeder::class);
});

it('exposes the seeded retirement strategy rows as metadata', function (): void {
    $rows = app(RetirementStrategySource::class)->metadataRows();

    expect($rows->pluck('strategy_type')->sort()->values()->all())->toBe([
        'carry_forward_unused_allowance',
        'increase_pension_contribution',
        'plan_retirement_income',
        'salary_sacrifice_pension',
    ]);
});

it('locks every retirement strategy whose required_data are unmet for a bare user', function (): void {
    $user = User::factory()->create(['target_retirement_age' => null]);

    $plan = app(ComposedModulePlanService::class)
        ->forSource(app(RetirementStrategySource::class), $user);

    $lockedTypes = collect($plan['locked'])->pluck('strategy_type')->sort()->values()->all();

    // Carry forward is not waiting on anything: a bare user has neither the
    // earnings nor the savings for it to apply (CSJ 2026-09-28).
    expect($lockedTypes)->toBe([
        'increase_pension_contribution',
        'plan_retirement_income',
        'salary_sacrifice_pension',
    ]);
});

it('waits for past pension payments only when carry forward could apply', function (): void {
    $user = User::factory()->create(['target_retirement_age' => null, 'annual_employment_income' => 150000]);
    SavingsAccount::factory()->for($user)->create(['current_balance' => 100000, 'ownership_type' => 'individual', 'ownership_percentage' => 100, 'joint_owner_id' => null]);

    $plan = app(ComposedModulePlanService::class)
        ->forSource(app(RetirementStrategySource::class), $user->fresh());

    expect(collect($plan['locked'])->pluck('strategy_type')->all())->toContain('carry_forward_unused_allowance');
});

it('unlocks the DC-pension strategies once a pension exists', function (): void {
    $user = User::factory()->create(['target_retirement_age' => null]);
    DCPension::factory()->create(['user_id' => $user->id]);

    $plan = app(ComposedModulePlanService::class)
        ->forSource(app(RetirementStrategySource::class), $user->fresh());

    $lockedTypes = collect($plan['locked'])->pluck('strategy_type')->all();

    // dc_pension_exists is now true → the two DC-pension strategies are no longer locked.
    expect($lockedTypes)->not->toContain('increase_pension_contribution')
        ->and($lockedTypes)->not->toContain('salary_sacrifice_pension')
        // still gated on data the bare user lacks
        ->and($lockedTypes)->toContain('plan_retirement_income');
});
