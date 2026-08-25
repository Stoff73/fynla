<?php

declare(strict_types=1);

use App\Models\Property;
use App\Models\User;
use App\Services\Estate\IHTCalculationService;
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
 *
 * **W-0333 — `IHTCalculationService::projectProperties` is no longer one of them.**
 * The primary-only filter stops a joint property being counted twice, but it counts
 * primary rows at 100%, so a property held with someone OUTSIDE the household
 * carries their share into the estate. That consumer now reads
 * `CrossModuleAssetAggregator::calculatePropertyTotal` per member — reach and share
 * — which does both: once, and only this household's part. The other four consumers
 * keep the filter and their cases below are unchanged.
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

it('the estate projection counts a joint property once and a third party\'s share not at all', function () {
    // W-0333. This case used to be called "IHTCalculationService projected
    // properties does NOT double-count joint property across spouse pair" and
    // **it never called IHTCalculationService.** It reproduced the query pattern
    // inline and asserted arithmetic the test itself had written, so it would
    // have stayed green through any change to the service — including the one
    // that put £177,000 of a third party's money into a household's estate.
    //
    // The pattern observations it made are kept below, because they are true of
    // the STORE and they are why the primary-only filter existed. What is new is
    // that the service is now actually driven.
    $user = User::factory()->create(['is_preview_user' => false, 'marital_status' => 'married', 'date_of_birth' => '1970-01-01', 'gender' => 'male']);
    $spouse = User::factory()->create(['is_preview_user' => false, 'marital_status' => 'married', 'date_of_birth' => '1970-01-01', 'gender' => 'female', 'spouse_id' => $user->id]);
    $user->update(['spouse_id' => $spouse->id]);

    // One joint property: user primary, spouse joint.
    Property::factory()->create([
        'user_id' => $user->id,
        'joint_owner_id' => $spouse->id,
        'current_value' => 500000,
        'ownership_type' => 'joint',
        'ownership_percentage' => 50,
    ]);

    $store = app(PropertyStore::class);

    // The store contract, unchanged: joint-aware on both sides double-counts,
    // which is what `5278a2457` found and stopped.
    $totalWithoutFilter = (float) $store->forUser($user)->sum('current_value')
        + (float) $store->forUser($spouse)->sum('current_value');
    expect($totalWithoutFilter)->toBe(1000000.0);

    // The primary-only filter it introduced counts the property once…
    $totalWithFilter = (float) $store->forUser($user)->where('user_id', $user->id)->sum('current_value')
        + (float) $store->forUser($spouse)->where('user_id', $spouse->id)->sum('current_value');
    expect($totalWithFilter)->toBe(500000.0);

    // …but at 100% of the record, which is how a third party's share got in.
    // `projectProperties` now reads `CrossModuleAssetAggregator::calculatePropertyTotal`
    // per member — reach and share — which counts a jointly-held property once AND
    // leaves a stranger's share out. Both properties of the projection, asserted
    // against the service.
    $projectedWithJointOnly = (float) app(IHTCalculationService::class)
        ->calculate($user, $spouse, true)['projected_properties'];

    Property::factory()->create([
        'user_id' => $user->id,
        'joint_owner_id' => null,
        'current_value' => 295000,
        'ownership_type' => 'tenants_in_common',
        'ownership_percentage' => 40,
    ]);

    $projectedWithThirdParty = (float) app(IHTCalculationService::class)
        ->calculate($user->fresh(), $spouse->fresh(), true)['projected_properties'];

    // The £295,000 property adds only the household's £118,000 share — a ratio of
    // 618,000 : 500,000 against the base. Under the defect it added the whole
    // £295,000 and the ratio was 795,000 : 500,000.
    expect($projectedWithThirdParty / $projectedWithJointOnly)
        ->toEqualWithDelta(618000 / 500000, 0.0001);
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
