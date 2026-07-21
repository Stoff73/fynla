<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\Goal;
use App\Services\Cache\CacheInvalidationService;
use App\Services\Goals\GoalsProjectionService;

class GoalCacheObserver
{
    public function __construct(
        private readonly GoalsProjectionService $projectionService,
        private readonly CacheInvalidationService $cacheInvalidation
    ) {}

    public function created(Goal $goal): void
    {
        $this->clearCache($goal);
    }

    public function updated(Goal $goal): void
    {
        $this->clearCache($goal);
    }

    public function deleted(Goal $goal): void
    {
        $this->clearCache($goal);
    }

    private function clearCache(Goal $goal): void
    {
        if ($goal->user_id) {
            $this->projectionService->clearCache($goal->user_id);
            $this->cacheInvalidation->invalidateForUser($goal->user_id);
        }

        if ($goal->joint_owner_id) {
            $this->projectionService->clearCache($goal->joint_owner_id);
            $this->cacheInvalidation->invalidateForUser($goal->joint_owner_id);
        }
    }
}
