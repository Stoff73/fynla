<?php

declare(strict_types=1);

use App\Models\SavingsAccount;
use App\Models\SavingsAccountValueSnapshot;
use App\Models\User;
use App\Services\Stores\Exceptions\StoreValidationException;
use App\Services\Stores\IngestSource;
use App\Services\Stores\SavingsStore;
use Database\Seeders\TaxConfigurationSeeder;
use Illuminate\Database\Eloquent\Collection;

// Canonical store create()/update() now materialises ISA-allowance-used %
// via TaxConfigService::getISAAllowances(). Seed the real tax configuration
// (same pattern as SavingsReadConsumerParityTest) so the ISA derived-column
// path has an active tax year.
beforeEach(function () {
    $this->seed(TaxConfigurationSeeder::class);
});

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

    expect($result)->toBeInstanceOf(Collection::class);
    expect($result)->toHaveCount(0);
});

it('SavingsStore::findMany returns only the requesting user\'s accounts (excludes cross-user ids)', function () {
    $userA = User::factory()->create();
    $userB = User::factory()->create();

    $accA1 = SavingsAccount::factory()->create(['user_id' => $userA->id]);
    $accB = SavingsAccount::factory()->create(['user_id' => $userB->id]);  // foreign — must be excluded
    SavingsAccount::factory()->create(['user_id' => $userA->id]); // not in ids — must be excluded

    $store = app(SavingsStore::class);
    $result = $store->findMany([$accA1->id, $accB->id], $userA);

    // Pre-refactor `whereIn('id', $ids)` returned BOTH; post-refactor user-scoped narrowing
    // returns only accA1. This is the security fix surfaced by PR 5c-2 review.
    expect($result->pluck('id')->toArray())->toBe([$accA1->id]);
});

it('SavingsStore::findMany includes joint-owner accounts where requesting user is secondary', function () {
    $userA = User::factory()->create();
    $userB = User::factory()->create();

    $jointAcc = SavingsAccount::factory()->create([
        'user_id' => $userA->id,
        'joint_owner_id' => $userB->id,
        'ownership_type' => 'joint',
        'ownership_percentage' => 50,
    ]);

    $store = app(SavingsStore::class);
    // userB requests the joint account — should be returned (joint_owner_id matches)
    $result = $store->findMany([$jointAcc->id], $userB);

    expect($result->pluck('id')->toArray())->toBe([$jointAcc->id]);
});

it('SavingsStore::findMany returns empty Collection for empty array', function () {
    $user = User::factory()->create();
    SavingsAccount::factory()->create(['user_id' => $user->id]);

    $store = app(SavingsStore::class);
    $result = $store->findMany([], $user);

    expect($result)->toBeInstanceOf(Collection::class);
    expect($result)->toHaveCount(0);
});

it('SavingsStore::create materialises balance_gbp and writes initial snapshot', function () {
    $user = User::factory()->create();
    $store = app(SavingsStore::class);

    $account = $store->create([
        'account_name' => 'Halifax', 'current_balance' => 1000, 'interest_rate' => 4.0,
        'ownership_type' => 'individual', 'ownership_percentage' => 100, 'country' => 'United Kingdom',
    ], $user, IngestSource::FORM);

    expect((float) $account->balance_gbp)->toBe(1000.00);
    expect((float) $account->annual_interest_projected_gbp)->toBe(40.00);
    expect($account->balance_gbp_calculated_at)->not->toBeNull();

    expect(SavingsAccountValueSnapshot::where('savings_account_id', $account->id)->count())
        ->toBeGreaterThanOrEqual(2); // balance + interest snapshots
});

it('SavingsStore::create materialises annual interest identically for percent and decimal interest_rate conventions', function () {
    $user = User::factory()->create();
    $store = app(SavingsStore::class);

    // Percent convention (onboarding/seeders): interest_rate 4.0
    $percentAcc = $store->create([
        'account_name' => 'Percent', 'current_balance' => 1000, 'interest_rate' => 4.0,
        'ownership_type' => 'individual', 'ownership_percentage' => 100, 'country' => 'United Kingdom',
    ], $user, IngestSource::FORM);

    // Decimal convention (factory): interest_rate 0.04
    $decimalAcc = $store->create([
        'account_name' => 'Decimal', 'current_balance' => 1000, 'interest_rate' => 0.04,
        'ownership_type' => 'individual', 'ownership_percentage' => 100, 'country' => 'United Kingdom',
    ], $user, IngestSource::FORM);

    expect((float) $percentAcc->annual_interest_projected_gbp)->toBe(40.00);
    expect((float) $decimalAcc->annual_interest_projected_gbp)->toBe(40.00);
});

it('SavingsStore::update fires snapshot only when policy threshold exceeded', function () {
    $user = User::factory()->create();
    $store = app(SavingsStore::class);

    $account = $store->create([
        'account_name' => 'Halifax', 'current_balance' => 1000,
        'ownership_type' => 'individual', 'ownership_percentage' => 100, 'country' => 'United Kingdom',
    ], $user, IngestSource::FORM);

    $initialSnapshotCount = SavingsAccountValueSnapshot::where('savings_account_id', $account->id)->count();

    // Sub-threshold change — no new snapshot. The spec §10.3 balance policy
    // fires when |Δ| > £100 OR relative change > 1%. From a £1,000 base a
    // sub-threshold delta must be < £10 (and < £100). £1,005 = £5 / 0.5%.
    // (The plan's draft used £1,020, but that is a 2% move which DOES exceed
    // the spec-defined 1% relative threshold — fixture corrected, policy
    // left at the spec contract.)
    $store->update($account->id, ['current_balance' => 1005], $user, IngestSource::FORM);

    expect(SavingsAccountValueSnapshot::where('savings_account_id', $account->id)->count())
        ->toBe($initialSnapshotCount);

    // Super-threshold change — snapshot fires
    $store->update($account->id, ['current_balance' => 5000], $user, IngestSource::FORM);

    expect(SavingsAccountValueSnapshot::where('savings_account_id', $account->id)->count())
        ->toBeGreaterThan($initialSnapshotCount);
});
