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
        ->and($availability['pension_input_history'])->toBeFalse();
});

it('returns exactly the 13 canonical vocabulary keys', function () {
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
        'spouse_income', 'workplace_pension',
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
    InvestmentAccount::factory()->for($user)->create(['account_type' => 'gia']);

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
    $user = User::factory()->create();
    PensionInputHistory::create([
        'user_id' => $user->id,
        'tax_year' => '2025/26',
        'pension_input_amount' => 10000,
    ]);

    expect($this->svc->availability($user)['pension_input_history'])->toBeTrue();
});
