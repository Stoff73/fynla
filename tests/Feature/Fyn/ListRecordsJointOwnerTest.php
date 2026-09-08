<?php

declare(strict_types=1);

use App\Agents\CoordinatingAgent;
use App\Models\SavingsAccount;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// The production guard, made explicit so this test fails the way the persona did.
beforeEach(fn () => Model::preventLazyLoading(true));
afterEach(fn () => Model::preventLazyLoading(false));

/**
 * fyn-wiring Batch A, Task 8. Fyn's list_records(savings_account) died with
 * "Attempted to lazy load [jointOwner]" on the young family persona: the
 * co-owner label reads the relation, the store did not load it, and lazy
 * loading is disabled outside production. The same tool also serves the
 * question-scoped fallback, so one joint account took both paths down.
 */
it('lists a joint savings account with its co-owner without lazy loading', function (): void {
    $user = User::factory()->create();
    $spouse = User::factory()->create(['first_name' => 'Emily']);
    SavingsAccount::factory()->create([
        'user_id' => $user->id,
        'ownership_type' => 'joint',
        'ownership_percentage' => 50,
        'joint_owner_id' => $spouse->id,
        'joint_owner_name' => null,
        'current_balance' => 8500,
    ]);
    // Eloquent only arms the lazy-load guard on collections of two or more, which
    // is why the persona (four accounts) hit it and a one-account fixture cannot.
    SavingsAccount::factory()->create(['user_id' => $user->id, 'ownership_type' => 'individual', 'joint_owner_id' => null, 'current_balance' => 3250]);

    $result = app(CoordinatingAgent::class)->executeTool('list_records', ['entity_type' => 'savings_account'], $user);

    expect($result)->not->toHaveKey('error')
        ->and($result['records'])->toHaveCount(2)
        ->and(collect($result['records'])->pluck('co_owner')->filter()->first())->toContain('Emily');
});
