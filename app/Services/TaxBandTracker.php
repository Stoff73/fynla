<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Tracks Personal Allowance and tax band consumption as income sources are stacked.
 * Used for detailed per-income-type tax breakdowns.
 */
class TaxBandTracker
{
    private float $personalAllowance;

    private float $basicRateLimit;

    private float $higherRateLimit;

    private float $basicRate;

    private float $higherRate;

    private float $additionalRate;

    private float $usedPersonalAllowance = 0;

    private float $usedBasicBand = 0;

    private float $usedHigherBand = 0;

    public function __construct(array $taxConfig)
    {
        $this->personalAllowance = $taxConfig['personal_allowance'];
        $bands = $taxConfig['bands'];

        // Calculate absolute thresholds
        $this->basicRateLimit = $this->personalAllowance + $bands[0]['max'];
        $this->higherRateLimit = $this->personalAllowance + $bands[1]['max'];

        $this->basicRate = $bands[0]['rate'];
        $this->higherRate = $bands[1]['rate'];
        $this->additionalRate = $bands[2]['rate'];
    }

    /**
     * Get remaining personal allowance
     */
    public function getRemainingPersonalAllowance(): float
    {
        return max(0, $this->personalAllowance - $this->usedPersonalAllowance);
    }

    /**
     * Get remaining basic rate band capacity
     */
    public function getRemainingBasicBand(): float
    {
        $totalBasicBand = $this->basicRateLimit - $this->personalAllowance;

        return max(0, $totalBasicBand - $this->usedBasicBand);
    }

    /**
     * Get remaining higher rate band capacity
     */
    public function getRemainingHigherBand(): float
    {
        $totalHigherBand = $this->higherRateLimit - $this->basicRateLimit;

        return max(0, $totalHigherBand - $this->usedHigherBand);
    }

    /**
     * Allocate income to bands and return breakdown with tax calculations.
     * This consumes the bands for subsequent allocations.
     */
    public function allocateIncome(float $income): array
    {
        if ($income <= 0) {
            return $this->emptyAllocation();
        }

        $remaining = $income;
        $allocation = [
            'personal_allowance_used' => 0,
            'basic_rate' => ['taxable' => 0, 'tax' => 0, 'rate' => $this->basicRate],
            'higher_rate' => ['taxable' => 0, 'tax' => 0, 'rate' => $this->higherRate],
            'additional_rate' => ['taxable' => 0, 'tax' => 0, 'rate' => $this->additionalRate],
            'total_income_tax' => 0,
        ];

        // Use personal allowance first
        $paAvailable = $this->getRemainingPersonalAllowance();
        if ($paAvailable > 0 && $remaining > 0) {
            $paUsed = min($remaining, $paAvailable);
            $allocation['personal_allowance_used'] = $paUsed;
            $this->usedPersonalAllowance += $paUsed;
            $remaining -= $paUsed;
        }

        // Use basic rate band
        $basicAvailable = $this->getRemainingBasicBand();
        if ($basicAvailable > 0 && $remaining > 0) {
            $basicUsed = min($remaining, $basicAvailable);
            $allocation['basic_rate']['taxable'] = $basicUsed;
            $allocation['basic_rate']['tax'] = round($basicUsed * $this->basicRate, 2);
            $this->usedBasicBand += $basicUsed;
            $remaining -= $basicUsed;
        }

        // Use higher rate band
        $higherAvailable = $this->getRemainingHigherBand();
        if ($higherAvailable > 0 && $remaining > 0) {
            $higherUsed = min($remaining, $higherAvailable);
            $allocation['higher_rate']['taxable'] = $higherUsed;
            $allocation['higher_rate']['tax'] = round($higherUsed * $this->higherRate, 2);
            $this->usedHigherBand += $higherUsed;
            $remaining -= $higherUsed;
        }

        // Additional rate for anything remaining
        if ($remaining > 0) {
            $allocation['additional_rate']['taxable'] = $remaining;
            $allocation['additional_rate']['tax'] = round($remaining * $this->additionalRate, 2);
        }

        $allocation['total_income_tax'] = $allocation['basic_rate']['tax']
            + $allocation['higher_rate']['tax']
            + $allocation['additional_rate']['tax'];

        return $allocation;
    }

    /**
     * Get current band position for determining PSA and dividend rates
     */
    public function getCurrentBandPosition(): string
    {
        $totalUsed = $this->usedPersonalAllowance + $this->usedBasicBand + $this->usedHigherBand;

        if ($totalUsed <= $this->personalAllowance) {
            return 'personal_allowance';
        }

        if ($totalUsed <= $this->basicRateLimit) {
            return 'basic';
        }

        if ($totalUsed <= $this->higherRateLimit) {
            return 'higher';
        }

        return 'additional';
    }

    /**
     * Get total income allocated so far
     */
    public function getTotalAllocated(): float
    {
        return $this->usedPersonalAllowance + $this->usedBasicBand + $this->usedHigherBand;
    }

    /**
     * Get tax configuration values for reference
     */
    public function getConfig(): array
    {
        return [
            'personal_allowance' => $this->personalAllowance,
            'basic_rate_limit' => $this->basicRateLimit,
            'higher_rate_limit' => $this->higherRateLimit,
            'basic_rate' => $this->basicRate,
            'higher_rate' => $this->higherRate,
            'additional_rate' => $this->additionalRate,
        ];
    }

    private function emptyAllocation(): array
    {
        return [
            'personal_allowance_used' => 0,
            'basic_rate' => ['taxable' => 0, 'tax' => 0, 'rate' => $this->basicRate],
            'higher_rate' => ['taxable' => 0, 'tax' => 0, 'rate' => $this->higherRate],
            'additional_rate' => ['taxable' => 0, 'tax' => 0, 'rate' => $this->additionalRate],
            'total_income_tax' => 0,
        ];
    }
}
