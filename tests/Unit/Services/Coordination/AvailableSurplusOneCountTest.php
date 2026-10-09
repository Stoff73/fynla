<?php

declare(strict_types=1);

use App\Models\DCPension;
use App\Models\LifeInsurancePolicy;
use App\Models\User;
use App\Services\Coordination\CashFlowCoordinator;
use App\Services\Plans\DisposableIncomeAccessor;
use Database\Seeders\TaxConfigurationSeeder;

/*
 * The monthly surplus the plan and Fyn quote is the Income tab's disposable
 * income: take-home less spending, where spending already includes the
 * financial commitments (pension payments, protection premiums, regular
 * saving; UserProfileService::getFinancialCommitments, W-0140). Taking those
 * off again counted them twice: Jamie (csjones user 503, 2026-10-08) was told
 * "a monthly surplus of −£266.70" when take-home £22,719.60 / 12 − £1,900
 * spending − £130 pension is −£136.70.
 */
beforeEach(fn () => $this->seed(TaxConfigurationSeeder::class));

it('takes a pension payment and a premium off once', function () {
    $user = User::factory()->create([
        'employment_status' => 'part_time', 'annual_employment_income' => 26000,
        'monthly_expenditure' => 1900, 'expenditure_entry_mode' => 'simple',
    ]);
    DCPension::factory()->create([
        'user_id' => $user->id, 'scheme_type' => 'personal',
        'monthly_contribution_amount' => 130, 'employee_contribution_percent' => null,
        'employer_contribution_percent' => null, 'salary_sacrifice' => false,
    ]);
    LifeInsurancePolicy::factory()->create([
        'user_id' => $user->id, 'premium_amount' => 20, 'premium_frequency' => 'monthly',
    ]);

    $disposable = app(DisposableIncomeAccessor::class)->getForUser($user->fresh());
    // The commitments are inside the spending the disposable figure deducts.
    expect($disposable['expenditure_composition']['commitments_annual'])->toBe(1800.0);

    expect(app(CashFlowCoordinator::class)->calculateAvailableSurplus($user->id))
        ->toBe(round($disposable['monthly'], 2));
});
