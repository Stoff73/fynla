<?php

declare(strict_types=1);

use App\Models\Investment\InvestmentAccount;
use App\Models\SavingsAccount;
use App\Models\User;
use App\Services\Savings\ISATracker;
use App\Services\Tax\TaxStrategyMath;
use App\Services\TaxConfigService;
use Database\Seeders\TaxConfigurationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/*
 * One rule for "ISA allowance used this year" (CSJ 2026-10-01; CSJTODO "one
 * rule, with the year kept right on each payment"): TaxStrategyMath reads
 * ISATracker, so the Tax Strategy tile, the plan, the Savings page and every
 * engine give one figure. Only amounts recorded for this tax year count; a
 * balance alone is not a subscription (it can be a transfer in, which does not
 * use the allowance).
 */
beforeEach(function () {
    $this->seed(TaxConfigurationSeeder::class);
    $this->math = app(TaxStrategyMath::class);
    $this->taxYear = app(TaxConfigService::class)->getTaxYear();
});

it('counts what was paid in this tax year, from cash and stocks and shares ISAs', function () {
    $user = User::factory()->create();
    SavingsAccount::factory()->create([
        'user_id' => $user->id, 'is_isa' => true, 'account_type' => 'cash_isa', 'current_balance' => 19000,
        'isa_subscription_year' => $this->taxYear, 'isa_subscription_amount' => 3000,
    ]);
    InvestmentAccount::factory()->create([
        'user_id' => $user->id, 'account_type' => 'isa', 'current_value' => 40000,
        'isa_subscription_current_year' => 5000, 'tax_year' => $this->taxYear,
    ]);

    expect($this->math->estimateIsaSubscriptionsThisYear($user->fresh()))->toBe(8000.0)
        ->and($this->math->estimateIsaSubscriptionsThisYear($user->fresh()))
        ->toBe((float) app(ISATracker::class)->usedThisTaxYear($user->fresh())['total_used']);
});

it('does not count last year\'s subscriptions', function () {
    $user = User::factory()->create();
    SavingsAccount::factory()->create([
        'user_id' => $user->id, 'is_isa' => true, 'account_type' => 'cash_isa', 'current_balance' => 8000,
        'isa_subscription_year' => '2025/26', 'isa_subscription_amount' => 7500,
    ]);
    InvestmentAccount::factory()->create([
        'user_id' => $user->id, 'account_type' => 'isa', 'current_value' => 20000,
        'isa_subscription_current_year' => 20000, 'tax_year' => '2025/26',
    ]);

    expect($this->math->estimateIsaSubscriptionsThisYear($user->fresh()))->toBe(0.0);
});

it('does not treat the balance of an ISA opened this year as a subscription', function () {
    $user = User::factory()->create();
    SavingsAccount::factory()->create([
        'user_id' => $user->id, 'is_isa' => true, 'account_type' => 'cash_isa', 'current_balance' => 5000,
        'isa_subscription_year' => null, 'isa_subscription_amount' => null, 'created_at' => now(),
    ]);

    expect($this->math->estimateIsaSubscriptionsThisYear($user->fresh()))->toBe(0.0);
});

it('leaves a Junior ISA out of the holder\'s allowance', function () {
    $user = User::factory()->create();
    SavingsAccount::factory()->create([
        'user_id' => $user->id, 'is_isa' => true, 'account_type' => 'junior_isa', 'current_balance' => 4000,
        'isa_subscription_year' => $this->taxYear, 'isa_subscription_amount' => 4000,
    ]);

    expect($this->math->estimateIsaSubscriptionsThisYear($user->fresh()))->toBe(0.0);
});

it('stamps the current tax year on a subscription written without one', function () {
    $data = \App\Services\Stores\Normalisers\InvestmentAccountNormaliser::fromForm([
        'account_type' => 'isa', 'current_value' => 1000, 'isa_subscription_current_year' => 1000,
    ], User::factory()->create());

    expect($data['tax_year'])->toBe($this->taxYear);
});
