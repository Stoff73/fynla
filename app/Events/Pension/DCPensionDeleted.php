<?php

declare(strict_types=1);

namespace App\Events\Pension;

use App\Models\User;

class DCPensionDeleted
{
    public function __construct(
        public readonly int $entityId,
        public readonly User $user,
        public readonly string $reason,
    ) {}
}
