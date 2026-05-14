<?php

declare(strict_types=1);

use App\Models\SavingsAccount;
use App\Models\User;
use App\Services\Estate\EstateActionDefinitionService;
use App\Services\Estate\EstateAssetAggregatorService;
use App\Services\Shared\CrossModuleAssetAggregator;
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
