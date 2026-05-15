<?php

declare(strict_types=1);

use App\Models\Goal;
use App\Models\LifeEvent;
use App\Models\SavingsAccount;
use App\Models\User;
use App\Services\Coordination\CashFlowCoordinator;
use App\Services\Coordination\HouseholdPlanningService;
use App\Services\Estate\EstateActionDefinitionService;
use App\Services\Estate\EstateAssetAggregatorService;
use App\Services\Goals\LifeEventAllocationService;
use App\Services\Investment\Recommendation\UserContextBuilder;
use App\Services\Investment\Tax\ISAAllowanceOptimizer;
use App\Services\Investment\Tax\TaxOptimizationAnalyzer;
use App\Services\Plans\BasePlanService;
use App\Services\Plans\SavingsPlanService;
use App\Services\Shared\CrossModuleAssetAggregator;
use App\Services\Stores\SavingsStore;
use App\Services\Tax\TaxStrategyMath;
use App\Services\TaxConfigService;
use App\Services\UserProfile\LetterToSpouseService;
use Database\Seeders\EstateActionDefinitionSeeder;
use Database\Seeders\TaxConfigurationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

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
    $store = app(SavingsStore::class);
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

// PR 5c-2 parity tests — Retirement cluster

it('RetirementPlanService cash-account funding source read identical after store migration', function () {
    $user = User::factory()->create(['is_preview_user' => false]);

    $cashTypes = ['current_account', 'instant_access', 'business_current', 'business_savings'];

    SavingsAccount::factory()->create([
        'user_id' => $user->id,
        'account_name' => 'BigCash',
        'account_type' => 'instant_access',
        'is_isa' => false,
        'current_balance' => 15000,
        'ownership_type' => 'individual',
        'ownership_percentage' => 100,
    ]);
    SavingsAccount::factory()->create([
        'user_id' => $user->id,
        'account_name' => 'SmallCash',
        'account_type' => 'current_account',
        'is_isa' => false,
        'current_balance' => 1000,
        'ownership_type' => 'individual',
        'ownership_percentage' => 100,
    ]);
    // ISA — must be excluded
    SavingsAccount::factory()->create([
        'user_id' => $user->id,
        'account_name' => 'CashISA',
        'account_type' => 'instant_access',
        'is_isa' => true,
        'current_balance' => 20000,
        'ownership_type' => 'individual',
        'ownership_percentage' => 100,
    ]);

    // Pre-refactor: SavingsAccount::where('user_id', $user->id)->where('is_isa', false)->whereIn('account_type', ...)->orderByDesc()->get()
    $preRefactor = SavingsAccount::where('user_id', $user->id)
        ->where('is_isa', false)
        ->whereIn('account_type', $cashTypes)
        ->orderByDesc('current_balance')
        ->get();

    // Post-refactor: store->forUser()->where()->whereIn()->sortByDesc()
    $store = app(SavingsStore::class);
    $postRefactor = $store->forUser($user)
        ->where('user_id', $user->id)
        ->where('is_isa', false)
        ->whereIn('account_type', $cashTypes)
        ->sortByDesc('current_balance')
        ->values();

    expect($preRefactor)->toHaveCount(2);
    expect($postRefactor)->toHaveCount(2);
    expect($preRefactor->first()->account_name)->toBe('BigCash');
    expect($postRefactor->first()->account_name)->toBe('BigCash');
    expect($preRefactor->pluck('account_name')->toArray())
        ->toBe($postRefactor->pluck('account_name')->toArray());
});

it('RetirementStrategyService savings read identical after store migration', function () {
    $user = User::factory()->create(['is_preview_user' => false]);

    SavingsAccount::factory()->create([
        'user_id' => $user->id,
        'account_name' => 'CashSavings',
        'current_balance' => 8000,
        'include_in_retirement' => true,
        'ownership_type' => 'individual',
        'ownership_percentage' => 100,
    ]);
    SavingsAccount::factory()->create([
        'user_id' => $user->id,
        'account_name' => 'EmptyAccount',
        'current_balance' => 0,
        'ownership_type' => 'individual',
        'ownership_percentage' => 100,
    ]);
    // Different user — must be excluded
    $other = User::factory()->create(['is_preview_user' => false]);
    SavingsAccount::factory()->create([
        'user_id' => $other->id,
        'account_name' => 'OtherUser',
        'current_balance' => 50000,
    ]);

    // Pre-refactor: SavingsAccount::where('user_id', $userId)->get()
    $preRefactor = SavingsAccount::where('user_id', $user->id)->get();

    // Post-refactor: store->forUser()->where('user_id', ...)
    $store = app(SavingsStore::class);
    $postRefactor = $store->forUser($user)->where('user_id', $user->id)->values();

    expect($preRefactor->count())->toBe(2);
    expect($postRefactor->count())->toBe(2);
    expect($preRefactor->pluck('id')->sort()->values()->toArray())
        ->toBe($postRefactor->pluck('id')->sort()->values()->toArray());
});

it('RetirementIncomeService household-savings forUsers parity on individual accounts (no joint involvement)', function () {
    // Baseline parity: when no joint accounts exist, post-refactor forUsers
    // returns the same set as pre-refactor whereIn('user_id', $userIds).
    $userA = User::factory()->create(['is_preview_user' => false]);
    $userB = User::factory()->create(['is_preview_user' => false]);
    $userC = User::factory()->create(['is_preview_user' => false]);

    $accA = SavingsAccount::factory()->create([
        'user_id' => $userA->id, 'include_in_retirement' => true, 'is_isa' => true, 'current_balance' => 5000,
        'ownership_type' => 'individual', 'ownership_percentage' => 100,
    ]);
    $accB = SavingsAccount::factory()->create([
        'user_id' => $userB->id, 'include_in_retirement' => true, 'is_isa' => true, 'current_balance' => 3000,
        'ownership_type' => 'individual', 'ownership_percentage' => 100,
    ]);
    SavingsAccount::factory()->create([
        'user_id' => $userC->id, 'include_in_retirement' => true, 'is_isa' => true, 'current_balance' => 9999,
        'ownership_type' => 'individual', 'ownership_percentage' => 100,
    ]);

    $userIds = [$userA->id, $userB->id];
    $preRefactor = SavingsAccount::whereIn('user_id', $userIds)->where('include_in_retirement', true)->where('is_isa', true)->get();
    $postRefactor = app(SavingsStore::class)->forUsers($userIds)->where('include_in_retirement', true)->where('is_isa', true)->values();

    expect($preRefactor)->toHaveCount(2);
    expect($postRefactor)->toHaveCount(2);
    expect($preRefactor->pluck('id')->sort()->values()->toArray())
        ->toBe($postRefactor->pluck('id')->sort()->values()->toArray());
});

it('RetirementIncomeService household-savings forUsers — DELIBERATE WIDENING: includes joint accounts where one requested user is the secondary owner', function () {
    // Pre-refactor `whereIn('user_id', $userIds)` was strictly user_id-only — joint
    // accounts where one of the requested users is the joint_owner_id (not user_id)
    // were excluded. Post-refactor `forUsers` is OR-combined `(user_id IN OR joint_owner_id IN)`.
    // For household-level retirement income calculations this widening is correct
    // (household includes joint accounts regardless of which spouse is primary). This
    // test makes the deliberate widening explicit so future readers understand the intent.
    $userA = User::factory()->create(['is_preview_user' => false]);
    $userB = User::factory()->create(['is_preview_user' => false]);
    $userExternal = User::factory()->create(['is_preview_user' => false]);

    // Joint account where userExternal is primary, userB is joint — pre-refactor missed this
    $jointAccBSecondary = SavingsAccount::factory()->create([
        'user_id' => $userExternal->id,
        'joint_owner_id' => $userB->id,
        'ownership_type' => 'joint',
        'ownership_percentage' => 50,
        'is_isa' => false,
        'current_balance' => 8000,
    ]);
    // Plain individual for userA so pre-refactor returns at least one row
    $accA = SavingsAccount::factory()->create([
        'user_id' => $userA->id, 'is_isa' => false, 'current_balance' => 1000,
        'ownership_type' => 'individual', 'ownership_percentage' => 100,
    ]);

    $userIds = [$userA->id, $userB->id];
    $preRefactor = SavingsAccount::whereIn('user_id', $userIds)->get();
    $postRefactor = app(SavingsStore::class)->forUsers($userIds);

    // Pre-refactor: only $accA (1 row) — joint where userB is secondary is missed
    expect($preRefactor->pluck('id')->toArray())->toBe([$accA->id]);
    // Post-refactor: $accA AND the joint account (2 rows) — correct household scope
    expect($postRefactor->pluck('id')->sort()->values()->toArray())
        ->toBe(collect([$accA->id, $jointAccBSecondary->id])->sort()->values()->toArray());
});

it('RetirementIncomeService id-based whereIn — DELIBERATE NARROWING: store->findMany excludes cross-user account ids (security fix)', function () {
    // Reviewer-flagged HIGH/HIGH: pre-refactor `SavingsAccount::whereIn('id', $ids)->get()`
    // returned ANY account whose id matched, regardless of ownership. Combined with
    // client-supplied source_id values flowing through the controller, that was a data
    // leak path. Post-refactor `store->findMany($ids, $user)` enforces ownership at the
    // store boundary. This test makes the narrowing explicit.
    $userA = User::factory()->create(['is_preview_user' => false]);
    $userB = User::factory()->create(['is_preview_user' => false]);

    $accA = SavingsAccount::factory()->create([
        'user_id' => $userA->id,
        'is_isa' => true,
        'current_balance' => 10000,
    ]);
    $accB_foreign = SavingsAccount::factory()->create([
        'user_id' => $userB->id,
        'is_isa' => false,
        'current_balance' => 4000,
    ]);

    $ids = [$accA->id, $accB_foreign->id];

    // Pre-refactor returned BOTH accounts (the leak)
    $preRefactor = SavingsAccount::whereIn('id', $ids)->get();
    expect($preRefactor->count())->toBe(2);

    // Post-refactor returns ONLY userA's account when userA is the requesting user
    $store = app(SavingsStore::class);
    $postRefactor = $store->findMany($ids, $userA);
    expect($postRefactor->count())->toBe(1);
    expect($postRefactor->pluck('id')->toArray())->toBe([$accA->id]);
});

it('BasePlanService::resolveFundingSource returns null result when goal references a deleted user (regression)', function () {
    // Pre-refactor used raw $userId in WHERE so missing user → empty Builder result, no crash.
    // Post-refactor uses User::find($userId) + Collection chain — null user must short-circuit.
    $orphanGoal = new Goal([
        'name' => 'Orphan',
        'target_amount' => 10000,
        'current_amount' => 0,
        'target_date' => now()->addYear(),
        'priority' => 'high',
    ]);
    $orphanGoal->user_id = 99999999;  // non-existent user

    // BasePlanService is abstract — use a concrete subclass that inherits resolveFundingSource.
    $service = app(SavingsPlanService::class);
    $reflection = new ReflectionClass(BasePlanService::class);
    $method = $reflection->getMethod('resolveFundingSource');
    $method->setAccessible(true);

    $result = $method->invoke($service, $orphanGoal);

    expect($result)->toBe(['name' => null, 'warning' => null]);
});

// PR 5d parity tests — Tax strategies cluster

it('JointSavingsStrategy:56 sole-name non-ISA read identical after store migration (joint excluded)', function () {
    $user = User::factory()->create(['is_preview_user' => false]);
    $spouse = User::factory()->create(['is_preview_user' => false]);

    // Sole-name non-ISA accounts — must be included
    SavingsAccount::factory()->create([
        'user_id' => $user->id, 'is_isa' => false, 'current_balance' => 10000,
        'interest_rate' => 0.04, 'joint_owner_id' => null,
        'ownership_type' => 'individual', 'ownership_percentage' => 100,
    ]);
    SavingsAccount::factory()->create([
        'user_id' => $user->id, 'is_isa' => false, 'current_balance' => 5000,
        'interest_rate' => 4.0, 'joint_owner_id' => null,
        'ownership_type' => 'individual', 'ownership_percentage' => 100,
    ]);
    // ISA — must be excluded
    SavingsAccount::factory()->create([
        'user_id' => $user->id, 'is_isa' => true, 'current_balance' => 20000,
        'joint_owner_id' => null, 'ownership_type' => 'individual', 'ownership_percentage' => 100,
    ]);
    // Joint account owned by user (joint_owner_id set) — must be excluded (whereNull guard)
    SavingsAccount::factory()->create([
        'user_id' => $user->id, 'is_isa' => false, 'current_balance' => 99999,
        'joint_owner_id' => $spouse->id, 'ownership_type' => 'joint', 'ownership_percentage' => 50,
    ]);
    // Joint account owned by SPOUSE with user as joint_owner_id — forUser() is
    // joint-aware and would surface this; the Collection where('user_id') post-filter
    // must exclude it (proves single-owner semantics preserved).
    SavingsAccount::factory()->create([
        'user_id' => $spouse->id, 'is_isa' => false, 'current_balance' => 88888,
        'joint_owner_id' => $user->id, 'ownership_type' => 'joint', 'ownership_percentage' => 50,
    ]);

    $preRefactor = SavingsAccount::query()
        ->where('user_id', $user->id)
        ->whereNull('joint_owner_id')
        ->where('is_isa', false)
        ->get(['current_balance', 'interest_rate']);

    $postRefactor = app(SavingsStore::class)->forUser($user)
        ->where('user_id', $user->id)
        ->whereNull('joint_owner_id')
        ->where('is_isa', false);

    expect($preRefactor)->toHaveCount(2);
    expect($postRefactor)->toHaveCount(2);
    expect((float) $postRefactor->sum('current_balance'))->toBe((float) $preRefactor->sum('current_balance'));
    expect((float) $postRefactor->sum('current_balance'))->toBe(15000.0);
    // Average interest-rate parity (the other column the strategy consumes via ->map()).
    $rate = fn ($acc) => ((float) $acc->interest_rate) > 1 ? ((float) $acc->interest_rate) / 100 : (float) $acc->interest_rate;
    expect((float) $postRefactor->map($rate)->avg())->toBe((float) $preRefactor->map($rate)->avg());
});

it('AssetShiftingBundleStrategy:64 single-owner non-ISA read identical after store migration', function () {
    $user = User::factory()->create(['is_preview_user' => false]);

    SavingsAccount::factory()->create([
        'user_id' => $user->id, 'is_isa' => false, 'current_balance' => 7000,
        'interest_rate' => 0.03, 'ownership_type' => 'individual', 'ownership_percentage' => 100,
    ]);
    SavingsAccount::factory()->create([
        'user_id' => $user->id, 'is_isa' => true, 'current_balance' => 12000,
        'ownership_type' => 'individual', 'ownership_percentage' => 100,
    ]);

    $preRefactor = SavingsAccount::query()
        ->where('user_id', $user->id)->where('is_isa', false)
        ->get(['current_balance', 'interest_rate']);
    $postRefactor = app(SavingsStore::class)->forUser($user)
        ->where('user_id', $user->id)->where('is_isa', false);

    expect((float) $postRefactor->sum('current_balance'))->toBe((float) $preRefactor->sum('current_balance'));
    expect((float) $postRefactor->sum('current_balance'))->toBe(7000.0);
});

it('PensionAACarryForwardStrategy:95 single-owner non-ISA liquid-wealth sum identical', function () {
    $user = User::factory()->create(['is_preview_user' => false]);

    SavingsAccount::factory()->create([
        'user_id' => $user->id, 'is_isa' => false, 'current_balance' => 25000,
        'ownership_type' => 'individual', 'ownership_percentage' => 100,
    ]);
    SavingsAccount::factory()->create([
        'user_id' => $user->id, 'is_isa' => true, 'current_balance' => 50000,
        'ownership_type' => 'individual', 'ownership_percentage' => 100,
    ]);

    $preRefactor = (float) SavingsAccount::query()
        ->where('user_id', $user->id)->where('is_isa', false)->sum('current_balance');
    $postRefactor = (float) app(SavingsStore::class)->forUser($user)
        ->where('user_id', $user->id)->where('is_isa', false)->sum('current_balance');

    expect($postRefactor)->toBe($preRefactor);
    expect($postRefactor)->toBe(25000.0);
});

it('IsaTopUpStrategy:43 single-owner non-ISA balance sum identical after store migration', function () {
    $user = User::factory()->create(['is_preview_user' => false]);

    SavingsAccount::factory()->create([
        'user_id' => $user->id, 'is_isa' => false, 'current_balance' => 3000,
        'ownership_type' => 'individual', 'ownership_percentage' => 100,
    ]);
    SavingsAccount::factory()->create([
        'user_id' => $user->id, 'is_isa' => false, 'current_balance' => 4500,
        'ownership_type' => 'individual', 'ownership_percentage' => 100,
    ]);

    $preRefactor = (float) SavingsAccount::query()
        ->where('user_id', $user->id)->where('is_isa', false)->sum('current_balance');
    $postRefactor = (float) app(SavingsStore::class)->forUser($user)
        ->where('user_id', $user->id)->where('is_isa', false)->sum('current_balance');

    expect($postRefactor)->toBe($preRefactor);
    expect($postRefactor)->toBe(7500.0);
});

it('TaxOptimisationService:113,126,424 ISA / non-ISA / spouse-ISA reads identical after store migration', function () {
    $user = User::factory()->create(['is_preview_user' => false]);
    $spouse = User::factory()->create(['is_preview_user' => false]);

    // user cash ISA (account_type isa)
    SavingsAccount::factory()->create([
        'user_id' => $user->id, 'account_type' => 'isa', 'is_isa' => true,
        'isa_subscription_amount' => 8000, 'current_balance' => 8000,
        'ownership_type' => 'individual', 'ownership_percentage' => 100,
    ]);
    // user non-ISA
    SavingsAccount::factory()->create([
        'user_id' => $user->id, 'account_type' => 'easy_access', 'is_isa' => false,
        'current_balance' => 6000, 'ownership_type' => 'individual', 'ownership_percentage' => 100,
    ]);
    // spouse cash ISA
    SavingsAccount::factory()->create([
        'user_id' => $spouse->id, 'account_type' => 'isa', 'is_isa' => true,
        'isa_subscription_amount' => 3000, 'current_balance' => 3000,
        'ownership_type' => 'individual', 'ownership_percentage' => 100,
    ]);

    // :113 — user cash ISAs subscribed
    $preISA = (float) SavingsAccount::where('user_id', $user->id)
        ->where('account_type', 'isa')->get()->sum('isa_subscription_amount');
    $postISA = (float) app(SavingsStore::class)->forUser($user)
        ->where('user_id', $user->id)->where('account_type', 'isa')->sum('isa_subscription_amount');
    expect($postISA)->toBe($preISA);
    expect($postISA)->toBe(8000.0);

    // :126 — user non-ISA balance
    $preNon = (float) SavingsAccount::where('user_id', $user->id)
        ->where('account_type', '!=', 'isa')->sum('current_balance');
    $postNon = (float) app(SavingsStore::class)->forUser($user)
        ->where('user_id', $user->id)->where('account_type', '!=', 'isa')->sum('current_balance');
    expect($postNon)->toBe($preNon);
    expect($postNon)->toBe(6000.0);

    // :424 — spouse cash ISA subscribed
    $preSpouse = (float) SavingsAccount::where('user_id', $spouse->id)
        ->where('account_type', 'isa')->sum('isa_subscription_amount');
    $postSpouse = (float) app(SavingsStore::class)->forUser($spouse)
        ->where('user_id', $spouse->id)->where('account_type', 'isa')->sum('isa_subscription_amount');
    expect($postSpouse)->toBe($preSpouse);
    expect($postSpouse)->toBe(3000.0);
});

it('TaxStrategyMath::estimateAnnualInterest output identical after store migration (joint excluded)', function () {
    $user = User::factory()->create(['is_preview_user' => false]);
    $spouse = User::factory()->create(['is_preview_user' => false]);

    // £10k @ 4% (percent convention) + £5k @ 0.03 (decimal convention) = 400 + 150 = 550
    SavingsAccount::factory()->create([
        'user_id' => $user->id, 'is_isa' => false, 'current_balance' => 10000,
        'interest_rate' => 4.0, 'ownership_type' => 'individual', 'ownership_percentage' => 100,
    ]);
    SavingsAccount::factory()->create([
        'user_id' => $user->id, 'is_isa' => false, 'current_balance' => 5000,
        'interest_rate' => 0.03, 'ownership_type' => 'individual', 'ownership_percentage' => 100,
    ]);
    // ISA excluded
    SavingsAccount::factory()->create([
        'user_id' => $user->id, 'is_isa' => true, 'current_balance' => 20000,
        'interest_rate' => 5.0, 'ownership_type' => 'individual', 'ownership_percentage' => 100,
    ]);
    // Joint owned by spouse with user as joint_owner_id — forUser() is joint-aware;
    // post-filter where('user_id') must exclude it from estimateAnnualInterest.
    SavingsAccount::factory()->create([
        'user_id' => $spouse->id, 'is_isa' => false, 'current_balance' => 100000,
        'interest_rate' => 5.0, 'joint_owner_id' => $user->id,
        'ownership_type' => 'joint', 'ownership_percentage' => 50,
    ]);

    $math = app(TaxStrategyMath::class);
    expect($math->estimateAnnualInterest($user))->toBe(550.0);
});

it('TaxStrategyMath::estimateIsaSubscriptionsThisYear only sums current-year ISA balances (Carbon filter parity)', function () {
    $user = User::factory()->create(['is_preview_user' => false]);

    $taxYearStart = app(TaxConfigService::class)->getEffectiveFrom();
    expect($taxYearStart)->not->toBe('');

    // Current-year ISA — created after tax-year start: counted
    SavingsAccount::factory()->create([
        'user_id' => $user->id, 'is_isa' => true, 'current_balance' => 12000,
        'created_at' => Carbon::parse($taxYearStart)->addDays(10),
        'ownership_type' => 'individual', 'ownership_percentage' => 100,
    ]);
    // Old ISA — created before tax-year start: excluded
    SavingsAccount::factory()->create([
        'user_id' => $user->id, 'is_isa' => true, 'current_balance' => 25000,
        'created_at' => Carbon::parse($taxYearStart)->subDays(30),
        'ownership_type' => 'individual', 'ownership_percentage' => 100,
    ]);
    // Non-ISA — always excluded
    SavingsAccount::factory()->create([
        'user_id' => $user->id, 'is_isa' => false, 'current_balance' => 9000,
        'ownership_type' => 'individual', 'ownership_percentage' => 100,
    ]);

    // Pre-refactor: SQL where created_at >= taxYearStart
    $preRefactor = (float) SavingsAccount::query()
        ->where('user_id', $user->id)->where('is_isa', true)
        ->where('created_at', '>=', $taxYearStart)->sum('current_balance');

    $math = app(TaxStrategyMath::class);
    $post = $math->estimateIsaSubscriptionsThisYear($user);

    expect($post)->toBe($preRefactor);
    expect($post)->toBe(12000.0);
});

it('TaxActionDefinitionService:110,291 cash-ISA subscribed read identical after store migration', function () {
    $user = User::factory()->create(['is_preview_user' => false]);

    SavingsAccount::factory()->create([
        'user_id' => $user->id, 'account_type' => 'isa', 'is_isa' => true,
        'isa_subscription_amount' => 4500, 'current_balance' => 4500,
        'ownership_type' => 'individual', 'ownership_percentage' => 100,
    ]);
    SavingsAccount::factory()->create([
        'user_id' => $user->id, 'account_type' => 'easy_access', 'is_isa' => false,
        'current_balance' => 9000, 'ownership_type' => 'individual', 'ownership_percentage' => 100,
    ]);

    $preRefactor = (float) SavingsAccount::where('user_id', $user->id)
        ->where('account_type', 'isa')->sum('isa_subscription_amount');
    $postRefactor = (float) app(SavingsStore::class)->forUser($user)
        ->where('user_id', $user->id)->where('account_type', 'isa')->sum('isa_subscription_amount');

    expect($postRefactor)->toBe($preRefactor);
    expect($postRefactor)->toBe(4500.0);
});

// PR 5e parity tests — Investment ISA consumers cluster

it('ISAAllowanceOptimizer::calculateAllowanceUsage cash + LISA savings figures identical after store migration', function () {
    $user = User::factory()->create(['is_preview_user' => false]);

    SavingsAccount::factory()->create([
        'user_id' => $user->id, 'account_type' => 'cash_isa', 'is_isa' => true,
        'current_balance' => 12000, 'ownership_type' => 'individual', 'ownership_percentage' => 100,
    ]);
    SavingsAccount::factory()->create([
        'user_id' => $user->id, 'account_type' => 'lifetime_isa', 'is_isa' => true,
        'current_balance' => 4000, 'ownership_type' => 'individual', 'ownership_percentage' => 100,
    ]);
    // Non-ISA — must be excluded by the whereIn account_type filter
    SavingsAccount::factory()->create([
        'user_id' => $user->id, 'account_type' => 'easy_access', 'is_isa' => false,
        'current_balance' => 99999, 'ownership_type' => 'individual', 'ownership_percentage' => 100,
    ]);

    $usage = app(ISAAllowanceOptimizer::class)
        ->calculateAllowanceUsage($user->id, '2025/26');

    expect($usage['cash_isa'])->toBe(12000.0);
    expect($usage['lifetime_isa'])->toBe(4000.0);
    expect($usage['breakdown']['savings_accounts'])->toBe(2);
});

it('ISAAllowanceOptimizer::calculateAllowanceUsage non-existent userId returns the same zeroed structure (collect() empty-equivalent)', function () {
    $usage = app(ISAAllowanceOptimizer::class)
        ->calculateAllowanceUsage(999999, '2025/26');

    expect($usage['cash_isa'])->toBe(0.0);
    expect($usage['lifetime_isa'])->toBe(0.0);
    expect($usage['stocks_and_shares_isa'])->toBe(0.0);
    expect($usage['total_used'])->toBe(0.0);
    expect($usage['breakdown']['savings_accounts'])->toBe(0);
});

it('TaxOptimizationAnalyzer::analyzeCompleteTaxPosition savings-only structure parity after store migration', function () {
    $user = User::factory()->create(['is_preview_user' => false]);

    SavingsAccount::factory()->create([
        'user_id' => $user->id, 'account_type' => 'easy_access', 'is_isa' => false,
        'current_balance' => 15000, 'ownership_type' => 'individual', 'ownership_percentage' => 100,
    ]);

    $result = app(TaxOptimizationAnalyzer::class)
        ->analyzeCompleteTaxPosition($user->id);

    expect($result['success'])->toBeTrue();
    expect($result)->toHaveKey('current_position');
});

it('TaxOptimizationAnalyzer::analyzeCompleteTaxPosition non-existent userId returns no-accounts failure (collect() empty-equivalent)', function () {
    $result = app(TaxOptimizationAnalyzer::class)
        ->analyzeCompleteTaxPosition(999999);

    expect($result)->toBe([
        'success' => false,
        'message' => 'No investment or savings accounts found',
    ]);
});

it('UserContextBuilder::build emergency-fund total INCLUDES joint non-ISA account (site 86 joint-aware path preserved, NO single-owner post-filter)', function () {
    $user = User::factory()->create(['is_preview_user' => false]);
    $spouse = User::factory()->create(['is_preview_user' => false]);

    // Single-owner non-ISA account owned by user — £X
    SavingsAccount::factory()->create([
        'user_id' => $user->id, 'account_type' => 'easy_access', 'is_isa' => false,
        'current_balance' => 30000, 'joint_owner_id' => null,
        'ownership_type' => 'individual', 'ownership_percentage' => 100,
    ]);
    // Joint NON-ISA account owned by SPOUSE with user as joint_owner_id — £Y.
    // forUser() is joint-aware (forUserOrJoint) — this MUST be included in the
    // emergency-fund total. If a single-owner where('user_id') post-filter were
    // wrongly added at site 86 this would be excluded (the regression we guard).
    SavingsAccount::factory()->create([
        'user_id' => $spouse->id, 'account_type' => 'easy_access', 'is_isa' => false,
        'current_balance' => 20000, 'joint_owner_id' => $user->id,
        'ownership_type' => 'joint', 'ownership_percentage' => 50,
    ]);

    $context = app(UserContextBuilder::class)->build($user);

    // £X + £Y = 30000 + 20000 — joint account INCLUDED.
    expect($context['emergency_fund']['total_savings'])->toBe(50000.0);
});

it('UserContextBuilder::build ISA-used reflects single-owner cash-ISA current-year subscription (site 65)', function () {
    $user = User::factory()->create(['is_preview_user' => false]);
    $taxYear = app(TaxConfigService::class)->getTaxYear();
    $isaAllowance = app(TaxConfigService::class)->getISAAllowances()['annual_allowance'];

    SavingsAccount::factory()->create([
        'user_id' => $user->id, 'account_type' => 'cash_isa', 'is_isa' => true,
        'isa_subscription_year' => $taxYear, 'isa_subscription_amount' => 5000,
        'current_balance' => 5000, 'joint_owner_id' => null,
        'ownership_type' => 'individual', 'ownership_percentage' => 100,
    ]);
    // Different-year subscription — must be excluded by the isa_subscription_year filter
    SavingsAccount::factory()->create([
        'user_id' => $user->id, 'account_type' => 'cash_isa', 'is_isa' => true,
        'isa_subscription_year' => '2020/21', 'isa_subscription_amount' => 9000,
        'current_balance' => 9000, 'joint_owner_id' => null,
        'ownership_type' => 'individual', 'ownership_percentage' => 100,
    ]);

    $context = app(UserContextBuilder::class)->build($user);

    expect($context['allowances']['isa_used'])->toBe(5000.0);
    expect($context['allowances']['isa_remaining'])->toBe(round($isaAllowance - 5000.0, 2));
});

it('UserContextBuilder::buildSpouseContext spouse cash-ISA single-owner read identical after store migration (site 464)', function () {
    $user = User::factory()->create([
        'is_preview_user' => false, 'marital_status' => 'married',
    ]);
    $spouse = User::factory()->create(['is_preview_user' => false]);
    $user->spouse_id = $spouse->id;
    $user->save();

    $taxYear = app(TaxConfigService::class)->getTaxYear();

    SavingsAccount::factory()->create([
        'user_id' => $spouse->id, 'account_type' => 'cash_isa', 'is_isa' => true,
        'isa_subscription_year' => $taxYear, 'isa_subscription_amount' => 7000,
        'current_balance' => 7000, 'joint_owner_id' => null,
        'ownership_type' => 'individual', 'ownership_percentage' => 100,
    ]);

    $preRefactor = (float) SavingsAccount::where('user_id', $spouse->id)
        ->whereIn('account_type', ['isa', 'cash_isa'])
        ->where('isa_subscription_year', $taxYear)
        ->sum('isa_subscription_amount');
    $postRefactor = (float) app(SavingsStore::class)->forUser($spouse)
        ->where('user_id', $spouse->id)
        ->whereIn('account_type', ['isa', 'cash_isa'])
        ->where('isa_subscription_year', $taxYear)
        ->sum('isa_subscription_amount');

    expect($postRefactor)->toBe($preRefactor);
    expect($postRefactor)->toBe(7000.0);
});

// PR 5f parity tests — Coordination + Goals cluster

it('HouseholdPlanningService::gatherAssetsForUser savings value INCLUDES the user share of joint non-ISA accounts as BOTH primary and secondary owner (site 397 joint-aware preserved, NO single-owner post-filter)', function () {
    $user = User::factory()->create(['is_preview_user' => false]);
    $spouse = User::factory()->create(['is_preview_user' => false]);
    $other = User::factory()->create(['is_preview_user' => false]);

    // Single-owner non-ISA owned by the user — full value counts.
    SavingsAccount::factory()->create([
        'user_id' => $user->id, 'account_type' => 'easy_access', 'is_isa' => false,
        'current_balance' => 30000, 'joint_owner_id' => null,
        'ownership_type' => 'individual', 'ownership_percentage' => 100,
    ]);
    // Joint NON-ISA owned by SPOUSE with the user as joint_owner_id @50%
    // (user is the SECONDARY owner). forUser() is joint-aware
    // (forUserOrJoint) and calculateUserShare gives the user the
    // complementary (100-50)=50% share. If a single-owner where('user_id')
    // post-filter were wrongly added at site 397, this joint account would
    // be excluded entirely (the regression we guard).
    SavingsAccount::factory()->create([
        'user_id' => $spouse->id, 'account_type' => 'easy_access', 'is_isa' => false,
        'current_balance' => 20000, 'joint_owner_id' => $user->id,
        'ownership_type' => 'joint', 'ownership_percentage' => 50,
    ]);
    // Joint NON-ISA owned by the USER with $other as joint_owner_id @50%
    // (user is the PRIMARY owner). calculateUserShare gives the primary
    // owner their ownership_percentage (50%). forUserOrJoint already
    // includes user-as-primary rows, so a single-owner post-filter would
    // NOT drop this one — but seeding it proves the primary-owner joint
    // share is also preserved with no narrowing of the joint sweep.
    SavingsAccount::factory()->create([
        'user_id' => $user->id, 'account_type' => 'easy_access', 'is_isa' => false,
        'current_balance' => 16000, 'joint_owner_id' => $other->id,
        'ownership_type' => 'joint', 'ownership_percentage' => 50,
    ]);

    $service = app(HouseholdPlanningService::class);
    $reflection = new ReflectionClass($service);
    $method = $reflection->getMethod('gatherAssetsForUser');
    $method->setAccessible(true);

    $result = $method->invoke($service, $user);

    // 30000 (single-owner, 100%)
    //  + 20000 * 50% = 10000 (joint, user is SECONDARY owner → 100-50%)
    //  + 16000 * 50% =  8000 (joint, user is PRIMARY owner → ownership_pct)
    //  = 48000
    expect($result['breakdown']['savings'])->toBe(48000.0);
});

it('HouseholdPlanningService::calculateISAUsage single-owner ISA subscription sum identical after store migration', function () {
    $user = User::factory()->create(['is_preview_user' => false]);

    SavingsAccount::factory()->create([
        'user_id' => $user->id, 'account_type' => 'cash_isa', 'is_isa' => true,
        'isa_subscription_amount' => 6000, 'current_balance' => 6000,
        'joint_owner_id' => null, 'ownership_type' => 'individual', 'ownership_percentage' => 100,
    ]);
    SavingsAccount::factory()->create([
        'user_id' => $user->id, 'account_type' => 'cash_isa', 'is_isa' => true,
        'isa_subscription_amount' => 2500, 'current_balance' => 2500,
        'joint_owner_id' => null, 'ownership_type' => 'individual', 'ownership_percentage' => 100,
    ]);
    // Non-ISA — must be excluded by the is_isa filter.
    SavingsAccount::factory()->create([
        'user_id' => $user->id, 'account_type' => 'easy_access', 'is_isa' => false,
        'isa_subscription_amount' => 99999, 'current_balance' => 1000,
        'joint_owner_id' => null, 'ownership_type' => 'individual', 'ownership_percentage' => 100,
    ]);

    $service = app(HouseholdPlanningService::class);
    $reflection = new ReflectionClass($service);
    $method = $reflection->getMethod('calculateISAUsage');
    $method->setAccessible(true);

    expect($method->invoke($service, $user))->toBe(8500.0);
});

it('CashFlowCoordinator::calculateCommittedContributions monthly-equivalent of regular savings contributions identical after store migration', function () {
    $user = User::factory()->create(['is_preview_user' => false]);

    SavingsAccount::factory()->create([
        'user_id' => $user->id, 'account_type' => 'easy_access', 'is_isa' => false,
        'current_balance' => 5000, 'regular_contribution_amount' => 300,
        'contribution_frequency' => 'monthly',
        'joint_owner_id' => null, 'ownership_type' => 'individual', 'ownership_percentage' => 100,
    ]);
    SavingsAccount::factory()->create([
        'user_id' => $user->id, 'account_type' => 'easy_access', 'is_isa' => false,
        'current_balance' => 5000, 'regular_contribution_amount' => 1200,
        'contribution_frequency' => 'annually',
        'joint_owner_id' => null, 'ownership_type' => 'individual', 'ownership_percentage' => 100,
    ]);
    // Null contribution — excluded by whereNotNull.
    SavingsAccount::factory()->create([
        'user_id' => $user->id, 'account_type' => 'easy_access', 'is_isa' => false,
        'current_balance' => 5000, 'regular_contribution_amount' => null,
        'joint_owner_id' => null, 'ownership_type' => 'individual', 'ownership_percentage' => 100,
    ]);

    $service = app(CashFlowCoordinator::class);
    $reflection = new ReflectionClass($service);
    $method = $reflection->getMethod('calculateCommittedContributions');
    $method->setAccessible(true);

    // 300 (monthly) + 1200/12 (annual → 100/mo) = 400.0
    expect($method->invoke($service, $user->id))->toBe(400.0);
});

it('CashFlowCoordinator::calculateCommittedContributions non-existent userId returns 0.0 (collect() empty-equivalent)', function () {
    $service = app(CashFlowCoordinator::class);
    $reflection = new ReflectionClass($service);
    $method = $reflection->getMethod('calculateCommittedContributions');
    $method->setAccessible(true);

    expect($method->invoke($service, 999999))->toBe(0.0);
});

it('LifeEventAllocationService::buildExpenseFundFrom draws from cash accounts LARGEST-BALANCE-FIRST (sortByDesc allocation order parity, sites 205/258)', function () {
    $user = User::factory()->create(['is_preview_user' => false]);

    // Insert in deliberately NON-sorted order (mid, large, small). The
    // allocation MUST draw largest-first: 9000 fully, then 5000 partially.
    $mid = SavingsAccount::factory()->create([
        'user_id' => $user->id, 'account_type' => 'easy_access', 'is_isa' => false,
        'current_balance' => 5000, 'account_name' => 'MidAccount',
        'joint_owner_id' => null, 'ownership_type' => 'individual', 'ownership_percentage' => 100,
    ]);
    $large = SavingsAccount::factory()->create([
        'user_id' => $user->id, 'account_type' => 'easy_access', 'is_isa' => false,
        'current_balance' => 9000, 'account_name' => 'LargeAccount',
        'joint_owner_id' => null, 'ownership_type' => 'individual', 'ownership_percentage' => 100,
    ]);
    $small = SavingsAccount::factory()->create([
        'user_id' => $user->id, 'account_type' => 'easy_access', 'is_isa' => false,
        'current_balance' => 1000, 'account_name' => 'SmallAccount',
        'joint_owner_id' => null, 'ownership_type' => 'individual', 'ownership_percentage' => 100,
    ]);

    // Expense event for £12,000 — needs LargeAccount (9000) fully + 3000 from MidAccount.
    $event = LifeEvent::factory()->create([
        'user_id' => $user->id, 'impact_type' => 'expense', 'amount' => 12000,
        'ownership_type' => 'individual', 'joint_owner_id' => null, 'ownership_percentage' => 100,
    ]);

    $service = app(LifeEventAllocationService::class);
    $reflection = new ReflectionClass($service);
    $method = $reflection->getMethod('buildExpenseFundFrom');
    $method->setAccessible(true);

    $rows = $method->invoke($service, $event, $user);
    $cashRows = array_values(array_filter($rows, fn ($r) => $r['step'] === 'cash' && $r['account_type'] === 'savings_account'));

    // Largest first: LargeAccount fully drawn, then MidAccount partial. SmallAccount never reached.
    expect($cashRows)->toHaveCount(2);
    expect($cashRows[0]['account_id'])->toBe($large->id);
    expect($cashRows[0]['amount'])->toBe(9000.0);
    expect($cashRows[1]['account_id'])->toBe($mid->id);
    expect($cashRows[1]['amount'])->toBe(3000.0);
    expect(collect($cashRows)->pluck('account_id'))->not->toContain($small->id);
});

it('LifeEventAllocationService::findCashAccountModel returns emergency-fund account first, else largest non-ISA, else null (null-coalesce + sortByDesc parity)', function () {
    $service = app(LifeEventAllocationService::class);
    $reflection = new ReflectionClass($service);
    $method = $reflection->getMethod('findCashAccountModel');
    $method->setAccessible(true);

    // Case 1: emergency-fund account wins even though a larger non-ISA exists.
    $userA = User::factory()->create(['is_preview_user' => false]);
    $emergency = SavingsAccount::factory()->create([
        'user_id' => $userA->id, 'account_type' => 'easy_access', 'is_isa' => false,
        'is_emergency_fund' => true, 'current_balance' => 3000,
        'joint_owner_id' => null, 'ownership_type' => 'individual', 'ownership_percentage' => 100,
    ]);
    SavingsAccount::factory()->create([
        'user_id' => $userA->id, 'account_type' => 'easy_access', 'is_isa' => false,
        'is_emergency_fund' => false, 'current_balance' => 50000,
        'joint_owner_id' => null, 'ownership_type' => 'individual', 'ownership_percentage' => 100,
    ]);
    expect($method->invoke($service, $userA->id)->id)->toBe($emergency->id);

    // Case 2: no emergency-fund account → largest non-ISA (sortByDesc fallback).
    $userB = User::factory()->create(['is_preview_user' => false]);
    SavingsAccount::factory()->create([
        'user_id' => $userB->id, 'account_type' => 'easy_access', 'is_isa' => false,
        'is_emergency_fund' => false, 'current_balance' => 4000,
        'joint_owner_id' => null, 'ownership_type' => 'individual', 'ownership_percentage' => 100,
    ]);
    $biggest = SavingsAccount::factory()->create([
        'user_id' => $userB->id, 'account_type' => 'easy_access', 'is_isa' => false,
        'is_emergency_fund' => false, 'current_balance' => 18000,
        'joint_owner_id' => null, 'ownership_type' => 'individual', 'ownership_percentage' => 100,
    ]);
    expect($method->invoke($service, $userB->id)->id)->toBe($biggest->id);

    // Case 3: non-existent userId → null (User::find null guard).
    expect($method->invoke($service, 999999))->toBeNull();
});

it('LifeEventAllocationService::getGoalAccountType picks the int-userId single-owner cash ISA (site 677)', function () {
    $user = User::factory()->create(['is_preview_user' => false]);
    $goal = Goal::factory()->create([
        'user_id' => $user->id,
        'goal_type' => 'emergency_fund',
        'assigned_module' => 'savings',
    ]);

    $isa = SavingsAccount::factory()->create([
        'user_id' => $user->id, 'account_type' => 'cash_isa', 'is_isa' => true,
        'current_balance' => 8000,
        'joint_owner_id' => null, 'ownership_type' => 'individual', 'ownership_percentage' => 100,
    ]);

    $service = app(LifeEventAllocationService::class);
    $reflection = new ReflectionClass($service);
    $method = $reflection->getMethod('getGoalAccountType');
    $method->setAccessible(true);

    $result = $method->invoke($service, $goal, $user->id);

    expect($result['account_type'])->toBe('savings_account');
    expect($result['account_id'])->toBe($isa->id);
    expect($result['label'])->toBe('Cash ISA');
});
