<?php

declare(strict_types=1);

namespace App\Services\Tax\Thresholds\Lines;

use App\Services\Benefits\ChildBenefitService;
use App\Services\Tax\Thresholds\ThresholdContext;
use App\Services\Tax\Thresholds\ThresholdCopy;
use App\Services\Tax\Thresholds\ThresholdCost;
use App\Services\Tax\Thresholds\ThresholdCostCalculator;
use App\Services\Tax\Thresholds\ThresholdResult;
use App\Services\TaxConfigService;

final class HighIncomeChildBenefitLine extends MoneyLine
{
    public function __construct(
        ThresholdCostCalculator $costs,
        TaxConfigService $taxConfig,
        private readonly ChildBenefitService $childBenefit,
    ) {
        parent::__construct($costs, $taxConfig);
    }

    public function key(): string
    {
        return 'hicbc';
    }

    public function evaluate(ThresholdContext $context): ?ThresholdResult
    {
        if ($this->childBenefit->getEligibleChildren($context->user)->isEmpty()) {
            return null;
        }
        $config = $this->taxConfig->getChildBenefit();
        $threshold = (float) ($config['high_income_charge_threshold'] ?? 60000);
        $top = (float) ($config['high_income_full_clawback'] ?? 80000);
        $ani = $context->adjustedNetIncome();
        if (! $this->within($ani, $threshold)) {
            return null;
        }

        // `calculateChildBenefitPosition` is the ONE gated entry to this figure
        // (`benefits_child`, W-0532). Calling `calculateAnnualChildBenefit` and
        // `calculateHICBC` separately would reach round the gate and make a second
        // decision about tiers here; Rule 20 says the gate stays in one place.
        // Adjusted net income is passed rather than recomputed, so the charge is
        // priced against the same figure the position on the line uses.
        $position = $this->childBenefit->calculateChildBenefitPosition($context->user, $ani);
        $benefit = (float) ($position['benefit']['annual_amount'] ?? 0);
        if ($benefit <= 0) {
            // Eligible children exist, so a nil benefit here is the tier withholding
            // the figure. A line with no amount is a row the user cannot act on.
            return null;
        }
        $charge = $position['hicbc'];
        $excess = max(0.0, $ani - $threshold);
        $mechanism = $this->mechanismFor($context, $excess);
        $amount0 = $this->affordableAmount($context, $mechanism, $excess);
        $cost = ($amount0 > 0 ? $this->costs->delta($context, $amount0, $mechanism) : new ThresholdCost)
            ->withBenefit('Child Benefit charge', sprintf('%d%% of your %s Child Benefit repaid', (int) $charge['clawback_percentage'], ThresholdCopy::pounds($benefit)), (float) $charge['charge']);

        $lever = null;
        if ($excess > 0 && $cost->applied > 0) {
            $lever = $this->incomeLever($context, min($amount0, $cost->applied), $cost, $mechanism);
            $lever['downside'] .= $this->constraintNote($context, $mechanism, $excess, $amount0, $cost);
        }

        // Past the top of the band there is no "how far in" left to state: the whole
        // benefit is already repaid and the distance stops meaning anything.
        $past = $ani >= $top;

        return new ThresholdResult(
            key: $this->key(),
            title: 'High Income Child Benefit Charge',
            range: ['from' => $threshold, 'to' => $top],
            position: $this->position($ani, $threshold),
            headline: $past
                ? ThresholdCopy::past('Child Benefit charge band')
                : ThresholdCopy::into($ani - $threshold, 'Child Benefit charge band'),
            body: $past
                ? sprintf('Above %s all of your Child Benefit is repaid.', ThresholdCopy::pounds($top))
                : sprintf('Between %s and %s you repay 1%% of your Child Benefit for every %s of income. A pension contribution brings you back under.', ThresholdCopy::pounds($threshold), ThresholdCopy::pounds($top), ThresholdCopy::pounds((float) ($config['clawback_increment'] ?? 200))),
            explanation: sprintf('The charge is collected through your tax return. At %s it is %s of the %s you receive.', ThresholdCopy::pounds($ani), ThresholdCopy::pounds((float) $charge['charge']), ThresholdCopy::pounds($benefit)),
            cost: $cost,
            lever: $lever,
            incomeMix: $this->costs->mix($context),
        );
    }
}
