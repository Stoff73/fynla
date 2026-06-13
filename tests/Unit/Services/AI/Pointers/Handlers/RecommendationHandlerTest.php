<?php

declare(strict_types=1);

use App\Models\User;
use App\Services\AI\Pointers\FetchContext;
use App\Services\AI\Pointers\Handlers\RecommendationHandler;
use Database\Seeders\TaxConfigurationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(fn () => $this->seed(TaxConfigurationSeeder::class));

it('fetches the live composed plan for the user as a JSON payload', function (): void {
    $user = User::factory()->create();
    $handler = app(RecommendationHandler::class);

    $res = $handler->fetch(new FetchContext($user, 'what should i do'));

    expect($handler->id())->toBe('recommendations')
        ->and($res->sourceLabel)->toBe('recommendation engine')
        ->and($res->value)->toBeString()
        ->and($res->sourceVersion)->not->toBe('')
        ->and($res->extra)->toHaveKeys(['strategy_ids', 'locked_strategy_ids']);
});

it('returns the composed tax plan as its payload', function (): void {
    $user = User::factory()->create();

    $res = app(RecommendationHandler::class)->fetch(new FetchContext($user, 'what should i do'));

    $decoded = json_decode($res->value, true);

    expect($decoded)->toBeArray()
        ->and($decoded)->toHaveKey('composed_tax_plan')
        ->and($decoded['composed_tax_plan'])->toHaveKeys(['items', 'combined_annual_saving', 'locked']);
});
