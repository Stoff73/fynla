<?php

declare(strict_types=1);

namespace App\Services\Tax\Strategies;

use App\DataTransferObjects\TaxStrategyOverridesDTO;
use App\Models\TaxStrategyHouseholdInput;
use App\Models\User;

/**
 * Immutable bundle of state passed to every TaxStrategy::generate() call.
 * Keeps strategy signatures clean and lets us extend the input set without
 * touching every implementor.
 */
final class TaxStrategyContext
{
    public function __construct(
        public readonly User $user,
        public readonly ?TaxStrategyOverridesDTO $overrides,
        public readonly ?TaxStrategyHouseholdInput $household,
        public readonly string $mode,
    ) {}
}
