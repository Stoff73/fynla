<?php

declare(strict_types=1);

use App\Models\SavingsAccount;
use App\Models\User;
use App\Services\Tax\TaxStrategyMath;
use App\Services\TaxConfigService;
use Database\Seeders\TaxConfigurationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(TaxConfigurationSeeder::class);
    $this->math = app(TaxStrategyMath::class);
});

// Step 0 verdict: isa_subscription_year is stored as 'YYYY/YY' (slash format).
// OnboardingService writes $this->taxConfig->getTaxYear() → '2026/27'.
// The SavingsAccountFactory::isa() state incorrectly uses '2025-26' (dash) —
// tests here use the real stored format from TaxConfigService::getTaxYear().

describe('estimateIsaSubscriptionsThisYear — captured vs proxy', function () {

    it('prefers captured isa_subscription_amount for the current tax year', function () {
        $user = User::factory()->create();
        $taxYear = app(TaxConfigService::class)->getTaxYear(); // '2026/27'

        SavingsAccount::factory()->create([
            'user_id' => $user->id,
            'is_isa' => true,
            'current_balance' => 19000,
            'isa_subscription_year' => $taxYear,
            'isa_subscription_amount' => 100,
            'created_at' => now()->subYears(3), // proxy would say £0 — captured says £100
        ]);

        expect($this->math->estimateIsaSubscriptionsThisYear($user->fresh()))
            ->toBe(100.0);
    });

    it('falls back to the created-this-tax-year proxy when no subscription amounts are captured', function () {
        $user = User::factory()->create();

        SavingsAccount::factory()->create([
            'user_id' => $user->id,
            'is_isa' => true,
            'current_balance' => 5000,
            'isa_subscription_year' => null,
            'isa_subscription_amount' => null,
            'created_at' => now(), // opened this tax year → proxy counts balance
        ]);

        expect($this->math->estimateIsaSubscriptionsThisYear($user->fresh()))
            ->toBe(5000.0);
    });

    it('sums captured amounts across multiple ISAs in the current tax year', function () {
        $user = User::factory()->create();
        $taxYear = app(TaxConfigService::class)->getTaxYear();

        SavingsAccount::factory()->create([
            'user_id' => $user->id,
            'is_isa' => true,
            'current_balance' => 10000,
            'isa_subscription_year' => $taxYear,
            'isa_subscription_amount' => 3000,
            'created_at' => now()->subYears(2),
        ]);
        SavingsAccount::factory()->create([
            'user_id' => $user->id,
            'is_isa' => true,
            'current_balance' => 5000,
            'isa_subscription_year' => $taxYear,
            'isa_subscription_amount' => 2000,
            'created_at' => now()->subYears(1),
        ]);

        expect($this->math->estimateIsaSubscriptionsThisYear($user->fresh()))
            ->toBe(5000.0);
    });

    it('ignores captured amounts from a prior tax year and falls back to proxy', function () {
        $user = User::factory()->create();

        SavingsAccount::factory()->create([
            'user_id' => $user->id,
            'is_isa' => true,
            'current_balance' => 8000,
            'isa_subscription_year' => '2025/26', // prior year — must not count
            'isa_subscription_amount' => 7500,
            'created_at' => now(), // proxy would count this
        ]);

        // Prior-year captured amount must NOT be returned; proxy returns balance (8000).
        expect($this->math->estimateIsaSubscriptionsThisYear($user->fresh()))
            ->toBe(8000.0);
    });
});
