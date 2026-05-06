<?php

declare(strict_types=1);

namespace App\Services\Tax;

use App\DataTransferObjects\TaxStrategyOverridesDTO;
use App\Models\User;

/**
 * Orchestrates the /tax-strategy dashboard payload + slider recalculations.
 *
 * - getDashboardPayload(User) — initial GET, returns the calculator output
 *   keyed for the frontend dashboard.
 * - recalculate(User, OverridesDTO) — POST /calculate, returns the calculator
 *   output with overrides applied (no DB writes).
 */
final class TaxStrategyService
{
    public function __construct(
        private readonly TaxStrategyCalculator $calculator,
    ) {}

    public function getDashboardPayload(User $user): array
    {
        return $this->calculator->calculate($user)->toArray();
    }

    public function recalculate(User $user, TaxStrategyOverridesDTO $overrides): array
    {
        return $this->calculator->calculate($user, $overrides)->toArray();
    }
}
