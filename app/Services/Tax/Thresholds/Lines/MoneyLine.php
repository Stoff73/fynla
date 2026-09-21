<?php

declare(strict_types=1);

namespace App\Services\Tax\Thresholds\Lines;

use App\Services\Tax\Thresholds\ThresholdContext;
use App\Services\Tax\Thresholds\ThresholdCopy;
use App\Services\Tax\Thresholds\ThresholdCost;
use App\Services\Tax\Thresholds\ThresholdCostCalculator;
use App\Services\Tax\Thresholds\ThresholdLine;
use App\Services\TaxConfigService;

abstract class MoneyLine implements ThresholdLine
{
    /** A line applies when the user is over it, or within this share below it. */
    protected const WINDOW = 0.25;

    public function __construct(
        protected readonly ThresholdCostCalculator $costs,
        protected readonly TaxConfigService $taxConfig,
    ) {}

    protected function within(float $value, float $threshold): bool
    {
        return $threshold > 0 && $value >= $threshold * (1 - static::WINDOW);
    }

    /** @return array{value: float, distance: float, unit: string, over: bool} */
    protected function position(float $value, float $threshold): array
    {
        return ['value' => round($value, 2), 'distance' => round($value - $threshold, 2), 'unit' => 'gbp', 'over' => $value > $threshold];
    }

    /** The ISA move applies when savings income alone covers the excess. */
    protected function mechanismFor(ThresholdContext $context, float $excess): string
    {
        $c = $context->components();

        return $excess > 0 && (($c['interest'] ?? 0) + ($c['dividend'] ?? 0)) >= $excess ? 'isa' : 'pension';
    }

    /**
     * The lever for an income excess: a pension contribution equal to the excess
     * (default), or the ISA move when the excess is covered by savings income.
     *
     * `$cost` MUST have been priced with `$mechanism`, because `ThresholdCost::applied`
     * means a different thing for each — relevant UK earnings for a pension
     * contribution, interest and dividends actually held for an ISA move. So the
     * mechanism is resolved ONCE from the full excess and passed to both `delta()`
     * and here, never re-derived from the capped `$amount`: a pension lever capped
     * at a director's small salary would otherwise flip to ISA wording over a cost
     * priced as a pension contribution, and quote a saving the move cannot produce.
     *
     * @return array{title: string, amount: float, recovers: float, downside: string, action: array{route: string}, mechanism: string}
     */
    protected function incomeLever(ThresholdContext $context, float $amount, ThresholdCost $cost, string $mechanism): array
    {
        if ($mechanism === 'isa') {
            return [
                'title' => sprintf('Move %s of savings income into an ISA', ThresholdCopy::pounds($amount)),
                'amount' => round($amount, 2),
                'recovers' => $cost->total(),
                'downside' => "Uses this year's ISA allowance.",
                'action' => ['route' => '/tax-strategy'],
                'mechanism' => 'isa',
            ];
        }

        return [
            // "Pay into", never "salary sacrifice": the cost is priced as an ordinary
            // pension contribution, which relieves income tax but not National
            // Insurance. Naming sacrifice would promise a saving this figure does
            // not contain (plan amendment, 2026-09-21).
            'title' => sprintf('Pay %s into your pension', ThresholdCopy::pounds($amount)),
            'amount' => round($amount, 2),
            'recovers' => $cost->total(),
            'downside' => ThresholdCopy::lockedUntil($this->minimumPensionAge(), $context->vests()[0] ?? null),
            'action' => ['route' => '/tax-strategy'],
            'mechanism' => 'pension',
        ];
    }

    protected function minimumPensionAge(): int
    {
        return (int) $this->taxConfig->get('pension.normal_minimum_pension_age', 57);
    }
}
