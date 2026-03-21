<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\LifeEvent;
use App\Services\Goals\GoalsProjectionService;
use App\Services\Goals\LifeEventIntegrationService;
use App\Services\Investment\MonteCarloSimulator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Clears Monte Carlo simulation cache and goals projection cache
 * when life events change, ensuring projections reflect the latest event data.
 */
class LifeEventMonteCarloObserver
{
    public function __construct(
        private readonly MonteCarloSimulator $simulator,
        private readonly GoalsProjectionService $projectionService
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

            // Clear recommendation caches for affected modules
            try {
                $integrationService = app(LifeEventIntegrationService::class);
                $affectedModules = $integrationService->getEventModules($event);

                foreach ($affectedModules as $module) {
                    Cache::tags([$module, 'user_' . $event->user_id])->flush();
                }
            } catch (\Exception $e) {
                // Log but don't fail — cache clearing is best-effort
                Log::warning('Failed to clear module caches for life event', [
                    'event_id' => $event->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }
}
