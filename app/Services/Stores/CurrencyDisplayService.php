<?php

declare(strict_types=1);

namespace App\Services\Stores;

use App\Models\User;
use App\Services\Tiers\TierResolver;

/**
 * SP2 PR8 §12 — Currency display mode resolver.
 *
 * Reads the per-tier currency_display_mode from TierConfigurationStore
 * (single source of truth). SP1 §9.2 consumers MUST call this service —
 * never hardcode tier → mode mappings.
 *
 * Modes:
 *   'gbp_only'    — show values in GBP only (Free)
 *   'user_choice' — allow user-selected display currency (Premium)
 *
 * Preview users and admins are NOT in tiers; they resolve to 'free' via
 * TierResolver and therefore get 'gbp_only' by default.
 */
class CurrencyDisplayService
{
    public function __construct(
        private readonly TierConfigurationStore $store,
        private readonly TierResolver $resolver,
    ) {}

    /**
     * Return the currency display mode for the given user.
     * Returns 'gbp_only' | 'user_choice'.
     */
    public function modeFor(User $user): string
    {
        $tier = $this->resolver->resolve($user);

        return $this->store->forTier($tier)->currency_display_mode;
    }

    /**
     * Convenience: returns true when the user may choose their display currency.
     */
    public function canChooseCurrency(User $user): bool
    {
        return $this->modeFor($user) === 'user_choice';
    }
}
