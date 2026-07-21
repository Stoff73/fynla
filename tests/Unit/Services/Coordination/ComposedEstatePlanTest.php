<?php

declare(strict_types=1);

use App\Models\Estate\LastingPowerOfAttorney;
use App\Models\Estate\Will;
use App\Models\User;
use App\Services\Coordination\ComposedModulePlanService;
use App\Services\Coordination\PlanSources\EstateStrategySource;
use Database\Seeders\EstateActionDefinitionSeeder;

beforeEach(function (): void {
    $this->seed(EstateActionDefinitionSeeder::class);
});

it('exposes the seeded estate strategy rows as metadata', function (): void {
    $rows = app(EstateStrategySource::class)->metadataRows();

    expect($rows->pluck('strategy_type')->sort()->values()->all())->toBe([
        'gift_to_reduce_estate',
        'make_a_will',
        'reduce_iht_exposure',
        'register_lpa',
    ]);
});

it('locks every estate strategy whose required_data are unmet for a bare user', function (): void {
    $user = User::factory()->create();

    $plan = app(ComposedModulePlanService::class)
        ->forSource(app(EstateStrategySource::class), $user);

    $lockedTypes = collect($plan['locked'])->pluck('strategy_type')->sort()->values()->all();

    // Bare user: no Will, no LPA, no savings/investment assets.
    // All four strategy rows should be locked.
    expect($lockedTypes)->toBe([
        'gift_to_reduce_estate',
        'make_a_will',
        'reduce_iht_exposure',
        'register_lpa',
    ]);
});

it('unlocks make_a_will once a Will record exists for the user', function (): void {
    $user = User::factory()->create();

    // Create a Will row — will_in_place availability key checks Will::exists()
    Will::factory()->withWill()->create(['user_id' => $user->id]);

    $plan = app(ComposedModulePlanService::class)
        ->forSource(app(EstateStrategySource::class), $user->fresh());

    $lockedTypes = collect($plan['locked'])->pluck('strategy_type')->all();

    // will_in_place is now true, so make_a_will is no longer locked.
    expect($lockedTypes)->not->toContain('make_a_will')
        // lpa_registered still false
        ->and($lockedTypes)->toContain('register_lpa')
        // estate_value_known still false (no savings or GIA)
        ->and($lockedTypes)->toContain('reduce_iht_exposure')
        ->and($lockedTypes)->toContain('gift_to_reduce_estate');
});

it('unlocks register_lpa once an LPA record exists for the user', function (): void {
    $user = User::factory()->create();

    LastingPowerOfAttorney::factory()->create(['user_id' => $user->id]);

    $plan = app(ComposedModulePlanService::class)
        ->forSource(app(EstateStrategySource::class), $user->fresh());

    $lockedTypes = collect($plan['locked'])->pluck('strategy_type')->all();

    expect($lockedTypes)->not->toContain('register_lpa')
        ->and($lockedTypes)->toContain('make_a_will')
        ->and($lockedTypes)->toContain('reduce_iht_exposure')
        ->and($lockedTypes)->toContain('gift_to_reduce_estate');
});
