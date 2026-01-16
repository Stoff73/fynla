<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\DCPension;

/**
 * Observer that triggers risk recalculation when DCPension changes.
 *
 * Monitors: current_fund_value changes (affects capacity for loss)
 */
class DCPensionRiskObserver extends RiskRecalculationObserver
{
    public function created(DCPension $pension): void
    {
        $this->dispatchRecalculation($pension->user_id, 'pension_created');
    }

    public function updated(DCPension $pension): void
    {
        $changedFields = array_keys($pension->getChanges());

        if (in_array('current_fund_value', $changedFields, true)) {
            $this->dispatchRecalculation($pension->user_id, 'pension_updated');
        }
    }

    public function deleted(DCPension $pension): void
    {
        $this->dispatchRecalculation($pension->user_id, 'pension_deleted');
    }
}
