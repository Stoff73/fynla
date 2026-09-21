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
        $cost = $excess > 0 ? $this->costs->delta($context, $excess, $mechanism) : new ThresholdCost;
        foreach ($this->childcare->for($context->user) as $item) {
            $cost = $cost->withBenefit($item['label'], $item['detail'], $item['amount']);
        }

        $lever = null;
        if ($excess > 0) {
            // The one calculator every surface uses sizes the contribution; the
            // excess is the fallback when the strategy has not fired (no AA headroom).
            // `applied` is what the cost delta could actually take out: relief is
            // limited to relevant UK earnings, so a dividend-heavy user's lever is
            // smaller than their excess and the copy says why.
            $suggested = (float) ($context->strategy('pa_taper_rescue')['suggested_contribution'] ?? $excess);
            $amount = min($suggested > 0 ? min($excess, $suggested) : $excess, $cost->applied);
            $lever = $this->incomeLever($context, $amount, $cost, $mechanism);
            if ($mechanism === 'pension' && $cost->applied < $excess) {
                $lever['downside'] .= sprintf(' A pension contribution can only take %s off this year: tax relief is limited to your earnings from work.', ThresholdCopy::pounds($cost->applied));
            }
        }

        $effectivePct = (int) round($higherRate * 150);

        return new ThresholdResult(
            key: $this->key(),
            title: 'Personal Allowance taper',
            range: ['from' => $threshold, 'to' => $bandTop],
            position: $this->position($ani, $threshold),
            headline: ThresholdCopy::into($ani - $threshold, sprintf('%d%% band', $effectivePct)),
            body: ThresholdCopy::taperBody($ani - $threshold, $effectivePct, $threshold),
            explanation: ThresholdCopy::taperExplanation($threshold, (int) round($higherRate * 100), (int) round($higherRate * 50), $taperRate),
            cost: $cost,
            lever: $lever,
            incomeMix: $this->costs->mix($context),
        );
    }
}
