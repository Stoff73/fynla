<?php

declare(strict_types=1);

namespace App\Events\Savings;

use App\Models\User;

class SavingsAccountDeleted
{
    public function __construct(
        public readonly int $entityId,
        public readonly User $user,
        public readonly string $reason,
    ) {}
}
