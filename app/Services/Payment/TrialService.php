<?php

declare(strict_types=1);

namespace App\Services\Payment;

/** @deprecated Use SubscriptionExpiryService. Remove after one compatibility release. */
class TrialService
{
    public function __construct(
        private readonly SubscriptionExpiryService $subscriptionExpiryService,
    ) {}

    public function expireCancelledSubscriptions(): int
    {
        return $this->subscriptionExpiryService->expireCancelledSubscriptions();
    }
}
