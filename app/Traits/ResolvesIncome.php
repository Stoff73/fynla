<?php

declare(strict_types=1);

namespace App\Traits;

use App\Models\User;
use App\Services\Tax\IncomeDefinitionsService;
use App\Services\UserProfile\UserProfileService;

trait ResolvesIncome
{
    /**
     * The user's total income: the Income page's figure (IncomeDefinitionsService
     * `total_income`, ITA 2007 s23 Step 1), so every engine reads the one figure
     * the user sees (CSJ 2026-10-02: "why are we not using the income figure
     * provided?"). This summed the users income columns, so it left out a
     * pension being paid, used gross rent instead of the rental profit and
     * missed share vests: a retiree on £9,000 of pension was told "gross annual
     * income is required" by the module checks.
     */
    protected function resolveGrossAnnualIncome(User $user): float
    {
        return (float) (app(IncomeDefinitionsService::class)->calculateFor($user)['total_income'] ?? 0);
    }

    /**
     * Pension income the user is ACTUALLY receiving right now.
     *
     * Defined Benefit pensions only once in payment (DBPension::isInPayment), plus
     * the State Pension only when `already_receiving`.
     *
     * This existed three times over — `UserProfileService`,
     * `Tax\IncomeDefinitionsService` and `PersonalAccountsService` each had a
     * byte-identical private copy — and all three carried the same defect: the
     * State Pension half gated correctly on `already_receiving` while the Defined
     * Benefit half counted any non-zero `accrued_annual_pension` as income today,
     * four lines above. Each docblock claimed the check its code did not do.
     *
     * One home now, so "what is this household actually receiving" has one answer
     * (Rule 20). It is a tax figure as much as a retirement one: the phantom income
     * moved a user past the additional-rate threshold and through the whole Personal
     * Allowance taper, and changed her Child Benefit position (W-0036).
     */
    protected function resolvePensionIncomeInPayment(User $user): float
    {
        $user->loadMissing('dcPensions');

        $age = $user->date_of_birth?->age;

        // Each pension's owner is this user: hand it over, so isInPayment never
        // reloads it when no date of birth is recorded (lazy-load guard).
        $income = $user->dbPensions
            ->filter(fn ($pension): bool => $pension->setRelation('user', $user)->isInPayment($age))
            ->sum(fn ($pension): float => (float) ($pension->accrued_annual_pension ?? 0));

        $statePension = $user->statePension;
        if ($statePension && $statePension->already_receiving) {
            $income += (float) ($statePension->state_pension_forecast_annual ?? 0);
        }

        // Taxable drawdown from a DC pot. `annual_drawdown_income` is summed
        // wherever it is greater than zero: the recorded income is the fact, so
        // this does not gate on `has_flexibly_accessed` — a flag can lag or be
        // unset while the figure the user actually stated is the one that must
        // enter the tax computation. The tax-free lump sum (`pcls_taken`) is
        // recorded on the same row and is never income (FA 2004 Sch 29 para 1),
        // so it is not read here or anywhere else that taxes.
        $income += $user->dcPensions
            ->sum(fn ($pension): float => (float) ($pension->annual_drawdown_income ?? 0));

        return (float) $income;
    }

    /**
     * The user's take-home pay: the Income tab's figure (UserProfileService::
     * incomeAndTaxFor `net_income`; CSJ 2026-10-02, one income figure). This
     * summed the users columns and taxed them itself, so a pension being paid
     * never counted and rent was the stored figure, not the rental profit.
     */
    protected function resolveNetAnnualIncome(User $user): float
    {
        return (float) (app(UserProfileService::class)->incomeAndTaxFor($user)['net_income'] ?? 0);
    }
}
