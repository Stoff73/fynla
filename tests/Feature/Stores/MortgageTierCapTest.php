<?php

declare(strict_types=1);

use App\Models\Mortgage;
use App\Models\Property;
use App\Models\User;
use App\Services\Stores\Exceptions\TierLimitExceededException;
use App\Services\Stores\IngestSource;
use App\Services\Stores\MortgageStore;
use App\Services\Stores\TierGate;
use App\Services\Tiers\DbTierGate;
use Database\Seeders\TaxConfigurationSeeder;
use Database\Seeders\TierConfigurationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * Store-side tier-cap integration for mortgages.
 *
 * Spec §13 + TierConfigurationSeeder: free tier `count_caps['mortgage'] = 10`.
 * Premium is unlimited (`null`). Enforcement seam was wired in PR 1 of Pass 5
 * (`MortgageStore::enforceTierCap` invoked from `create()` and `updateOrCreate()`);
 * this test pins the contract.
 *
 * Note: `enforceTierCap` counts `Mortgage::where('user_id', $user->id)` —
 * records where the user is `joint_owner_id` do NOT count toward their cap.
 */
beforeEach(function () {
    $this->seed(TaxConfigurationSeeder::class);
    $this->seed(TierConfigurationSeeder::class);
});

it('refuses to create an 11th mortgage for a free-tier user', function () {
    $user = User::factory()->create(['tier' => 'free']);
    $store = app(MortgageStore::class);

    $property = Property::factory()->create(['user_id' => $user->id]);
    Mortgage::factory(10)->create(['user_id' => $user->id, 'property_id' => $property->id]);

    expect(fn () => $store->create([
        'user_id' => $user->id,
        'property_id' => $property->id,
        'lender_name' => 'Overflow Bank',
        'mortgage_type' => 'repayment',
        'outstanding_balance' => 150000,
        'monthly_payment' => 900,
        'ownership_type' => 'individual',
        'ownership_percentage' => 100,
    ], $user, IngestSource::FORM))->toThrow(TierLimitExceededException::class);
});

it('carries the entity key, current count and hard limit on the thrown exception', function () {
    $user = User::factory()->create(['tier' => 'free']);
    $store = app(MortgageStore::class);

    $property = Property::factory()->create(['user_id' => $user->id]);
    Mortgage::factory(10)->create(['user_id' => $user->id, 'property_id' => $property->id]);

    try {
        $store->create([
            'user_id' => $user->id,
            'property_id' => $property->id,
            'lender_name' => 'Overflow Bank',
            'mortgage_type' => 'repayment',
            'outstanding_balance' => 150000,
            'monthly_payment' => 900,
            'ownership_type' => 'individual',
            'ownership_percentage' => 100,
        ], $user, IngestSource::FORM);
        $this->fail('Expected TierLimitExceededException was not thrown');
    } catch (TierLimitExceededException $e) {
        expect($e->entityKey)->toBe(MortgageStore::ENTITY_KEY);
        expect($e->currentCount)->toBe(10);
        expect($e->hardLimit)->toBe(10);
    }
});

it('allows the first ten mortgages for a free-tier user', function () {
    $user = User::factory()->create(['tier' => 'free']);
    $store = app(MortgageStore::class);

    $property = Property::factory()->create(['user_id' => $user->id]);

    foreach (range(1, 10) as $i) {
        $store->create([
            'user_id' => $user->id,
            'property_id' => $property->id,
            'lender_name' => "Lender {$i} Bank",
            'mortgage_type' => 'repayment',
            'outstanding_balance' => 100000 + ($i * 10000),
            'monthly_payment' => 600 + ($i * 50),
            'ownership_type' => 'individual',
            'ownership_percentage' => 100,
        ], $user, IngestSource::FORM);
    }

    expect(Mortgage::where('user_id', $user->id)->count())->toBe(10);
});

it('does NOT enforce the cap for a Premium user (unlimited)', function () {
    $user = User::factory()->create(['tier' => 'premium']);
    $store = app(MortgageStore::class);

    $property = Property::factory()->create(['user_id' => $user->id]);
    Mortgage::factory(10)->create(['user_id' => $user->id, 'property_id' => $property->id]);

    $store->create([
        'user_id' => $user->id,
        'property_id' => $property->id,
        'lender_name' => 'Eleventh Lender Bank',
        'mortgage_type' => 'interest_only',
        'outstanding_balance' => 200000,
        'monthly_payment' => 750,
        'ownership_type' => 'individual',
        'ownership_percentage' => 100,
    ], $user, IngestSource::FORM);

    expect(Mortgage::where('user_id', $user->id)->count())->toBe(11);
});

it('enforces the cap under the global DbTierGate binding', function () {
    expect(app(TierGate::class))->toBeInstanceOf(DbTierGate::class);

    $user = User::factory()->create(['tier' => 'free']);
    $store = app(MortgageStore::class);

    $property = Property::factory()->create(['user_id' => $user->id]);
    Mortgage::factory(10)->create(['user_id' => $user->id, 'property_id' => $property->id]);

    expect(fn () => $store->create([
        'user_id' => $user->id,
        'property_id' => $property->id,
        'lender_name' => 'Cap Enforcement Bank',
        'mortgage_type' => 'repayment',
        'outstanding_balance' => 150000,
        'monthly_payment' => 900,
        'ownership_type' => 'individual',
        'ownership_percentage' => 100,
    ], $user, IngestSource::FORM))->toThrow(TierLimitExceededException::class);
});
