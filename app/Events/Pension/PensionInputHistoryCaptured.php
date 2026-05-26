<?php

declare(strict_types=1);

namespace App\Events\Pension;

use App\Models\User;
use App\Services\Stores\IngestSource;

class PensionInputHistoryCaptured
{
    public function __construct(
        public readonly User $user,
        /** @var array<string, float> tax_year => pension_input_amount */
        public readonly array $written,
        public readonly IngestSource $source,
    ) {}
}
