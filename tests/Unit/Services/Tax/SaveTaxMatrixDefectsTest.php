<?php

declare(strict_types=1);

use App\Models\DCPension;
use App\Models\SavingsAccount;
use App\Models\SpousePermission;
use App\Models\TaxStrategyHouseholdInput;
use App\Models\User;
use App\Services\Coordination\ComposedTaxPlanService;
use App\Services\Tax\TaxStrategyCalculator;
use App\Services\Tax\TaxStrategyMath;
use App\Services\TaxConfigService;
use Database\Seeders\TaxActionDefinitionSeeder;
use Database\Seeders\TaxConfigurationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * Defects from the SaveTax scenario matrix and couple run, 2026-09-29
 * (docs/testing/2026-09-29-savetax-scenario-matrix.md E6, E7;
 * docs/testing/2026-09-29-prod-savetax-mobile-couple.md M2), and the E1
 * double count re-measured on dev the same day.
 */
uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(TaxConfigurationSeeder::class);
});

function matrixRecs(User $user): array
{
    return collect(app(TaxStrategyCalculator::class)->calculate($user)->recommendations)->keyBy('type')->all();
}

function matrixSaver(float $income, float $balance, array $attrs = []): User
{
    $user = User::factory()->create(array_merge([
        'household_calculation_mode' => 'single',
        'employment_status' => 'employed',
        'annual_employment_income' => $income,
        'marital_status' => 'single',
        'date_of_birth' => now()->subYears(44)->toDateString(),
    ], $attrs));
    SavingsAccount::factory()->for($user)->create([
        'current_balance' => $balance, 'interest_rate' => 4.5, 'is_isa' => false,
        'ownership_type' => 'individual', 'joint_owner_id' => null,
    ]);

    return $user;
}

describe('E6 — ISA wrap copy when the ISA allowance caps it', function () {
    it('states the interest above the Savings Allowance and, separately, the part the wrap shelters', function () {
        $user = matrixSaver(60000, 50000);
        $math = app(TaxStrategyMath::class);
        $allowance = (float) app(TaxConfigService::class)->getISAAllowances()['annual_allowance'];

        $interest = $math->estimateAnnualInterest($user);
        $above = $interest - $math->psaForBand('higher');
        $sheltered = $allowance * 0.045;
        // The fixture only probes the capped branch if the allowance really caps it.
        expect($sheltered)->toBeLessThan($above);

        $rec = matrixRecs($user)['isa_topup_vs_psa'];

        expect($rec['taxable_interest_sheltered'])->toBe(round($sheltered, 2))
            ->and($rec['interest_above_savings_allowance'])->toBe(round($above, 2))
            ->and($rec['description'])->toContain('of which £'.number_format($above, 2).' is above your')
            ->and($rec['description'])->toContain('shelters £'.number_format($sheltered, 2).' of that taxable interest')
            ->and($rec['description'])->not->toContain('of which £'.number_format($sheltered, 2).' is above');
    });

    it('keeps the plain wording when the wrap shelters all of it', function () {
        $user = matrixSaver(60000, 30000);
        $math = app(TaxStrategyMath::class);
        $above = $math->estimateAnnualInterest($user) - $math->psaForBand('higher');

        $rec = matrixRecs($user)['isa_topup_vs_psa'];

        expect($rec['taxable_interest_sheltered'])->toBe(round($above, 2))
            ->and($rec['description'])->toContain('of which £'.number_format($above, 2).' is above your')
            ->and($rec['description'])->toContain('shelters that taxable interest');
    });
});

describe('E7 — pension relief route for a self-employed user', function () {
    it('does not tell someone with no employment income that relief comes through their pay', function () {
        $user = matrixSaver(0, 0, [
            'employment_status' => 'self_employed',
            'annual_self_employment_income' => 55000,
        ]);

        $rec = matrixRecs($user)['pension_tax_relief'];

        expect($rec['tax_band'])->toBe('higher')
            ->and($rec['description'])->not->toContain('through your pay')
            ->and($rec['description'])->not->toContain('workplace scheme')
            ->and($rec['description'])->toContain('Self Assessment tax return');
    });

    it('still names the workplace route for an employee', function () {
        $rec = matrixRecs(matrixSaver(60000, 0))['pension_tax_relief'];

        expect($rec['description'])->toContain('A workplace scheme gives the relief through your pay');
    });
});

describe('M2 — a linked spouse\'s allowances come from their own records', function () {
    beforeEach(function () {
        $this->user = matrixSaver(72000, 15000, [
            'household_calculation_mode' => 'dual_earner',
            'marital_status' => 'married',
        ]);
        $this->spouse = User::factory()->create([
            'household_calculation_mode' => 'dual_earner',
            'employment_status' => 'employed',
            'annual_employment_income' => 32000,
            'marital_status' => 'married',
            'date_of_birth' => now()->subYears(41)->toDateString(),
        ]);
        DCPension::factory()->for($this->spouse)->create([
            'scheme_type' => 'workplace',
            'monthly_contribution_amount' => 600, // £7,200 a year, as in the couple run
            'salary_sacrifice' => false,
            'employer_ni_rebate_pct' => null,
        ]);
        SavingsAccount::factory()->for($this->spouse)->create([
            'current_balance' => 4000, 'interest_rate' => 3.5, 'is_isa' => false,
            'ownership_type' => 'individual', 'joint_owner_id' => null,
        ]);
        // What the primary user told the campaign about the spouse.
        TaxStrategyHouseholdInput::create(['user_id' => $this->user->id, 'spouse_annual_income' => 32000]);

        $this->user->update(['spouse_id' => $this->spouse->id]);
        $this->spouse->update(['spouse_id' => $this->user->id]);
    });

    it('shows the linked spouse\'s pension and savings use when they share data', function () {
        SpousePermission::create([
            'user_id' => $this->user->id, 'spouse_id' => $this->spouse->id,
            'status' => 'accepted', 'requested_at' => now(), 'responded_at' => now(),
        ]);
        $math = app(TaxStrategyMath::class);
        $spouse = $this->spouse->fresh();

        $grid = collect(app(TaxStrategyCalculator::class)->calculate($this->user->fresh())->spouseAllowances)->keyBy('key');

        expect($grid['pension_annual_allowance']['known'])->toBeTrue()
            // Earnings below the Annual Allowance: the tile is the FA 2004 s190
            // limit measured against their own gross contributions.
            ->and($grid['pension_annual_allowance']['limit_basis'])->toBe('relief')
            ->and($grid['pension_annual_allowance']['used'])->toBe(round($math->grossEmployeePensionContributions($spouse), 2))
            ->and($grid['pension_annual_allowance']['used'])->toBe(7200.0)
            ->and($grid['savings_allowance']['known'])->toBeTrue()
            ->and($grid['savings_allowance']['used'])->toBe(round($math->estimateAnnualInterest($spouse), 2))
            ->and($grid->pluck('owner')->unique()->values()->all())->toBe(['spouse'])
            ->and($grid->pluck('label')->implode(' '))->not->toContain('your earnings')
            ->and($grid['marriage_allowance']['owner'])->toBe('spouse');
    });

    it('stays unconfirmed when the spouse has declined data sharing', function () {
        // A reciprocal link with no permission row is honoured as consent
        // (User::hasAcceptedSpousePermission), so the negative needs a row.
        SpousePermission::create([
            'user_id' => $this->user->id, 'spouse_id' => $this->spouse->id,
            'status' => 'rejected', 'requested_at' => now(), 'responded_at' => now(),
        ]);

        $grid = collect(app(TaxStrategyCalculator::class)->calculate($this->user->fresh())->spouseAllowances)->keyBy('key');

        expect($grid['pension_annual_allowance']['known'])->toBeFalse();
    });
});

describe('E1 — the tax-trap pension and the savings gift are not counted twice', function () {
    // The live £110k household: 5% + 5% workplace pension, £50,000 at 4.5% in
    // sole name, a non-earning spouse confirmed to hold no savings.
    function trapHousehold(bool $withSavings = true, float $extraPension = 0.0): User
    {
        $user = User::factory()->create([
            'household_calculation_mode' => 'single_earner_couple',
            'employment_status' => 'employed',
            'annual_employment_income' => 110000,
            'marital_status' => 'married',
            'date_of_birth' => '1980-06-20',
        ]);
        TaxStrategyHouseholdInput::create([
            'user_id' => $user->id,
            'spouse_existing_savings_balance' => 0,
            'spouse_existing_isa_balance' => 0,
            'spouse_existing_investment_balance' => 0,
            'spouse_existing_dividend_holdings_value' => 0,
            'spouse_existing_pension_balance' => 0,
        ]);
        if ($withSavings) {
            SavingsAccount::factory()->for($user)->create([
                'current_balance' => 50000, 'interest_rate' => 4.5, 'is_isa' => false,
                'ownership_type' => 'individual', 'joint_owner_id' => null,
            ]);
        }
        DCPension::factory()->for($user)->create([
            'scheme_type' => 'workplace', 'current_fund_value' => 160000,
            'employee_contribution_percent' => 5, 'employer_contribution_percent' => 5,
            'monthly_contribution_amount' => (110000 * 0.05 + $extraPension) / 12, 'annual_salary' => 110000,
            'salary_sacrifice' => false, 'employer_ni_rebate_pct' => null,
        ]);

        return $user->fresh();
    }

    it('prices the trap card after the gift so the pair adds up to what doing both saves', function () {
        $this->seed(TaxActionDefinitionSeeder::class);
        $user = trapHousehold();
        $items = collect(app(ComposedTaxPlanService::class)->forUser($user)['items'])->keyBy('type');
        $trap = $items['pa_taper_rescue'];
        $gift = $items['savings_to_spouse'];

        // Ground truth from the records themselves: the same household after
        // giving the savings away and paying the suggested contribution.
        $math = app(TaxStrategyMath::class);
        $doneBoth = trapHousehold(withSavings: false, extraPension: (float) $trap['suggested_contribution']);
        $truth = $math->incomeTaxNow($user) - $math->incomeTaxNow($doneBoth);

        // The contribution is still sized on today's income (relief below the
        // threshold is real); only its price moves.
        expect((float) $trap['suggested_contribution'])->toBe(6700.0)
            ->and($trap['estimated_annual_tax_saved'] + $gift['estimated_annual_tax_saved'])
            ->toEqualWithDelta($truth, 1.0)
            // Before the fix the pair was £441 more than the truth.
            ->and($trap['estimated_annual_tax_saved'])->toBeLessThan(6700 * app(TaxStrategyMath::class)->bandRateForBand('higher') * 1.5);
    });

    it('says how much allowance the contribution wins back once the interest has gone', function () {
        $math = app(TaxStrategyMath::class);
        $user = trapHousehold();
        $threshold = (float) app(TaxConfigService::class)->getIncomeTax()['personal_allowance_taper_threshold'];
        $aniAfterGift = $math->adjustedNetIncomeFor($user) - $math->estimateAnnualInterest($user);

        $trap = matrixRecs($user)['pa_taper_rescue'];

        expect($trap['description'])
            ->toContain('For your income of £'.number_format((int) round($aniAfterGift)).' once your savings interest is out of tax')
            ->toContain('Reclaim £'.number_format((int) (($aniAfterGift - $threshold) / 2)).' of your Personal Allowance');
    });

    it('leaves the trap card alone when no savings item shelters interest', function () {
        $user = trapHousehold(withSavings: false);
        $trap = matrixRecs($user)['pa_taper_rescue'];

        expect($trap['description'])->toContain('Reclaim £'.number_format((int) ($trap['suggested_contribution'] / 2)).' of your Personal Allowance')
            ->and($trap['description'])->not->toContain('once your savings interest is out of tax');
    });
});
