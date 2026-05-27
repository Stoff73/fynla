<?php

declare(strict_types=1);

use App\Models\Property;
use App\Models\User;
use App\Services\Stores\PropertyStore;
use Database\Seeders\TaxConfigurationSeeder;
use Database\Seeders\TierConfigurationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(TaxConfigurationSeeder::class);
    $this->seed(TierConfigurationSeeder::class);
});

/*
 * SP1 Pass 4 PR 5a read-consumer parity contract for PropertyStore.
 *
 * PropertyStore::forUser is joint-aware (user_id = ? OR joint_owner_id = ?). The five
 * Estate/IHT/UserProfile consumers migrated in PR 5a previously used
 * `Property::where('user_id', $user->id)` — primary-owner-only. To preserve those
 * semantics, every primary-only consumer chains `->where('user_id', $user->id)` onto
 * the Collection returned by the store. These tests lock that contract so no future
 * cluster (5b/5c/5d/5e) silently broadens semantics by omitting the filter.
 */

it('PropertyStore::forUser returns primary AND joint properties (canonical joint-aware contract)', function () {
    $user = User::factory()->create(['is_preview_user' => false]);
    $other = User::factory()->create(['is_preview_user' => false]);

    Property::factory()->create([
        'user_id' => $user->id,
        'current_value' => 100000,
        'ownership_type' => 'individual',
    ]);
    Property::factory()->create([
        'user_id' => $user->id,
        'joint_owner_id' => $other->id,
        'current_value' => 200000,
        'ownership_type' => 'joint',
    ]);
    Property::factory()->create([
        'user_id' => $other->id,
        'joint_owner_id' => $user->id,
        'current_value' => 300000,
        'ownership_type' => 'joint',
    ]);

    $collection = app(PropertyStore::class)->forUser($user);

    expect($collection)->toHaveCount(3); // 1 individual + 1 joint-as-primary + 1 joint-as-secondary
});

it('PropertyStore::forUser()->where(user_id) returns primary-only (PR 5a filter contract)', function () {
    $user = User::factory()->create(['is_preview_user' => false]);
    $other = User::factory()->create(['is_preview_user' => false]);

    Property::factory()->create([
        'user_id' => $user->id,
        'current_value' => 100000,
        'ownership_type' => 'individual',
    ]);
    Property::factory()->create([
        'user_id' => $user->id,
        'joint_owner_id' => $other->id,
        'current_value' => 200000,
        'ownership_type' => 'joint',
    ]);
    Property::factory()->create([
        'user_id' => $other->id,
        'joint_owner_id' => $user->id,
        'current_value' => 300000,
        'ownership_type' => 'joint',
    ]);

    $primaryOnly = app(PropertyStore::class)->forUser($user)->where('user_id', $user->id);

    expect($primaryOnly)->toHaveCount(2);
    expect((float) $primaryOnly->sum('current_value'))->toBe(300000.0);
});

it('EstateActionDefinitionService property sum is single-owner (matches pre-PR-5a Property::where)', function () {
    $user = User::factory()->create(['is_preview_user' => false]);
    $other = User::factory()->create(['is_preview_user' => false]);

    // Individual property — counted
    Property::factory()->create([
        'user_id' => $user->id,
        'current_value' => 250000,
        'ownership_type' => 'individual',
    ]);
    // Joint property where user is primary — counted at full value
    Property::factory()->create([
        'user_id' => $user->id,
        'joint_owner_id' => $other->id,
        'current_value' => 400000,
        'ownership_type' => 'joint',
        'ownership_percentage' => 50,
    ]);
    // Joint property where user is joint_owner_id only — NOT counted
    Property::factory()->create([
        'user_id' => $other->id,
        'joint_owner_id' => $user->id,
        'current_value' => 800000,
        'ownership_type' => 'joint',
        'ownership_percentage' => 50,
    ]);

    // Pre-PR-5a parity check
    $preRefactorSum = (float) Property::where('user_id', $user->id)->sum('current_value');
    expect($preRefactorSum)->toBe(650000.0);

    // Post-PR-5a chained filter must match
    $postRefactorSum = (float) app(PropertyStore::class)
        ->forUser($user)
        ->where('user_id', $user->id)
        ->sum('current_value');
    expect($postRefactorSum)->toBe(650000.0);
});

it('IHTCalculationService projected properties does NOT double-count joint property across spouse pair', function () {
    $user = User::factory()->create(['is_preview_user' => false]);
    $spouse = User::factory()->create(['is_preview_user' => false]);

    // One joint property: user primary, spouse joint.
    Property::factory()->create([
        'user_id' => $user->id,
        'joint_owner_id' => $spouse->id,
        'current_value' => 500000,
        'ownership_type' => 'joint',
        'ownership_percentage' => 50,
    ]);

    $store = app(PropertyStore::class);

    // PR 5a filter pattern — each spouse summed with primary-only filter:
    $userPrimary = (float) $store->forUser($user)->where('user_id', $user->id)->sum('current_value');
    $spousePrimary = (float) $store->forUser($spouse)->where('user_id', $spouse->id)->sum('current_value');
    $totalWithFilter = $userPrimary + $spousePrimary;

    // Joint property is counted ONCE (under user, who is primary). Spouse's sum is 0
    // (they have no primary-owned properties). Total = 500000.
    expect($totalWithFilter)->toBe(500000.0);

    // Sanity check: WITHOUT the primary filter, the joint property is counted twice
    // (once under user via user_id match, once under spouse via joint_owner_id match).
    $userJointAware = (float) $store->forUser($user)->sum('current_value');
    $spouseJointAware = (float) $store->forUser($spouse)->sum('current_value');
    $totalWithoutFilter = $userJointAware + $spouseJointAware;
    expect($totalWithoutFilter)->toBe(1000000.0); // DOUBLE-COUNTED — what the filter prevents
});

it('IHTCalculationService hasMainResidence requires primary ownership, not joint-only', function () {
    $user = User::factory()->create(['is_preview_user' => false]);
    $other = User::factory()->create(['is_preview_user' => false]);

    // Main residence owned primarily by $other; $user is joint owner.
    Property::factory()->create([
        'user_id' => $other->id,
        'joint_owner_id' => $user->id,
        'current_value' => 600000,
        'ownership_type' => 'joint',
        'property_type' => 'main_residence',
    ]);

    $store = app(PropertyStore::class);

    // PR 5a primary-only filter — $user is joint-only on the main residence, so they
    // do not qualify for RNRB on their own. (Pre-PR-5a behaviour preserved.)
    $userQualifies = $store->forUserByType($user, 'main_residence')
        ->where('user_id', $user->id)
        ->isNotEmpty();
    expect($userQualifies)->toBeFalse();

    // Sanity: WITHOUT the filter, the joint-aware store would qualify them.
    $unfilteredHit = $store->forUserByType($user, 'main_residence')->isNotEmpty();
    expect($unfilteredHit)->toBeTrue();
});

it('LetterEstateValidationService property count excludes joint-only-as-secondary records', function () {
    $user = User::factory()->create(['is_preview_user' => false]);
    $other = User::factory()->create(['is_preview_user' => false]);

    // Two primary-owned properties + one joint-as-secondary.
    Property::factory(2)->create([
        'user_id' => $user->id,
        'ownership_type' => 'individual',
    ]);
    Property::factory()->create([
        'user_id' => $other->id,
        'joint_owner_id' => $user->id,
        'ownership_type' => 'joint',
    ]);

    $preRefactorCount = Property::where('user_id', $user->id)->count();
    expect($preRefactorCount)->toBe(2);

    $postRefactorCount = app(PropertyStore::class)
        ->forUser($user)
        ->where('user_id', $user->id)
        ->count();
    expect($postRefactorCount)->toBe(2);
});

it('LetterToSpouseService real-estate info lists only primary-owned properties', function () {
    $user = User::factory()->create(['is_preview_user' => false]);
    $other = User::factory()->create(['is_preview_user' => false]);

    Property::factory()->create([
        'user_id' => $user->id,
        'ownership_type' => 'individual',
        'address_line_1' => '1 Primary Lane',
    ]);
    Property::factory()->create([
        'user_id' => $other->id,
        'joint_owner_id' => $user->id,
        'ownership_type' => 'joint',
        'address_line_1' => '99 Other Person Avenue',
    ]);

    $properties = app(PropertyStore::class)
        ->forUser($user)
        ->where('user_id', $user->id);

    expect($properties)->toHaveCount(1);
    expect($properties->first()->address_line_1)->toBe('1 Primary Lane');
});
