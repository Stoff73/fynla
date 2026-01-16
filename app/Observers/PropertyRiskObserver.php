<?php

declare(strict_types=1);

namespace App\Observers;

use App\Jobs\RecalculateRiskProfileJob;
use App\Models\Property;
use Illuminate\Support\Facades\Cache;

/**
 * Observer for Property model that triggers risk profile recalculation
 * when property values change (affects net worth and capacity for loss).
 */
class PropertyRiskObserver
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
        // Only recalculate if risk-relevant fields changed
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

    /**
     * Dispatch the recalculation job with debouncing.
     */
    private function dispatchRecalculation(int $userId, string $trigger): void
    {
        // Debounce: only dispatch if not already queued recently
        $cacheKey = "risk_recalc_pending_{$userId}";

        if (Cache::has($cacheKey)) {
            return;
        }

        // Set cache to prevent duplicate dispatches for 5 seconds
        Cache::put($cacheKey, true, 5);

        // Dispatch with delay to allow multiple rapid changes to batch
        RecalculateRiskProfileJob::dispatch($userId, $trigger)
            ->delay(now()->addSeconds(5));
    }
}
