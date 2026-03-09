<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\LifeEvent;

/**
 * Observer that triggers risk recalculation when significant life events change.
 *
 * Major life events (marriage, divorce, child, large income/expense changes)
 * can shift a user's risk capacity and should prompt a recalculation.
 */
class LifeEventRiskObserver extends RiskRecalculationObserver
{
    /**
     * Event types considered significant for risk profile recalculation.
     */
    private const RISK_RELEVANT_TYPES = [
        'wedding',
        'redundancy_payment',
        'inheritance',
        'business_sale',
        'property_sale',
        'pension_lump_sum',
        'large_purchase',
    ];

    public function created(LifeEvent $event): void
    {
        if ($this->isRiskRelevant($event)) {
            $this->dispatchRecalculation($event->user_id, 'life_event_created');
        }
    }

    public function updated(LifeEvent $event): void
    {
        if ($this->isRiskRelevant($event)) {
            $this->dispatchRecalculation($event->user_id, 'life_event_updated');
        }
    }

    public function deleted(LifeEvent $event): void
    {
        if ($this->isRiskRelevant($event)) {
            $this->dispatchRecalculation($event->user_id, 'life_event_deleted');
        }
    }

    private function isRiskRelevant(LifeEvent $event): bool
    {
        return in_array($event->event_type, self::RISK_RELEVANT_TYPES, true);
    }
}
