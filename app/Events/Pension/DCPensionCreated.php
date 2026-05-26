<?php

declare(strict_types=1);

namespace App\Events\Pension;

use App\Models\DCPension;
use App\Models\User;
use App\Services\Stores\IngestSource;

class DCPensionCreated
{
    public function __construct(
        public readonly DCPension $entity,
        public readonly User $user,
        public readonly IngestSource $source,
    ) {}
}
