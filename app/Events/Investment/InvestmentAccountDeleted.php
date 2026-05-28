<?php

declare(strict_types=1);

namespace App\Events\Investment;

use App\Models\Investment\InvestmentAccount;
use App\Models\User;
use App\Services\Stores\IngestSource;

class InvestmentAccountDeleted
{
    public function __construct(
        public readonly InvestmentAccount $entity,
        public readonly User $user,
        public readonly IngestSource $source,
        public readonly bool $force = false,
    ) {}
}
