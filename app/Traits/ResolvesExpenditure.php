<?php

declare(strict_types=1);

namespace App\Traits;

use App\Models\ExpenditureProfile;
use App\Models\User;

trait ResolvesExpenditure
{
    /**
     * Resolve monthly expenditure from available data sources.
     *
     * Returns the amount and the source used, following a priority chain:
     * 1. ExpenditureProfile (Cashflow Profile)
     * 2. User.monthly_expenditure (Profile Monthly)
     * 3. User.annual_expenditure / 12 (Profile Annual)
     *
     * @return array{amount: float, source: string, label: string}
     */
    protected function resolveMonthlyExpenditure(User $user): array
    {
        $expenditureProfile = ExpenditureProfile::where('user_id', $user->id)->first();

        if ($expenditureProfile && $expenditureProfile->total_monthly_expenditure > 0) {
            return [
                'amount' => (float) $expenditureProfile->total_monthly_expenditure,
                'source' => 'expenditure_profile',
                'label' => 'Cashflow Profile',
            ];
        }

        if ($user->monthly_expenditure > 0) {
            return [
                'amount' => (float) $user->monthly_expenditure,
                'source' => 'user_monthly',
                'label' => 'Profile (Monthly)',
            ];
        }

        if ($user->annual_expenditure > 0) {
            return [
                'amount' => (float) ($user->annual_expenditure / 12),
                'source' => 'user_annual',
                'label' => 'Profile (Annual / 12)',
            ];
        }

        return [
            'amount' => 0.0,
            'source' => 'none',
            'label' => 'Not Set',
        ];
    }
}
