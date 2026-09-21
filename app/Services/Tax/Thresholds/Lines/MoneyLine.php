<?php

declare(strict_types=1);

namespace App\Services\Tax\Thresholds\Lines;

use App\Services\Tax\TaxStrategyMath;
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

    /**
     * The ISA move applies when savings income alone covers the excess AND there is
     * ISA allowance left to receive it. Someone who has already used this year's
     * allowance cannot move anything, so offering the move would be offering nothing.
     *
     * ponytail: the ceiling is the allowance, not the capital. Whether the user holds
     * enough outside an ISA to generate that income is a question about their balances
     * that nothing here asks, and converting capital to income is not this lever's job.
     */
    protected function mechanismFor(ThresholdContext $context, float $excess): string
    {
        $c = $context->components();
        $savingsIncome = ($c['interest'] ?? 0) + ($c['dividend'] ?? 0);

        return $excess > 0 && $savingsIncome >= $excess && $this->isaAllowanceRemaining($context) > 0
            ? 'isa'
            : 'pension';
    }

    protected function isaAllowanceRemaining(ThresholdContext $context): float
    {
        $allowance = (float) ($this->taxConfig->getISAAllowances()['annual_allowance'] ?? 0);

        return max(0.0, $allowance - $this->math()->estimateIsaSubscriptionsThisYear($context->user));
    }

    /**
     * Whether there is an estate worth computing. One home for the test, because both
     * estate lines ask it and two copies is how they drift apart.
     *
     * Pensions count: from the date in `inheritance_tax.pension_iht_inclusion` unused
     * pots fall into the estate, so a household holding nothing but a pension has an
     * estate that the property-and-accounts test would have missed entirely.
     */
    protected function hasEstate(ThresholdContext $context): bool
    {
        $user = $context->user;

        return $user->properties()->exists()
            || $user->investmentAccounts()->exists()
            || $user->savingsAccounts()->exists()
            || $user->dcPensions()->exists()
            || $user->dbPensions()->exists();
    }

    protected function math(): TaxStrategyMath
    {
        return app(TaxStrategyMath::class);
    }

    /**
     * The explanation depends on the mechanism, because the two move different money.
     * A pension contribution takes non-savings income out; an ISA move takes savings
     * income out of the calculation entirely, where "income tax" is the wrong name for
     * what was being charged (under top-slicing an interest-only excess lands in
     * interest tax with income tax nil).
     */
    protected function bandExplanation(string $mechanism, int $pct): string
    {
        return $mechanism === 'isa'
            ? 'The savings income above this line is taxed at the higher rates. Moving it into an ISA takes it out of the calculation.'
            : sprintf('Income tax is %d%% on this slice, and the allowances that go with basic rate go with it.', $pct);
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
                'downside' => sprintf("Uses this year's ISA allowance; you have %s of it left.", ThresholdCopy::pounds($this->isaAllowanceRemaining($context))),
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
            'downside' => ThresholdCopy::lockedUntil($this->lockedUntilAge($context), $context->vests()[0] ?? null),
            'action' => ['route' => '/tax-strategy'],
            'mechanism' => 'pension',
        ];
    }

    /**
     * The age the money is locked until, or null when the user has already reached it
     * and nothing is locked. Someone of 60 told their pension is "locked until you are
     * 55" would rightly stop trusting the rest of the card.
     */
    protected function lockedUntilAge(ThresholdContext $context): ?int
    {
        $minimum = $this->minimumPensionAge();
        $age = $this->math()->ageOf($context->user->date_of_birth);

        return $age !== null && $age >= $minimum ? null : $minimum;
    }

    protected function minimumPensionAge(): int
    {
        return (int) $this->taxConfig->get('pension.normal_minimum_pension_age', 55);
    }
}
