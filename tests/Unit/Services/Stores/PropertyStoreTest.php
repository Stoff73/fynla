<?php

declare(strict_types=1);

use App\Models\Estate\Trust;
use App\Models\Property;
use App\Models\User;
use App\Services\Stores\Exceptions\StoreValidationException;
use App\Services\Stores\IngestSource;
use App\Services\Stores\PropertyStore;
use Database\Seeders\TaxConfigurationSeeder;
use Database\Seeders\TierConfigurationSeeder;
use Illuminate\Database\Eloquent\ModelNotFoundException;

beforeEach(function () {
    $this->seed(TaxConfigurationSeeder::class);
    $this->seed(TierConfigurationSeeder::class);
});

it('PropertyStore::create persists a Property row scoped to the user', function () {
    $user = User::factory()->create(['tier' => 'tier1']);
    $store = app(PropertyStore::class);

    $property = $store->create([
        'property_type' => 'main_residence',
        'ownership_type' => 'individual',
        'ownership_percentage' => 100,
        'address_line_1' => '5 Acacia Avenue',
        'city' => 'Bristol',
        'postcode' => 'BS1 1AA',
        'current_value' => 350000,
        'country' => 'United Kingdom',
    ], $user, IngestSource::FORM);

    expect($property->user_id)->toBe($user->id);
    expect($property->property_type)->toBe('main_residence');
    expect($property->ownership_type)->toBe('individual');
    expect((string) $property->ownership_percentage)->toBe('100.00');
    expect((string) $property->current_value)->toBe('350000.00');
});

it('PropertyStore::create accepts tenants_in_common ownership (property-only enum value)', function () {
    $user = User::factory()->create(['tier' => 'tier1']);
    $store = app(PropertyStore::class);

    $property = $store->create([
        'property_type' => 'main_residence',
        'ownership_type' => 'tenants_in_common',
        'ownership_percentage' => 60,
        'joint_owner_name' => 'Jane Doe',
        'current_value' => 500000,
    ], $user, IngestSource::FORM);

    expect($property->ownership_type)->toBe('tenants_in_common');
    expect((string) $property->ownership_percentage)->toBe('60.00');
});

it('PropertyStore::create rejects invalid property_type via inner validator', function () {
    $user = User::factory()->create(['tier' => 'tier1']);
    $store = app(PropertyStore::class);

    expect(fn () => $store->create([
        'property_type' => 'not_a_valid_type',
        'ownership_type' => 'individual',
        'current_value' => 100,
    ], $user, IngestSource::FORM))->toThrow(StoreValidationException::class);
});

it('PropertyStore::find is joint-aware (joint owner sees the same property)', function () {
    $owner = User::factory()->create(['tier' => 'tier1']);
    $jointOwner = User::factory()->create(['tier' => 'tier1']);
    $store = app(PropertyStore::class);

    $property = $store->create([
        'property_type' => 'main_residence',
        'ownership_type' => 'joint',
        'joint_ownership_type' => 'joint_tenancy',
        'joint_owner_id' => $jointOwner->id,
        'ownership_percentage' => 50,
        'current_value' => 400000,
    ], $owner, IngestSource::FORM);

    expect($store->find($property->id, $owner))->not->toBeNull();
    expect($store->find($property->id, $jointOwner)->id)->toBe($property->id);
});

it('PropertyStore::update is primary-owner-only — joint owner cannot mutate', function () {
    $owner = User::factory()->create(['tier' => 'tier1']);
    $jointOwner = User::factory()->create(['tier' => 'tier1']);
    $store = app(PropertyStore::class);

    $property = $store->create([
        'property_type' => 'main_residence',
        'ownership_type' => 'joint',
        'joint_ownership_type' => 'joint_tenancy',
        'joint_owner_id' => $jointOwner->id,
        'ownership_percentage' => 50,
        'current_value' => 400000,
    ], $owner, IngestSource::FORM);

    expect(fn () => $store->update($property->id, ['current_value' => 999999], $jointOwner, IngestSource::FORM))
        ->toThrow(ModelNotFoundException::class);
});

it('PropertyStore::forUser returns properties where user is primary or joint owner', function () {
    $alice = User::factory()->create(['tier' => 'tier1']);
    $bob = User::factory()->create(['tier' => 'tier1']);
    $store = app(PropertyStore::class);

    $store->create([
        'property_type' => 'main_residence',
        'ownership_type' => 'joint',
        'joint_owner_id' => $bob->id,
        'ownership_percentage' => 50,
        'current_value' => 400000,
    ], $alice, IngestSource::FORM);

    $store->create([
        'property_type' => 'buy_to_let',
        'ownership_type' => 'individual',
        'ownership_percentage' => 100,
        'current_value' => 200000,
    ], $alice, IngestSource::FORM);

    expect($store->forUser($alice)->count())->toBe(2);
    expect($store->forUser($bob)->count())->toBe(1);
});

it('PropertyStore::forTrust returns properties matching trust_id', function () {
    $user = User::factory()->create(['tier' => 'tier1']);
    $trust = Trust::factory()->create(['user_id' => $user->id]);

    Property::factory(2)->create([
        'user_id' => $user->id,
        'trust_id' => $trust->id,
        'ownership_type' => 'trust',
    ]);

    $collection = app(PropertyStore::class)->forTrust($trust->id);

    expect($collection)->toHaveCount(2);
    expect($collection->pluck('trust_id')->unique()->values()->all())->toBe([$trust->id]);
});

it('PropertyStore::forTrust returns empty Collection when trust has no properties', function () {
    $user = User::factory()->create(['tier' => 'tier1']);
    $trust = Trust::factory()->create(['user_id' => $user->id]);

    $collection = app(PropertyStore::class)->forTrust($trust->id);

    expect($collection)->toHaveCount(0);
});

it('PropertyStore::forTrust does NOT return properties where trust_id is null', function () {
    $user = User::factory()->create(['tier' => 'tier1']);
    $trust = Trust::factory()->create(['user_id' => $user->id]);

    // One trust-held property
    Property::factory()->create([
        'user_id' => $user->id,
        'trust_id' => $trust->id,
        'ownership_type' => 'trust',
    ]);
    // Three non-trust (trust_id is null) properties
    Property::factory(3)->create([
        'user_id' => $user->id,
        'trust_id' => null,
        'ownership_type' => 'individual',
    ]);

    $collection = app(PropertyStore::class)->forTrust($trust->id);

    expect($collection)->toHaveCount(1);
    expect($collection->first()->trust_id)->toBe($trust->id);
});

it('PropertyStore::delete soft-deletes; restore brings the row back', function () {
    $user = User::factory()->create(['tier' => 'tier1']);
    $store = app(PropertyStore::class);

    $property = $store->create([
        'property_type' => 'main_residence',
        'ownership_type' => 'individual',
        'ownership_percentage' => 100,
        'current_value' => 350000,
    ], $user, IngestSource::FORM);

    $store->delete($property->id, $user, 'sold');

    expect(Property::find($property->id))->toBeNull();
    expect(Property::withTrashed()->find($property->id))->not->toBeNull();

    $restored = $store->restore($property->id, $user);
    expect($restored->id)->toBe($property->id);
    expect(Property::find($property->id))->not->toBeNull();
});
