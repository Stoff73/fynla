<?php

declare(strict_types=1);

use App\Models\SavingsAccount;
use App\Models\TaxStrategyHouseholdInput;
use App\Models\User;
use App\Services\Tax\TaxStrategyCalculator;
use App\Services\Tax\TaxStrategyMath;
use App\Services\TaxConfigService;
use Database\Seeders\TaxConfigurationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(TaxConfigurationSeeder::class);
});

function maUser(array $attrs, array $household = []): User
{
    // marriage_allowance_eligible mirrors what FunnelAnswersMapper writes for a
    // non-earning spouse, so the old flag-only gate cannot pass these alone.
    $user = User::factory()->create($attrs + ['marital_status' => 'married', 'marriage_allowance_eligible' => true]);
    TaxStrategyHouseholdInput::create(['user_id' => $user->id] + $household);

    return $user;
}

function maRec(User $user): ?array
{
    return collect(app(TaxStrategyCalculator::class)->calculate($user)->recommendations)
        ->firstWhere('type', 'marriage_allowance_transfer');
}

function maBasicSaving(): float
{
    $math = app(TaxStrategyMath::class);

    return round($math->marriageAllowanceAmount() * $math->bandRateForBand('basic'), 2);
}

it('does not offer Marriage Allowance when the user has no taxable income (B6)', function () {
    $user = maUser(['household_calculation_mode' => 'single_earner_couple', 'annual_employment_income' => 0]);

    expect(maRec($user))->toBeNull();
});

it('caps the saving at the tax the user actually pays', function () {
    $pa = (float) app(TaxConfigService::class)->getIncomeTax()['personal_allowance'];
    $user = maUser(['household_calculation_mode' => 'single_earner_couple', 'annual_employment_income' => $pa + 430]);

    $basic = app(TaxStrategyMath::class)->bandRateForBand('basic');
    expect(maRec($user)['estimated_annual_tax_saved'])->toBe(round(430 * $basic, 2));
});

it('gives the full saving to a basic-rate recipient with a non-earning spouse', function () {
    $user = maUser(['household_calculation_mode' => 'single_earner_couple', 'annual_employment_income' => 35000]);

    expect(maRec($user)['estimated_annual_tax_saved'])->toBe(maBasicSaving());
});

it('offers Marriage Allowance in dual_earner mode when the spouse earns below the Personal Allowance (B7)', function () {
    $user = maUser(['household_calculation_mode' => 'dual_earner', 'annual_employment_income' => 35000], ['spouse_annual_income' => 8000]);

    expect(maRec($user)['estimated_annual_tax_saved'])->toBe(maBasicSaving());
});

it('does not offer it when the spouse earns above the Personal Allowance or their income is unknown', function (?float $spouseIncome) {
    $user = maUser(['household_calculation_mode' => 'dual_earner', 'annual_employment_income' => 35000], ['spouse_annual_income' => $spouseIncome]);

    expect(maRec($user))->toBeNull();
})->with(['above' => [20000.0], 'unknown' => [null]]);

it('does not offer it to a couple who are not married or in a civil partnership (B8)', function () {
    $user = maUser(['household_calculation_mode' => 'single_earner_couple', 'annual_employment_income' => 35000, 'marital_status' => 'single']);

    expect(maRec($user))->toBeNull();
});

it('shrinks the spouse Personal Allowance used for the savings gift by the transferred amount (B4)', function () {
    $user = maUser(
        ['household_calculation_mode' => 'single_earner_couple', 'annual_employment_income' => 35000],
        ['spouse_existing_savings_balance' => 0],
    );
    SavingsAccount::factory()->for($user)->create([
        'current_balance' => 200000, 'interest_rate' => 4.5, 'is_isa' => false,
        'ownership_type' => 'individual', 'joint_owner_id' => null,
    ]);

    $gift = collect(app(TaxStrategyCalculator::class)->calculate($user)->recommendations)
        ->firstWhere('type', 'savings_to_spouse');
    $math = app(TaxStrategyMath::class);
    $pa = (float) app(TaxConfigService::class)->getIncomeTax()['personal_allowance'];

    expect(maRec($user))->not->toBeNull()
        ->and($gift['spouse_personal_allowance'])->toBe($pa - $math->marriageAllowanceAmount());
});
