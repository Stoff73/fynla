<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\User;

/**
 * Observer that triggers risk recalculation when User profile changes.
 *
 * Monitors: income, employment, retirement age, expenditure
 */
class UserRiskObserver extends RiskRecalculationObserver
{
    /**
     * User fields that affect risk calculation
     */
    private array $relevantFields = [
        'annual_employment_income',
        'annual_self_employment_income',
        'annual_rental_income',
        'annual_dividend_income',
        'annual_interest_income',
        'annual_other_income',
        'annual_trust_income',
        'monthly_expenditure',
        'employment_status',
        'target_retirement_age',
        'date_of_birth',
    ];

    public function updated(User $user): void
    {
        $changedFields = array_keys($user->getChanges());
        $relevantChanges = array_intersect($changedFields, $this->relevantFields);

        if (! empty($relevantChanges)) {
            $this->dispatchRecalculation($user->id, 'user_updated');
        }
    }
}
