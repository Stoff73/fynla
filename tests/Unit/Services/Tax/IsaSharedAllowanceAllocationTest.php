<?php

declare(strict_types=1);

use App\Models\Investment\Holding;
use App\Models\Investment\InvestmentAccount;
use App\Models\SavingsAccount;
use App\Models\TaxStrategyHouseholdInput;
use App\Models\User;
use App\Services\Tax\TaxStrategyCalculator;
use App\Services\TaxConfigService;
use Database\Seeders\TaxConfigurationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(TaxConfigurationSeeder::class);
});

/**
 * All adult ISA types share ONE overall annual allowance per person per tax
 * year — cash ISA top-ups (isa_topup_vs_psa), Bed & ISA proceeds
 * (bed_and_isa) and Lifetime ISA contributions (lifetime_isa) all draw from
 * the same £20,000 pool. The calculator must allocate the pool greedily by
 * annual saving: the highest-saving strategy keeps its full sizing, each
 * subsequent consumer is re-evaluated against the remaining capacity, and a
 * consumer left with no usable capacity is excluded from the combined total
 * and carries a note.
 */
describe('shared ISA allowance allocation across strategies', function () {
    it('constrains the lower-saving consumer to the pool remaining after the winner', function () {
        // £60k higher-rate user (PSA £500), age 30 (Lifetime ISA eligible),
        // £50k of non-ISA cash at 4% → £2,000 interest, well above PSA.
        $user = User::factory()->create([
            'household_calculation_mode' => 'single',
            'annual_employment_income' => 60000,
            'date_of_birth' => now()->subYears(30),
            'marital_status' => 'single',
        ]);
        SavingsAccount::factory()->for($user)->create([
            'is_isa' => false,
            'current_balance' => 50000,
            'interest_rate' => 0.04,
        ]);

        $output = app(TaxStrategyCalculator::class)->calculate($user);
        $recs = collect($output->recommendations);

        $lisa = $recs->firstWhere('type', 'lifetime_isa');
        $topup = $recs->firstWhere('type', 'isa_topup_vs_psa');

        // Winner (Lifetime ISA, £1,000 bonus > £320 wrap saving) keeps its
        // full sizing — and its own £4,000 sub-limit caps its pool draw.
        expect($lisa)->not->toBeNull()
            ->and($lisa['estimated_annual_tax_saved'])->toBe(1000.0)
            ->and($lisa['suggested_contribution'])->toBe(4000.0)
            ->and($lisa)->not->toHaveKey('isa_allowance_excluded');

        // Loser is re-evaluated against the remaining £16,000 — its own maths
        // produces the honest saving on the smaller amount:
        // £16,000 × 4% × 40% = £256, not £20,000 × 4% × 40% = £320.
        expect($topup)->not->toBeNull()
            ->and($topup['estimated_annual_tax_saved'])->toBe(256.0)
            ->and($topup['suggested_transfer_amount'])->toBe(16000.0)
            ->and($topup)->toHaveKey('isa_allowance_note')
            ->and($topup['isa_allowance_note'])->toContain('lifetime_isa')
            ->and($topup)->not->toHaveKey('isa_allowance_excluded');
    });

    it('never lets combined pool draws exceed the overall ISA allowance', function () {
        $user = User::factory()->create([
            'household_calculation_mode' => 'single',
            'annual_employment_income' => 60000,
            'date_of_birth' => now()->subYears(30),
            'marital_status' => 'single',
        ]);
        SavingsAccount::factory()->for($user)->create([
            'is_isa' => false,
            'current_balance' => 50000,
            'interest_rate' => 0.04,
        ]);
        $gia = InvestmentAccount::factory()->for($user)->create(['account_type' => 'gia']);
        Holding::factory()->forAccount($gia)->create([
            'quantity' => 100,
            'purchase_price' => 50,
            'current_price' => 100,
            'cost_basis' => 5000,
            'current_value' => 10000,
        ]);

        $output = app(TaxStrategyCalculator::class)->calculate($user);
        $recs = collect($output->recommendations);

        $lisa = $recs->firstWhere('type', 'lifetime_isa');
        $bed = $recs->firstWhere('type', 'bed_and_isa');
        $topup = $recs->firstWhere('type', 'isa_topup_vs_psa');

        // Ranking by saving: lifetime_isa £1,000 > bed_and_isa £720 > isa_topup £320.
        // lifetime_isa draws £4,000; bed_and_isa wanted £6,000 ≤ £16,000 remaining
        // so it keeps its pass-1 sizing untouched; isa_topup gets the final £10,000.
        expect($lisa['estimated_annual_tax_saved'])->toBe(1000.0)
            ->and($lisa['suggested_contribution'])->toBe(4000.0);

        expect($bed)->not->toBeNull()
            ->and($bed['estimated_annual_tax_saved'])->toBe(720.0)
            ->and($bed['estimated_proceeds_to_transfer'])->toBe(6000.0)
            ->and($bed)->not->toHaveKey('isa_allowance_note');

        expect($topup)->not->toBeNull()
            ->and($topup['estimated_annual_tax_saved'])->toBe(160.0) // £10,000 × 4% × 40%
            ->and($topup['suggested_transfer_amount'])->toBe(10000.0)
            ->and($topup)->toHaveKey('isa_allowance_note');

        $isaAllowance = (float) (app(TaxConfigService::class)->getISAAllowances()['annual_allowance'] ?? 20000);
        $totalDraw = $lisa['suggested_contribution']
            + $bed['estimated_proceeds_to_transfer']
            + $topup['suggested_transfer_amount'];
        expect($totalDraw)->toBeLessThanOrEqual($isaAllowance);
    });

    it('excludes a consumer whose remaining pool capacity falls below its own floor', function () {
        // £15,500 already subscribed this tax year → only £4,500 of pool left.
        // lifetime_isa (£1,000 saving) wins and draws £4,000, leaving £500 —
        // below isa_topup's £1,000 minimum-transfer floor → excluded + noted.
        $user = User::factory()->create([
            'household_calculation_mode' => 'single',
            'annual_employment_income' => 60000,
            'date_of_birth' => now()->subYears(30),
            'marital_status' => 'single',
        ]);
        SavingsAccount::factory()->for($user)->create([
            'is_isa' => false,
            'current_balance' => 50000,
            'interest_rate' => 0.04,
        ]);
        SavingsAccount::factory()->isa()->for($user)->create([
            'current_balance' => 15500,
            'isa_subscription_year' => app(TaxConfigService::class)->getTaxYear(),
            'isa_subscription_amount' => 15500,
        ]);

        $output = app(TaxStrategyCalculator::class)->calculate($user);
        $recs = collect($output->recommendations);

        $lisa = $recs->firstWhere('type', 'lifetime_isa');
        $topup = $recs->firstWhere('type', 'isa_topup_vs_psa');

        expect($lisa)->not->toBeNull()
            ->and($lisa['estimated_annual_tax_saved'])->toBe(1000.0);

        // Kept in the plan (mirrors the conflict mechanism) but flagged so the
        // composer leaves it out of combined_annual_saving.
        expect($topup)->not->toBeNull()
            ->and($topup['isa_allowance_excluded'])->toBeTrue()
            ->and($topup['isa_allowance_note'])->toContain('lifetime_isa')
            ->and($topup['isa_allowance_note'])->toContain('same ISA allowance');
    });

    it('changes nothing when only one ISA-consuming strategy fires', function () {
        // Age 45 → no Lifetime ISA; no holdings → no Bed & ISA. Only the cash
        // wrap fires and must keep its exact pre-allocation shape and figures.
        $user = User::factory()->create([
            'household_calculation_mode' => 'single',
            'annual_employment_income' => 60000,
            'date_of_birth' => now()->subYears(45),
            'marital_status' => 'single',
        ]);
        SavingsAccount::factory()->for($user)->create([
            'is_isa' => false,
            'current_balance' => 50000,
            'interest_rate' => 0.04,
        ]);

        $output = app(TaxStrategyCalculator::class)->calculate($user);
        $topup = collect($output->recommendations)->firstWhere('type', 'isa_topup_vs_psa');

        expect($topup)->not->toBeNull()
            ->and($topup['estimated_annual_tax_saved'])->toBe(320.0) // full £20,000 × 4% × 40%
            ->and($topup['suggested_transfer_amount'])->toBe(20000.0)
            ->and($topup)->not->toHaveKey('isa_allowance_note')
            ->and($topup)->not->toHaveKey('isa_allowance_excluded');
    });

    it('leaves the spouse ISA pool untouched by the user-pool allocation', function () {
        // single_earner_couple: the user-pool consumers (lifetime_isa +
        // isa_topup_vs_psa) share the user's allowance; the spouse-side
        // isa_topup_spouse draws on the SPOUSE's own separate pool and must
        // still show the full spouse allowance.
        $user = User::factory()->create([
            'household_calculation_mode' => 'single_earner_couple',
            'annual_employment_income' => 60000,
            'date_of_birth' => now()->subYears(30),
            'marital_status' => 'married',
        ]);
        TaxStrategyHouseholdInput::create([
            'user_id' => $user->id,
            'spouse_existing_savings_balance' => 0,
            'spouse_existing_isa_balance' => 0,
        ]);
        SavingsAccount::factory()->for($user)->create([
            'is_isa' => false,
            'current_balance' => 50000,
            'interest_rate' => 0.04,
        ]);

        $output = app(TaxStrategyCalculator::class)->calculate($user);
        $recs = collect($output->recommendations);

        // User pool allocated between lifetime_isa and isa_topup_vs_psa.
        expect($recs->firstWhere('type', 'lifetime_isa'))->not->toBeNull()
            ->and($recs->firstWhere('type', 'isa_topup_vs_psa'))->not->toBeNull();

        $spouseTopup = $recs->firstWhere('type', 'isa_topup_spouse');
        $isaAllowance = (float) (app(TaxConfigService::class)->getISAAllowances()['annual_allowance'] ?? 20000);
        expect($spouseTopup)->not->toBeNull()
            ->and($spouseTopup['available_allowance'])->toBe($isaAllowance)
            ->and($spouseTopup)->not->toHaveKey('isa_allowance_note')
            ->and($spouseTopup)->not->toHaveKey('isa_allowance_excluded');
    });
});
