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

final class TaperedAnnualAllowanceLine extends MoneyLine
{
    public function __construct(ThresholdCostCalculator $costs, TaxConfigService $taxConfig, private readonly TaxStrategyMath $math)
    {
        parent::__construct($costs, $taxConfig);
    }

    public function key(): string
    {
        return 'tapered_aa';
    }

    public function evaluate(ThresholdContext $context): ?ThresholdResult
    {
        $pension = $this->taxConfig->getPensionAllowances();
        $taper = $pension['tapered_annual_allowance'] ?? [];
        $thresholdLimit = (float) ($taper['threshold_income'] ?? 200000);
        $adjustedLimit = (float) ($taper['adjusted_income_threshold'] ?? $taper['adjusted_income'] ?? 260000);
        $minimum = (float) ($taper['minimum_allowance'] ?? 10000);

        // One source for all three figures. The position, the gate and the allowance
        // all come from `IncomeDefinitionsService`, which computes adjusted income and
        // the tapered allowance together from the same basis. Mixing its adjusted
        // income with a second service's allowance put two different answers to
        // "what is my allowance" on one card.
        $allowances = $context->definitions['adjusted_allowances'] ?? [];
        $adjusted = (float) ($context->definitions['adjusted_income'] ?? 0);
        $full = (float) ($allowances['pension_annual_allowance_full'] ?? $pension['annual_allowance'] ?? 60000);
        $lost = max(0.0, $full - (float) ($allowances['pension_annual_allowance'] ?? $full));

        // No approach window: this line has nothing to show until the allowance is
        // actually reduced. Both statutory gates must bite, and the second is exactly
        // "the allowance came out lower than the full one".
        if ($context->thresholdIncome() <= $thresholdLimit || $lost <= 0) {
            return null;
        }
        $cost = (new ThresholdCost)->withBenefit(
            'Pension Annual Allowance lost',
            sprintf('%s of your %s allowance, at your marginal rate', ThresholdCopy::pounds($lost), ThresholdCopy::pounds($full)),
            $lost * $this->math->bandRateFor($context->user),
        );

        return new ThresholdResult(
            key: $this->key(),
            title: 'Tapered Annual Allowance',
            // The taper stops at the minimum allowance, reached after (full − minimum)
            // of allowance has been withdrawn at £1 per £(1/rate) of adjusted income.
            range: ['from' => $adjustedLimit, 'to' => $adjustedLimit + ($full - $minimum) / max((float) ($taper['taper_rate'] ?? 0.5), 0.0001)],
            position: $this->position($adjusted, $adjustedLimit),
            headline: ThresholdCopy::into($adjusted - $adjustedLimit, 'pension taper'),
            body: sprintf('For every %s of adjusted income above %s you lose £1 of pension Annual Allowance, down to %s.', ThresholdCopy::perPoundLost((float) ($taper['taper_rate'] ?? 0.5)), ThresholdCopy::pounds($adjustedLimit), ThresholdCopy::pounds($minimum)),
            explanation: sprintf('Your allowance this year is %s against the full %s.', ThresholdCopy::pounds($full - $lost), ThresholdCopy::pounds($full)),
            cost: $cost,
            // No lever in this slice: a pension contribution does not reduce adjusted
            // income (FA 2004 s228ZA adds it back), and the strategy's own adjusted-income
            // figure is under review. The line sits behind the click.
            lever: null,
            incomeMix: $this->costs->mix($context),
        );
    }
}
