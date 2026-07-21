<?php

declare(strict_types=1);

namespace App\Events\Savings;

use App\Models\SavingsAccount;
use App\Models\User;

class SavingsAccountRestored
{
    public function __construct(
        public readonly SavingsAccount $entity,
        public readonly User $user,
    ) {}
}
