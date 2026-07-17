<?php

declare(strict_types=1);

namespace App\Services\Payment;

use App\Models\Subscription;
use App\Models\User;
use App\Services\Stores\TierConfigurationStore;
use Illuminate\Support\Collection;

class SubscriptionEntitlementService
{
    public function activePremiumFor(User $user): ?Subscription
    {
        return $this->activePaidFrom($this->candidatesFor($user), null, true);
    }

    public function activePaidFor(User $user, ?int $preferredSubscriptionId = null): ?Subscription
    {
        return $this->activePaidFrom($this->candidatesFor($user), $preferredSubscriptionId);
    }

    /** @param Collection<int, Subscription> $subscriptions */
    public function activePaidFrom(
        Collection $subscriptions,
        ?int $preferredSubscriptionId = null,
        bool $premiumOnly = false,
    ): ?Subscription {
        $eligible = fn (Subscription $subscription): bool => $subscription->plan !== 'free'
            && $subscription->isActive()
            && (! $premiumOnly
                || TierConfigurationStore::canonicalPlanForEntitlement($subscription->plan) === 'premium');

        if ($preferredSubscriptionId !== null) {
            $preferred = $subscriptions->firstWhere('id', $preferredSubscriptionId);

            return $preferred instanceof Subscription && $eligible($preferred) ? $preferred : null;
        }

        return $subscriptions->sortByDesc('id')->first($eligible);
    }

    /** @return Collection<int, Subscription> */
    private function candidatesFor(User $user): Collection
    {
        return $user->subscriptions()
            ->whereIn('status', ['active', 'cancelled', 'past_due'])
            ->orderByDesc('id')
            ->get();
    }
}
