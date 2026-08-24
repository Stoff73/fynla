<?php

declare(strict_types=1);

namespace App\Traits;

use App\Models\User;
use App\Services\UKTaxCalculator;

trait ResolvesIncome
{
    /**
     * Resolve gross annual income from all user income sources.
     */
    protected function resolveGrossAnnualIncome(User $user): float
    {
        return (float) ($user->annual_employment_income ?? 0)
            + (float) ($user->annual_self_employment_income ?? 0)
            + (float) ($user->annual_rental_income ?? 0)
            + (float) ($user->annual_dividend_income ?? 0)
            + (float) ($user->annual_interest_income ?? 0)
            + (float) ($user->annual_other_income ?? 0)
            + (float) ($user->annual_trust_income ?? 0);
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
        $age = $user->date_of_birth?->age;

        $income = $user->dbPensions
            ->filter(fn ($pension): bool => $pension->isInPayment($age))
            ->sum(fn ($pension): float => (float) ($pension->accrued_annual_pension ?? 0));

        $statePension = $user->statePension;
        if ($statePension && $statePension->already_receiving) {
            $income += (float) ($statePension->state_pension_forecast_annual ?? 0);
        }

        return (float) $income;
    }

    /**
     * Resolve net annual income after UK tax using UKTaxCalculator.
     *
     * Requires the consuming class to have a UKTaxCalculator dependency
     * accessible via $this->taxCalculator.
     */
    protected function resolveNetAnnualIncome(User $user): float
    {
        $employmentIncome = (float) ($user->annual_employment_income ?? 0);
        $selfEmploymentIncome = (float) ($user->annual_self_employment_income ?? 0);
        $rentalIncome = (float) ($user->annual_rental_income ?? 0);
        $dividendIncome = (float) ($user->annual_dividend_income ?? 0);
        $interestIncome = (float) ($user->annual_interest_income ?? 0);
        $otherIncome = (float) ($user->annual_other_income ?? 0) + (float) ($user->annual_trust_income ?? 0);

        $grossIncome = $employmentIncome + $selfEmploymentIncome + $rentalIncome
            + $dividendIncome + $interestIncome + $otherIncome;

        if ($grossIncome <= 0) {
            return 0.0;
        }

        // Adjusted Net Income deductions for PA-taper consistency (HMRC ITA 2007 s58).
        $pensionContributions = 0.0;
        if ($user->relationLoaded('dcPensions') || $user->dcPensions()->exists()) {
            foreach ($user->dcPensions as $pension) {
                $salary = (float) ($pension->annual_salary ?? 0);
                $employeePercent = (float) ($pension->employee_contribution_percent ?? 0);
                $pensionContributions += $salary * ($employeePercent / 100);
            }
        }
        $giftAidGross = $user->is_gift_aid
            ? (float) ($user->annual_charitable_donations ?? 0) * 1.25
            : 0.0;

        $taxResult = $this->getIncomeTaxCalculator()->calculateNetIncome(
            $employmentIncome,
            $selfEmploymentIncome,
            $rentalIncome,
            $dividendIncome,
            $interestIncome,
            $otherIncome,
            $pensionContributions,
            $giftAidGross
        );

        return (float) ($taxResult['net_income'] ?? 0);
    }

    /**
     * Get the UKTaxCalculator instance.
     *
     * Override this method if the property name differs from $taxCalculator.
     */
    protected function getIncomeTaxCalculator(): UKTaxCalculator
    {
        return $this->taxCalculator;
    }
}
