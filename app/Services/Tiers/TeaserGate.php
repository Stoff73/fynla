<?php

declare(strict_types=1);

namespace App\Services\Tiers;

use App\Models\User;
use App\Services\Stores\TierConfigurationStore;

/**
 * Generic capability teaser-gate.
 *
 * Consults TierConfigurationStore (the single source of truth) via the
 * TierResolver boundary. Estate is the only SP2 consumer.
 */
class TeaserGate
{
    public function __construct(
        private readonly TierConfigurationStore $store,
        private readonly TierResolver $resolver,
    ) {}

    /**
     * Returns 'full' | 'teaser' | 'none' for a teaser-capable capability key.
     */
    public function mode(User $user, string $capabilityKey): string
    {
        return $this->store->capabilityFor($this->resolver->resolve($user), $capabilityKey);
    }

    public function isFull(User $user, string $capabilityKey): bool
    {
        return $this->mode($user, $capabilityKey) === 'full';
    }
}
