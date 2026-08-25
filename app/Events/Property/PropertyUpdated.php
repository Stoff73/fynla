<?php

declare(strict_types=1);

namespace App\Events\Property;

use App\Models\Property;
use App\Models\User;
use App\Services\Stores\IngestSource;

class PropertyUpdated
{
    /**
     * @param  array<string, mixed>  $changes  Eloquent getDirty() diff captured pre-save — the NEW values.
     * @param  array<string, mixed>  $previous  the values those same keys held BEFORE the write.
     *                                          A listener reacting to a change of ownership needs the
     *                                          party who has just been removed, and `$changes` only
     *                                          carries the party who replaced them.
     */
    public function __construct(
        public readonly Property $entity,
        public readonly array $changes,
        public readonly User $user,
        public readonly IngestSource $source,
        public readonly array $previous = [],
    ) {}
}
