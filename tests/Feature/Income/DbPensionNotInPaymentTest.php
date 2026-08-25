<?php

declare(strict_types=1);

use App\Models\DBPension;
use App\Models\StatePension;
use App\Models\User;
use App\Services\Tax\IncomeDefinitionsService;
use App\Services\UserProfile\PersonalAccountsService;
use App\Services\UserProfile\UserProfileService;
use Database\Seeders\TaxConfigurationSeeder;
use Database\Seeders\TierConfigurationSeeder;

/**
 * W-0036. A Defined Benefit pension was counted as income from the day it was
 * entered. `accrued_annual_pension` holds a FUTURE figure — the form labels it
 * "Annual Income at Retirement" — so a 48-year-old with a Normal Retirement Age of
 * 60 was treated as receiving £35,000 a year.
 *
 * That is a tax defect, not a retirement display one: it took her from £120,000 to
 * £155,000, past the additional-rate threshold and through the whole Personal
 * Allowance taper, and changed her Child Benefit position.
 *
 * Three services carried a byte-identical copy of the function, each gating the
 * State Pension correctly on `already_receiving` four lines below the ungated
 * Defined Benefit loop. They now share one implementation.
 */
beforeEach(function (): void {
    $this->seed(TaxConfigurationSeeder::class);
    $this->seed(TierConfigurationSeeder::class);
});

function sarahAged(int $age, ?int $normalRetirementAge = 60): User
{
    $user = User::factory()->create([
        'date_of_birth' => now()->subYears($age)->subMonths(2)->format('Y-m-d'),
        'annual_employment_income' => 120000,
    ]);

    DBPension::factory()->create([
        'user_id' => $user->id,
        'scheme_name' => 'NHS Pension Scheme',
        'accrued_annual_pension' => 35000,
        'normal_retirement_age' => $normalRetirementAge,
    ]);

    return $user->fresh();
}

it('counts no pension income for a user below the scheme retirement age', function (): void {
    $user = sarahAged(48);

    $profile = app(UserProfileService::class)->getCompleteProfile($user);

    expect((float) $profile['income_occupation']['annual_pension_income'])->toBe(0.0)
        ->and((float) $profile['income_occupation']['total_annual_income'])->toBe(120000.0);
});

it('counts the pension once the user reaches the scheme retirement age', function (): void {
    $user = sarahAged(61);

    $profile = app(UserProfileService::class)->getCompleteProfile($user);

    expect((float) $profile['income_occupation']['annual_pension_income'])->toBe(35000.0)
        ->and((float) $profile['income_occupation']['total_annual_income'])->toBe(155000.0);
});

it('gives the same answer on all three services that ask the question', function (): void {
    $below = sarahAged(48);
    $above = sarahAged(61);

    $profileService = app(UserProfileService::class);
    $taxService = app(IncomeDefinitionsService::class);
    $accountsService = app(PersonalAccountsService::class);

    $ask = fn (User $u): array => [
        (float) $profileService->getCompleteProfile($u)['income_occupation']['annual_pension_income'],
        (float) (new ReflectionMethod($taxService, 'calculatePensionIncome'))->invoke($taxService, $u),
        (float) (new ReflectionMethod($accountsService, 'calculateAnnualPensionIncome'))->invoke($accountsService, $u),
    ];

    expect($ask($below))->toBe([0.0, 0.0, 0.0])
        ->and($ask($above))->toBe([35000.0, 35000.0, 35000.0]);
});

it('falls back to the projector default when the scheme records no retirement age', function (): void {
    // Sarah's real row has a null Normal Retirement Age, so this is the live shape.
    expect(app(UserProfileService::class)
        ->getCompleteProfile(sarahAged(48, null))['income_occupation']['annual_pension_income'])
        ->toBe(0.0);

    // A retired user with the same null must NOT lose income they are receiving.
    expect(app(UserProfileService::class)
        ->getCompleteProfile(sarahAged(70, null))['income_occupation']['annual_pension_income'])
        ->toBe(35000.0);
});

it('counts nothing when the date of birth is unknown, rather than inventing income', function (): void {
    $user = User::factory()->create(['date_of_birth' => null, 'annual_employment_income' => 120000]);
    DBPension::factory()->create([
        'user_id' => $user->id,
        'accrued_annual_pension' => 35000,
        'normal_retirement_age' => 60,
    ]);

    expect(app(UserProfileService::class)
        ->getCompleteProfile($user->fresh())['income_occupation']['annual_pension_income'])
        ->toBe(0.0);
});

it('still gates the State Pension on already_receiving, as it always did', function (): void {
    $user = sarahAged(70, 60);
    StatePension::factory()->create([
        'user_id' => $user->id,
        'state_pension_forecast_annual' => 11502,
        'already_receiving' => false,
    ]);

    expect(app(UserProfileService::class)
        ->getCompleteProfile($user->fresh())['income_occupation']['annual_pension_income'])
        ->toBe(35000.0);

    $user->statePension->update(['already_receiving' => true]);

    expect(app(UserProfileService::class)
        ->getCompleteProfile($user->fresh())['income_occupation']['annual_pension_income'])
        ->toBe(46502.0);
});

it('reports isInPayment per record without needing the user preloaded', function (): void {
    $user = sarahAged(48);
    $pension = $user->dbPensions->first();

    expect($pension->isInPayment(48))->toBeFalse()
        ->and($pension->isInPayment(60))->toBeTrue()
        ->and($pension->isInPayment(null))->toBeFalse()
        // No age passed: resolves through the user relation.
        ->and($pension->isInPayment())->toBeFalse();
});
