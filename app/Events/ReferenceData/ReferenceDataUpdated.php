<?php

declare(strict_types=1);

namespace App\Events\ReferenceData;

use Illuminate\Foundation\Events\Dispatchable;

class ReferenceDataUpdated
{
    use Dispatchable;

    public function __construct(
        public readonly string $entityKey,
        public readonly int $entityId,
        public readonly array $changedKeys,
        public readonly ?int $actorUserId,
    ) {}
}
