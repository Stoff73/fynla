<?php

declare(strict_types=1);

namespace App\Services\Tax\Thresholds\Lines;

use App\Services\Tax\TaxStrategyMath;
use App\Services\Tax\Thresholds\ChildcareEntitlements;
use App\Services\Tax\Thresholds\ThresholdContext;
use App\Services\Tax\Thresholds\ThresholdCopy;
use App\Services\Tax\Thresholds\ThresholdCost;
use App\Services\Tax\Thresholds\ThresholdCostCalculator;
use App\Services\Tax\Thresholds\ThresholdResult;
use App\Services\TaxConfigService;

final class PersonalAllowanceTaperLine extends MoneyLine
{
    public function __construct(
        ThresholdCostCalculator $costs,
        TaxConfigService $taxConfig,
        private readonly TaxStrategyMath $math,
        private readonly ChildcareEntitlements $childcare,
    ) {
        parent::__construct($costs, $taxConfig);
    }

    public function key(): string
    {
        return 'pa_taper';
    }

    public function evaluate(ThresholdContext $context): ?ThresholdResult
    {
        $income = $this->taxConfig->getIncomeTax();
        $threshold = (float) ($income['personal_allowance_taper_threshold'] ?? 100000);
        $ani = $context->adjustedNetIncome();
        if (! $this->within($ani, $threshold)) {
            return null;
        }

        $higherRate = $this->math->bandRateForBand('higher');
        // The band ends where the allowance runs out: £1 withdrawn per £(1/rate)
        // earned, so the whole allowance is gone after allowance/rate of income.
        $taperRate = (float) ($income['personal_allowance_taper_rate'] ?? 0.5);
        $bandTop = $threshold + (float) ($income['personal_allowance'] ?? 12570) / max($taperRate, 0.0001);
        $excess = max(0.0, $ani - $threshold);

        $mechanism = $this->mechanismFor($context, $excess);
        $effectivePct = (int) round($higherRate * 150);

        // The move is sized FIRST, then priced, so `amount` and `recovers` describe
        // the same contribution. Pricing the whole excess and then capping the amount
        // quoted a saving from a bigger move than the one on the button.
        $amount0 = 0.0;
        if ($excess > 0) {
            // The one calculator every surface uses sizes the contribution; the
            // excess is the fallback when the strategy has not fired (no Annual
            // Allowance headroom).
            $suggested = (float) ($context->strategy('pa_taper_rescue')['suggested_contribution'] ?? $excess);
            $amount0 = $suggested > 0 ? min($excess, $suggested) : $excess;
        }
        $cost = $amount0 > 0 ? $this->costs->delta($context, $amount0, $mechanism) : new ThresholdCost;

        // `applied` is what the priced move could actually take out: pension relief is
        // limited to relevant UK earnings, an ISA move to what is actually held.
        $amount = min($amount0, $cost->applied);
        $fullyUnder = $excess > 0 && $cost->applied >= $excess;

        // Childcare returns only when the contribution reaches back under the line. A
        // move that stops short restores nothing, so listing the entitlements as
        // recovered would be a promise the lever cannot keep.
        if ($fullyUnder) {
            foreach ($this->childcare->for($context->user) as $item) {
                $cost = $cost->withBenefit($item['label'], $item['detail'], $item['amount']);
            }
        }

        $lever = null;
        if ($amount > 0) {
            $lever = $this->incomeLever($context, $amount, $cost, $mechanism);
            if ($mechanism === 'pension' && $cost->applied < $excess) {
                $lever['downside'] .= sprintf(' A pension contribution can only take %s off this year: tax relief is limited to your earnings from work.', ThresholdCopy::pounds($cost->applied));
            }
        }

        $past = $ani >= $bandTop;
        $body = $past
            ? sprintf('Above %s your Personal Allowance is gone entirely; income there is taxed at %d%%.', ThresholdCopy::pounds($bandTop), (int) round($this->math->bandRateForBand('additional') * 100))
            : ThresholdCopy::taperBody($ani - $threshold, $effectivePct, $threshold);
        if ($excess > 0 && ! $fullyUnder && $amount > 0) {
            $body .= sprintf(' A contribution of %s gets you part of the way; the childcare entitlements return only once you are under %s.', ThresholdCopy::pounds($amount), ThresholdCopy::pounds($threshold));
        }

        return new ThresholdResult(
            key: $this->key(),
            title: 'Personal Allowance taper',
            range: ['from' => $threshold, 'to' => $bandTop],
            position: $this->position($ani, $threshold),
            headline: $past
                ? ThresholdCopy::past($bandTop, sprintf('%d%% band', $effectivePct))
                : ThresholdCopy::into($ani - $threshold, sprintf('%d%% band', $effectivePct)),
            body: $body,
            explanation: ThresholdCopy::taperExplanation($threshold, (int) round($higherRate * 100), (int) round($higherRate * 50), $taperRate),
            cost: $cost,
            lever: $lever,
            incomeMix: $this->costs->mix($context),
        );
    }
}
