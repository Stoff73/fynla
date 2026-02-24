<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\LifeEvent;
use App\Services\Investment\MonteCarloSimulator;

/**
 * Clears Monte Carlo simulation cache when life events change,
 * ensuring projections reflect the latest event data.
 */
class LifeEventMonteCarloObserver
{
    public function __construct(
        private readonly MonteCarloSimulator $simulator
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
        }
    }
}
