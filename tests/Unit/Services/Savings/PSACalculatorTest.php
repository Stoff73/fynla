<?php

declare(strict_types=1);

use App\Models\SavingsAccount;
use App\Models\User;
use App\Services\Savings\PSACalculator;
use Database\Seeders\TaxConfigurationSeeder;

/*
 * An additional-rate taxpayer has no Personal Savings Allowance (ITA 2007
 * s12B; https://www.gov.uk/apply-tax-free-interest-on-savings/how-much-is-tax-free).
 * With no interest, there is nothing to breach or approach: the old sum set
 * utilisation to 100% whenever the allowance was £0, so a household earning
 * £0 of interest was told to shelter it in a Cash ISA.
 */
beforeEach(fn () => $this->seed(TaxConfigurationSeeder::class));

function additionalRateSaver(float $rate): User
{
    $user = User::factory()->create([
        'employment_status' => 'employed', 'annual_employment_income' => 200000,
        'annual_self_employment_income' => 0, 'annual_dividend_income' => 0, 'annual_interest_income' => 0,
        'annual_rental_income' => 0, 'annual_other_income' => 0,
    ]);
    SavingsAccount::factory()->create([
        'user_id' => $user->id, 'is_isa' => false, 'current_balance' => 20000, 'interest_rate' => $rate,
        'ownership_type' => 'individual', 'joint_owner_id' => null,
    ]);

    return $user;
}

it('does not call a £0 allowance approached when no interest is earned', function () {
    $position = app(PSACalculator::class)->assessPSAPosition(additionalRateSaver(0.0));

    expect($position['tax_band'])->toBe('additional')
        ->and($position['psa_amount'])->toBe(0.0)
        ->and($position['annual_interest'])->toBe(0.0)
        ->and($position['utilisation_percent'])->toBe(0.0)
        ->and($position['is_approaching'])->toBeFalse()
        ->and($position['is_breached'])->toBeFalse();
});

it('still calls any interest on a £0 allowance breached', function () {
    $position = app(PSACalculator::class)->assessPSAPosition(additionalRateSaver(4.0));

    expect($position['annual_interest'])->toBeGreaterThan(0.0)
        ->and($position['is_breached'])->toBeTrue()
        ->and($position['breach_amount'])->toBe($position['annual_interest']);
});
