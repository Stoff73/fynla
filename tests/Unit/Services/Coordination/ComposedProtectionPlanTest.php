<?php

declare(strict_types=1);

use App\Models\LifeInsurancePolicy;
use App\Models\User;
use App\Services\Coordination\ComposedModulePlanService;
use App\Services\Coordination\PlanSources\ProtectionStrategySource;
use Database\Seeders\ProtectionActionDefinitionSeeder;

beforeEach(function () {
    $this->seed(ProtectionActionDefinitionSeeder::class);
});

it('exposes the seeded protection strategy rows as metadata', function (): void {
    $rows = app(ProtectionStrategySource::class)->metadataRows();

    expect($rows->pluck('strategy_type')->sort()->values()->all())->toBe([
        'protection_critical_illness_gap',
        'protection_income_protection_gap',
        'protection_life_cover_gap',
        'protection_policy_in_trust',
        'protection_policy_review',
    ]);
});

it('locks every protection strategy whose required_data are unmet for a bare user', function (): void {
    // A bare user has no FamilyMember, no LifeInsurancePolicy, no income fields set.
    $user = User::factory()->create([
        'annual_employment_income' => null,
        'annual_self_employment_income' => null,
    ]);

    $plan = app(ComposedModulePlanService::class)
        ->forSource(app(ProtectionStrategySource::class), $user);

    $lockedTypes = collect($plan['locked'])->pluck('strategy_type')->sort()->values()->all();

    expect($lockedTypes)->toBe([
        'protection_critical_illness_gap',
        'protection_income_protection_gap',
        'protection_life_cover_gap',
        'protection_policy_in_trust',
        'protection_policy_review',
    ]);
});

it('unlocks trust and review strategies once a life insurance policy exists', function (): void {
    $user = User::factory()->create([
        'annual_employment_income' => null,
        'annual_self_employment_income' => null,
    ]);

    LifeInsurancePolicy::factory()->create(['user_id' => $user->id]);

    $plan = app(ComposedModulePlanService::class)
        ->forSource(app(ProtectionStrategySource::class), $user->fresh());

    $lockedTypes = collect($plan['locked'])->pluck('strategy_type')->all();

    // life_cover_in_force is now true — trust and review strategies unlock.
    expect($lockedTypes)->not->toContain('protection_policy_in_trust')
        ->and($lockedTypes)->not->toContain('protection_policy_review')
        // income_known and dependants_known are still unmet for this bare user.
        ->and($lockedTypes)->toContain('protection_life_cover_gap')
        ->and($lockedTypes)->toContain('protection_income_protection_gap')
        ->and($lockedTypes)->toContain('protection_critical_illness_gap');
});
