<?php

declare(strict_types=1);

use App\Models\Investment\InvestmentAccount;
use App\Models\User;
use App\Services\Stores\Exceptions\StoreValidationException;
use App\Services\Stores\IngestSource;
use App\Services\Stores\InvestmentAccountStore;
use Database\Seeders\TaxConfigurationSeeder;
use Database\Seeders\TierConfigurationSeeder;

beforeEach(function () {
    $this->seed(TaxConfigurationSeeder::class);
    $this->seed(TierConfigurationSeeder::class);
    $this->user = User::factory()->create();
    $this->store = app(InvestmentAccountStore::class);
});

function makeCanonical(int $userId, array $overrides = []): array
{
    return array_merge([
        'user_id' => $userId,
        'account_name' => 'Stocks & Shares ISA',
        'account_type' => 'isa',
        'ownership_type' => 'individual',
        'ownership_percentage' => 100.00,
        'current_value' => 10000.00,
        'provider' => 'Vanguard',
        'country' => 'United Kingdom',
    ], $overrides);
}

it('creates an investment account via the store', function () {
    $canonical = makeCanonical($this->user->id);

    $account = $this->store->create($canonical, $this->user, IngestSource::FORM);

    expect($account)->toBeInstanceOf(InvestmentAccount::class);
    expect((float) $account->current_value)->toEqual(10000.00);
    expect($account->user_id)->toBe($this->user->id);
    expect($account->account_type)->toBe('isa');
});

it('rejects ownership_type=tenants_in_common at the store layer', function () {
    $canonical = makeCanonical($this->user->id, ['ownership_type' => 'tenants_in_common']);

    expect(fn () => $this->store->create($canonical, $this->user, IngestSource::FORM))
        ->toThrow(StoreValidationException::class);
});

it('returns joint-aware reads via forUser', function () {
    $spouse = User::factory()->create();
    InvestmentAccount::factory()->create([
        'user_id' => $this->user->id,
        'joint_owner_id' => $spouse->id,
        'ownership_type' => 'joint',
    ]);

    expect($this->store->forUser($this->user)->count())->toBe(1);
    expect($this->store->forUser($spouse)->count())->toBe(1);
    expect($this->store->forUserPrimaryOnly($spouse)->count())->toBe(0);
});

it('forUserPrimaryOnly returns only primary-owner accounts', function () {
    InvestmentAccount::factory()->create(['user_id' => $this->user->id]);
    InvestmentAccount::factory()->create(['user_id' => $this->user->id]);

    expect($this->store->forUserPrimaryOnly($this->user)->count())->toBe(2);
});

it('forUserWithJointOwner eager-loads joint owner', function () {
    $spouse = User::factory()->create();
    InvestmentAccount::factory()->create([
        'user_id' => $this->user->id,
        'joint_owner_id' => $spouse->id,
        'ownership_type' => 'joint',
    ]);

    $accounts = $this->store->forUserWithJointOwner($this->user);

    expect($accounts->count())->toBe(1);
    expect($accounts->first()->relationLoaded('jointOwner'))->toBeTrue();
});

it('forUserByType returns accounts of that type only', function () {
    InvestmentAccount::factory()->create(['user_id' => $this->user->id, 'account_type' => 'isa']);
    InvestmentAccount::factory()->create(['user_id' => $this->user->id, 'account_type' => 'gia']);

    expect($this->store->forUserByType($this->user, 'isa')->count())->toBe(1);
    expect($this->store->forUserByType($this->user, 'gia')->count())->toBe(1);
});

it('find returns the account for the owner', function () {
    $account = InvestmentAccount::factory()->create(['user_id' => $this->user->id]);

    $found = $this->store->find($account->id, $this->user);

    expect($found)->not->toBeNull();
    expect($found->id)->toBe($account->id);
});

it('find returns null for another user account', function () {
    $other = User::factory()->create();
    $account = InvestmentAccount::factory()->create(['user_id' => $other->id]);

    expect($this->store->find($account->id, $this->user))->toBeNull();
});

it('findMany returns only ids belonging to the user', function () {
    $a = InvestmentAccount::factory()->create(['user_id' => $this->user->id]);
    $b = InvestmentAccount::factory()->create(['user_id' => $this->user->id]);
    $other = User::factory()->create();
    $c = InvestmentAccount::factory()->create(['user_id' => $other->id]);

    $found = $this->store->findMany([$a->id, $b->id, $c->id], $this->user);

    expect($found->count())->toBe(2);
    expect($found->pluck('id')->sort()->values()->toArray())->toBe(collect([$a->id, $b->id])->sort()->values()->toArray());
});

it('updates an investment account via the store', function () {
    $account = InvestmentAccount::factory()->create([
        'user_id' => $this->user->id,
        'current_value' => 10000.00,
    ]);

    $updated = $this->store->update($account->id, ['current_value' => 15000.00], $this->user, IngestSource::FORM);

    expect((float) $updated->current_value)->toEqual(15000.00);
});

it('soft-deletes and restores an investment account', function () {
    $account = InvestmentAccount::factory()->create(['user_id' => $this->user->id]);

    $this->store->delete($account->id, $this->user, IngestSource::FORM);

    expect(InvestmentAccount::find($account->id))->toBeNull();
    expect(InvestmentAccount::withTrashed()->find($account->id))->not->toBeNull();

    $restored = $this->store->restore($account->id, $this->user, IngestSource::FORM);

    expect($restored)->not->toBeNull();
    expect($restored->deleted_at)->toBeNull();
});

it('updateOrCreate creates a new account when no match exists', function () {
    $canonical = makeCanonical($this->user->id, ['account_name' => 'New GIA', 'account_type' => 'gia']);

    $account = $this->store->updateOrCreate($canonical, $this->user, IngestSource::SEEDER);

    expect($account->wasRecentlyCreated)->toBeTrue();
    expect(InvestmentAccount::where('account_name', 'New GIA')->count())->toBe(1);
});

it('updateOrCreate updates an existing account matched by (user_id, account_name, account_type)', function () {
    $existing = InvestmentAccount::factory()->create([
        'user_id' => $this->user->id,
        'account_name' => 'Halifax ISA',
        'account_type' => 'isa',
        'current_value' => 10000.00,
        'ownership_type' => 'individual',
        'ownership_percentage' => 100.00,
    ]);

    $canonical = makeCanonical($this->user->id, [
        'account_name' => 'Halifax ISA',
        'account_type' => 'isa',
        'current_value' => 20000.00,
    ]);

    $result = $this->store->updateOrCreate($canonical, $this->user, IngestSource::SEEDER);

    expect($result->id)->toBe($existing->id);
    expect((float) $result->current_value)->toEqual(20000.0);
});
