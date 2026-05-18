<?php

declare(strict_types=1);

use App\Models\SavingsAccount;
use App\Models\User;
use App\Services\Stores\Exceptions\TierLimitExceededException;
use App\Services\Stores\IngestSource;
use App\Services\Stores\PermissiveTierGate;
use App\Services\Stores\SavingsStore;
use App\Services\Stores\StaticTierGate;
use App\Services\Stores\TierGate;
use Database\Seeders\TaxConfigurationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * Store-side tier-cap integration.
 *
 * SCOPE: the global TierGate binding stays PermissiveTierGate (sub-project 1
 * does NOT activate enforcement — no `users.tier` column exists). These tests
 * therefore bind StaticTierGate *locally* to exercise the store enforcement
 * point, and additionally prove that the unmodified global default does NOT
 * enforce — i.e. enforcement is strictly opt-in until sub-project 2.
 *
 * The plan's Step 7.1 fixtures used User::factory()->create(['tier'=>...]);
 * that would fail (no tier column). Free-tier resolves naturally (no attr);
 * tier1+ is proven authoritatively in the StaticTierGate unit test, since the
 * store re-counts/re-reads and an in-memory `tier` attr is not load-bearing
 * for the free-cap path under test here.
 */
beforeEach(function () {
    $this->seed(TaxConfigurationSeeder::class);
});

$payload = fn (string $name) => [
    'account_name' => $name,
    'current_balance' => 100,
    'ownership_type' => 'individual',
    'ownership_percentage' => 100,
    'country' => 'UK',
];

it('refuses to create a 4th savings account for a free-tier user when StaticTierGate is bound', function () use ($payload) {
    app()->bind(TierGate::class, StaticTierGate::class);

    $user = User::factory()->create(); // no tier attr → resolves 'free' (cap 3)
    $store = app(SavingsStore::class);

    SavingsAccount::factory(3)->create(['user_id' => $user->id]);

    expect(fn () => $store->create($payload('Fourth'), $user, IngestSource::FORM))
        ->toThrow(TierLimitExceededException::class);

    expect(SavingsAccount::where('user_id', $user->id)->count())->toBe(3);
});

it('carries the entity key, current count and hard limit on the thrown exception', function () use ($payload) {
    app()->bind(TierGate::class, StaticTierGate::class);

    $user = User::factory()->create();
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

it('allows the first three savings accounts for a free-tier user with StaticTierGate bound', function () use ($payload) {
    app()->bind(TierGate::class, StaticTierGate::class);

    $user = User::factory()->create();
    $store = app(SavingsStore::class);

    $store->create($payload('One'), $user, IngestSource::FORM);
    $store->create($payload('Two'), $user, IngestSource::FORM);
    $store->create($payload('Three'), $user, IngestSource::FORM);

    expect(SavingsAccount::where('user_id', $user->id)->count())->toBe(3);
});

it('does NOT enforce the cap under the unmodified global default (PermissiveTierGate)', function () use ($payload) {
    // No local bind — exercise the real container default. This proves
    // enforcement is opt-in and that PR 7 ships zero behavioural change to
    // the existing suite (the global binding remains PermissiveTierGate).
    expect(app(TierGate::class))->toBeInstanceOf(PermissiveTierGate::class);

    $user = User::factory()->create();
    $store = app(SavingsStore::class);

    SavingsAccount::factory(3)->create(['user_id' => $user->id]);

    $store->create($payload('Fourth'), $user, IngestSource::FORM);
    $store->create($payload('Fifth'), $user, IngestSource::FORM);

    expect(SavingsAccount::where('user_id', $user->id)->count())->toBe(5);
});
