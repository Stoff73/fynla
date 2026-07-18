<?php

declare(strict_types=1);

namespace App\Services\Billing;

use App\Data\Billing\ResolvedEntitlement;
use App\Models\Subscription;
use App\Models\User;
use App\Services\Stores\TierConfigurationStore;

final class RevolutEntitlementAdapter
{
    /** @return list<array{entitlement: ResolvedEntitlement, subscription: Subscription}> */
    public function liveCandidatesFor(User $user): array
    {
        return $user->subscriptions()
            ->whereIn('status', ['active', 'cancelled', 'past_due'])
            ->get()
            ->filter(fn (Subscription $subscription): bool => $this->confersPremium($subscription))
            ->map(fn (Subscription $subscription): array => [
                'entitlement' => new ResolvedEntitlement(
                    tier: 'premium',
                    provider: 'revolut',
                    status: $subscription->status,
                    renews: $subscription->status === 'active' && (bool) $subscription->auto_renew,
                    periodEndsAt: $subscription->current_period_end?->toImmutable(),
                ),
                'subscription' => $subscription,
            ])
            ->values()
            ->all();
    }

    private function confersPremium(Subscription $subscription): bool
    {
        return TierConfigurationStore::canonicalPlanForEntitlement($subscription->plan) === 'premium'
            && $subscription->isActive();
    }
}
