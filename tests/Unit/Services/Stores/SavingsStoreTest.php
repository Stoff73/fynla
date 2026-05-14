<?php

declare(strict_types=1);

use App\Models\SavingsAccount;
use App\Models\User;
use App\Services\Stores\Exceptions\StoreValidationException;
use App\Services\Stores\IngestSource;
use App\Services\Stores\SavingsStore;

it('SavingsStore::create persists a SavingsAccount through the canonical write path', function () {
    $user = User::factory()->create();
    $store = app(SavingsStore::class);

    $account = $store->create([
        'account_name' => 'Nationwide Cash ISA',
        'account_type' => 'cash_isa',
        'institution' => 'Nationwide',
        'current_balance' => 5000,
        'interest_rate' => 4.5,
        'is_isa' => true,
        'ownership_type' => 'individual',
        'ownership_percentage' => 100.00,
        'country' => 'United Kingdom',
    ], $user, IngestSource::FORM);

    expect($account)->toBeInstanceOf(SavingsAccount::class);
    expect($account->user_id)->toBe($user->id);
    expect($account->account_name)->toBe('Nationwide Cash ISA');
    expect((float) $account->current_balance)->toBe(5000.00);
    expect(SavingsAccount::count())->toBe(1);
});

it('SavingsStore::create rejects writes that violate canonical-shape rules', function () {
    // The store mirrors StoreSavingsAccountRequest (no field is strictly required at
    // store level), so we trigger a validation failure via an out-of-range enum.
    $user = User::factory()->create();
    $store = app(SavingsStore::class);

    expect(fn () => $store->create([
        'account_name' => 'Aviva',
        'ownership_type' => 'sole', // not in canonical enum (individual|joint|trust)
    ], $user, IngestSource::FORM))
        ->toThrow(StoreValidationException::class);

    expect(SavingsAccount::count())->toBe(0);
});

it('SavingsStore::update mutates the account through the canonical write path', function () {
    $user = User::factory()->create();
    $store = app(SavingsStore::class);

    $account = SavingsAccount::factory()->create([
        'user_id' => $user->id,
        'current_balance' => 1000,
    ]);

    $updated = $store->update($account->id, ['current_balance' => 2500], $user, IngestSource::FORM);

    expect((float) $updated->current_balance)->toBe(2500.00);
});

it('SavingsStore::delete soft-deletes the account', function () {
    $user = User::factory()->create();
    $store = app(SavingsStore::class);

    $account = SavingsAccount::factory()->create(['user_id' => $user->id]);

    $store->delete($account->id, $user, 'user_requested');

    expect(SavingsAccount::find($account->id))->toBeNull();
    expect(SavingsAccount::withTrashed()->find($account->id))->not->toBeNull();
});
