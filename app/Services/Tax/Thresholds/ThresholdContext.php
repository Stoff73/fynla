<?php

declare(strict_types=1);

namespace App\Services\Tax\Thresholds;

use App\Models\User;
use App\Services\Estate\IHTCalculationService;
use App\Services\Tax\TaxStrategyCalculator;
use App\Services\Tax\VestScheduleResolver;
use Carbon\Carbon;

/**
 * Everything the lines share for one user, computed once. The income definitions
 * are passed in; the heavier estate and strategy runs are lazy so a user with no
 * estate line never pays for an IHT computation.
 */
final class ThresholdContext
{
    private ?array $iht = null;

    /** @var array<string, array<string, mixed>>|null */
    private ?array $strategies = null;

    private ?array $vests = null;

    public function __construct(
        public readonly User $user,
        public readonly array $definitions,
    ) {}

    public function adjustedNetIncome(): float
    {
        return (float) ($this->definitions['adjusted_net_income'] ?? 0);
    }

    public function totalIncome(): float
    {
        return (float) ($this->definitions['total_income'] ?? 0);
    }

    public function thresholdIncome(): float
    {
        return (float) ($this->definitions['threshold_income'] ?? 0);
    }

    /** @return array<string, float> */
    public function components(): array
    {
        return array_map('floatval', $this->definitions['components'] ?? []);
    }

    public function iht(): array
    {
        return $this->iht ??= app(IHTCalculationService::class)->calculate(
            $this->user,
            $this->user->liveSpouse(),
            $this->user->sharesFinancialDataWithSpouse(),
        );
    }

    /**
     * Strategy recommendations keyed by type, from the one calculator every surface uses.
     *
     * These are arrays, not `StrategyRecommendation` objects: `TaxStrategyCalculator`
     * serialises each recommendation with `toArray()` before constructing its output
     * DTO, and nothing exposes the objects it built. `toArray()` merges the `extra`
     * payload in flat, so a strategy-specific field sits at the top level beside the
     * named ones:
     *
     *     $context->strategy('pa_taper_rescue')['suggested_contribution'] ?? null
     *     $context->strategy('pa_taper_rescue')['estimated_annual_tax_saved'] ?? null
     *
     * @return array<string, array<string, mixed>>
     */
    public function strategies(): array
    {
        if ($this->strategies === null) {
            $this->strategies = [];
            foreach (app(TaxStrategyCalculator::class)->calculate($this->user)->recommendations as $rec) {
                $this->strategies[(string) ($rec['type'] ?? '')] = $rec;
            }
        }

        return $this->strategies;
    }

    /** @return array<string, mixed>|null */
    public function strategy(string $type): ?array
    {
        return $this->strategies()[$type] ?? null;
    }

    /** @return list<array{date: Carbon, value: float, account_id: int, account_name: string}> */
    public function vests(): array
    {
        return $this->vests ??= app(VestScheduleResolver::class)->schedule($this->user);
    }
}
