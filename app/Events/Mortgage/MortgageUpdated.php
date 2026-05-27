<?php

declare(strict_types=1);

namespace App\Events\Mortgage;

use App\Models\Mortgage;
use Illuminate\Foundation\Events\Dispatchable;

final class MortgageUpdated
{
    use Dispatchable;

    /**
     * @param  array<string, array{0: mixed, 1: mixed}>  $dirty  Map of changed field → [from, to]
     */
    public function __construct(
        public readonly Mortgage $mortgage,
        public readonly int $userId,
        public readonly array $dirty,
    ) {}
}
