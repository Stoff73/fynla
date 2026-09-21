<?php

declare(strict_types=1);

namespace App\Services\Tax\Thresholds;

use App\DataTransferObjects\StrategyRecommendation;
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

    /** @var array<string, StrategyRecommendation>|null */
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
     * `TaxStrategyCalculator` serialises its recommendations before publishing them —
     * `TaxStrategyOutputDTO::$recommendations` is a list of arrays, not a list of
     * `StrategyRecommendation`, and no accessor exposes the objects it built. Reading
     * `->type` off those arrays would not throw; PHP 8 warns and yields null, so every
     * entry would key silently under the empty string. They are rebuilt here instead,
     * so a line asks for a strategy by type and gets a typed object back.
     *
     * @return array<string, StrategyRecommendation>
     */
    public function strategies(): array
    {
        if ($this->strategies === null) {
            $this->strategies = [];
            foreach (app(TaxStrategyCalculator::class)->calculate($this->user)->recommendations as $rec) {
                $this->strategies[(string) ($rec['type'] ?? '')] = $this->hydrate($rec);
            }
        }

        return $this->strategies;
    }

    /** @param array<string, mixed> $rec */
    private function hydrate(array $rec): StrategyRecommendation
    {
        // Everything not named by the constructor was merged in from `extra` by
        // `StrategyRecommendation::toArray()`, so it goes back there.
        $named = ['type', 'category', 'priority', 'title', 'description',
            'estimated_annual_tax_saved', 'requires_advice', 'required_monthly_cost', 'required_lump_sum'];

        return new StrategyRecommendation(
            type: (string) ($rec['type'] ?? ''),
            category: (string) ($rec['category'] ?? ''),
            priority: (string) ($rec['priority'] ?? ''),
            title: (string) ($rec['title'] ?? ''),
            description: (string) ($rec['description'] ?? ''),
            estimatedAnnualTaxSaved: isset($rec['estimated_annual_tax_saved']) ? (float) $rec['estimated_annual_tax_saved'] : null,
            requiresAdvice: (bool) ($rec['requires_advice'] ?? false),
            extra: array_diff_key($rec, array_flip($named)),
            requiredMonthlyCost: isset($rec['required_monthly_cost']) ? (float) $rec['required_monthly_cost'] : null,
            requiredLumpSum: isset($rec['required_lump_sum']) ? (float) $rec['required_lump_sum'] : null,
        );
    }

    public function strategy(string $type): ?StrategyRecommendation
    {
        return $this->strategies()[$type] ?? null;
    }

    /** @return list<array{date: Carbon, value: float, account_id: int, account_name: string}> */
    public function vests(): array
    {
        return $this->vests ??= app(VestScheduleResolver::class)->schedule($this->user);
    }
}
