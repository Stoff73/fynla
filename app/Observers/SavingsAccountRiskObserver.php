<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\SavingsAccount;

/**
 * Observer that triggers risk recalculation when SavingsAccount changes.
 *
 * Monitors: emergency fund balance changes
 */
class SavingsAccountRiskObserver extends RiskRecalculationObserver
{
    public function created(SavingsAccount $account): void
    {
        if ($account->is_emergency_fund) {
            $this->dispatchRecalculation($account->user_id, 'savings_created');
        }
    }

    public function updated(SavingsAccount $account): void
    {
        $changedFields = array_keys($account->getChanges());
        $relevantChanges = array_intersect($changedFields, ['current_balance', 'is_emergency_fund']);

        if (! empty($relevantChanges) && ($account->is_emergency_fund || $account->getOriginal('is_emergency_fund'))) {
            $this->dispatchRecalculation($account->user_id, 'savings_updated');
        }
    }

    public function deleted(SavingsAccount $account): void
    {
        if ($account->is_emergency_fund) {
            $this->dispatchRecalculation($account->user_id, 'savings_deleted');
        }
    }
}
