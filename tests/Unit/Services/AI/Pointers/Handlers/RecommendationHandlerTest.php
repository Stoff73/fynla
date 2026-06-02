<?php

declare(strict_types=1);

use App\Models\User;
use App\Services\AI\Pointers\FetchContext;
use App\Services\AI\Pointers\Handlers\RecommendationHandler;
use Database\Seeders\TaxConfigurationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(fn () => $this->seed(TaxConfigurationSeeder::class));

it('fetches live recommendations for the user as rendered text', function (): void {
    $user = User::factory()->create();
    $handler = app(RecommendationHandler::class);

    $res = $handler->fetch(new FetchContext($user, 'what should i do'));

    expect($handler->id())->toBe('recommendations')
        ->and($res->sourceLabel)->toBe('recommendation engine')
        ->and($res->value)->toBeString()
        ->and($res->sourceVersion)->not->toBe('');
});
