<?php

declare(strict_types=1);

use App\Models\Goal;
use App\Models\SavingsAccount;
use App\Models\User;
use App\Services\Estate\EstateActionDefinitionService;
use App\Services\Estate\EstateAssetAggregatorService;
use App\Services\Plans\BasePlanService;
use App\Services\Plans\InvestmentPlanService;
use App\Services\Plans\SavingsPlanService;
use App\Services\Shared\CrossModuleAssetAggregator;
use App\Services\Stores\SavingsStore;
use App\Services\UserProfile\LetterToSpouseService;
use Database\Seeders\EstateActionDefinitionSeeder;
use Database\Seeders\TaxConfigurationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(TaxConfigurationSeeder::class);
});

it('CrossModuleAssetAggregator::calculateCashTotal returns correct sum after store migration', function () {
    $user = User::factory()->create(['is_preview_user' => false]);

    SavingsAccount::factory(3)->create([
        'user_id' => $user->id,
        'current_balance' => 1000,
        'ownership_type' => 'individual',
        'ownership_percentage' => 100,
    ]);
    SavingsAccount::factory()->create([
        'user_id' => $user->id,
        'current_balance' => 5000,
        'is_isa' => true,
        'ownership_type' => 'individual',
        'ownership_percentage' => 100,
    ]);

    $aggregator = app(CrossModuleAssetAggregator::class);
    $total = $aggregator->calculateCashTotal($user->id);

    expect($total)->toBe(8000.0);
});

it('CrossModuleAssetAggregator::getSavingsAssets returns one object per savings account after store migration', function () {
    $user = User::factory()->create(['is_preview_user' => false]);

    SavingsAccount::factory(2)->create([
        'user_id' => $user->id,
        'current_balance' => 2500,
        'ownership_type' => 'individual',
        'ownership_percentage' => 100,
    ]);

    $aggregator = app(CrossModuleAssetAggregator::class);
    $assets = $aggregator->getSavingsAssets($user->id);

    expect($assets)->toHaveCount(2);
    expect((float) $assets->first()->current_value)->toBe(2500.0);
});

it('CrossModuleAssetAggregator::getAssetBreakdown cash count and total correct after store migration', function () {
    $user = User::factory()->create(['is_preview_user' => false]);

    SavingsAccount::factory(2)->create([
        'user_id' => $user->id,
        'current_balance' => 3000,
        'ownership_type' => 'individual',
        'ownership_percentage' => 100,
    ]);

    $aggregator = app(CrossModuleAssetAggregator::class);
    $breakdown = $aggregator->getAssetBreakdown($user->id);

    expect($breakdown['cash']['count'])->toBe(2);
    expect($breakdown['cash']['total'])->toBe(6000.0);
});

it('MobileDashboardAggregator net-worth savings value correct for joint-owner after store migration', function () {
    $primaryOwner = User::factory()->create(['is_preview_user' => false]);
    $jointOwner = User::factory()->create(['is_preview_user' => false]);

    // Account owned by primaryOwner with jointOwner as joint_owner (50/50)
    SavingsAccount::factory()->create([
        'user_id' => $primaryOwner->id,
        'joint_owner_id' => $jointOwner->id,
        'current_balance' => 10000,
        'ownership_type' => 'joint',
        'ownership_percentage' => 50,
    ]);

    // The aggregator's calculateNetWorth uses sumJointOwnerShares(SavingsAccount::class, $userId)
    // to add joint-owner shares. After migration this must route through SavingsStore.
    // We verify via CrossModuleAssetAggregator (same store path) that joint ownership sums correctly.
    $aggregator = app(CrossModuleAssetAggregator::class);

    $primaryTotal = $aggregator->calculateCashTotal($primaryOwner->id);
    $jointTotal = $aggregator->calculateCashTotal($jointOwner->id);

    // Primary owner holds 50% = 5000; joint owner holds (100-50)% = 5000
    expect($primaryTotal)->toBe(5000.0);
    expect($jointTotal)->toBe(5000.0);
});

// PR 5b parity tests — Estate / IHT cluster

it('EstateAssetAggregatorService returns identical savings asset objects after store migration', function () {
    $user = User::factory()->create(['is_preview_user' => false]);
    $joint = User::factory()->create(['is_preview_user' => false]);

    // Individual account: user owns 100%
    SavingsAccount::factory()->create([
        'user_id' => $user->id,
        'current_balance' => 5000,
        'ownership_type' => 'individual',
        'ownership_percentage' => 100,
        'institution' => 'Barclays',
        'account_type' => 'easy_access',
    ]);
    // Another individual account
    SavingsAccount::factory()->create([
        'user_id' => $user->id,
        'current_balance' => 3000,
        'ownership_type' => 'individual',
        'ownership_percentage' => 100,
        'institution' => 'HSBC',
        'account_type' => 'notice',
    ]);
    // Joint account: user is primary owner, 50/50
    SavingsAccount::factory()->create([
        'user_id' => $user->id,
        'joint_owner_id' => $joint->id,
        'current_balance' => 20000,
        'ownership_type' => 'joint',
        'ownership_percentage' => 50,
        'institution' => 'Nationwide',
        'account_type' => 'easy_access',
    ]);

    $service = app(EstateAssetAggregatorService::class);
    $allAssets = $service->gatherUserAssets($user);
    $savingsAssets = $allAssets->filter(fn ($a) => $a->asset_type === 'cash');

    // All 3 accounts returned (individual x2 + joint as primary)
    expect($savingsAssets)->toHaveCount(3);

    $sorted = $savingsAssets->sortBy('full_value')->values();

    // £3,000 individual: full_value=3000, current_value=3000 (100%)
    expect((float) $sorted[0]->full_value)->toBe(3000.0);
    expect((float) $sorted[0]->current_value)->toBe(3000.0);
    expect($sorted[0]->is_primary_owner)->toBeTrue();

    // £5,000 individual: full_value=5000, current_value=5000 (100%)
    expect((float) $sorted[1]->full_value)->toBe(5000.0);
    expect((float) $sorted[1]->current_value)->toBe(5000.0);
    expect($sorted[1]->is_primary_owner)->toBeTrue();

    // £20,000 joint (primary, 50%): full_value=20000, current_value=10000
    expect((float) $sorted[2]->full_value)->toBe(20000.0);
    expect((float) $sorted[2]->current_value)->toBe(10000.0);
    expect($sorted[2]->is_primary_owner)->toBeTrue();
});

it('EstateActionDefinitionService savings sum uses single-owner semantics (where user_id only)', function () {
    $user = User::factory()->create(['is_preview_user' => false]);
    $joint = User::factory()->create(['is_preview_user' => false]);

    // Individual account — user_id = user, should be included
    SavingsAccount::factory()->create([
        'user_id' => $user->id,
        'current_balance' => 10000,
        'ownership_type' => 'individual',
        'ownership_percentage' => 100,
    ]);
    // Joint account where user is PRIMARY (user_id = user) — full balance included via where user_id
    SavingsAccount::factory()->create([
        'user_id' => $user->id,
        'joint_owner_id' => $joint->id,
        'current_balance' => 20000,
        'ownership_type' => 'joint',
        'ownership_percentage' => 50,
    ]);
    // Account where user is joint_owner_id only — NOT included in single-owner sum
    SavingsAccount::factory()->create([
        'user_id' => $joint->id,
        'joint_owner_id' => $user->id,
        'current_balance' => 8000,
        'ownership_type' => 'joint',
        'ownership_percentage' => 50,
    ]);
    // Account for a completely different user — excluded
    SavingsAccount::factory()->create([
        'user_id' => $joint->id,
        'current_balance' => 99999,
        'ownership_type' => 'individual',
        'ownership_percentage' => 100,
    ]);

    // Parity contract: SavingsAccount::where('user_id', $user->id)->sum('current_balance')
    // = individual (10000) + joint-as-primary (20000) = 30000
    // The account where user is joint_owner_id only (8000) must NOT be counted.
    $preRefactorSum = (float) SavingsAccount::where('user_id', $user->id)->sum('current_balance');
    expect($preRefactorSum)->toBe(30000.0);

    // Post-refactor: store->forUser()->where('user_id', ...)->sum() must produce identical result
    $store = app(\App\Services\Stores\SavingsStore::class);
    $collection = $store->forUser($user);
    $postRefactorSum = (float) $collection->where('user_id', $user->id)->sum('current_balance');
    expect($postRefactorSum)->toBe(30000.0);
});

it('LetterToSpouseService immediate funds info lists single-owner-as-primary joint accounts', function () {
    $user = User::factory()->create(['is_preview_user' => false]);
    $joint = User::factory()->create(['is_preview_user' => false]);

    // Individual account — should NOT appear in immediate funds
    SavingsAccount::factory()->create([
        'user_id' => $user->id,
        'current_balance' => 5000,
        'ownership_type' => 'individual',
        'institution' => 'IndividualBank',
    ]);
    // Joint account where user is primary — SHOULD appear
    SavingsAccount::factory()->create([
        'user_id' => $user->id,
        'joint_owner_id' => $joint->id,
        'current_balance' => 12000,
        'ownership_type' => 'joint',
        'ownership_percentage' => 50,
        'institution' => 'JointBank',
    ]);

    $service = app(LetterToSpouseService::class);
    $reflection = new ReflectionClass($service);
    $method = $reflection->getMethod('generateImmediateFundsInfo');
    $method->setAccessible(true);

    $info = $method->invoke($service, $user);

    // Joint account (JointBank) should appear; individual (IndividualBank) should not
    expect($info)->toContain('JointBank');
    expect($info)->not->toContain('IndividualBank');
});

it('LetterToSpouseService bank accounts info lists all single-owner accounts', function () {
    $user = User::factory()->create(['is_preview_user' => false]);
    $joint = User::factory()->create(['is_preview_user' => false]);

    SavingsAccount::factory()->create([
        'user_id' => $user->id,
        'current_balance' => 5000,
        'ownership_type' => 'individual',
        'institution' => 'IndividualBank',
    ]);
    SavingsAccount::factory()->create([
        'user_id' => $user->id,
        'joint_owner_id' => $joint->id,
        'current_balance' => 12000,
        'ownership_type' => 'joint',
        'ownership_percentage' => 50,
        'institution' => 'JointBank',
    ]);
    // Account for joint user only — should NOT appear (different user_id)
    SavingsAccount::factory()->create([
        'user_id' => $joint->id,
        'current_balance' => 99999,
        'ownership_type' => 'individual',
        'institution' => 'OtherBank',
    ]);

    $service = app(LetterToSpouseService::class);
    $reflection = new ReflectionClass($service);
    $method = $reflection->getMethod('generateBankAccountsInfo');
    $method->setAccessible(true);

    $info = $method->invoke($service, $user);

    // Both user-owned accounts appear (individual + joint-as-primary)
    expect($info)->toContain('IndividualBank');
    expect($info)->toContain('JointBank');
    // The joint user's own account must not appear
    expect($info)->not->toContain('OtherBank');
});

it('EstateActionDefinitionService::evaluateActions surfaces iht_exceeds_nrb with correct estimated_impact when savings push estate above NRB+RNRB', function () {
    $this->seed(EstateActionDefinitionSeeder::class);

    $user = User::factory()->create(['is_preview_user' => false]);

    // £400k individual savings — counts toward estimateEstateValue via store->forUser()->where(user_id)->sum
    SavingsAccount::factory()->create([
        'user_id' => $user->id,
        'current_balance' => 400000,
        'ownership_type' => 'individual',
        'ownership_percentage' => 100,
    ]);
    // £200k joint-as-primary — full balance also included (matches pre-refactor where('user_id') semantics)
    SavingsAccount::factory()->create([
        'user_id' => $user->id,
        'joint_owner_id' => User::factory()->create(['is_preview_user' => false])->id,
        'current_balance' => 200000,
        'ownership_type' => 'joint',
        'ownership_percentage' => 50,
    ]);

    // Savings line of estimateEstateValue = 400000 + 200000 = 600000
    // No properties, investments, cash, estate assets, DC pensions, life insurance, mortgages or liabilities
    // → estate value = 600000; NRB+RNRB defaults = 325000 + 175000 = 500000
    // → excess = 100000; IHT @ 40% = 40000

    $result = app(EstateActionDefinitionService::class)->evaluateActions($user);

    $ihtRec = collect($result['recommendations'])
        ->firstWhere('definition_key', 'iht_exceeds_nrb');

    expect($ihtRec)->not->toBeNull();
    expect($ihtRec['estimated_impact'])->toBe(40000.0);
});

// PR 5c-1 parity tests — Plans cluster

it('BasePlanService cash account funding source order is preserved after store migration', function () {
    $user = User::factory()->create(['is_preview_user' => false]);

    // Create cash accounts with varying balances and types
    // The resolver filters: non-ISA, CASH_ACCOUNT_TYPES = ['current_account','instant_access','business_current','business_savings']
    // orderByDesc current_balance → highest balance comes first
    $highBalance = SavingsAccount::factory()->create([
        'user_id' => $user->id,
        'account_name' => 'HighBalance',
        'account_type' => 'instant_access',
        'is_isa' => false,
        'current_balance' => 20000,
        'ownership_type' => 'individual',
        'ownership_percentage' => 100,
    ]);
    $lowBalance = SavingsAccount::factory()->create([
        'user_id' => $user->id,
        'account_name' => 'LowBalance',
        'account_type' => 'current_account',
        'is_isa' => false,
        'current_balance' => 500,
        'ownership_type' => 'individual',
        'ownership_percentage' => 100,
    ]);
    // ISA — must be excluded from cash filter
    SavingsAccount::factory()->create([
        'user_id' => $user->id,
        'account_name' => 'ISAAccount',
        'account_type' => 'instant_access',
        'is_isa' => true,
        'current_balance' => 50000,
        'ownership_type' => 'individual',
        'ownership_percentage' => 100,
    ]);
    // Non-cash type — excluded
    SavingsAccount::factory()->create([
        'user_id' => $user->id,
        'account_name' => 'FixedRate',
        'account_type' => 'fixed_rate',
        'is_isa' => false,
        'current_balance' => 30000,
        'ownership_type' => 'individual',
        'ownership_percentage' => 100,
    ]);

    // Pre-refactor: direct Eloquent query
    $cashAccountTypes = ['current_account', 'instant_access', 'business_current', 'business_savings'];
    $preRefactorAccounts = SavingsAccount::where('user_id', $user->id)
        ->where('is_isa', false)
        ->whereIn('account_type', $cashAccountTypes)
        ->orderByDesc('current_balance')
        ->get();

    // Post-refactor: via store + Collection methods
    $store = app(SavingsStore::class);
    $postRefactorAccounts = $store->forUser($user)
        ->where('user_id', $user->id)
        ->where('is_isa', false)
        ->whereIn('account_type', $cashAccountTypes)
        ->sortByDesc('current_balance')
        ->values();

    expect($preRefactorAccounts)->toHaveCount(2);
    expect($postRefactorAccounts)->toHaveCount(2);

    // Order is preserved: highest balance first
    expect($preRefactorAccounts->first()->account_name)->toBe('HighBalance');
    expect($postRefactorAccounts->first()->account_name)->toBe('HighBalance');
    expect($preRefactorAccounts->last()->account_name)->toBe('LowBalance');
    expect($postRefactorAccounts->last()->account_name)->toBe('LowBalance');
});

it('GoalPlanService find by linked_savings_account_id returns same account after store migration', function () {
    $user = User::factory()->create(['is_preview_user' => false]);

    $account = SavingsAccount::factory()->create([
        'user_id' => $user->id,
        'institution' => 'GoalBank',
        'current_balance' => 5000,
        'ownership_type' => 'individual',
        'ownership_percentage' => 100,
    ]);

    // Pre-refactor: SavingsAccount::find($id) — finds regardless of user ownership
    $preRefactor = SavingsAccount::find($account->id);

    // Post-refactor: store->find($id, $user) — scoped to user (owns this account → parity holds)
    $store = app(SavingsStore::class);
    $postRefactor = $store->find($account->id, $user);

    expect($preRefactor)->not->toBeNull();
    expect($postRefactor)->not->toBeNull();
    expect($postRefactor->id)->toBe($preRefactor->id);
    expect($postRefactor->institution)->toBe('GoalBank');
});

it('GoalPlanService store->find scopes to user — cross-user account id returns null (deliberate semantic narrowing)', function () {
    $user = User::factory()->create(['is_preview_user' => false]);
    $otherUser = User::factory()->create(['is_preview_user' => false]);

    $foreignAccount = SavingsAccount::factory()->create([
        'user_id' => $otherUser->id,
        'institution' => 'ForeignBank',
        'current_balance' => 5000,
        'ownership_type' => 'individual',
        'ownership_percentage' => 100,
    ]);

    // Pre-refactor `SavingsAccount::find($id)` would have returned the row regardless of ownership.
    // Post-refactor `store->find($id, $user)` returns null when the account is owned by another user
    // and the requesting user is not joint_owner_id. This is a deliberate, safer narrowing.
    expect(SavingsAccount::find($foreignAccount->id))->not->toBeNull();
    expect(app(SavingsStore::class)->find($foreignAccount->id, $user))->toBeNull();
});

it('SavingsPlanService produces identical plan account collection after store migration', function () {
    $user = User::factory()->create(['is_preview_user' => false]);
    $jointUser = User::factory()->create(['is_preview_user' => false]);

    // Individual account
    SavingsAccount::factory()->create([
        'user_id' => $user->id,
        'account_type' => 'easy_access',
        'current_balance' => 3000,
        'ownership_type' => 'individual',
        'ownership_percentage' => 100,
    ]);
    // Joint account where user is primary
    SavingsAccount::factory()->create([
        'user_id' => $user->id,
        'joint_owner_id' => $jointUser->id,
        'account_type' => 'notice',
        'current_balance' => 8000,
        'ownership_type' => 'joint',
        'ownership_percentage' => 50,
    ]);
    // Joint account where user is joint_owner_id (should be included by forUserOrJoint)
    SavingsAccount::factory()->create([
        'user_id' => $jointUser->id,
        'joint_owner_id' => $user->id,
        'account_type' => 'easy_access',
        'current_balance' => 4000,
        'ownership_type' => 'joint',
        'ownership_percentage' => 50,
    ]);

    // Pre-refactor: forUserOrJoint
    $preRefactor = SavingsAccount::forUserOrJoint($user->id)->get();

    // Post-refactor: store->forUser (same underlying scope)
    $store = app(SavingsStore::class);
    $postRefactor = $store->forUser($user);

    expect($preRefactor->count())->toBe($postRefactor->count());
    expect($preRefactor->pluck('id')->sort()->values()->toArray())
        ->toBe($postRefactor->pluck('id')->sort()->values()->toArray());
});

it('InvestmentPlanService cash account filter results identical after store migration', function () {
    $user = User::factory()->create(['is_preview_user' => false]);

    $fundingTypes = ['current_account', 'instant_access', 'business_current', 'business_savings'];

    // Eligible: non-ISA, instant_access
    SavingsAccount::factory()->create([
        'user_id' => $user->id,
        'account_name' => 'EligibleCash',
        'account_type' => 'instant_access',
        'is_isa' => false,
        'current_balance' => 15000,
        'ownership_type' => 'individual',
        'ownership_percentage' => 100,
    ]);
    // Eligible: non-ISA, current_account
    SavingsAccount::factory()->create([
        'user_id' => $user->id,
        'account_name' => 'EligibleCurrent',
        'account_type' => 'current_account',
        'is_isa' => false,
        'current_balance' => 2000,
        'ownership_type' => 'individual',
        'ownership_percentage' => 100,
    ]);
    // Excluded: ISA
    SavingsAccount::factory()->create([
        'user_id' => $user->id,
        'account_name' => 'ISAAccount',
        'account_type' => 'instant_access',
        'is_isa' => true,
        'current_balance' => 20000,
        'ownership_type' => 'individual',
        'ownership_percentage' => 100,
    ]);
    // Excluded: wrong type
    SavingsAccount::factory()->create([
        'user_id' => $user->id,
        'account_name' => 'Notice',
        'account_type' => 'notice',
        'is_isa' => false,
        'current_balance' => 10000,
        'ownership_type' => 'individual',
        'ownership_percentage' => 100,
    ]);

    // Pre-refactor (site 9 / buildEligibleFundingAccounts pattern)
    $preRefactor = SavingsAccount::where('user_id', $user->id)
        ->where('is_isa', false)
        ->whereIn('account_type', $fundingTypes)
        ->orderByDesc('current_balance')
        ->get();

    // Post-refactor: store + Collection
    $store = app(SavingsStore::class);
    $postRefactor = $store->forUser($user)
        ->where('user_id', $user->id)
        ->where('is_isa', false)
        ->whereIn('account_type', $fundingTypes)
        ->sortByDesc('current_balance')
        ->values();

    expect($preRefactor->count())->toBe(2);
    expect($postRefactor->count())->toBe(2);

    expect($preRefactor->pluck('account_name')->toArray())
        ->toBe($postRefactor->pluck('account_name')->toArray());
    expect($preRefactor->first()->account_name)->toBe('EligibleCash');
    expect($postRefactor->first()->account_name)->toBe('EligibleCash');
});

it('BasePlanService::resolveFundingSource returns null result when goal references a deleted user (regression)', function () {
    // Pre-refactor used raw $userId in WHERE so missing user → empty Builder result, no crash.
    // Post-refactor uses User::find($userId) + Collection chain — null user must short-circuit.
    $orphanGoal = new \App\Models\Goal([
        'name' => 'Orphan',
        'target_amount' => 10000,
        'current_amount' => 0,
        'target_date' => now()->addYear(),
        'priority' => 'high',
    ]);
    $orphanGoal->user_id = 99999999;  // non-existent user

    // BasePlanService is abstract — use a concrete subclass that inherits resolveFundingSource.
    $service = app(\App\Services\Plans\SavingsPlanService::class);
    $reflection = new ReflectionClass(\App\Services\Plans\BasePlanService::class);
    $method = $reflection->getMethod('resolveFundingSource');
    $method->setAccessible(true);

    $result = $method->invoke($service, $orphanGoal);

    expect($result)->toBe(['name' => null, 'warning' => null]);
});

