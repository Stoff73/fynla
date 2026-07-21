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
    /**
     * @param  float|null  $isaPoolCap  When non-null, the maximum slice of the
     *                                  user's overall ISA allowance this
     *                                  evaluation may assume is still free.
     *                                  Set by the shared-allowance allocation
     *                                  pass when several ISA-consuming
     *                                  strategies draw on the same pool; null
     *                                  (the default) means "size against the
     *                                  full remaining allowance" — the
     *                                  original pass-1 behaviour.
     */
    public function __construct(
        public readonly User $user,
        public readonly ?TaxStrategyOverridesDTO $overrides,
        public readonly ?TaxStrategyHouseholdInput $household,
        public readonly string $mode,
        public readonly ?float $isaPoolCap = null,
    ) {}

    public function withIsaPoolCap(float $isaPoolCap): self
    {
        return new self($this->user, $this->overrides, $this->household, $this->mode, $isaPoolCap);
    }
}
