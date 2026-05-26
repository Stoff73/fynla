<?php

declare(strict_types=1);

namespace App\Events\Pension;

use App\Models\StatePension;
use App\Models\User;
use App\Services\Stores\IngestSource;

class StatePensionUpserted
{
    public function __construct(
        public readonly StatePension $entity,
        public readonly User $user,
        public readonly IngestSource $source,
        public readonly bool $wasRecentlyCreated,
    ) {}
}
