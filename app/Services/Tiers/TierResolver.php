<?php

declare(strict_types=1);

namespace App\Services\Tiers;

use App\Models\User;
use App\Services\Billing\PremiumEntitlementResolver;

class TierResolver
{
    public function __construct(
        private readonly PremiumEntitlementResolver $entitlements,
    ) {}

    /**
     * Canonical gating tier for $user. Entitlement is PROVIDER TRUTH ONLY: a live
     * Revolut subscription or a live Apple entitlement, resolved by
     * PremiumEntitlementResolver. Preview users resolve to 'free', and so does
     * everyone with no live grant.
     *
     * `users.tier` is NOT consulted here and must not be. It is a query cache
     * maintained by the provider event handlers (AuthController, the subscription
     * renewal and expiry services) — never a grant. Setting it alone confers
     * nothing, by design:
     *
     *   codex/plans/ios/2026-07-14-ios-04-storekit-entitlements.md:95-96
     *     "Make TierResolver use this resolver for paid access and otherwise
     *      return Free. A stale users.tier='premium' without a live provider
     *      grant must not grant Premium."
     *     "Provider event handlers may maintain users.tier as a query cache, but
     *      capability checks use the resolver."
     *
     * This docblock previously read "Explicit users.tier wins", which predated
     * that decision and had been contradicting the code ever since (W-0018). If
     * you are here because you want a manual tier override, it goes through a
     * Subscription row or a PremiumEntitlement — not this column. Reversing that
     * would turn every writer of `users.tier`, including the test-support
     * endpoint, into an entitlement grant.
     */
    public function resolve(User $user): string
    {
        return $this->entitlements->resolve($user)->tier;
    }

    // isGrandfatheredLegacyPaid() lived here until 2026-09-08. It protected the
    // row caps of subscribers on the plans sold before the tier scheme; those
    // subscriptions are premium in the data now (unbounded quotas), so there is
    // nothing left to grandfather.
}
