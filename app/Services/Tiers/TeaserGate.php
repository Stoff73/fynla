<?php

declare(strict_types=1);

namespace App\Services\Tiers;

use App\Models\User;
use App\Services\Stores\TierConfigurationStore;

/**
 * Generic capability teaser-gate.
 *
 * Consults TierConfigurationStore (the single source of truth) via the
 * TierResolver boundary. Estate and the Holistic Plan consume this gate.
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

    /**
     * Whether the user may use a capability outright — the tier's `full` mode
     * plus the admin / preview-persona bypass every consumer applies.
     *
     * The bypass used to be re-implemented per call site, and the copies had
     * drifted: the write path allowed admins through while the payload told
     * their client the capability was unavailable, and the Fyn capture handlers
     * applied no check at all — so Fyn wrote detailed expenditure for a Free
     * user that their own Expenditure page then refused to show them.
     */
    public function allows(User $user, string $capabilityKey): bool
    {
        return $user->is_admin
            || $user->is_preview_user
            || $this->isFull($user, $capabilityKey);
    }
}
