<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\Property;

/**
 * Observer for Property model that triggers risk profile recalculation
 * when property values change (affects net worth and capacity for loss).
 */
class PropertyRiskObserver extends RiskRecalculationObserver
{
    /**
     * Fields that affect risk calculation when changed.
     */
    private const RISK_RELEVANT_FIELDS = [
        'current_value',
        'purchase_price',
        'ownership_percentage',
    ];

    /**
     * Handle the Property "created" event.
     */
    public function created(Property $property): void
    {
        $this->dispatchRecalculation($property->user_id, 'property_created');
    }

    /**
     * Handle the Property "updated" event.
     */
    public function updated(Property $property): void
    {
        if ($this->hasRiskRelevantChanges($property)) {
            $this->dispatchRecalculation($property->user_id, 'property_updated');
        }
    }

    /**
     * Handle the Property "deleted" event.
     */
    public function deleted(Property $property): void
    {
        $this->dispatchRecalculation($property->user_id, 'property_deleted');
    }

    /**
     * Check if any risk-relevant fields were changed.
     */
    private function hasRiskRelevantChanges(Property $property): bool
    {
        foreach (self::RISK_RELEVANT_FIELDS as $field) {
            if ($property->isDirty($field)) {
                return true;
            }
        }

        return false;
    }
}
