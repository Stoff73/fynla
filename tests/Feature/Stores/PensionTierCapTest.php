<?php

declare(strict_types=1);

use App\Models\DBPension;
use App\Models\DCPension;
use App\Models\StatePension;
use App\Models\User;
use App\Services\Stores\Exceptions\TierLimitExceededException;
use App\Services\Stores\IngestSource;
use App\Services\Stores\PensionStore;
use App\Services\Stores\TierGate;
use App\Services\Tiers\DbTierGate;
use Database\Seeders\TaxConfigurationSeeder;
use Database\Seeders\TierConfigurationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * Store-side tier-cap integration for pensions.
 *
 * Spec §13: free tier = 5 pension_account (DC + DB combined). State pension
 * is one-per-user so not subject to the cap. tier1+ is unlimited.
 *
 * Mirrors the SavingsTierCapTest contract — SP1 PR 3 wired DbTierGate as the
 * global binding, so enforcement is always on. PR 7 of SP1 Pass 3 (pensions)
 * adds nothing to the store: the enforcement seam landed with PR 1
 * (PensionStore facade) and pension_account cap was already in
 * TierConfigurationSeeder. This test locks the contract.
 */
beforeEach(function () {
    $this->seed(TaxConfigurationSeeder::class);
    $this->seed(TierConfigurationSeeder::class);
});

it('refuses to create a 6th pension (DC + DB combined) for a free-tier user', function () {
    $user = User::factory()->create(['tier' => 'free']);
    $store = app(PensionStore::class);

    DCPension::factory(3)->create(['user_id' => $user->id]);
    DBPension::factory(2)->create(['user_id' => $user->id]);

    expect(fn () => $store->createDc(
        ['scheme_name' => 'Sixth', 'current_fund_value' => 100],
        $user,
        IngestSource::FORM
    ))->toThrow(TierLimitExceededException::class);

    expect(DCPension::where('user_id', $user->id)->count()
        + DBPension::where('user_id', $user->id)->count()
    )->toBe(5);
});

it('also refuses createDb when combined count is at the cap', function () {
    $user = User::factory()->create(['tier' => 'free']);
    $store = app(PensionStore::class);

    DCPension::factory(5)->create(['user_id' => $user->id]);

    expect(fn () => $store->createDb(
        ['scheme_name' => 'Sixth', 'scheme_type' => 'final_salary'],
        $user,
        IngestSource::FORM
    ))->toThrow(TierLimitExceededException::class);

    expect(DBPension::where('user_id', $user->id)->count())->toBe(0);
});

it('carries the entity key, current count and hard limit on the thrown exception', function () {
    $user = User::factory()->create(['tier' => 'free']);
    $store = app(PensionStore::class);

    DCPension::factory(3)->create(['user_id' => $user->id]);
    DBPension::factory(2)->create(['user_id' => $user->id]);

    try {
        $store->createDc(
            ['scheme_name' => 'Sixth', 'current_fund_value' => 100],
            $user,
            IngestSource::FORM
        );
        $this->fail('Expected TierLimitExceededException was not thrown');
    } catch (TierLimitExceededException $e) {
        expect($e->entityKey)->toBe(PensionStore::ENTITY_KEY);
        expect($e->currentCount)->toBe(5);
        expect($e->hardLimit)->toBe(5);
    }
});

it('allows unlimited pensions for a tier1 user', function () {
    $user = User::factory()->create(['tier' => 'tier1']);
    $store = app(PensionStore::class);

    DCPension::factory(10)->create(['user_id' => $user->id]);

    $store->createDc(
        ['scheme_name' => 'Eleventh', 'current_fund_value' => 100],
        $user,
        IngestSource::FORM
    );

    expect(DCPension::where('user_id', $user->id)->count())->toBe(11);
});

it('does not cap upsertState — state pension is one-per-user by nature', function () {
    $user = User::factory()->create(['tier' => 'free']);
    $store = app(PensionStore::class);

    DCPension::factory(5)->create(['user_id' => $user->id]);

    $store->upsertState(['ni_years_completed' => 10], $user, IngestSource::FORM);

    expect(StatePension::where('user_id', $user->id)->count())->toBe(1);
});

it('enforces the cap under the global DbTierGate binding (PR 3: caps live)', function () {
    expect(app(TierGate::class))->toBeInstanceOf(DbTierGate::class);

    $user = User::factory()->create(['tier' => 'free']);
    $store = app(PensionStore::class);

    DCPension::factory(5)->create(['user_id' => $user->id]);

    expect(fn () => $store->createDc(
        ['scheme_name' => 'Sixth', 'current_fund_value' => 100],
        $user,
        IngestSource::FORM
    ))->toThrow(TierLimitExceededException::class);
});
