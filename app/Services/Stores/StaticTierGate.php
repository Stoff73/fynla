<?php

declare(strict_types=1);

namespace App\Services\Stores;

use App\Models\User;

/**
 * Interim TierGate impl with hardcoded sub-project-2 defaults per spec §13.
 * Sub-project 2 will replace this with a database-backed implementation
 * that reads from the freemium tier configuration table.
 *
 * NOT bound globally in pass 1: `users.tier` does not exist yet, so
 * resolveTier() resolves every user to 'free'. Binding this would cap all
 * real users to 3 savings accounts — a freemium change owned by sub-project 2.
 * AppServiceProvider keeps PermissiveTierGate bound until then.
 */
class StaticTierGate implements TierGate
{
    private const LIMITS = [
        'savings_account' => ['free' => 3, 'tier1' => null, 'tier2' => null, 'tier3' => null],
    ];

    public function canCreate(User $user, string $entityKey, int $currentCount): bool
    {
        $hard = $this->hardLimit($user, $entityKey);
        if ($hard === null) {
            return true;
        }

        return $currentCount < $hard;
    }

    public function softLimit(User $user, string $entityKey): ?int
    {
        return $this->hardLimit($user, $entityKey); // soft == hard until sub-project 2
    }

    public function hardLimit(User $user, string $entityKey): ?int
    {
        $tier = $this->resolveTier($user);

        return self::LIMITS[$entityKey][$tier] ?? null;
    }

    private function resolveTier(User $user): string
    {
        // Adjust to whatever User exposes for tier in pass-1 reality. If User
        // doesn't have a tier column yet, default to 'free' for everyone.
        return $user->tier ?? 'free';
    }
}
