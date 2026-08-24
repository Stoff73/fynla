<?php

declare(strict_types=1);

namespace App\Services\Tiers;

use App\Models\User;
use App\Services\Billing\PremiumEntitlementResolver;
use App\Services\Stores\TierConfigurationStore;

class TierResolver
{
    private const LEGACY_PAID_PLANS = ['student', 'standard', 'family', 'pro'];

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
     * renewal and expiry services), plus a migration-cohort marker read by
     * isGrandfatheredLegacyPaid() below — never a grant. Setting it alone confers
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

    /**
     * True when the user is a legacy *paid* subscriber not yet assigned a
     * new tier (per-cohort CSJ conversion decision pending). The gate must not
     * block their existing-data creates.
     *
     * The citation that used to sit here, "spec §5.2/§22 A9", pointed at a
     * document that does not exist in this repo (W-0018). It is removed rather
     * than reproduced: it was the sole written authority for the abandoned
     * reading in which `users.tier` grants entitlement, and chasing it cost real
     * time. The same phantom reference survives in the comments of
     * tests/Unit/Services/Tiers/TierResolverTest.php — left there deliberately,
     * as those comments describe the tests' own history.
     *
     * This is the one legitimate read of `users.tier`, and it is asking a
     * different question from resolve(): "has this user been migrated onto the
     * new tier scheme yet?" A canonical value present means yes, so they are not
     * a grandfathering candidate. That is the column used as a cohort marker,
     * which is what a cache is for — it grants nothing on its own.
     */
    public function isGrandfatheredLegacyPaid(User $user): bool
    {
        if (in_array($user->tier, TierConfigurationStore::TIERS, true)) {
            return false;
        }
        if ($user->is_preview_user) {
            return false;
        }
        if (! in_array($user->plan ?? '', self::LEGACY_PAID_PLANS, true)) {
            return false;
        }

        $subscription = $user->relationLoaded('subscription')
            ? $user->subscription
            : $user->subscription()->first();

        return $subscription !== null
            && in_array($subscription->plan ?? '', self::LEGACY_PAID_PLANS, true);
    }
}
