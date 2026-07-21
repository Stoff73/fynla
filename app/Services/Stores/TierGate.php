<?php

declare(strict_types=1);

namespace App\Services\Stores;

use App\Models\User;

interface TierGate
{
    /**
     * Whether $user is permitted to create another record of $entityKey
     * given the current count $currentCount.
     */
    public function canCreate(User $user, string $entityKey, int $currentCount): bool;

    /**
     * Soft limit for an upgrade prompt, or null for unlimited.
     */
    public function softLimit(User $user, string $entityKey): ?int;

    /**
     * Hard limit beyond which create() throws, or null for unlimited.
     */
    public function hardLimit(User $user, string $entityKey): ?int;
}
