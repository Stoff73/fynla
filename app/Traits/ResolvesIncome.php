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
