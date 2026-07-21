<?php

declare(strict_types=1);

namespace App\Services\AI\Pointers;

use App\Models\User;

/** Inputs a handler needs to fetch. Immutable. */
final class FetchContext
{
    /** @param array<string,mixed> $params */
    public function __construct(
        public readonly User $user,
        public readonly string $query,
        public readonly array $params = [],
    ) {}
}
