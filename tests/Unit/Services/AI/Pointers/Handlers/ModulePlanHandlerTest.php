<?php

declare(strict_types=1);

use App\Models\User;
use App\Services\AI\Pointers\FetchContext;
use App\Services\AI\Pointers\FetchHandlerRegistry;
use App\Services\AI\Pointers\FetchResult;
use App\Services\AI\Pointers\Handlers\RetirementPlanHandler;
use Database\Seeders\RetirementActionDefinitionSeeder;

it('registers all five module-plan handler ids in the whitelist', function (): void {
    $ids = app(FetchHandlerRegistry::class)->ids();

    expect($ids)
        ->toContain('retirement-plan')
        ->toContain('savings-plan')
        ->toContain('investment-plan')
        ->toContain('protection-plan')
        ->toContain('estate-plan');
});

it('fetches a composed module plan as a FetchResult keyed by the module', function (): void {
    $this->seed(RetirementActionDefinitionSeeder::class);
    $user = User::factory()->create();

    $result = app(RetirementPlanHandler::class)
        ->fetch(new FetchContext($user, 'what should I do about my pension'));

    expect($result)->toBeInstanceOf(FetchResult::class)
        ->and($result->value)->toContain('composed_retirement_plan')
        ->and(strlen($result->digest))->toBe(16)
        ->and($result->extra)->toHaveKey('strategy_ids')
        ->and($result->extra)->toHaveKey('locked_strategy_ids');
});
