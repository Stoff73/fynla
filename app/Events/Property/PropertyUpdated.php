<?php

declare(strict_types=1);

namespace App\Events\Property;

use App\Models\Property;
use App\Models\User;
use App\Services\Stores\IngestSource;

class PropertyUpdated
{
    /**
     * @param  array<string, mixed>  $changes  Eloquent getDirty() diff captured pre-save.
     */
    public function __construct(
        public readonly Property $entity,
        public readonly array $changes,
        public readonly User $user,
        public readonly IngestSource $source,
    ) {}
}
