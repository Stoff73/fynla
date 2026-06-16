<?php

declare(strict_types=1);

use App\Models\User;
use App\Services\AI\Pointers\FetchContext;
use App\Services\AI\Pointers\FetchHandlerRegistry;
use App\Services\AI\Pointers\FetchResult;
use App\Services\AI\Pointers\Handlers\CrossModulePlanHandler;
use App\Services\Coordination\CompositePlanService;
use Database\Seeders\EstateActionDefinitionSeeder;
use Database\Seeders\InvestmentActionDefinitionSeeder;
use Database\Seeders\ProtectionActionDefinitionSeeder;
use Database\Seeders\RetirementActionDefinitionSeeder;
use Database\Seeders\SavingsActionDefinitionSeeder;
use Database\Seeders\TaxActionDefinitionSeeder;
use Database\Seeders\TaxConfigurationSeeder;

beforeEach(function () {
    $this->seed([
        TaxConfigurationSeeder::class,
        TaxActionDefinitionSeeder::class,
        RetirementActionDefinitionSeeder::class,
        SavingsActionDefinitionSeeder::class,
        InvestmentActionDefinitionSeeder::class,
        ProtectionActionDefinitionSeeder::class,
        EstateActionDefinitionSeeder::class,
    ]);
});

it('composes a cross-module plan across all six modules with affordability + financials', function (): void {
    $user = User::factory()->create();

    $plan = app(CompositePlanService::class)->compose($user->fresh());

    expect($plan)->toHaveKeys([
        'items', 'by_module', 'locked', 'available_monthly_surplus',
        'goal_commitments', 'effective_surplus', 'goals',
    ])
        ->and(array_keys($plan['by_module']))->toEqualCanonicalizing([
            'tax', 'retirement', 'savings', 'investment', 'protection', 'estate',
        ]);

    // Every locked entry is module-tagged; the bare user locks strategies across modules.
    expect($plan['locked'])->not->toBeEmpty();
    foreach ($plan['locked'] as $lock) {
        expect($lock)->toHaveKey('module')->toHaveKey('strategy_type');
    }

    // Every surfaced item carries an affordability annotation (nothing dropped).
    foreach ($plan['items'] as $item) {
        expect($item)->toHaveKey('affordability')->toHaveKey('surplus_consumed_to_here')
            ->and($item['affordability'])->toBeIn(['fits', 'partially_fits', 'beyond_current_surplus']);
    }
});

it('registers the cross-module-plan handler and fetches the composite plan', function (): void {
    expect(app(FetchHandlerRegistry::class)->ids())->toContain('cross-module-plan');

    $result = app(CrossModulePlanHandler::class)
        ->fetch(new FetchContext(User::factory()->create(), 'what should I do overall'));

    expect($result)->toBeInstanceOf(FetchResult::class)
        ->and($result->value)->toContain('composite_plan')
        ->and(strlen($result->digest))->toBe(16)
        ->and($result->extra)->toHaveKey('item_count')
        ->and($result->extra)->toHaveKey('locked_count');
});
