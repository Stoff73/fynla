<?php

declare(strict_types=1);

use App\Models\Investment\InvestmentAccount;
use App\Models\SavingsAccount;
use App\Models\TaxStrategyHouseholdInput;
use App\Models\User;
use App\Services\Tax\TaxStrategyCalculator;
use Database\Seeders\TaxConfigurationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(TaxConfigurationSeeder::class);
});

function spouseTypes(User $user): array
{
    return collect(app(TaxStrategyCalculator::class)->calculate($user)->recommendations)
        ->where('category', 'household')->pluck('type')->all();
}

it('gives no spouse-transfer advice when the user is not married or in a civil partnership', function (string $mode) {
    $user = User::factory()->create([
        'household_calculation_mode' => $mode,
        'annual_employment_income' => 80000,
        'marital_status' => 'single',
    ]);
    TaxStrategyHouseholdInput::create(['user_id' => $user->id, 'spouse_annual_income' => 20000, 'spouse_existing_isa_balance' => 0]);
    InvestmentAccount::factory()->create(['user_id' => $user->id, 'account_type' => 'gia']);

    // One assertion per type: a negated multi-value toContain passes as soon
    // as ANY one value is missing.
    $types = spouseTypes($user);
    foreach (['gia_to_spouse', 'gia_rebalance', 'savings_to_spouse', 'isa_topup_spouse'] as $type) {
        expect($types)->not->toContain($type);
    }
})->with(['single_earner_couple', 'dual_earner']);

it('does not treat an unknown spouse income as basic rate (B12)', function () {
    $user = User::factory()->create([
        'household_calculation_mode' => 'dual_earner',
        'annual_employment_income' => 80000,
        'marital_status' => 'married',
    ]);
    TaxStrategyHouseholdInput::create(['user_id' => $user->id, 'spouse_annual_income' => null]);
    InvestmentAccount::factory()->create(['user_id' => $user->id, 'account_type' => 'gia']);

    expect(spouseTypes($user))->not->toContain('gia_rebalance');
});

it('does not suggest funding a spouse ISA when the user has nothing to fund it from', function () {
    $user = User::factory()->create([
        'household_calculation_mode' => 'single_earner_couple',
        'annual_employment_income' => 40000,
        'marital_status' => 'married',
    ]);
    TaxStrategyHouseholdInput::create(['user_id' => $user->id, 'spouse_existing_isa_balance' => 0]);

    expect(spouseTypes($user))->not->toContain('isa_topup_spouse');
});

it('offers no £0 gift or spouse ISA to a couple with no income (ice-cube, PR 991)', function () {
    $user = User::factory()->create([
        'household_calculation_mode' => 'single_earner_couple',
        'employment_status' => 'unemployed',
        'annual_employment_income' => 0,
        'marital_status' => 'married',
    ]);
    TaxStrategyHouseholdInput::create(['user_id' => $user->id, 'spouse_existing_savings_balance' => 0, 'spouse_existing_isa_balance' => 0]);
    SavingsAccount::factory()->for($user)->create(['is_isa' => false, 'current_balance' => 4000, 'interest_rate' => 4.0, 'ownership_type' => 'individual', 'ownership_percentage' => 100, 'joint_owner_id' => null]);

    // Their £160 of interest sits inside their own Personal Allowance: moving it saves nothing.
    $types = spouseTypes($user);
    expect($types)->not->toContain('savings_to_spouse');
    expect($types)->not->toContain('isa_topup_spouse');
});

it('still offers the gift and the spouse ISA when the user\'s interest is taxed', function () {
    $user = User::factory()->create([
        'household_calculation_mode' => 'single_earner_couple',
        'employment_status' => 'full_time',
        'annual_employment_income' => 60000,
        'marital_status' => 'married',
    ]);
    TaxStrategyHouseholdInput::create(['user_id' => $user->id, 'spouse_existing_savings_balance' => 0, 'spouse_existing_isa_balance' => 0]);
    SavingsAccount::factory()->for($user)->create(['is_isa' => false, 'current_balance' => 50000, 'interest_rate' => 4.0, 'ownership_type' => 'individual', 'ownership_percentage' => 100, 'joint_owner_id' => null]);

    $recommendations = collect(app(TaxStrategyCalculator::class)->calculate($user)->recommendations);
    $gift = $recommendations->firstWhere('type', 'savings_to_spouse');
    expect($gift)->not->toBeNull()
        ->and((float) $gift['estimated_annual_tax_saved'])->toBeGreaterThanOrEqual(1.0)
        ->and($recommendations->pluck('type')->all())->toContain('isa_topup_spouse');
});
