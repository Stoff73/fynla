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
 * These tests exercise the SavingsStore enforcement seam via the live global
 * binding and cover:
 *  - a true free user is blocked at cap 2 (third create throws);
 *  - the thrown TierLimitExceededException carries entity key / count / limit;
 *  - the first two free-tier creates succeed;
 *  - the global binding really is DbTierGate (caps live);
 *  - a Premium user is unlimited (cap not enforced).
 *
 * The grandfathered-legacy-paid bypass is NOT proven here — that invariant is
 * a gate-level concern owned by tests/Unit/Services/Tiers/DbTierGateTest.php
 * (plan §1058–1064). See that file for the §4.4 grandfather proof.
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

it('refuses to create a third savings account for a free-tier user (DbTierGate global binding)', function () use ($payload) {
    $user = User::factory()->create(['tier' => 'free']); // explicit free tier
    $store = app(SavingsStore::class);

    SavingsAccount::factory(2)->create(['user_id' => $user->id]);

    expect(fn () => $store->create($payload('Third'), $user, IngestSource::FORM))
        ->toThrow(TierLimitExceededException::class);

    expect(SavingsAccount::where('user_id', $user->id)->count())->toBe(2);
});

it('carries the entity key, current count and hard limit on the thrown exception', function () use ($payload) {
    $user = User::factory()->create(['tier' => 'free']);
    $store = app(SavingsStore::class);

    SavingsAccount::factory(2)->create(['user_id' => $user->id]);

    try {
        $store->create($payload('Third'), $user, IngestSource::FORM);
        $this->fail('Expected TierLimitExceededException was not thrown');
    } catch (TierLimitExceededException $e) {
        expect($e->entityKey)->toBe(SavingsStore::ENTITY_KEY);
        expect($e->currentCount)->toBe(2);
        expect($e->hardLimit)->toBe(2);
    }
});

it('allows the first two savings accounts for a free-tier user', function () use ($payload) {
    $user = User::factory()->create(['tier' => 'free']);
    $store = app(SavingsStore::class);

    $store->create($payload('One'), $user, IngestSource::FORM);
    $store->create($payload('Two'), $user, IngestSource::FORM);

    expect(SavingsAccount::where('user_id', $user->id)->count())->toBe(2);
});

it('enforces the cap under the global DbTierGate binding (PR 3: caps live)', function () use ($payload) {
    // PR 3 replaces PermissiveTierGate with DbTierGate globally. Enforcement
    // is now always on; true free users are blocked beyond cap 2.
    expect(app(TierGate::class))->toBeInstanceOf(DbTierGate::class);

    $user = User::factory()->create(['tier' => 'free']);
    $store = app(SavingsStore::class);

    SavingsAccount::factory(2)->create(['user_id' => $user->id]);

    expect(fn () => $store->create($payload('Third'), $user, IngestSource::FORM))
        ->toThrow(TierLimitExceededException::class);

    expect(SavingsAccount::where('user_id', $user->id)->count())->toBe(2);
});

it('does NOT enforce the cap for a Premium user (unlimited)', function () use ($payload) {
    $user = User::factory()->withActivePremiumSubscription()->create(['tier' => 'premium']);
    $store = app(SavingsStore::class);

    SavingsAccount::factory(3)->create(['user_id' => $user->id]);

    $store->create($payload('Fourth'), $user, IngestSource::FORM);
    $store->create($payload('Fifth'), $user, IngestSource::FORM);

    expect(SavingsAccount::where('user_id', $user->id)->count())->toBe(5);
});
