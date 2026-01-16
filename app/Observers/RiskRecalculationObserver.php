<?php

declare(strict_types=1);

namespace App\Observers;

use App\Jobs\RecalculateRiskProfileJob;
use Illuminate\Support\Facades\Cache;

/**
 * Base observer that triggers risk profile recalculation when relevant data changes.
 */
class RiskRecalculationObserver
{
    /**
     * Dispatch the recalculation job with debouncing
     */
    protected function dispatchRecalculation(int $userId, string $trigger): void
    {
        // Skip if no user ID
        if ($userId <= 0) {
            return;
        }

        // Use cache to debounce rapid changes (5 second window)
        $cacheKey = "risk_recalc_pending_{$userId}";

        if (Cache::has($cacheKey)) {
            return;
        }

        // Mark as pending for 5 seconds
        Cache::put($cacheKey, true, 5);

        // Dispatch job with delay
        RecalculateRiskProfileJob::dispatch($userId, $trigger)->delay(now()->addSeconds(5));
    }
}
