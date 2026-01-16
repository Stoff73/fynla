<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\Investment\InvestmentAccount;

/**
 * Observer that triggers risk recalculation when InvestmentAccount changes.
 *
 * Monitors: current_value changes (affects capacity for loss)
 */
class InvestmentAccountRiskObserver extends RiskRecalculationObserver
{
    public function created(InvestmentAccount $account): void
    {
        $this->dispatchRecalculation($account->user_id, 'investment_created');
    }

    public function updated(InvestmentAccount $account): void
    {
        $changedFields = array_keys($account->getChanges());

        if (in_array('current_value', $changedFields, true)) {
            $this->dispatchRecalculation($account->user_id, 'investment_updated');
        }
    }

    public function deleted(InvestmentAccount $account): void
    {
        $this->dispatchRecalculation($account->user_id, 'investment_deleted');
    }
}
