<?php

declare(strict_types=1);

namespace App\Services\Billing;

use App\Data\Billing\ResolvedEntitlement;
use App\Models\Subscription;
use App\Models\User;
use App\Services\Stores\TierConfigurationStore;

final class RevolutEntitlementAdapter
{
    /** @return list<ResolvedEntitlement> */
    public function liveEntitlementsFor(User $user): array
    {
        return $user->subscriptions()
            ->whereIn('status', ['active', 'cancelled', 'past_due'])
            ->get()
            ->filter(fn (Subscription $subscription): bool => $this->confersPremium($subscription))
            ->map(fn (Subscription $subscription): ResolvedEntitlement => new ResolvedEntitlement(
                tier: 'premium',
                provider: 'revolut',
                status: $subscription->status,
                renews: $subscription->status === 'active' && (bool) $subscription->auto_renew,
                periodEndsAt: $subscription->current_period_end?->toImmutable(),
            ))
            ->values()
            ->all();
    }

    private function confersPremium(Subscription $subscription): bool
    {
        return TierConfigurationStore::canonicalPlanForEntitlement($subscription->plan) === 'premium'
            && $subscription->isActive();
    }
}
