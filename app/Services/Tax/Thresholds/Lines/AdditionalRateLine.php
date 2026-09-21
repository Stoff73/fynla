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

final class AdditionalRateLine extends MoneyLine
{
    public function __construct(ThresholdCostCalculator $costs, TaxConfigService $taxConfig, private readonly TaxStrategyMath $math)
    {
        parent::__construct($costs, $taxConfig);
    }

    public function key(): string
    {
        return 'additional_rate';
    }

    public function evaluate(ThresholdContext $context): ?ThresholdResult
    {
        $threshold = $this->math->bandThresholdsFor($context->user)['additional'];
        $income = $context->totalIncome();
        if (! $this->within($income, $threshold)) {
            return null;
        }
        $excess = max(0.0, $income - $threshold);
        $mechanism = $this->mechanismFor($context, $excess);
        $pct = (int) round($this->math->bandRateForBand('additional') * 100);

        // Size the move, then price that move: `amount` and `recovers` must describe
        // the same contribution.
        $amount0 = 0.0;
        if ($excess > 0) {
            // The one calculator every surface uses sizes the contribution; a zero or
            // absent suggestion falls back to the excess itself.
            $suggested = (float) ($context->strategy('additional_rate_avoidance')['suggested_contribution'] ?? $excess);
            $amount0 = $suggested > 0 ? min($excess, $suggested) : $excess;
        }
        $cost = $amount0 > 0 ? $this->costs->delta($context, $amount0, $mechanism) : new ThresholdCost;
        $amount = min($amount0, $cost->applied);

        $lever = null;
        if ($amount > 0) {
            $lever = $this->incomeLever($context, $amount, $cost, $mechanism);
            if ($mechanism === 'pension' && $cost->applied < $excess) {
                $lever['downside'] .= sprintf(' A pension contribution can only take %s off this year: tax relief is limited to your earnings from work.', ThresholdCopy::pounds($cost->applied));
            }
        }

        return new ThresholdResult(
            key: $this->key(),
            title: 'Additional-rate threshold',
            range: ['from' => $threshold, 'to' => null],
            position: $this->position($income, $threshold),
            headline: ThresholdCopy::into($income - $threshold, sprintf('%d%% band', $pct)),
            body: sprintf('Above %s income tax is %d%% and the Savings Allowance is nil.', ThresholdCopy::pounds($threshold), $pct),
            explanation: $this->bandExplanation($mechanism, $pct),
            cost: $cost,
            lever: $lever,
            incomeMix: $this->costs->mix($context),
        );
    }
}
