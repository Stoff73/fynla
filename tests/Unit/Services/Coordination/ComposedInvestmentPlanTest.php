<?php

declare(strict_types=1);

use App\Models\Investment\InvestmentAccount;
use App\Models\User;
use App\Services\Coordination\ComposedModulePlanService;
use App\Services\Coordination\PlanSources\InvestmentStrategySource;
use Database\Seeders\InvestmentActionDefinitionSeeder;

beforeEach(function () {
    $this->seed(InvestmentActionDefinitionSeeder::class);
});

it('exposes the seeded investment strategy rows as metadata', function (): void {
    $rows = app(InvestmentStrategySource::class)->metadataRows();

    expect($rows->pluck('strategy_type')->sort()->values()->all())->toBe([
        'rebalance_to_target',
        'reduce_platform_fees',
        'set_risk_profile',
    ]);
});

it('locks every investment strategy whose required_data are unmet for a bare user', function (): void {
    $user = User::factory()->create();

    $plan = app(ComposedModulePlanService::class)
        ->forSource(app(InvestmentStrategySource::class), $user);

    $lockedTypes = collect($plan['locked'])->pluck('strategy_type')->sort()->values()->all();

    expect($lockedTypes)->toBe([
        'rebalance_to_target',
        'reduce_platform_fees',
        'set_risk_profile',
    ]);
});

it('unlocks reduce_platform_fees once a non-ISA investment account exists', function (): void {
    $user = User::factory()->create();

    // A GIA (non-ISA) account satisfies the gia_holdings availability key.
    InvestmentAccount::factory()->gia()->create(['user_id' => $user->id]);

    $plan = app(ComposedModulePlanService::class)
        ->forSource(app(InvestmentStrategySource::class), $user->fresh());

    $lockedTypes = collect($plan['locked'])->pluck('strategy_type')->all();

    // gia_holdings is now true → reduce_platform_fees (requires only gia_holdings) leaves the
    // locked list. A strategy is locked only when its required_data are unmet AND it did not
    // fire; with gia_holdings met, reduce_platform_fees can never be locked. The risk-gated
    // strategies (rebalance_to_target, set_risk_profile) are intentionally not asserted here —
    // the agent may *fire* them for a GIA user, and a fired strategy is never "locked".
    expect($lockedTypes)->not->toContain('reduce_platform_fees');
});
