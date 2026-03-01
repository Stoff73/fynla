<?php

declare(strict_types=1);

namespace App\Services\Plans;

use App\Models\User;
use App\Services\UserProfile\UserProfileService;

/**
 * Fetches the user's disposable income from the income tab.
 *
 * This does NOT recalculate disposable income — it retrieves the same
 * values already computed by UserProfileService (income tab).
 */
class DisposableIncomeAccessor
{
    public function __construct(
        private readonly UserProfileService $userProfileService
    ) {}

    /**
     * Get the user's disposable income figures.
     *
     * Returns the annual and monthly disposable income as calculated
     * on the user's income tab (net income minus expenditure).
     *
     * @return array{annual: float, monthly: float, net_income: float, annual_expenditure: float}
     */
    public function getForUser(User $user): array
    {
        $profile = $this->userProfileService->getCompleteProfile($user);
        $incomeData = $profile['income_occupation'] ?? [];

        $netIncome = (float) ($incomeData['net_income'] ?? 0);
        $annualExpenditure = (float) ($incomeData['annual_expenditure'] ?? 0);
        $disposableIncome = (float) ($incomeData['disposable_income'] ?? 0);
        $monthlyDisposable = (float) ($incomeData['monthly_disposable'] ?? 0);

        return [
            'annual' => round($disposableIncome, 2),
            'monthly' => round($monthlyDisposable, 2),
            'net_income' => round($netIncome, 2),
            'annual_expenditure' => round($annualExpenditure, 2),
        ];
    }

    /**
     * Get the monthly disposable income for a user.
     *
     * Convenience method for plan services that only need the monthly figure.
     */
    public function getMonthlyForUser(User $user): float
    {
        return $this->getForUser($user)['monthly'];
    }

    /**
     * Get the annual disposable income for a user.
     *
     * Convenience method for plan services that only need the annual figure.
     */
    public function getAnnualForUser(User $user): float
    {
        return $this->getForUser($user)['annual'];
    }
}
