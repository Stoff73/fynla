<?php

declare(strict_types=1);

use App\Models\Investment\InvestmentAccount;
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

/**
 * bandRateFromIncome prices a marginal rate from an arbitrary income figure
 * without a User model — used by HouseholdFinancialContext to derive a
 * dual-earner spouse's rate from the household input's spouse_annual_income.
 */
describe('bandRateFromIncome', function () {
    it('returns basic-rate for income under £50,270', function () {
        expect($this->math->bandRateFromIncome(30000.0))->toBe(0.20);
    });

    it('returns higher-rate for income between £50,270 and £125,140', function () {
        expect($this->math->bandRateFromIncome(65000.0))->toBe(0.40);
    });

    it('returns additional-rate for income above £125,140', function () {
        expect($this->math->bandRateFromIncome(130000.0))->toBe(0.45);
    });

    it('agrees with bandRateFor when the user has only employment income', function () {
        $user = User::factory()->create([
            'annual_employment_income' => 50000, // no dividends, no savings
        ]);

        expect($this->math->bandRateFromIncome(50000.0))->toBe($this->math->bandRateFor($user));
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

    // Regression: Stocks & Shares / investment ISAs subscribe against the SAME
    // £20k allowance but live on investment_accounts (account_type 'isa') under
    // isa_subscription_current_year. Pre-fix the helper read only savings_accounts,
    // so an S&S-ISA subscriber's allowance was over-stated and ISA top-up
    // strategies recommended wrapping more cash than they could still subscribe.
    it('counts a stocks & shares (investment) ISA subscription against the same allowance', function () {
        $user = User::factory()->create();
        InvestmentAccount::factory()->for($user)->create([
            'account_type' => 'isa',
            'isa_type' => 'stocks_and_shares',
            'isa_subscription_current_year' => 5000,
        ]);

        expect($this->math->estimateIsaSubscriptionsThisYear($user))->toBe(5000.0);
    });

    it('sums cash ISA and investment ISA subscriptions toward the one allowance', function () {
        $user = User::factory()->create();
        SavingsAccount::factory()->for($user)->create([
            'is_isa' => true,
            'isa_subscription_amount' => 3000,
            'isa_subscription_year' => app(TaxConfigService::class)->getTaxYear(),
            'current_balance' => 3000,
            'created_at' => now(),
        ]);
        InvestmentAccount::factory()->for($user)->create([
            'account_type' => 'isa',
            'isa_subscription_current_year' => 5000,
        ]);

        expect($this->math->estimateIsaSubscriptionsThisYear($user))->toBe(8000.0);
    });
});

/**
 * Issue log 2026-07-23 #21 — joint-account interest was attributed in FULL to
 * the primary owner (and not at all to the joint owner), overstating the
 * primary's Personal Savings Allowance use. HMRC ITA 2007 s.836 splits
 * spouse/civil-partner joint-account interest 50/50 by default; the app's
 * single-record joint architecture stores the primary's share in
 * ownership_percentage. Attribution must follow CalculatesOwnershipShare —
 * the same rule net worth already applies.
 */
describe('estimateAnnualInterest — joint ownership attribution', function () {
    it('attributes only the ownership share of a joint account to the primary owner', function () {
        $user = User::factory()->create();
        // Campaign shape: spouse captured as household input, not a User —
        // joint_owner_id stays null but ownership_type/percentage are set.
        SavingsAccount::factory()->for($user)->create([
            'is_isa' => false,
            'current_balance' => 12000,
            'interest_rate' => 4.2,
            'ownership_type' => 'joint',
            'ownership_percentage' => 50,
            'joint_owner_id' => null,
        ]);

        expect($this->math->estimateAnnualInterest($user))->toEqualWithDelta(252.0, 0.001);
    });

    it('attributes the complement share to the joint-owner-side user', function () {
        $user = User::factory()->create();
        $spouse = User::factory()->create();
        SavingsAccount::factory()->create([
            'user_id' => $spouse->id,
            'joint_owner_id' => $user->id,
            'is_isa' => false,
            'current_balance' => 100000,
            'interest_rate' => 5.0,
            'ownership_type' => 'joint',
            'ownership_percentage' => 50,
        ]);

        expect($this->math->estimateAnnualInterest($user))->toEqualWithDelta(2500.0, 0.001);
        expect($this->math->estimateAnnualInterest($spouse))->toEqualWithDelta(2500.0, 0.001);
    });

    it('respects a non-50 ownership percentage on both sides', function () {
        $user = User::factory()->create();
        $spouse = User::factory()->create();
        SavingsAccount::factory()->for($user)->create([
            'joint_owner_id' => $spouse->id,
            'is_isa' => false,
            'current_balance' => 10000,
            'interest_rate' => 4.0,
            'ownership_type' => 'joint',
            'ownership_percentage' => 70,
        ]);

        expect($this->math->estimateAnnualInterest($user))->toEqualWithDelta(280.0, 0.001);
        expect($this->math->estimateAnnualInterest($spouse))->toEqualWithDelta(120.0, 0.001);
    });

    it('leaves individual accounts at full value', function () {
        $user = User::factory()->create();
        SavingsAccount::factory()->for($user)->create([
            'is_isa' => false,
            'current_balance' => 10000,
            'interest_rate' => 4.0,
            'ownership_type' => 'individual',
            'ownership_percentage' => 100,
        ]);

        expect($this->math->estimateAnnualInterest($user))->toEqualWithDelta(400.0, 0.001);
    });
});

describe('estimateSpouseJointInterest', function () {
    it('returns the other owner share of shared non-ISA accounts', function () {
        $user = User::factory()->create();
        SavingsAccount::factory()->for($user)->create([
            'is_isa' => false,
            'current_balance' => 12000,
            'interest_rate' => 4.2,
            'ownership_type' => 'joint',
            'ownership_percentage' => 50,
            'joint_owner_id' => null,
        ]);

        expect($this->math->estimateSpouseJointInterest($user))->toEqualWithDelta(252.0, 0.001);
    });

    it('returns zero for individual-only holdings', function () {
        $user = User::factory()->create();
        SavingsAccount::factory()->for($user)->create([
            'is_isa' => false,
            'current_balance' => 50000,
            'interest_rate' => 4.0,
            'ownership_type' => 'individual',
            'ownership_percentage' => 100,
        ]);

        expect($this->math->estimateSpouseJointInterest($user))->toBe(0.0);
    });

    it('ignores ISA accounts even when marked shared', function () {
        $user = User::factory()->create();
        SavingsAccount::factory()->for($user)->create([
            'is_isa' => true,
            'current_balance' => 20000,
            'interest_rate' => 5.0,
            'ownership_type' => 'joint',
            'ownership_percentage' => 50,
        ]);

        expect($this->math->estimateSpouseJointInterest($user))->toBe(0.0);
    });
});
