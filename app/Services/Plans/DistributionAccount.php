<?php

declare(strict_types=1);

namespace App\Services\Plans;

/**
 * In-memory allocation tracker for distributing disposable income across plan actions.
 *
 * Each plan agent requests an allocation from the shared disposable income pool.
 * The DistributionAccount tracks what has been allocated and what remains,
 * preventing over-allocation and providing a clear audit trail.
 */
class DistributionAccount
{
    /** @var array<string, float> */
    private array $allocations = [];

    private float $remaining;

    public function __construct(
        private readonly float $initialBalance
    ) {
        $this->remaining = max(0.0, $initialBalance);
    }

    /**
     * Allocate an amount for a given label.
     *
     * If the requested amount exceeds the remaining balance, only the
     * remaining balance is allocated (capped allocation). Returns the
     * actual amount allocated.
     */
    public function allocate(string $label, float $amount): float
    {
        if ($amount <= 0 || $this->remaining <= 0) {
            return 0.0;
        }

        $actual = min($amount, $this->remaining);
        $actual = round($actual, 2);

        $this->allocations[$label] = round(($this->allocations[$label] ?? 0.0) + $actual, 2);
        $this->remaining = round($this->remaining - $actual, 2);

        return $actual;
    }

    /**
     * Get the unallocated balance remaining.
     */
    public function remaining(): float
    {
        return $this->remaining;
    }

    /**
     * Get all allocations made so far.
     *
     * @return array<string, float>
     */
    public function getAllocations(): array
    {
        return $this->allocations;
    }

    /**
     * Get the total amount allocated across all labels.
     */
    public function totalAllocated(): float
    {
        return round(array_sum($this->allocations), 2);
    }

    /**
     * Get the initial balance this account was created with.
     */
    public function initialBalance(): float
    {
        return $this->initialBalance;
    }

    /**
     * Reset the account back to its initial balance, clearing all allocations.
     */
    public function reset(): void
    {
        $this->allocations = [];
        $this->remaining = max(0.0, $this->initialBalance);
    }

    /**
     * Check if the account has been fully exhausted (nothing remaining).
     */
    public function isExhausted(): bool
    {
        return $this->remaining <= 0;
    }
}
