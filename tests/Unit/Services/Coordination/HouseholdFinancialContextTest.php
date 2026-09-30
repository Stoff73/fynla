<?php

declare(strict_types=1);

use App\Models\DCPension;
use App\Models\Investment\InvestmentAccount;
use App\Models\PensionInputHistory;
use App\Models\SavingsAccount;
use App\Models\TaxStrategyHouseholdInput;
use App\Models\User;
use App\Services\Coordination\HouseholdFinancialContext;
use Database\Seeders\TaxConfigurationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(TaxConfigurationSeeder::class);
    $this->svc = app(HouseholdFinancialContext::class);
});

it('reports which catalogue data points are available for a user', function () {
    $user = User::factory()->create([
        'date_of_birth' => '1982-02-19',
        'marital_status' => 'married',
        'employment_status' => 'full_time',
        'annual_employment_income' => 110000,
        'household_calculation_mode' => 'single_earner_couple',
    ]);
    TaxStrategyHouseholdInput::create(['user_id' => $user->id, 'spouse_existing_savings_balance' => 0]);

    $availability = $this->svc->availability($user->fresh());

    expect($availability['annual_income'])->toBeTrue()
        ->and($availability['date_of_birth'])->toBeTrue()
        ->and($availability['marital_status'])->toBeTrue()
        ->and($availability['employment_status'])->toBeTrue()
        ->and($availability['spouse_income'])->toBeTrue()      // single_earner_couple => spouse income known to be £0
        ->and($availability['workplace_pension'])->toBeFalse() // no pension records
        ->and($availability['savings_balances'])->toBeFalse()  // no savings accounts
        // No savings to pay in more than this year's allowance: carry forward
        // cannot apply, so past pension payments are not asked for.
        ->and($availability['pension_input_history'])->toBeNull();
});

it('returns exactly the 14 canonical vocabulary keys', function () {
    // Only asserts the key set — values vary with factory defaults (e.g. marital_status defaults to 'single' = available).
    $user = User::factory()->create([
        'annual_employment_income' => null,
        'annual_self_employment_income' => null,
        'annual_dividend_income' => null,
        'date_of_birth' => null,
        'employment_status' => null,
        'annual_charitable_donations' => null,
        'household_calculation_mode' => 'single',
    ]);

    $keys = array_keys($this->svc->availability($user));
    sort($keys);

    expect($keys)->toBe([
        'annual_income', 'charitable_giving', 'date_of_birth', 'dividend_income',
        'employment_status', 'gia_holdings', 'isa_subscriptions_ytd', 'marital_status',
        'pension_contributions', 'pension_input_history', 'savings_balances',
        'spouse_income', 'spouse_income_amount', 'workplace_pension',
    ]);
});

it('exposes the user marginal rate and a spouse rate of zero for a non-earning spouse', function () {
    $user = User::factory()->create([
        'annual_employment_income' => 110000,
        'household_calculation_mode' => 'single_earner_couple',
    ]);

    expect($this->svc->marginalRateFor($user))->toBeGreaterThan(0.0)->toBeLessThanOrEqual(1.0)
        ->and($this->svc->spouseMarginalRate($user))->toBe(0.0);
});

it('returns null spouse rate when spouse income is unknown (single mode)', function () {
    $user = User::factory()->create(['household_calculation_mode' => 'single']);

    expect($this->svc->spouseMarginalRate($user))->toBeNull();
});

it('returns null spouse rate when household_calculation_mode is not set', function () {
    $user = User::factory()->create(['household_calculation_mode' => null]);

    expect($this->svc->spouseMarginalRate($user))->toBeNull();
});

it('returns spouse rate from dual_earner household input income', function () {
    $user = User::factory()->create([
        'annual_employment_income' => 80000,
        'household_calculation_mode' => 'dual_earner',
    ]);
    TaxStrategyHouseholdInput::create([
        'user_id' => $user->id,
        'spouse_annual_income' => 30000,
    ]);

    $rate = $this->svc->spouseMarginalRate($user->fresh());

    expect($rate)->toBeFloat()
        ->and($rate)->toBeGreaterThan(0.0)
        ->and($rate)->toBeLessThanOrEqual(1.0);
});

it('marks charitable_giving available when answered zero (null vs zero semantics)', function () {
    $user = User::factory()->create(['annual_charitable_donations' => '0.00']);

    expect($this->svc->availability($user)['charitable_giving'])->toBeTrue();
});

it('marks charitable_giving unavailable when never asked (null)', function () {
    $user = User::factory()->create(['annual_charitable_donations' => null]);

    expect($this->svc->availability($user)['charitable_giving'])->toBeFalse();
});

it('marks savings_balances available when user owns a savings account', function () {
    $user = User::factory()->create();
    SavingsAccount::factory()->for($user)->create(['is_isa' => false]);

    expect($this->svc->availability($user)['savings_balances'])->toBeTrue();
});

it('marks isa_subscriptions_ytd available when user owns an ISA account', function () {
    $user = User::factory()->create();
    SavingsAccount::factory()->for($user)->create(['is_isa' => true]);

    expect($this->svc->availability($user)['isa_subscriptions_ytd'])->toBeTrue();
});

it('marks gia_holdings available when user has a non-ISA investment account', function () {
    $user = User::factory()->create();
    InvestmentAccount::factory()->for($user)->create(['account_type' => 'gia', 'ownership_type' => 'individual', 'ownership_percentage' => 100, 'joint_owner_id' => null]);

    expect($this->svc->availability($user)['gia_holdings'])->toBeTrue();
});

it('marks gia_holdings unavailable when user only has ISA investment accounts', function () {
    $user = User::factory()->create();
    InvestmentAccount::factory()->for($user)->create(['account_type' => 'isa']);

    expect($this->svc->availability($user)['gia_holdings'])->toBeFalse();
});

it('marks workplace_pension and pension_contributions available when DC pension exists', function () {
    $user = User::factory()->create();
    DCPension::factory()->for($user)->create();

    $availability = $this->svc->availability($user);

    expect($availability['workplace_pension'])->toBeTrue()
        ->and($availability['pension_contributions'])->toBeTrue();
});

it('marks pension_input_history available when input history rows exist', function () {
    $user = User::factory()->create(['annual_employment_income' => 150000]);
    SavingsAccount::factory()->for($user)->create(['current_balance' => 100000, 'ownership_type' => 'individual', 'ownership_percentage' => 100, 'joint_owner_id' => null]);
    PensionInputHistory::create([
        'user_id' => $user->id,
        'tax_year' => '2025/26',
        'pension_input_amount' => 10000,
    ]);

    expect($this->svc->availability($user)['pension_input_history'])->toBeTrue();
});

// Batch 5 (CSJ 2026-09-19): "I have none of those" answers the question.
it('treats a declared none as available data', function () {
    $user = User::factory()->create(['onboarding_fyn_context' => ['declared_none' => ['investment', 'pension'], 'declared_none_keys' => ['dividend_income']]]);

    $availability = $this->svc->availability($user);

    expect($availability['gia_holdings'])->toBeTrue()
        ->and($availability['pension_contributions'])->toBeTrue()
        ->and($availability['pension_input_history'])->not->toBeFalse()
        ->and($availability['dividend_income'])->toBeTrue()
        ->and($availability['savings_balances'])->toBeFalse();
});

// Release walk 2026-09-28: a user with a Stocks and Shares ISA was asked for
// their "ISA info". TaxStrategyMath already counts investment ISA payments
// towards this year's allowance, so the ISA question is answered.
it('marks isa_subscriptions_ytd available when user owns a Stocks and Shares ISA', function () {
    $user = User::factory()->create();
    InvestmentAccount::factory()->for($user)->create(['account_type' => 'isa', 'ownership_type' => 'individual', 'ownership_percentage' => 100, 'joint_owner_id' => null]);

    expect($this->svc->availability($user)['isa_subscriptions_ytd'])->toBeTrue();
});

it('treats a blank childcare and charitable donations form as no charitable giving', function () {
    $user = User::factory()->create(['onboarding_fyn_context' => ['declared_none' => ['expenditure_tax']]]);

    expect($this->svc->availability($user)['charitable_giving'])->toBeTrue();
});

// The declarations are read after onboarding (the actions page, the tax
// plan), so completing onboarding must not drop them with the scratch state.
it('keeps the none declarations and joint records past onboarding, and nothing else', function () {
    $context = [
        'declared_none' => ['investment'],
        'declared_none_keys' => ['dividend_income'],
        'spouse_joint_records' => [['type' => 'savings_account', 'id' => 7]],
        'verify_section' => 'pensions',
    ];

    expect(HouseholdFinancialContext::outlivingOnboarding($context))->toBe([
        'spouse_joint_records' => [['type' => 'savings_account', 'id' => 7]],
        'declared_none' => ['investment'],
        'declared_none_keys' => ['dividend_income'],
    ])->and(HouseholdFinancialContext::outlivingOnboarding(['verify_section' => 'pensions']))->toBeNull()
        ->and(HouseholdFinancialContext::outlivingOnboarding(null))->toBeNull();
});

// CSJ 2026-09-28: past pension payments are only for carry forward, which
// needs earnings above this year's allowance AND the cash to pay in more.
it('asks for past pension payments only when carry forward could apply', function (int $income, float $cash, ?bool $expected) {
    $user = User::factory()->create(['annual_employment_income' => $income]);
    if ($cash > 0) {
        SavingsAccount::factory()->for($user)->create(['current_balance' => $cash, 'ownership_type' => 'individual', 'ownership_percentage' => 100, 'joint_owner_id' => null]);
    }

    expect($this->svc->availability($user)['pension_input_history'])->toBe($expected);
})->with([
    'basic-rate earner with savings' => [45000, 100000.0, null],
    'high earner, little cash' => [150000, 5000.0, null],
    'high earner with the cash' => [150000, 100000.0, false],
]);

it('knows the spouse income when a linked spouse holds income on their own account', function () {
    // The spouse strategies (savings_to_spouse, isa_topup_spouse, gia_to_spouse)
    // waited for "spouse's income" that the linked account already held; the
    // amount flag beside it read the same account (ice-cube, 2026-09-30 item 6).
    $user = User::factory()->create([
        'marital_status' => 'married',
        'annual_employment_income' => 60000,
        'household_calculation_mode' => 'dual_earner',
    ]);
    $spouse = User::factory()->create(['marital_status' => 'married', 'annual_employment_income' => 30000, 'spouse_id' => $user->id]);
    $user->update(['spouse_id' => $spouse->id]);

    $availability = $this->svc->availability($user->fresh());

    expect($availability['spouse_income'])->toBeTrue()
        ->and($availability['spouse_income_amount'])->toBeTrue();
});

it('still waits for the spouse income when a linked spouse holds none and none was given', function () {
    $user = User::factory()->create([
        'marital_status' => 'married',
        'annual_employment_income' => 60000,
        'household_calculation_mode' => 'dual_earner',
    ]);
    $spouse = User::factory()->create(['marital_status' => 'married', 'annual_employment_income' => null, 'spouse_id' => $user->id]);
    $user->update(['spouse_id' => $spouse->id]);

    $availability = $this->svc->availability($user->fresh());

    expect($availability['spouse_income'])->toBeFalse()
        ->and($availability['spouse_income_amount'])->toBeFalse();
});

it('knows the spouse income from the figure given for a dual-earner spouse', function () {
    $user = User::factory()->create([
        'marital_status' => 'married',
        'annual_employment_income' => 60000,
        'household_calculation_mode' => 'dual_earner',
    ]);
    TaxStrategyHouseholdInput::create(['user_id' => $user->id, 'spouse_annual_income' => 30000]);

    expect($this->svc->availability($user->fresh())['spouse_income'])->toBeTrue();
});
