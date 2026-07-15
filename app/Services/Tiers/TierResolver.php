<?php

declare(strict_types=1);

namespace App\Services\Tiers;

use App\Models\User;
use App\Services\Stores\TierConfigurationStore;

class TierResolver
{
    private const LEGACY_PAID_PLANS = ['student', 'standard', 'family', 'pro'];

    /**
     * Canonical gating tier for $user. Spec §5.2: NO mechanical plan->tier
     * map. Explicit users.tier wins. Otherwise preview/no-sub/legacy-paid
     * all resolve to 'free' for gating arithmetic; legacy paid subscribers
     * are additionally flagged via isGrandfatheredLegacyPaid() so the gate
     * never narrows their existing access (consumed by DbTierGate, PR 3).
     */
    public function resolve(User $user): string
    {
        if (in_array($user->tier, TierConfigurationStore::TIERS, true)) {
            return $user->tier;
        }

        return 'free';
    }

    /**
     * True when the user is a legacy *paid* subscriber not yet assigned a
     * new tier (per-cohort CSJ conversion decision pending, spec §5.2/§22
     * A9). The gate must not block their existing-data creates.
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
