<?php

declare(strict_types=1);

namespace App\Events\Pension;

use App\Models\DBPension;
use App\Models\User;
use App\Services\Stores\IngestSource;

class DBPensionUpdated
{
    public function __construct(
        public readonly DBPension $entity,
        public readonly array $changes,
        public readonly User $user,
        public readonly IngestSource $source,
    ) {}
}
