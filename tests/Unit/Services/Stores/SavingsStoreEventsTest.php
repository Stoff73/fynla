<?php

declare(strict_types=1);

use App\Events\Savings\SavingsAccountCreated;
use App\Events\Savings\SavingsAccountDeleted;
use App\Events\Savings\SavingsAccountUpdated;
use App\Models\SavingsAccount;
use App\Models\User;
use App\Services\Stores\IngestSource;
use App\Services\Stores\SavingsStore;
use Illuminate\Support\Facades\Event;

it('SavingsStore::create emits SavingsAccountCreated', function () {
    Event::fake();
    $user = User::factory()->create();

    app(SavingsStore::class)->create([
        'account_name' => 'X', 'current_balance' => 100,
        'ownership_type' => 'individual', 'ownership_percentage' => 100, 'country' => 'UK',
    ], $user, IngestSource::FORM);

    Event::assertDispatched(SavingsAccountCreated::class);
});

it('SavingsStore::update emits SavingsAccountUpdated with changes diff', function () {
    Event::fake();
    $user = User::factory()->create();
    $account = SavingsAccount::factory()->create(['user_id' => $user->id, 'current_balance' => 100]);

    app(SavingsStore::class)->update($account->id, ['current_balance' => 500], $user, IngestSource::FORM);

    Event::assertDispatched(SavingsAccountUpdated::class, function ($event) {
        return array_key_exists('current_balance', $event->changes);
    });
});

it('SavingsStore::delete emits SavingsAccountDeleted', function () {
    Event::fake();
    $user = User::factory()->create();
    $account = SavingsAccount::factory()->create(['user_id' => $user->id]);

    app(SavingsStore::class)->delete($account->id, $user, 'user_requested');

    Event::assertDispatched(SavingsAccountDeleted::class);
});
