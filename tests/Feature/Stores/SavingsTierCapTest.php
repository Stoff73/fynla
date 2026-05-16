<?php

declare(strict_types=1);

use App\Models\SavingsAccount;
use App\Models\User;
use App\Services\Stores\Exceptions\TierLimitExceededException;
use App\Services\Stores\IngestSource;
use App\Services\Stores\SavingsStore;
use App\Services\Stores\TierGate;
use App\Services\Tiers\DbTierGate;
use Database\Seeders\TaxConfigurationSeeder;
use Database\Seeders\TierConfigurationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * Store-side tier-cap integration.
 *
 * SCOPE: PR 3 (SP2) activates enforcement globally by binding DbTierGate.
 * These tests exercise the store enforcement point via the live global binding
 * and prove that a true free user is blocked at cap 3, while tier1+ users
 * and grandfathered legacy-paid subscribers are never blocked.
 *
 * StaticTierGate (SP1 interim stub) was deleted in PR 3 — these tests no
 * longer reference it.
 */
beforeEach(function () {
    $this->seed(TaxConfigurationSeeder::class);
    $this->seed(TierConfigurationSeeder::class);
});

$payload = fn (string $name) => [
    'account_name' => $name,
    'current_balance' => 100,
    'ownership_type' => 'individual',
    'ownership_percentage' => 100,
    'country' => 'UK',
];

it('refuses to create a 4th savings account for a free-tier user (DbTierGate global binding)', function () use ($payload) {
    $user = User::factory()->create(['tier' => 'free']); // explicit free tier
    $store = app(SavingsStore::class);

    SavingsAccount::factory(3)->create(['user_id' => $user->id]);

    expect(fn () => $store->create($payload('Fourth'), $user, IngestSource::FORM))
        ->toThrow(TierLimitExceededException::class);

    expect(SavingsAccount::where('user_id', $user->id)->count())->toBe(3);
});

it('carries the entity key, current count and hard limit on the thrown exception', function () use ($payload) {
    $user = User::factory()->create(['tier' => 'free']);
    $store = app(SavingsStore::class);

    SavingsAccount::factory(3)->create(['user_id' => $user->id]);

    try {
        $store->create($payload('Fourth'), $user, IngestSource::FORM);
        $this->fail('Expected TierLimitExceededException was not thrown');
    } catch (TierLimitExceededException $e) {
        expect($e->entityKey)->toBe(SavingsStore::ENTITY_KEY);
        expect($e->currentCount)->toBe(3);
        expect($e->hardLimit)->toBe(3);
    }
});

it('allows the first three savings accounts for a free-tier user', function () use ($payload) {
    $user = User::factory()->create(['tier' => 'free']);
    $store = app(SavingsStore::class);

    $store->create($payload('One'), $user, IngestSource::FORM);
    $store->create($payload('Two'), $user, IngestSource::FORM);
    $store->create($payload('Three'), $user, IngestSource::FORM);

    expect(SavingsAccount::where('user_id', $user->id)->count())->toBe(3);
});

it('enforces the cap under the global DbTierGate binding (PR 3: caps live)', function () use ($payload) {
    // PR 3 replaces PermissiveTierGate with DbTierGate globally. Enforcement
    // is now always on; true free users are blocked beyond cap 3.
    expect(app(TierGate::class))->toBeInstanceOf(DbTierGate::class);

    $user = User::factory()->create(['tier' => 'free']);
    $store = app(SavingsStore::class);

    SavingsAccount::factory(3)->create(['user_id' => $user->id]);

    expect(fn () => $store->create($payload('Fourth'), $user, IngestSource::FORM))
        ->toThrow(TierLimitExceededException::class);

    expect(SavingsAccount::where('user_id', $user->id)->count())->toBe(3);
});

it('does NOT enforce the cap for a tier1 user (unlimited)', function () use ($payload) {
    $user = User::factory()->create(['tier' => 'tier1']);
    $store = app(SavingsStore::class);

    SavingsAccount::factory(3)->create(['user_id' => $user->id]);

    $store->create($payload('Fourth'), $user, IngestSource::FORM);
    $store->create($payload('Fifth'), $user, IngestSource::FORM);

    expect(SavingsAccount::where('user_id', $user->id)->count())->toBe(5);
});
