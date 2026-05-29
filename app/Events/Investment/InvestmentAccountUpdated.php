<?php

declare(strict_types=1);

namespace App\Events\Investment;

use App\Models\Investment\InvestmentAccount;
use App\Models\User;
use App\Services\Stores\IngestSource;

class InvestmentAccountUpdated
{
    /**
     * @param  array<string, mixed>  $changes  Eloquent getDirty() diff captured pre-save.
     */
    public function __construct(
        public readonly InvestmentAccount $entity,
        public readonly array $changes,
        public readonly User $user,
        public readonly IngestSource $source,
    ) {}
}
