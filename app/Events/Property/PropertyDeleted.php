<?php

declare(strict_types=1);

namespace App\Events\Property;

use App\Models\User;

class PropertyDeleted
{
    /**
     * @param  int|null  $jointOwnerId  the co-owner the deleted record reached, if any. The row is
     *                                  gone by the time a listener runs, and a figure derived from it
     *                                  has to be recomputed for BOTH parties, not just the one who
     *                                  pressed delete.
     */
    public function __construct(
        public readonly int $entityId,
        public readonly User $user,
        public readonly string $reason,
        public readonly ?int $jointOwnerId = null,
    ) {}
}
