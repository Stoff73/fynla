<?php

declare(strict_types=1);

use App\Models\Investment\InvestmentAccount;
use App\Models\User;
use App\Services\Stores\InvestmentAccountStore;
use Database\Seeders\TaxConfigurationSeeder;
use Database\Seeders\TierConfigurationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(TaxConfigurationSeeder::class);
    $this->seed(TierConfigurationSeeder::class);
});

/*
 * SP1 Pass 6 PR 5a read-consumer parity contract for InvestmentAccountStore.
 *
 * InvestmentAccountStore::forUser is joint-aware (user_id = ? OR joint_owner_id = ?).
 * The five Investment-internal consumers migrated in PR 5a previously used various
 * patterns — some primary-only (InvestmentAccount::where('user_id', $id)), some
 * joint-aware (InvestmentAccount::forUserOrJoint($id)). These tests lock both
 * contracts so no future cluster (5b/5c/5d/5e) silently alters semantics.
 */

it('InvestmentAccountStore::forUser returns primary AND joint accounts (canonical joint-aware contract)', function () {
    $user = User::factory()->create(['is_preview_user' => false]);
    $other = User::factory()->create(['is_preview_user' => false]);

    // Individual account — $user is primary
    InvestmentAccount::factory()->create([
        'user_id' => $user->id,
        'ownership_type' => 'individual',
        'ownership_percentage' => 100,
    ]);
    // Joint account — $user is primary, $other is joint
    InvestmentAccount::factory()->create([
        'user_id' => $user->id,
        'joint_owner_id' => $other->id,
        'ownership_type' => 'joint',
        'ownership_percentage' => 50,
    ]);
    // Joint account — $other is primary, $user is joint
    InvestmentAccount::factory()->create([
        'user_id' => $other->id,
        'joint_owner_id' => $user->id,
        'ownership_type' => 'joint',
        'ownership_percentage' => 50,
    ]);

    $collection = app(InvestmentAccountStore::class)->forUser($user);

    expect($collection)->toHaveCount(3); // 1 individual + 1 joint-as-primary + 1 joint-as-secondary
});

it('InvestmentAccountStore::forUserPrimaryOnly returns primary-only (PR 5a primary-only contract)', function () {
    $user = User::factory()->create(['is_preview_user' => false]);
    $other = User::factory()->create(['is_preview_user' => false]);

    InvestmentAccount::factory()->create([
        'user_id' => $user->id,
        'ownership_type' => 'individual',
        'ownership_percentage' => 100,
        'current_value' => 10000,
    ]);
    InvestmentAccount::factory()->create([
        'user_id' => $user->id,
        'joint_owner_id' => $other->id,
        'ownership_type' => 'joint',
        'ownership_percentage' => 50,
        'current_value' => 20000,
    ]);
    // Joint account where $user is only joint_owner — must NOT appear in primary-only result
    InvestmentAccount::factory()->create([
        'user_id' => $other->id,
        'joint_owner_id' => $user->id,
        'ownership_type' => 'joint',
        'ownership_percentage' => 50,
        'current_value' => 30000,
    ]);

    $primaryOnly = app(InvestmentAccountStore::class)->forUserPrimaryOnly($user);

    expect($primaryOnly)->toHaveCount(2); // individual + joint-as-primary; NOT joint-as-secondary
    expect((float) $primaryOnly->sum('current_value'))->toBe(30000.0);
});

it('User->investmentAccounts HasMany is equivalent to forUserPrimaryOnly (same ids)', function () {
    $user = User::factory()->create(['is_preview_user' => false]);
    $other = User::factory()->create(['is_preview_user' => false]);

    InvestmentAccount::factory()->create([
        'user_id' => $user->id,
        'ownership_type' => 'individual',
        'ownership_percentage' => 100,
    ]);
    InvestmentAccount::factory()->create([
        'user_id' => $user->id,
        'joint_owner_id' => $other->id,
        'ownership_type' => 'joint',
        'ownership_percentage' => 50,
    ]);
    // Joint-as-secondary — excluded from HasMany and from forUserPrimaryOnly
    InvestmentAccount::factory()->create([
        'user_id' => $other->id,
        'joint_owner_id' => $user->id,
        'ownership_type' => 'joint',
        'ownership_percentage' => 50,
    ]);

    $hasMany = $user->investmentAccounts()->pluck('id')->sort()->values();
    $storeIds = app(InvestmentAccountStore::class)->forUserPrimaryOnly($user)->pluck('id')->sort()->values();

    expect($storeIds->toArray())->toBe($hasMany->toArray());
});

it('joint account: primary user sees it in both forUser and forUserPrimaryOnly', function () {
    $userA = User::factory()->create(['is_preview_user' => false]);
    $userB = User::factory()->create(['is_preview_user' => false]);

    $account = InvestmentAccount::factory()->create([
        'user_id' => $userA->id,
        'joint_owner_id' => $userB->id,
        'ownership_type' => 'joint',
        'ownership_percentage' => 60,
    ]);

    $store = app(InvestmentAccountStore::class);

    // A is primary — appears in both
    expect($store->forUser($userA)->pluck('id'))->toContain($account->id);
    expect($store->forUserPrimaryOnly($userA)->pluck('id'))->toContain($account->id);
});

it('joint account: secondary user sees it in forUser but NOT forUserPrimaryOnly', function () {
    $userA = User::factory()->create(['is_preview_user' => false]);
    $userB = User::factory()->create(['is_preview_user' => false]);

    $account = InvestmentAccount::factory()->create([
        'user_id' => $userA->id,
        'joint_owner_id' => $userB->id,
        'ownership_type' => 'joint',
        'ownership_percentage' => 60,
    ]);

    $store = app(InvestmentAccountStore::class);

    // B is joint secondary — forUser includes it, forUserPrimaryOnly does not
    expect($store->forUser($userB)->pluck('id'))->toContain($account->id);
    expect($store->forUserPrimaryOnly($userB)->pluck('id'))->not->toContain($account->id);
});

it('cross-user isolation: user C sees none of user A or B accounts', function () {
    $userA = User::factory()->create(['is_preview_user' => false]);
    $userB = User::factory()->create(['is_preview_user' => false]);
    $userC = User::factory()->create(['is_preview_user' => false]);

    InvestmentAccount::factory()->create([
        'user_id' => $userA->id,
        'ownership_type' => 'individual',
        'ownership_percentage' => 100,
    ]);
    InvestmentAccount::factory()->create([
        'user_id' => $userA->id,
        'joint_owner_id' => $userB->id,
        'ownership_type' => 'joint',
        'ownership_percentage' => 50,
    ]);

    $store = app(InvestmentAccountStore::class);

    expect($store->forUser($userC))->toHaveCount(0);
    expect($store->forUserPrimaryOnly($userC))->toHaveCount(0);
});

it('forUserByType filters by account_type within joint-aware scope', function () {
    $user = User::factory()->create(['is_preview_user' => false]);
    $other = User::factory()->create(['is_preview_user' => false]);

    // ISA — primary-owned
    InvestmentAccount::factory()->create([
        'user_id' => $user->id,
        'account_type' => 'isa',
        'ownership_type' => 'individual',
        'ownership_percentage' => 100,
    ]);
    // GIA — primary-owned
    InvestmentAccount::factory()->create([
        'user_id' => $user->id,
        'account_type' => 'gia',
        'ownership_type' => 'individual',
        'ownership_percentage' => 100,
    ]);
    // ISA — $user is joint secondary
    InvestmentAccount::factory()->create([
        'user_id' => $other->id,
        'joint_owner_id' => $user->id,
        'account_type' => 'isa',
        'ownership_type' => 'joint',
        'ownership_percentage' => 50,
    ]);

    $isaAccounts = app(InvestmentAccountStore::class)->forUserByType($user, 'isa');

    // forUserByType is joint-aware — must include both the primary ISA and the joint-secondary ISA
    expect($isaAccounts)->toHaveCount(2);
    expect($isaAccounts->every(fn ($a) => $a->account_type === 'isa'))->toBeTrue();
});

it('Analytics primary-only consumers match pre-PR-5a InvestmentAccount::where(user_id) semantics', function () {
    $user = User::factory()->create(['is_preview_user' => false]);
    $other = User::factory()->create(['is_preview_user' => false]);

    InvestmentAccount::factory()->create([
        'user_id' => $user->id,
        'ownership_type' => 'individual',
        'ownership_percentage' => 100,
        'current_value' => 50000,
    ]);
    InvestmentAccount::factory()->create([
        'user_id' => $user->id,
        'joint_owner_id' => $other->id,
        'ownership_type' => 'joint',
        'ownership_percentage' => 50,
        'current_value' => 80000,
    ]);
    // Joint-as-secondary — must NOT appear in primary-only result
    InvestmentAccount::factory()->create([
        'user_id' => $other->id,
        'joint_owner_id' => $user->id,
        'ownership_type' => 'joint',
        'ownership_percentage' => 50,
        'current_value' => 120000,
    ]);

    // Pre-PR-5a parity: EfficientFrontierCalculator / HoldingsDataExtractor / AccountTypeRecommender /
    // AssetLocationOptimizer::analyzeCurrentAllocation all used where('user_id', $userId)
    $preRefactorSum = (float) InvestmentAccount::where('user_id', $user->id)->sum('current_value');
    expect($preRefactorSum)->toBe(130000.0); // 50000 + 80000

    // Post-PR-5a: forUserPrimaryOnly must match
    $postRefactorSum = (float) app(InvestmentAccountStore::class)
        ->forUserPrimaryOnly($user)
        ->sum('current_value');
    expect($postRefactorSum)->toBe(130000.0);
});

it('TaxDragCalculator joint-aware consumer matches pre-PR-5a forUserOrJoint semantics', function () {
    $user = User::factory()->create(['is_preview_user' => false]);
    $other = User::factory()->create(['is_preview_user' => false]);

    InvestmentAccount::factory()->create([
        'user_id' => $user->id,
        'ownership_type' => 'individual',
        'ownership_percentage' => 100,
        'current_value' => 40000,
    ]);
    // Joint account where $user is secondary — forUser must include this
    InvestmentAccount::factory()->create([
        'user_id' => $other->id,
        'joint_owner_id' => $user->id,
        'ownership_type' => 'joint',
        'ownership_percentage' => 50,
        'current_value' => 60000,
    ]);

    // Pre-PR-5a parity: TaxDragCalculator used forUserOrJoint($userId)
    $preRefactorCount = InvestmentAccount::where(function ($query) use ($user) {
        $query->where('user_id', $user->id)
            ->orWhere('joint_owner_id', $user->id);
    })->count();
    expect($preRefactorCount)->toBe(2);

    // Post-PR-5a: forUser must return the same 2 records
    $postRefactorCount = app(InvestmentAccountStore::class)->forUser($user)->count();
    expect($postRefactorCount)->toBe(2);
});
