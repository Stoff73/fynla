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
        $amount0 = $this->affordableAmount($context, $mechanism, $excess);
        $cost = $amount0 > 0 ? $this->costs->delta($context, $amount0, $mechanism) : new ThresholdCost;
        $pct = (int) round($this->math->bandRateForBand('higher') * 100);

        // No strategy sizes this line, so the move is the whole excess and the cost
        // already prices exactly that; `applied` only trims it where the mechanism
        // ran out of room, and the cost was priced on the same ceiling.
        $lever = null;
        if ($excess > 0 && $cost->applied > 0) {
            $lever = $this->incomeLever($context, min($amount0, $cost->applied), $cost, $mechanism);
            $lever['downside'] .= $this->constraintNote($context, $mechanism, $excess, $amount0, $cost);
        }

        return new ThresholdResult(
            key: $this->key(),
            title: 'Higher-rate threshold',
            range: ['from' => $threshold, 'to' => null],
            position: $this->position($income, $threshold),
            headline: ThresholdCopy::into($income - $threshold, sprintf('%d%% band', $pct)),
            body: sprintf('Above %s your Savings Allowance halves, dividends and gains are taxed at the higher rates, and Marriage Allowance is lost.', ThresholdCopy::pounds($threshold)),
            explanation: $this->bandExplanation($mechanism, $pct),
            cost: $cost,
            lever: $lever,
            incomeMix: $this->costs->mix($context),
        );
    }
}
