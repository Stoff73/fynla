<?php

declare(strict_types=1);

namespace App\Events\Property;

use App\Models\User;

class PropertyDeleted
{
    public function __construct(
        public readonly int $entityId,
        public readonly User $user,
        public readonly string $reason,
    ) {}
}
