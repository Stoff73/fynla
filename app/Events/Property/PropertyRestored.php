<?php

declare(strict_types=1);

namespace App\Events\Property;

use App\Models\Property;
use App\Models\User;

class PropertyRestored
{
    public function __construct(
        public readonly Property $entity,
        public readonly User $user,
    ) {}
}
