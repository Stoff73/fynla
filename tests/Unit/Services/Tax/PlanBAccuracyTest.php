<?php

declare(strict_types=1);

use App\Models\SavingsAccount;
use App\Models\TaxStrategyHouseholdInput;
use App\Models\User;
use App\Services\Marketing\SaveTaxEstimateService;
use App\Services\Tax\TaxStrategyCalculator;
use App\Services\Tax\TaxStrategyMath;
use App\Services\TaxConfigService;
use Database\Seeders\TaxConfigurationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

/*
 * Accuracy fixes after CSJ's review of PR #941 (2026-09-25). Every expected
 * figure is derived from its statutory source, cited beside it (CLAUDE.md
 * Rule 23).
 *
 * Marriage Allowance: ITA 2007 Part 3 Chapter 3A.
 *   s55B(1),(3): reduction = basic rate × transferable amount.
 *   s55B(2)(b),(ba): the recipient may only be liable at the basic, savings,
 *     dividend-ordinary and nil rates.
 *   s55B(6): the transferor's Personal Allowance is reduced by the
 *     transferable amount.
 *   s55C(2): the transferor's net income is LESS THAN the Personal Allowance.
 *   s23 Step 6 and s26: a tax reduction is deducted from the tax calculated
 *     at Step 5, so it can never exceed that tax.
 *   https://www.legislation.gov.uk/ukpga/2007/3/part/3/chapter/3A
 *   https://www.legislation.gov.uk/ukpga/2007/3/section/23
 * Pension relief age limit: FA 2004 s188(3)(a),
 *   https://www.legislation.gov.uk/ukpga/2004/12/section/188
 */

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(TaxConfigurationSeeder::class);
});

function accRecs(User $user): array
{
    return collect(app(TaxStrategyCalculator::class)->calculate($user)->recommendations)->keyBy('type')->all();
}

function accPa(): float
{
    return (float) app(TaxConfigService::class)->getIncomeTax()['personal_allowance'];
}

function accBasic(): float
{
    return app(TaxStrategyMath::class)->bandRateForBand('basic');
}

it('caps the Marriage Allowance saving at the recipient\'s actual income tax (s23 Step 6)', function () {
    // £130 of pay above the Personal Allowance is the only taxed income: the
    // £800 of interest sits in the starting rate for savings band (ITA 2007
    // s12). Tax at Step 5 = £130 × basic rate, so the reduction cannot exceed it.
    $user = User::factory()->create([
        'household_calculation_mode' => 'single_earner_couple',
        'annual_employment_income' => accPa() + 130,
        'marital_status' => 'married',
    ]);
    TaxStrategyHouseholdInput::create(['user_id' => $user->id]);
    SavingsAccount::factory()->for($user)->create([
        'current_balance' => 20000, 'interest_rate' => 4, 'is_isa' => false,
        'ownership_type' => 'individual', 'joint_owner_id' => null,
    ]);

    expect(accRecs($user)['marriage_allowance_transfer']['estimated_annual_tax_saved'])
        ->toBe(round(130 * accBasic(), 2));
});

it('offers Marriage Allowance when the user is the lower earner transferring to a basic-rate spouse (s55C)', function () {
    $math = app(TaxStrategyMath::class);
    $user = User::factory()->create([
        'household_calculation_mode' => 'dual_earner',
        'annual_employment_income' => 8000,
        'marital_status' => 'married',
    ]);
    TaxStrategyHouseholdInput::create(['user_id' => $user->id, 'spouse_annual_income' => 30000]);

    $rec = accRecs($user)['marriage_allowance_transfer'] ?? null;

    // £8,000 is below the Personal Allowance less the transferable amount, so
    // the user pays no tax on the slice they give up (s55B(6)).
    expect($rec)->not->toBeNull()
        ->and($rec['estimated_annual_tax_saved'])->toBe(round($math->marriageAllowanceAmount() * accBasic(), 2))
        ->and($rec['transfer_direction'])->toBe('to_spouse');
});

it('marks Marriage Allowance unavailable on the allowance grid when the statutory tests fail', function () {
    // Spouse earns £20,000: above the Personal Allowance, so s55C(2) fails.
    $user = User::factory()->create([
        'household_calculation_mode' => 'dual_earner',
        'annual_employment_income' => 35000,
        'marital_status' => 'married',
    ]);
    TaxStrategyHouseholdInput::create(['user_id' => $user->id, 'spouse_annual_income' => 20000]);

    $grid = collect(app(TaxStrategyCalculator::class)->calculate($user)->userAllowances)->keyBy('key');

    expect($grid['marriage_allowance']['available'])->toBeFalse();
});

it('does not count the transferred Marriage Allowance twice in the funnel estimate', function () {
    // The £1,260 transferred under s55B(6) leaves the spouse's Personal
    // Allowance, so the "use your spouse's Personal Allowance" line can only
    // use what is left.
    $estimate = app(SaveTaxEstimateService::class)->estimate([
        'income' => 'upto_50270', 'spouse' => 'yes', 'spouseIncome' => 'zero', 'assets' => ['savings'],
    ]);
    $lines = collect($estimate['savings'])->keyBy('key');
    $rate = $estimate['marginal_rate'];
    $ma = app(TaxStrategyMath::class)->marriageAllowanceAmount();

    expect($lines->has('marriage_allowance'))->toBeTrue()
        ->and($lines['spouse_pa']['amount'])->toBe((int) round((accPa() - $ma) * $rate));
});

it('sizes the higher-rate pension item without the interest the ISA wrap already shelters', function () {
    $user = User::factory()->create([
        'household_calculation_mode' => 'single',
        'employment_status' => 'employed',
        'annual_employment_income' => 60000,
        'marital_status' => 'single',
        'date_of_birth' => now()->subYears(45)->toDateString(),
    ]);
    SavingsAccount::factory()->for($user)->create([
        'current_balance' => 60000, 'interest_rate' => 4.5, 'is_isa' => false,
        'ownership_type' => 'individual', 'joint_owner_id' => null,
    ]);

    $recs = accRecs($user);
    $math = app(TaxStrategyMath::class);
    $sheltered = (float) $recs['isa_topup_vs_psa']['taxable_interest_sheltered'];
    $slice = $math->taxableIncomeFor($user) - $sheltered - $math->bandThresholdsFor($user)['higher'];

    expect($recs['pension_tax_relief']['suggested_contribution'])->toBe((float) (floor($slice / 100) * 100));
});

it('reads the pension relief age limit from the tax configuration (FA 2004 s188(3)(a))', function () {
    expect((int) app(TaxConfigService::class)->getPensionAllowances()['relief_max_age'])->toBe(75);

    $at75 = User::factory()->create([
        'household_calculation_mode' => 'single', 'employment_status' => 'employed',
        'annual_employment_income' => 30000, 'marital_status' => 'single',
        'date_of_birth' => now()->subYears(75)->toDateString(),
    ]);
    $at74 = User::factory()->create([
        'household_calculation_mode' => 'single', 'employment_status' => 'employed',
        'annual_employment_income' => 30000, 'marital_status' => 'single',
        'date_of_birth' => now()->subYears(74)->toDateString(),
    ]);

    expect(accRecs($at75))->not->toHaveKey('pension_tax_relief')
        ->and(accRecs($at74))->toHaveKey('pension_tax_relief');
});

it('gives one pension relief type whatever the band, so action state survives a band change', function () {
    $basic = User::factory()->create([
        'household_calculation_mode' => 'single', 'employment_status' => 'employed',
        'annual_employment_income' => 30000, 'marital_status' => 'single',
    ]);
    $higher = User::factory()->create([
        'household_calculation_mode' => 'single', 'employment_status' => 'employed',
        'annual_employment_income' => 60000, 'marital_status' => 'single',
    ]);

    expect(accRecs($basic)['pension_tax_relief']['tax_band'])->toBe('basic')
        ->and(accRecs($higher)['pension_tax_relief']['tax_band'])->toBe('higher');
});

it('gives no spouse pension top-up to a couple who are not married or civil partners', function () {
    $user = User::factory()->create([
        'household_calculation_mode' => 'single_earner_couple',
        'annual_employment_income' => 60000,
        'marital_status' => 'single',
    ]);
    TaxStrategyHouseholdInput::create(['user_id' => $user->id]);

    expect(accRecs($user))->not->toHaveKey('non_earner_spouse_pension');
});

it('titles the modest-earner top-up with the amount actually left to pay', function () {
    $user = User::factory()->create([
        'household_calculation_mode' => 'dual_earner',
        'annual_employment_income' => 60000,
        'marital_status' => 'married',
    ]);
    TaxStrategyHouseholdInput::create(['user_id' => $user->id, 'spouse_annual_income' => 8000, 'spouse_pension_input_annual' => 3000]);

    $rec = accRecs($user)['non_earner_spouse_pension'];

    expect($rec['title'])->toContain('£'.number_format((int) $rec['net_cost']))
        ->and($rec['title'])->not->toContain('Max out');
});

it('writes the ISA deadline the British way', function () {
    $user = User::factory()->create([
        'household_calculation_mode' => 'single', 'annual_employment_income' => 60000, 'marital_status' => 'single',
    ]);
    SavingsAccount::factory()->for($user)->create([
        'current_balance' => 60000, 'interest_rate' => 4.5, 'is_isa' => false,
        'ownership_type' => 'individual', 'joint_owner_id' => null,
    ]);

    expect(accRecs($user)['isa_topup_vs_psa']['title'])->toContain('before 5 April')
        ->not->toContain('April 5');
});
