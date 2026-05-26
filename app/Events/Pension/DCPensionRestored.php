<?php

declare(strict_types=1);

namespace App\Events\Pension;

use App\Models\DCPension;
use App\Models\User;

class DCPensionRestored
{
    public function __construct(
        public readonly DCPension $entity,
        public readonly User $user,
    ) {}
}
