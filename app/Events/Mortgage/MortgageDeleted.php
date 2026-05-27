<?php

declare(strict_types=1);

namespace App\Events\Mortgage;

use App\Models\Mortgage;
use Illuminate\Foundation\Events\Dispatchable;

final class MortgageDeleted
{
    use Dispatchable;

    public function __construct(
        public readonly Mortgage $mortgage,
        public readonly int $userId,
        public readonly bool $force,
    ) {}
}
