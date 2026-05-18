<?php

declare(strict_types=1);

namespace App\Events\Savings;

use App\Models\SavingsAccount;
use App\Models\User;
use App\Services\Stores\IngestSource;

class SavingsAccountCreated
{
    public function __construct(
        public readonly SavingsAccount $entity,
        public readonly User $user,
        public readonly IngestSource $source,
    ) {}
}
