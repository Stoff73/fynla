<?php

declare(strict_types=1);

namespace App\Events\Mortgage;

use App\Models\Mortgage;
use App\Models\User;
use App\Services\Stores\IngestSource;

class MortgageUpdated
{
    /**
     * @param  array<string, mixed>  $changes  Eloquent getDirty() diff captured pre-save.
     */
    public function __construct(
        public readonly Mortgage $entity,
        public readonly array $changes,
        public readonly User $user,
        public readonly IngestSource $source,
    ) {}
}
