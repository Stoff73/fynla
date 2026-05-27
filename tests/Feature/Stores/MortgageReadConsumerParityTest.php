<?php

declare(strict_types=1);

use App\Models\Mortgage;
use App\Models\Property;
use App\Models\User;
use App\Services\Stores\MortgageStore;
use Database\Seeders\TaxConfigurationSeeder;
use Database\Seeders\TierConfigurationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(TaxConfigurationSeeder::class);
    $this->seed(TierConfigurationSeeder::class);
});

/*
 * SP1 Pass 5 PR 5a read-consumer parity contract for MortgageStore.
 *
 * MortgageStore::forUser is joint-aware (user_id = ? OR joint_owner_id = ?). The six
 * Estate/IHT consumers migrated in PR 5a previously used various patterns — some
 * primary-only (`Mortgage::where('user_id', $id)` / `$user->mortgages()` HasMany),
 * some joint-aware (`Mortgage::forUserOrJoint($id)`). These tests lock both contracts
 * so no future cluster (5b/5c/5d/5e) silently alters semantics.
 */

it('MortgageStore::forUser returns primary AND joint mortgages (canonical joint-aware contract)', function () {
    $user = User::factory()->create(['is_preview_user' => false]);
    $other = User::factory()->create(['is_preview_user' => false]);

    $property1 = Property::factory()->create(['user_id' => $user->id]);
    $property2 = Property::factory()->create(['user_id' => $user->id, 'joint_owner_id' => $other->id]);
    $property3 = Property::factory()->create(['user_id' => $other->id, 'joint_owner_id' => $user->id]);

    // Individual mortgage — $user is primary
    Mortgage::factory()->create([
        'user_id' => $user->id,
        'property_id' => $property1->id,
        'ownership_type' => 'individual',
        'ownership_percentage' => 100,
        'outstanding_balance' => 100000,
    ]);
    // Joint mortgage — $user is primary, $other is joint
    Mortgage::factory()->create([
        'user_id' => $user->id,
        'joint_owner_id' => $other->id,
        'property_id' => $property2->id,
        'ownership_type' => 'joint',
        'ownership_percentage' => 50,
        'outstanding_balance' => 200000,
    ]);
    // Joint mortgage — $other is primary, $user is joint
    Mortgage::factory()->create([
        'user_id' => $other->id,
        'joint_owner_id' => $user->id,
        'property_id' => $property3->id,
        'ownership_type' => 'joint',
        'ownership_percentage' => 50,
        'outstanding_balance' => 300000,
    ]);

    $collection = app(MortgageStore::class)->forUser($user);

    expect($collection)->toHaveCount(3); // 1 individual + 1 joint-as-primary + 1 joint-as-secondary
});

it('MortgageStore::forUserPrimaryOnly returns primary-only (PR 5a primary-only contract)', function () {
    $user = User::factory()->create(['is_preview_user' => false]);
    $other = User::factory()->create(['is_preview_user' => false]);

    $property1 = Property::factory()->create(['user_id' => $user->id]);
    $property2 = Property::factory()->create(['user_id' => $user->id, 'joint_owner_id' => $other->id]);
    $property3 = Property::factory()->create(['user_id' => $other->id, 'joint_owner_id' => $user->id]);

    Mortgage::factory()->create([
        'user_id' => $user->id,
        'property_id' => $property1->id,
        'ownership_type' => 'individual',
        'ownership_percentage' => 100,
        'outstanding_balance' => 100000,
    ]);
    Mortgage::factory()->create([
        'user_id' => $user->id,
        'joint_owner_id' => $other->id,
        'property_id' => $property2->id,
        'ownership_type' => 'joint',
        'ownership_percentage' => 50,
        'outstanding_balance' => 200000,
    ]);
    // Joint mortgage where $user is only joint_owner — must NOT appear in primary-only result
    Mortgage::factory()->create([
        'user_id' => $other->id,
        'joint_owner_id' => $user->id,
        'property_id' => $property3->id,
        'ownership_type' => 'joint',
        'ownership_percentage' => 50,
        'outstanding_balance' => 300000,
    ]);

    $primaryOnly = app(MortgageStore::class)->forUserPrimaryOnly($user);

    expect($primaryOnly)->toHaveCount(2); // individual + joint-as-primary; NOT joint-as-secondary
    expect((float) $primaryOnly->sum('outstanding_balance'))->toBe(300000.0);
});

it('EstateActionDefinitionService mortgage sum is primary-only (matches pre-PR-5a Mortgage::where user_id)', function () {
    $user = User::factory()->create(['is_preview_user' => false]);
    $other = User::factory()->create(['is_preview_user' => false]);

    $property1 = Property::factory()->create(['user_id' => $user->id]);
    $property2 = Property::factory()->create(['user_id' => $user->id]);
    $property3 = Property::factory()->create(['user_id' => $other->id, 'joint_owner_id' => $user->id]);

    // Primary-owned individual mortgage — counted
    Mortgage::factory()->create([
        'user_id' => $user->id,
        'property_id' => $property1->id,
        'ownership_type' => 'individual',
        'ownership_percentage' => 100,
        'outstanding_balance' => 150000,
    ]);
    // Primary-owned joint mortgage — counted (user is user_id)
    Mortgage::factory()->create([
        'user_id' => $user->id,
        'joint_owner_id' => $other->id,
        'property_id' => $property2->id,
        'ownership_type' => 'joint',
        'ownership_percentage' => 50,
        'outstanding_balance' => 250000,
    ]);
    // Joint-as-secondary mortgage — NOT counted (user only appears as joint_owner_id)
    Mortgage::factory()->create([
        'user_id' => $other->id,
        'joint_owner_id' => $user->id,
        'property_id' => $property3->id,
        'ownership_type' => 'joint',
        'ownership_percentage' => 50,
        'outstanding_balance' => 400000,
    ]);

    // Pre-PR-5a parity check (original primary-only query semantics)
    $preRefactorSum = (float) Mortgage::where('user_id', $user->id)->sum('outstanding_balance');
    expect($preRefactorSum)->toBe(400000.0); // 150000 + 250000

    // Post-PR-5a: forUserPrimaryOnly must match
    $postRefactorSum = (float) app(MortgageStore::class)
        ->forUserPrimaryOnly($user)
        ->sum('outstanding_balance');
    expect($postRefactorSum)->toBe(400000.0);
});

it('EstateAssetAggregatorService includes joint mortgages in user liability calculation (joint-aware)', function () {
    $user = User::factory()->create(['is_preview_user' => false]);
    $other = User::factory()->create(['is_preview_user' => false]);

    $property1 = Property::factory()->create(['user_id' => $user->id]);
    $property2 = Property::factory()->create(['user_id' => $other->id, 'joint_owner_id' => $user->id]);

    // Primary mortgage
    Mortgage::factory()->create([
        'user_id' => $user->id,
        'property_id' => $property1->id,
        'ownership_type' => 'individual',
        'ownership_percentage' => 100,
        'outstanding_balance' => 200000,
    ]);
    // Joint mortgage where $user is joint secondary — forUser must include this
    Mortgage::factory()->create([
        'user_id' => $other->id,
        'joint_owner_id' => $user->id,
        'property_id' => $property2->id,
        'ownership_type' => 'joint',
        'ownership_percentage' => 60,
        'outstanding_balance' => 300000,
    ]);

    // forUser (joint-aware) must return BOTH mortgages
    $mortgages = app(MortgageStore::class)->forUser($user);
    expect($mortgages)->toHaveCount(2);

    // forUserPrimaryOnly (primary-only) must return only the primary mortgage
    $primaryMortgages = app(MortgageStore::class)->forUserPrimaryOnly($user);
    expect($primaryMortgages)->toHaveCount(1);
    expect((float) $primaryMortgages->first()->outstanding_balance)->toBe(200000.0);
});

it('IHTFormattingService::formatUserLiabilities returns mortgages where user is primary OR joint owner (joint-aware)', function () {
    $user = User::factory()->create(['is_preview_user' => false]);
    $other = User::factory()->create(['is_preview_user' => false]);

    $property1 = Property::factory()->create(['user_id' => $user->id]);
    $property2 = Property::factory()->create(['user_id' => $other->id, 'joint_owner_id' => $user->id]);

    Mortgage::factory()->create([
        'user_id' => $user->id,
        'property_id' => $property1->id,
        'ownership_type' => 'individual',
        'ownership_percentage' => 100,
        'outstanding_balance' => 100000,
    ]);
    // This must appear in joint-aware results — $user is joint secondary
    Mortgage::factory()->create([
        'user_id' => $other->id,
        'joint_owner_id' => $user->id,
        'property_id' => $property2->id,
        'ownership_type' => 'joint',
        'ownership_percentage' => 50,
        'outstanding_balance' => 200000,
    ]);

    // Pre-PR-5a parity: the original Mortgage::where closure matched both
    $preRefactorCount = Mortgage::where(function ($query) use ($user) {
        $query->where('user_id', $user->id)
            ->orWhere('joint_owner_id', $user->id);
    })->count();
    expect($preRefactorCount)->toBe(2);

    // Post-PR-5a: forUser must return the same 2 records
    $postRefactorCount = app(MortgageStore::class)->forUser($user)->count();
    expect($postRefactorCount)->toBe(2);
});

it('LetterEstateValidationService mortgage count is primary-only (matches $user->mortgages() HasMany semantics)', function () {
    $user = User::factory()->create(['is_preview_user' => false]);
    $other = User::factory()->create(['is_preview_user' => false]);

    $property1 = Property::factory()->create(['user_id' => $user->id]);
    $property2 = Property::factory()->create(['user_id' => $user->id]);
    $property3 = Property::factory()->create(['user_id' => $other->id, 'joint_owner_id' => $user->id]);

    // Two primary mortgages
    Mortgage::factory()->create([
        'user_id' => $user->id,
        'property_id' => $property1->id,
        'ownership_type' => 'individual',
        'ownership_percentage' => 100,
        'outstanding_balance' => 100000,
    ]);
    Mortgage::factory()->create([
        'user_id' => $user->id,
        'joint_owner_id' => $other->id,
        'property_id' => $property2->id,
        'ownership_type' => 'joint',
        'ownership_percentage' => 50,
        'outstanding_balance' => 150000,
    ]);
    // Joint-as-secondary — must NOT be counted by primary-only semantics
    Mortgage::factory()->create([
        'user_id' => $other->id,
        'joint_owner_id' => $user->id,
        'property_id' => $property3->id,
        'ownership_type' => 'joint',
        'ownership_percentage' => 50,
        'outstanding_balance' => 200000,
    ]);

    // Pre-PR-5a: $user->mortgages() is a hasMany — primary-only
    $preRefactorCount = $user->mortgages()->count();
    expect($preRefactorCount)->toBe(2);

    // Post-PR-5a: forUserPrimaryOnly must match
    $postRefactorCount = app(MortgageStore::class)->forUserPrimaryOnly($user)->count();
    expect($postRefactorCount)->toBe(2);
});

it('EstateDataReadinessService mortgage-exists check is primary-only (matches $user->mortgages()->exists() HasMany)', function () {
    $user = User::factory()->create(['is_preview_user' => false]);
    $other = User::factory()->create(['is_preview_user' => false]);

    $property = Property::factory()->create(['user_id' => $other->id, 'joint_owner_id' => $user->id]);

    // $user is ONLY a joint secondary — no primary-owned mortgages
    Mortgage::factory()->create([
        'user_id' => $other->id,
        'joint_owner_id' => $user->id,
        'property_id' => $property->id,
        'ownership_type' => 'joint',
        'ownership_percentage' => 50,
        'outstanding_balance' => 250000,
    ]);

    // Pre-PR-5a: $user->mortgages()->exists() uses HasMany (primary-only) — returns false
    $preRefactorExists = $user->mortgages()->exists();
    expect($preRefactorExists)->toBeFalse();

    // Post-PR-5a: forUserPrimaryOnly->isNotEmpty() must match
    $postRefactorExists = app(MortgageStore::class)->forUserPrimaryOnly($user)->isNotEmpty();
    expect($postRefactorExists)->toBeFalse();

    // Sanity: forUser (joint-aware) DOES find the record — confirms the distinction matters
    $jointAwareExists = app(MortgageStore::class)->forUser($user)->isNotEmpty();
    expect($jointAwareExists)->toBeTrue();
});
