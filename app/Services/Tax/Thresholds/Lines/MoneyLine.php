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
     * The ISA move applies when savings income alone covers the excess AND the
     * remaining ISA allowance is at least as large as the excess.
     *
     * The allowance bound is what stops the lever quoting a move it cannot make: the
     * income taken out of the calculation can never exceed the capital sheltered, so
     * £500 of allowance left cannot remove a £19,000 dividend excess however much the
     * user holds.
     *
     * ponytail: the ceiling is the allowance, not the capital. Whether the user holds
     * enough outside an ISA to generate that income is a question about their balances
     * that nothing here asks, and converting capital to income is not this lever's job.
     * That makes the bound conservative rather than exact — a yield above 100% is the
     * only case it would wave through, and there is no such case.
     */
    protected function mechanismFor(ThresholdContext $context, float $excess): string
    {
        $c = $context->components();
        $savingsIncome = ($c['interest'] ?? 0) + ($c['dividend'] ?? 0);

        return $excess > 0 && $savingsIncome >= $excess && $this->isaAllowanceRemaining($context) >= $excess
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
     * The most this mechanism can actually move, applied BEFORE pricing so the cost
     * prices the move the button offers.
     *
     * A pension contribution is bounded by the Annual Allowance the user has left this
     * year. Without this the higher-rate lever told a £142,400 earner to "Pay £92,130
     * into your pension" — their whole excess, more than any allowance permits, and a
     * contribution that would trigger a charge rather than avoid one. The taper lines
     * were already sized by the strategy, which applies the same ceiling; these two
     * asked for the raw excess.
     *
     * The ISA path has its own ceiling in `mechanismFor()`, so it passes through.
     */
    protected function affordableAmount(ThresholdContext $context, string $mechanism, float $amount): float
    {
        if ($mechanism !== 'pension' || $amount <= 0) {
            return max(0.0, $amount);
        }

        return max(0.0, min($amount, $this->math()->availableAnnualAllowance($context->user, null)));
    }

    /**
     * Why the lever stops short of the excess, appended to the downside.
     *
     * Two different things can cut the move, and saying the wrong one is worse than
     * saying nothing:
     *
     *  - The **Annual Allowance** cuts it before pricing. The strategy sizes the
     *    contribution from the headroom the user actually has, so `$amount0` comes
     *    back below the excess. Someone who has flexibly accessed a pension is held
     *    to the money purchase allowance, and the copy names that instead.
     *  - **Relevant earnings** cut it during pricing. `ThresholdCostCalculator` caps
     *    relief at earnings from work (FA 2004 s190), which is the only thing that can
     *    make `applied` fall below the amount that was asked for.
     *
     * Before this split the earnings sentence fired on `applied < excess`, which after
     * the move was sized first meant a user limited by their Annual Allowance was told
     * their earnings were the problem. Both causes can hold at once, and then both
     * sentences appear, in the order the constraints bite.
     */
    protected function constraintNote(ThresholdContext $context, string $mechanism, float $excess, float $amount0, ThresholdCost $cost): string
    {
        if ($mechanism !== 'pension') {
            return '';
        }

        $notes = [];
        if ($amount0 < $excess && $amount0 > 0) {
            $notes[] = $this->math()->moneyPurchaseAnnualAllowanceApplies($context->user)
                ? sprintf("Your money purchase Annual Allowance limits this year's contribution to %s.", ThresholdCopy::pounds($amount0))
                : sprintf("Your pension Annual Allowance limits this year's contribution to %s.", ThresholdCopy::pounds($amount0));
        }
        if ($cost->applied < $amount0) {
            $notes[] = sprintf('A pension contribution can only take %s off this year: tax relief is limited to your earnings from work.', ThresholdCopy::pounds($cost->applied));
        }

        return $notes === [] ? '' : ' '.implode(' ', $notes);
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
