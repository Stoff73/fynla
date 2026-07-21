<?php

declare(strict_types=1);

use App\Models\User;
use App\Services\Coordination\ComposedModulePlanService;
use App\Services\Coordination\ComposedTaxPlanService;
use App\Services\Coordination\PlanSources\TaxStrategySource;
use Database\Seeders\TaxActionDefinitionSeeder;
use Database\Seeders\TaxConfigurationSeeder;

beforeEach(function () {
    $this->seed(TaxConfigurationSeeder::class);
    $this->seed(TaxActionDefinitionSeeder::class);
});

it('produces the same plan via TaxStrategySource as ComposedTaxPlanService::forUser', function (): void {
    $user = User::factory()->create(['annual_employment_income' => 110000]);

    $viaFacade = app(ComposedTaxPlanService::class)->forUser($user->fresh());
    $viaSource = app(ComposedModulePlanService::class)
        ->forSource(app(TaxStrategySource::class), $user->fresh());

    expect(ComposedModulePlanService::planDigest($viaSource))
        ->toBe(ComposedModulePlanService::planDigest($viaFacade));
});

it('reports moduleKey tax', function (): void {
    expect(app(TaxStrategySource::class)->moduleKey())->toBe('tax');
});
