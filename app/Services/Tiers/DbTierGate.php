<?php

declare(strict_types=1);

namespace App\Services\Tiers;

use App\Models\User;
use App\Services\Stores\TierConfigurationStore;
use App\Services\Stores\TierGate;

class DbTierGate implements TierGate
{
    public function __construct(
        private readonly TierConfigurationStore $store,
        private readonly TierResolver $resolver,
    ) {}

    public function canCreate(User $user, string $entityKey, int $currentCount): bool
    {
        if ($user->is_admin) {
            return true; // SP1 §14.2 allowlist
        }
        if ($user->is_preview_user) {
            return true; // preview personas sit entirely outside the gate (Rule #2)
        }
        if ($this->resolver->isGrandfatheredLegacyPaid($user)) {
            return true; // spec §4.4 — never narrow a grandfathered paid sub
        }

        $hard = $this->hardLimit($user, $entityKey);

        return $hard === null ? true : $currentCount < $hard;
    }

    public function softLimit(User $user, string $entityKey): ?int
    {
        // For row counts soft == hard (spec §8.1). The "soft" concept is
        // reserved for Fyn metering (PR 6), not entity counts.
        return $this->hardLimit($user, $entityKey);
    }

    public function hardLimit(User $user, string $entityKey): ?int
    {
        if ($user->is_preview_user) {
            return null; // preview personas are unlimited — never surface a cap (Rule #2)
        }

        return $this->store->capFor($this->resolver->resolve($user), $entityKey);
    }
}
