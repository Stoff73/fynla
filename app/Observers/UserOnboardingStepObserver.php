<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\User;
use App\Services\Mobile\MobileDashboardAggregator;

/**
 * The mobile dashboard is fetched (and cached per-user) the instant a
 * campaign registrant lands — BEFORE the first Fyn turn stamps
 * onboarding_fyn_step — so the mid-walk unlock suppression
 * (NextActionsService::midWalk) was defeated by a stale cache for the full
 * TTL (live 2026-07-23, user 292). Any step transition, from any write
 * path, clears the cache here — the one place that sees them all.
 */
final class UserOnboardingStepObserver
{
    public function __construct(
        private readonly MobileDashboardAggregator $aggregator,
    ) {}

    public function saved(User $user): void
    {
        if ($user->wasChanged('onboarding_fyn_step')) {
            $this->aggregator->clearCache($user->id);
        }
    }
}
