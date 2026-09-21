<?php

declare(strict_types=1);

namespace App\Services\Tax\Thresholds\Lines;

use App\Services\Tax\TaxStrategyMath;
use App\Services\Tax\Thresholds\ThresholdContext;
use App\Services\Tax\Thresholds\ThresholdCopy;
use App\Services\Tax\Thresholds\ThresholdCost;
use App\Services\Tax\Thresholds\ThresholdCostCalculator;
use App\Services\Tax\Thresholds\ThresholdResult;
use App\Services\TaxConfigService;

final class HigherRateLine extends MoneyLine
{
    public function __construct(ThresholdCostCalculator $costs, TaxConfigService $taxConfig, private readonly TaxStrategyMath $math)
    {
        parent::__construct($costs, $taxConfig);
    }

    public function key(): string
    {
        return 'higher_rate';
    }

    public function evaluate(ThresholdContext $context): ?ThresholdResult
    {
        $threshold = $this->math->bandThresholdsFor($context->user)['higher'];
        $income = $context->totalIncome();
        if (! $this->within($income, $threshold)) {
            return null;
        }
        $excess = max(0.0, $income - $threshold);
        $mechanism = $this->mechanismFor($context, $excess);
        $cost = $excess > 0 ? $this->costs->delta($context, $excess, $mechanism) : new ThresholdCost;
        $pct = (int) round($this->math->bandRateForBand('higher') * 100);

        $lever = null;
        if ($excess > 0) {
            $lever = $this->incomeLever($context, min($excess, $cost->applied), $cost, $mechanism);
            if ($mechanism === 'pension' && $cost->applied < $excess) {
                $lever['downside'] .= sprintf(' A pension contribution can only take %s off this year: tax relief is limited to your earnings from work.', ThresholdCopy::pounds($cost->applied));
            }
        }

        return new ThresholdResult(
            key: $this->key(),
            title: 'Higher-rate threshold',
            range: ['from' => $threshold, 'to' => null],
            position: $this->position($income, $threshold),
            headline: ThresholdCopy::into($income - $threshold, sprintf('%d%% band', $pct)),
            body: sprintf('Above %s your Savings Allowance halves, dividends and gains are taxed at the higher rates, and Marriage Allowance is lost.', ThresholdCopy::pounds($threshold)),
            explanation: sprintf('Income tax is %d%% on this slice, and the allowances that go with basic rate go with it.', $pct),
            cost: $cost,
            lever: $lever,
            incomeMix: $this->costs->mix($context),
        );
    }
}
