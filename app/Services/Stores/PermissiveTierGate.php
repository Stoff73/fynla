<?php

declare(strict_types=1);

namespace App\Services\Stores;

use App\Models\User;

class PermissiveTierGate implements TierGate
{
    public function canCreate(User $user, string $entityKey, int $currentCount): bool
    {
        return true;
    }

    public function softLimit(User $user, string $entityKey): ?int
    {
        return null;
    }

    public function hardLimit(User $user, string $entityKey): ?int
    {
        return null;
    }
}
