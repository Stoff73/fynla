<?php

declare(strict_types=1);

use App\Models\SavingsAccount;
use App\Models\User;
use App\Services\AI\Pointers\FetchContext;
use App\Services\AI\Pointers\Handlers\UserFinancialHandler;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('formalises the existing records summary as a fetch result', function (): void {
    $user = User::factory()->create();
    SavingsAccount::factory()->create(['user_id' => $user->id, 'current_balance' => 5000]);
    $handler = app(UserFinancialHandler::class);

    $res = $handler->fetch(new FetchContext($user, 'what accounts do i have'));

    expect($handler->id())->toBe('user_financial')
        ->and($res->sourceLabel)->toBe('user records')
        ->and($res->value)->toBeString();
});
