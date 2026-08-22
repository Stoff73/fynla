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
     * `expenditure_composition` carries the profile's own disclosure of what the
     * expenditure figure is made of (W-0140). The plans previously took the composed
     * total alone and printed it under a bare "Annual Expenditure" label, so a user
     * who had recorded nothing was shown their financial commitments as though they
     * were their spending. Composed here — not recalculated — so every plan surface
     * says the same thing as the profile it came from.
     *
     * @return array{annual: float, monthly: float, net_income: float, annual_expenditure: float, expenditure_composition: array{recorded_annual: float, commitments_annual: float, has_recorded_expenditure: bool, basis: ?string}}
     */
    public function getForUser(User $user): array
    {
        $profile = $this->userProfileService->getCompleteProfile($user);
        $incomeData = $profile['income_occupation'] ?? [];
        $presentation = $profile['expenditure']['presentation'] ?? [];

        $netIncome = (float) ($incomeData['net_income'] ?? 0);
        $annualExpenditure = (float) ($incomeData['annual_expenditure'] ?? 0);
        $disposableIncome = (float) ($incomeData['disposable_income'] ?? 0);
        $monthlyDisposable = (float) ($incomeData['monthly_disposable'] ?? 0);

        return [
            'annual' => round($disposableIncome, 2),
            'monthly' => round($monthlyDisposable, 2),
            'net_income' => round($netIncome, 2),
            'annual_expenditure' => round($annualExpenditure, 2),
            'expenditure_composition' => [
                'recorded_annual' => round((float) ($presentation['manual_annual_total'] ?? 0), 2),
                'commitments_annual' => round((float) ($presentation['commitments_annual_total'] ?? 0), 2),
                'has_recorded_expenditure' => (bool) ($presentation['has_recorded_expenditure'] ?? false),
                'basis' => $presentation['total_basis'] ?? null,
            ],
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
