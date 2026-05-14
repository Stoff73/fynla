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

it('SavingsStore::updateOrCreate inserts when no match exists', function () {
    $user = User::factory()->create();
    $store = app(SavingsStore::class);

    $account = $store->updateOrCreate(
        match: ['account_name' => 'Chris Cash ISA'],
        data: ['current_balance' => 5000, 'account_type' => 'cash_isa'],
        user: $user,
        source: IngestSource::SEEDER,
    );

    expect(SavingsAccount::count())->toBe(1);
    expect($account->account_name)->toBe('Chris Cash ISA');
});

it('SavingsStore::updateOrCreate updates when match exists', function () {
    $user = User::factory()->create();
    $store = app(SavingsStore::class);

    SavingsAccount::factory()->create([
        'user_id' => $user->id,
        'account_name' => 'Chris Cash ISA',
        'current_balance' => 1000,
    ]);

    $store->updateOrCreate(
        match: ['account_name' => 'Chris Cash ISA'],
        data: ['current_balance' => 5000],
        user: $user,
        source: IngestSource::SEEDER,
    );

    expect(SavingsAccount::count())->toBe(1);
    expect((float) SavingsAccount::first()->current_balance)->toBe(5000.00);
});

it('SavingsStore::delete soft-deletes the account', function () {
    $user = User::factory()->create();
    $store = app(SavingsStore::class);

    $account = SavingsAccount::factory()->create(['user_id' => $user->id]);

    $store->delete($account->id, $user, 'user_requested');

    expect(SavingsAccount::find($account->id))->toBeNull();
    expect(SavingsAccount::withTrashed()->find($account->id))->not->toBeNull();
});

// PR 5c-2 — new read methods

it('SavingsStore::forUsers returns accounts where any user_id is in the array (joint-aware)', function () {
    $userA = User::factory()->create();
    $userB = User::factory()->create();
    $userC = User::factory()->create();

    // userA individual
    $acA = SavingsAccount::factory()->create(['user_id' => $userA->id, 'joint_owner_id' => null]);
    // userA primary, userC joint
    $acAC = SavingsAccount::factory()->create(['user_id' => $userA->id, 'joint_owner_id' => $userC->id]);
    // userB primary, userC joint
    $acBC = SavingsAccount::factory()->create(['user_id' => $userB->id, 'joint_owner_id' => $userC->id]);
    // userC individual — should NOT appear when querying [A, B]
    SavingsAccount::factory()->create(['user_id' => $userC->id, 'joint_owner_id' => null]);

    $store = app(SavingsStore::class);
    $result = $store->forUsers([$userA->id, $userB->id]);

    expect($result->pluck('id')->sort()->values()->toArray())
        ->toBe(collect([$acA->id, $acAC->id, $acBC->id])->sort()->values()->toArray());
});

it('SavingsStore::forUsers returns empty Collection for empty array', function () {
    SavingsAccount::factory()->create();

    $store = app(SavingsStore::class);
    $result = $store->forUsers([]);

    expect($result)->toBeInstanceOf(\Illuminate\Database\Eloquent\Collection::class);
    expect($result)->toHaveCount(0);
});

it('SavingsStore::findManyById returns all matching accounts by id regardless of user', function () {
    $userA = User::factory()->create();
    $userB = User::factory()->create();

    $acc1 = SavingsAccount::factory()->create(['user_id' => $userA->id]);
    $acc2 = SavingsAccount::factory()->create(['user_id' => $userB->id]);
    SavingsAccount::factory()->create(['user_id' => $userA->id]); // not in ids

    $store = app(SavingsStore::class);
    $result = $store->findManyById([$acc1->id, $acc2->id]);

    expect($result->pluck('id')->sort()->values()->toArray())
        ->toBe(collect([$acc1->id, $acc2->id])->sort()->values()->toArray());
});

it('SavingsStore::findManyById returns empty Collection for empty array', function () {
    SavingsAccount::factory()->create();

    $store = app(SavingsStore::class);
    $result = $store->findManyById([]);

    expect($result)->toBeInstanceOf(\Illuminate\Database\Eloquent\Collection::class);
    expect($result)->toHaveCount(0);
});
