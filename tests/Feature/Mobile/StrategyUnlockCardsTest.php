<?php

declare(strict_types=1);

use App\Models\User;
use App\Services\Mobile\NextActionsService;
use Database\Seeders\TaxActionDefinitionSeeder;
use Database\Seeders\TaxConfigurationSeeder;

beforeEach(function () {
    $this->seed(TaxConfigurationSeeder::class);
    $this->seed(TaxActionDefinitionSeeder::class);
});

it('surfaces a strategy-level unlock card naming the missing data point', function () {
    // Tax gate OPEN (has income + employment_status); no DC pension → salary_sacrifice_ni locked.
    $user = User::factory()->create([
        'date_of_birth' => '1982-02-19',
        'marital_status' => 'married',
        'employment_status' => 'full_time',
        'annual_employment_income' => 110000,
        'monthly_expenditure' => 3000,
    ]);

    $items = app(NextActionsService::class)->build($user->id);

    $strategyUnlocks = array_values(array_filter($items, fn ($a) => str_starts_with((string) ($a['id'] ?? ''), 'strategy_unlock:')));

    expect($strategyUnlocks)->not->toBeEmpty()
        ->and($strategyUnlocks[0]['id'])->toStartWith('strategy_unlock:')
        ->and($strategyUnlocks[0]['type'])->toBe('unlock')
        ->and($strategyUnlocks[0]['module'])->toBe('tax');

    // The card names the specific missing detail as a short noun (CSJ 4.2):
    // "Unlock pension info" / "Enter your pension details".
    $allTitleText = implode(' ', array_column($strategyUnlocks, 'title'));
    $allMetaText = implode(' ', array_column($strategyUnlocks, 'meta'));
    expect($allTitleText)->toContain('Unlock pension info')
        ->and($allMetaText)->toContain('Enter your pension details');
});

it('strategy unlock cards carry the full unlock item shape', function () {
    $user = User::factory()->create([
        'employment_status' => 'full_time',
        'annual_employment_income' => 110000,
    ]);

    $items = app(NextActionsService::class)->build($user->id);

    $strategyUnlock = collect($items)->first(fn ($a) => str_starts_with((string) ($a['id'] ?? ''), 'strategy_unlock:'));

    expect($strategyUnlock)->not->toBeNull()
        ->and($strategyUnlock)->toHaveKey('id')
        ->and($strategyUnlock)->toHaveKey('type')
        ->and($strategyUnlock)->toHaveKey('module')
        ->and($strategyUnlock)->toHaveKey('title')
        ->and($strategyUnlock)->toHaveKey('meta')
        ->and($strategyUnlock)->toHaveKey('value')
        ->and($strategyUnlock)->toHaveKey('done')
        ->and($strategyUnlock)->toHaveKey('action')
        ->and($strategyUnlock['action']['kind'])->toBe('fyn_capture')
        ->and($strategyUnlock['action']['payload'])->toBe('tax')
        ->and($strategyUnlock['done'])->toBeFalse();
});

it('emits no strategy unlock cards when the tax gate itself is closed', function () {
    // No income, no employment_status → tax gate closed; module-level unlock handles it.
    $user = User::factory()->create([
        'employment_status' => null,
        'annual_employment_income' => 0,
    ]);

    $items = app(NextActionsService::class)->build($user->id);

    $strategyUnlocks = array_values(array_filter($items, fn ($a) => str_starts_with((string) ($a['id'] ?? ''), 'strategy_unlock:')));

    expect($strategyUnlocks)->toBeEmpty();
});

it('surfaces at most two strategy unlock cards so the four-slot list is not crowded', function () {
    $user = User::factory()->create([
        'employment_status' => 'full_time',
        'annual_employment_income' => 110000,
    ]);

    $items = app(NextActionsService::class)->build($user->id);

    $strategyUnlocks = array_values(array_filter($items, fn ($a) => str_starts_with((string) ($a['id'] ?? ''), 'strategy_unlock:')));

    expect(count($strategyUnlocks))->toBeLessThanOrEqual(2);
});
