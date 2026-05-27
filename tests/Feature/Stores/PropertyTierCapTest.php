<?php

declare(strict_types=1);

use App\Models\Property;
use App\Models\User;
use App\Services\Stores\Exceptions\TierLimitExceededException;
use App\Services\Stores\IngestSource;
use App\Services\Stores\PropertyStore;
use App\Services\Stores\TierGate;
use App\Services\Tiers\DbTierGate;
use Database\Seeders\TaxConfigurationSeeder;
use Database\Seeders\TierConfigurationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * Store-side tier-cap integration for properties.
 *
 * Spec §13 + TierConfigurationSeeder: free tier `count_caps['property'] = 3`.
 * tier1+ is unlimited (`null`). Enforcement seam was wired in PR 1 of Pass 4
 * (`PropertyStore::enforceTierCap` invoked from `create()`); this test pins
 * the contract.
 */
beforeEach(function () {
    $this->seed(TaxConfigurationSeeder::class);
    $this->seed(TierConfigurationSeeder::class);
});

it('refuses to create a 4th property for a free-tier user', function () {
    $user = User::factory()->create(['tier' => 'free']);
    $store = app(PropertyStore::class);

    Property::factory(3)->create(['user_id' => $user->id]);

    expect(fn () => $store->create([
        'property_type' => 'main_residence',
        'ownership_type' => 'individual',
        'current_value' => 100000,
    ], $user, IngestSource::FORM))->toThrow(TierLimitExceededException::class);
});

it('carries the entity key, current count and hard limit on the thrown exception', function () {
    $user = User::factory()->create(['tier' => 'free']);
    $store = app(PropertyStore::class);

    Property::factory(3)->create(['user_id' => $user->id]);

    try {
        $store->create([
            'property_type' => 'main_residence',
            'ownership_type' => 'individual',
            'current_value' => 100000,
        ], $user, IngestSource::FORM);
        $this->fail('Expected TierLimitExceededException was not thrown');
    } catch (TierLimitExceededException $e) {
        expect($e->entityKey)->toBe(PropertyStore::ENTITY_KEY);
        expect($e->currentCount)->toBe(3);
        expect($e->hardLimit)->toBe(3);
    }
});

it('allows the first three properties for a free-tier user', function () {
    $user = User::factory()->create(['tier' => 'free']);
    $store = app(PropertyStore::class);

    foreach (range(1, 3) as $i) {
        $store->create([
            'property_type' => 'main_residence',
            'ownership_type' => 'individual',
            'current_value' => 100000 + ($i * 10000),
            'address_line_1' => "{$i} Test Street",
        ], $user, IngestSource::FORM);
    }

    expect(Property::where('user_id', $user->id)->count())->toBe(3);
});

it('does NOT enforce the cap for a tier1 user (unlimited)', function () {
    $user = User::factory()->create(['tier' => 'tier1']);
    $store = app(PropertyStore::class);

    Property::factory(3)->create(['user_id' => $user->id]);

    $store->create([
        'property_type' => 'buy_to_let',
        'ownership_type' => 'individual',
        'current_value' => 200000,
    ], $user, IngestSource::FORM);

    expect(Property::where('user_id', $user->id)->count())->toBe(4);
});

it('enforces the cap under the global DbTierGate binding', function () {
    expect(app(TierGate::class))->toBeInstanceOf(DbTierGate::class);

    $user = User::factory()->create(['tier' => 'free']);
    $store = app(PropertyStore::class);

    Property::factory(3)->create(['user_id' => $user->id]);

    expect(fn () => $store->create([
        'property_type' => 'main_residence',
        'ownership_type' => 'individual',
        'current_value' => 100000,
    ], $user, IngestSource::FORM))->toThrow(TierLimitExceededException::class);
});
