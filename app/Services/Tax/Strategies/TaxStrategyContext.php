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
     * @param  float|null  $pensionMoney  Net money the user can put into
     *                                    pensions this year
     *                                    (PensionAffordability), or null
     *                                    when it is not known yet.
     */
    public function __construct(
        public readonly User $user,
        public readonly ?TaxStrategyOverridesDTO $overrides,
        public readonly ?TaxStrategyHouseholdInput $household,
        public readonly string $mode,
        public readonly ?float $isaPoolCap = null,
        public readonly float $pensionPaidElsewhere = 0.0,
        public readonly ?float $pensionMoney = null,
    ) {}

    public function withIsaPoolCap(float $isaPoolCap): self
    {
        return new self($this->user, $this->overrides, $this->household, $this->mode, $isaPoolCap, $this->pensionPaidElsewhere, $this->pensionMoney);
    }

    /**
     * The gross pension contribution the plan's own pension item already
     * makes, so a savings item is priced on the income left after it: the
     * plan prices the pension first (29 Sep 2026, SaveTax matrix E1).
     */
    public function withPensionPaidElsewhere(float $gross): self
    {
        return new self($this->user, $this->overrides, $this->household, $this->mode, $this->isaPoolCap, $gross, $this->pensionMoney);
    }

    /**
     * The most a pension payment can be, gross, from the money the user has:
     * a relief-at-source payment of the money is grossed up at the basic
     * rate by the provider (FA 2004 s192). Null when the money is not known,
     * so the caller leaves the suggestion as it was.
     */
    public function pensionFundableGross(float $basicReliefRate): ?float
    {
        return $this->pensionMoney === null || $basicReliefRate >= 1
            ? null
            : round($this->pensionMoney / (1 - $basicReliefRate), 2);
    }
}
