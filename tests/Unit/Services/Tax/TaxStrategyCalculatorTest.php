<?php

declare(strict_types=1);

use App\DataTransferObjects\TaxStrategyOutputDTO;
use App\DataTransferObjects\TaxStrategyOverridesDTO;
use App\Models\Investment\InvestmentAccount;
use App\Models\SavingsAccount;
use App\Models\TaxStrategyHouseholdInput;
use App\Models\User;
use App\Services\Tax\TaxStrategyCalculator;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(\Database\Seeders\TaxConfigurationSeeder::class);
});

describe('Path A — single user', function () {
    it('returns 8 user allowance positions, no household sections', function () {
        $user = User::factory()->create([
            'household_calculation_mode' => 'single',
            'annual_employment_income' => 50000,
            'marital_status' => 'single',
        ]);

        $output = app(TaxStrategyCalculator::class)->calculate($user);

        expect($output)->toBeInstanceOf(TaxStrategyOutputDTO::class);
        expect($output->calculationMode)->toBe('single');
        expect($output->userAllowances)->toHaveCount(8);
        expect($output->spouseAllowances)->toBeNull();
        expect($output->assetShiftingSuggestions)->toBe([]);
        expect($output->crossSpouseSuggestions)->toBe([]);

        $keys = array_column($output->userAllowances, 'key');
        expect($keys)->toContain(
            'personal_allowance',
            'savings_allowance',
            'starting_rate_for_savings',
            'marriage_allowance',
            'isa_allowance',
            'cgt_allowance',
            'dividend_allowance',
            'pension_annual_allowance',
        );

        // Every position must have status in {spring, violet, raspberry} (no amber)
        foreach ($output->userAllowances as $pos) {
            expect($pos['status'])->toBeIn(['spring', 'violet', 'raspberry']);
            expect($pos['owner'])->toBe('user');
        }
    });

    it('does not write to the database during calculate()', function () {
        $user = User::factory()->create(['household_calculation_mode' => 'single']);
        $stamp = $user->updated_at?->copy();

        app(TaxStrategyCalculator::class)->calculate($user);

        expect($user->fresh()->updated_at?->equalTo($stamp))->toBeTrue();
        expect(TaxStrategyHouseholdInput::count())->toBe(0);
    });
});

describe('Path B — dual_earner', function () {
    it('returns twin grids + cross-spouse suggestions, no asset-shifting', function () {
        $user = User::factory()->create([
            'household_calculation_mode' => 'dual_earner',
            'annual_employment_income' => 80000, // higher rate
            'marital_status' => 'married',
        ]);
        TaxStrategyHouseholdInput::create([
            'user_id' => $user->id,
            'spouse_annual_income' => 30000,
            'spouse_employment_status' => 'full_time',
            'spouse_isa_balance' => 5000,
            'spouse_psa_band' => 'basic',
        ]);

        $output = app(TaxStrategyCalculator::class)->calculate($user);

        expect($output->calculationMode)->toBe('dual_earner');
        expect($output->userAllowances)->toHaveCount(8);
        expect($output->spouseAllowances)->toHaveCount(8);
        expect($output->crossSpouseSuggestions)->not->toBe([]);
        expect($output->assetShiftingSuggestions)->toBe([]);

        // Spouse positions all owned by 'spouse'
        foreach ($output->spouseAllowances as $pos) {
            expect($pos['owner'])->toBe('spouse');
        }
    });
});

describe('Path C — single_earner_couple', function () {
    it('emits asset-shifting suggestions sized to spouse unused capacity', function () {
        $user = User::factory()->create([
            'household_calculation_mode' => 'single_earner_couple',
            'annual_employment_income' => 100000,
            'marital_status' => 'married',
            'marriage_allowance_eligible' => true,
        ]);
        TaxStrategyHouseholdInput::create([
            'user_id' => $user->id,
            'spouse_existing_isa_balance' => 0,
            'spouse_existing_savings_balance' => 0,
            'spouse_existing_investment_balance' => 0,
            'spouse_existing_dividend_holdings_value' => 0,
        ]);
        SavingsAccount::factory()->for($user)->create([
            'is_isa' => false,
            'current_balance' => 200000,
            'interest_rate' => 0.035,
        ]);

        $output = app(TaxStrategyCalculator::class)->calculate($user);

        expect($output->calculationMode)->toBe('single_earner_couple');
        expect($output->assetShiftingSuggestions)->not->toBe([]);

        // Savings-shift suggestion sized ≤ user's at-risk holdings
        $shift = collect($output->assetShiftingSuggestions)->firstWhere('type', 'savings_to_spouse');
        expect($shift)->not->toBeNull();
        expect($shift['suggested_transfer_amount'])->toBeLessThanOrEqual(200000);
        expect($shift['estimated_annual_tax_saved'])->toBeGreaterThan(0);

        // ISA top-up suggestion (spouse has £20k unused)
        $isa = collect($output->assetShiftingSuggestions)->firstWhere('type', 'isa_topup_spouse');
        expect($isa)->not->toBeNull();
        expect($isa['available_allowance'])->toBe(20000.0);
    });

    it('omits Marriage Allowance suggestion for higher-rate users (HMRC rule)', function () {
        $user = User::factory()->create([
            'household_calculation_mode' => 'single_earner_couple',
            'annual_employment_income' => 80000, // higher rate — MA NOT available
            'marital_status' => 'married',
            'marriage_allowance_eligible' => true,
        ]);
        TaxStrategyHouseholdInput::create(['user_id' => $user->id]);

        $output = app(TaxStrategyCalculator::class)->calculate($user);

        $ma = collect($output->assetShiftingSuggestions)->firstWhere('type', 'marriage_allowance_transfer');
        expect($ma)->toBeNull();
    });

    it('reduces savings-shift suggestion when spouse already has standalone savings', function () {
        $user = User::factory()->create([
            'household_calculation_mode' => 'single_earner_couple',
            'annual_employment_income' => 100000,
            'marriage_allowance_eligible' => true,
        ]);
        // Spouse already has £100k of savings — eats some of their PSA capacity
        TaxStrategyHouseholdInput::create([
            'user_id' => $user->id,
            'spouse_existing_savings_balance' => 100000,
        ]);
        SavingsAccount::factory()->for($user)->create([
            'is_isa' => false,
            'current_balance' => 600000,
            'interest_rate' => 0.035,
        ]);

        $output = app(TaxStrategyCalculator::class)->calculate($user);

        $shift = collect($output->assetShiftingSuggestions)->firstWhere('type', 'savings_to_spouse');
        // Spouse already absorbing ~£3,500/yr interest from their own £100k @ 3.5%
        // Remaining capacity ≈ £18,570 - £3,500 = £15,070
        // Translates to ~£430k more transferable @ 3.5% — but also bounded by user's £600k
        // Expectation: suggested transfer < 600,000 (capped by spouse's reduced capacity)
        expect($shift)->not->toBeNull();
        expect($shift['suggested_transfer_amount'])->toBeLessThan(600000);
    });
});

describe('benchmark', function () {
    it('runs in under 50ms for a representative single_earner_couple persona', function () {
        $user = User::factory()->create([
            'household_calculation_mode' => 'single_earner_couple',
            'annual_employment_income' => 100000,
            'marriage_allowance_eligible' => true,
        ]);
        TaxStrategyHouseholdInput::create([
            'user_id' => $user->id,
            'spouse_existing_isa_balance' => 5000,
        ]);
        SavingsAccount::factory()->count(3)->for($user)->create();

        // Warm caches once
        app(TaxStrategyCalculator::class)->calculate($user);

        $start = hrtime(true);
        app(TaxStrategyCalculator::class)->calculate($user);
        $elapsedMs = (hrtime(true) - $start) / 1_000_000;

        expect($elapsedMs)->toBeLessThan(50);
    });
});

describe('overrides applied in-memory', function () {
    it('applies pension_contribution_percent override without DB write', function () {
        $user = User::factory()->create([
            'household_calculation_mode' => 'single',
            'annual_employment_income' => 50000,
        ]);

        $base = app(TaxStrategyCalculator::class)->calculate($user);
        $overridden = app(TaxStrategyCalculator::class)->calculate(
            $user,
            new TaxStrategyOverridesDTO(pensionContributionPercent: 20)
        );

        $baseAa = collect($base->userAllowances)->firstWhere('key', 'pension_annual_allowance');
        $overAa = collect($overridden->userAllowances)->firstWhere('key', 'pension_annual_allowance');

        expect($overAa['used'])->toBeGreaterThan($baseAa['used']);
        // No DB write — fresh user has untouched contribution
        expect($user->fresh()->annual_employment_income)->toBe('50000.00');
    });
});
