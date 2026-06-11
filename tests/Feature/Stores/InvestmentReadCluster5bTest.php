<?php

declare(strict_types=1);

use App\Models\Investment\Holding;
use App\Models\Investment\InvestmentAccount;
use App\Models\User;
use App\Services\Investment\Performance\PerformanceAttributionAnalyzer;
use Database\Seeders\TaxConfigurationSeeder;
use Database\Seeders\TierConfigurationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(TaxConfigurationSeeder::class);
    $this->seed(TierConfigurationSeeder::class);
});

/*
 * SP1 Pass 6 PR 5b — Goals/ModelPortfolio/Performance reads through
 * InvestmentAccountStore. These tests guard that PerformanceAttributionAnalyzer
 * (the one reachable InvestmentAccount read in the cluster) now resolves accounts
 * via the store with its original primary-only semantics preserved.
 *
 * The Goals read (GoalProgressAnalyzer::calculateCurrentValue) is also routed
 * through the store, but its $goal->account_id branch is pre-existing dead code
 * (investment_goals has no account_id column), so it is not behaviourally testable.
 */

it('PerformanceAttributionAnalyzer reads the user accounts through the store and totals their holdings', function () {
    $user = User::factory()->create(['is_preview_user' => false]);

    $account = InvestmentAccount::factory()->create([
        'user_id' => $user->id,
        'ownership_type' => 'individual',
        'ownership_percentage' => 100,
    ]);
    Holding::factory()->forAccount($account)->create(['current_value' => 10000]);

    $result = app(PerformanceAttributionAnalyzer::class)->analyzePerformance($user);

    expect($result['success'])->toBeTrue()
        ->and($result['total_portfolio_value'])->toEqual(10000.0);
});

it('PerformanceAttributionAnalyzer is primary-only: excludes accounts where the user is only the joint owner', function () {
    $user = User::factory()->create(['is_preview_user' => false]);
    $other = User::factory()->create(['is_preview_user' => false]);

    // Primary account for $user — counted.
    $own = InvestmentAccount::factory()->create([
        'user_id' => $user->id,
        'ownership_type' => 'individual',
        'ownership_percentage' => 100,
    ]);
    Holding::factory()->forAccount($own)->create(['current_value' => 4000]);

    // Joint account where $user is the SECONDARY owner — primary-only semantics
    // exclude it (matches the original where('user_id') query).
    $jointSecondary = InvestmentAccount::factory()->create([
        'user_id' => $other->id,
        'joint_owner_id' => $user->id,
        'ownership_type' => 'joint',
        'ownership_percentage' => 50,
    ]);
    Holding::factory()->forAccount($jointSecondary)->create(['current_value' => 99999]);

    $result = app(PerformanceAttributionAnalyzer::class)->analyzePerformance($user);

    expect($result['success'])->toBeTrue()
        ->and($result['total_portfolio_value'])->toEqual(4000.0);
});

it('PerformanceAttributionAnalyzer returns a no-accounts result for a user with none', function () {
    $user = User::factory()->create(['is_preview_user' => false]);

    $result = app(PerformanceAttributionAnalyzer::class)->analyzePerformance($user);

    expect($result['success'])->toBeFalse();
});
