<?php

declare(strict_types=1);

use App\Models\SavingsAccount;
use App\Models\User;
use App\Services\Tax\TaxStrategyMath;
use Database\Seeders\TaxConfigurationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(TaxConfigurationSeeder::class);
    $this->math = app(TaxStrategyMath::class);
});

/**
 * Regression for P0.6 — estimateIsaSubscriptionsThisYear must only count ISAs
 * opened in the current tax year. Pre-fix, the helper summed every ISA
 * balance the user held, so a £25k legacy-ISA holder appeared "fully
 * subscribed" against the £20k annual cap and missed legitimate top-up,
 * Lifetime ISA, and Bed-and-ISA suggestions. Captures the £75k smoke-test
 * defect surfaced on 2026-04-30.
 */
/**
 * M11 follow-up regression — bandRateFor() must derive its band from TOTAL
 * taxable income (employment + dividends + savings interest), not employment
 * alone. Pre-fix a £45k employee with £20k of dividend income was returned
 * 0.20 (basic-rate marginal) when HMRC would tax additional savings interest
 * at 0.40. Affects IsaTopUp, TaperedAA, PensionAACarryForward marginal rates.
 */
describe('bandRateFor', function () {
    it('returns higher-rate marginal when dividends push total taxable income above basic-rate', function () {
        $user = User::factory()->create([
            'annual_employment_income' => 45000, // basic-by-employment
            'annual_dividend_income' => 20000,   // pushes total to £65k → higher
        ]);

        expect($this->math->bandRateFor($user))->toBe(0.40);
    });

    it('returns additional-rate marginal when employment+dividends crosses £125,140', function () {
        $user = User::factory()->create([
            'annual_employment_income' => 100000,
            'annual_dividend_income' => 30000, // total £130k > £125,140
        ]);

        expect($this->math->bandRateFor($user))->toBe(0.45);
    });

    it('still returns basic-rate when total taxable income stays under £50,270', function () {
        $user = User::factory()->create([
            'annual_employment_income' => 30000,
            'annual_dividend_income' => 5000, // total £35k → basic
        ]);

        expect($this->math->bandRateFor($user))->toBe(0.20);
    });
});

describe('estimateIsaSubscriptionsThisYear', function () {
    it('counts ISAs opened in the current tax year', function () {
        $user = User::factory()->create();
        SavingsAccount::factory()->for($user)->create([
            'is_isa' => true,
            'current_balance' => 5000,
            'created_at' => now(), // current tax year
        ]);

        expect($this->math->estimateIsaSubscriptionsThisYear($user))->toBe(5000.0);
    });

    it('excludes ISAs opened before the current tax year', function () {
        $user = User::factory()->create();
        SavingsAccount::factory()->for($user)->create([
            'is_isa' => true,
            'current_balance' => 25000,
            'created_at' => now()->subYears(2), // legacy ISA
        ]);

        expect($this->math->estimateIsaSubscriptionsThisYear($user))->toBe(0.0);
    });

    it('counts only the current-year subset when the user has a mix', function () {
        $user = User::factory()->create();
        SavingsAccount::factory()->for($user)->create([
            'is_isa' => true,
            'current_balance' => 25000,
            'created_at' => now()->subYears(2), // legacy
        ]);
        SavingsAccount::factory()->for($user)->create([
            'is_isa' => true,
            'current_balance' => 8000,
            'created_at' => now(), // current
        ]);
        // Non-ISA must never be counted regardless of created_at
        SavingsAccount::factory()->for($user)->create([
            'is_isa' => false,
            'current_balance' => 50000,
            'created_at' => now(),
        ]);

        expect($this->math->estimateIsaSubscriptionsThisYear($user))->toBe(8000.0);
    });
});
