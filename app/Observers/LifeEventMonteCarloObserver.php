<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\LifeEvent;
use App\Services\Cache\CacheInvalidationService;
use App\Services\Goals\GoalsProjectionService;
use App\Services\Investment\MonteCarloSimulator;

/**
 * Clears Monte Carlo simulation cache and goals projection cache
 * when life events change, ensuring projections reflect the latest event data.
 */
class LifeEventMonteCarloObserver
{
    public function __construct(
        private readonly MonteCarloSimulator $simulator,
        private readonly GoalsProjectionService $projectionService,
        private readonly CacheInvalidationService $cacheInvalidation
    ) {}

    public function created(LifeEvent $event): void
    {
        $this->clearUserCache($event);
    }

    public function updated(LifeEvent $event): void
    {
        $this->clearUserCache($event);
    }

    public function deleted(LifeEvent $event): void
    {
        $this->clearUserCache($event);
    }

    private function clearUserCache(LifeEvent $event): void
    {
        if ($event->user_id) {
            $this->simulator->clearUserCache($event->user_id);
            $this->projectionService->clearCache($event->user_id);
            $this->cacheInvalidation->invalidateForUser($event->user_id);
        }
    }
}
