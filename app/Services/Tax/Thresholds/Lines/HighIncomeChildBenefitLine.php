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

        $benefit = (float) $this->childBenefit->calculateAnnualChildBenefit($context->user)['annual_amount'];
        $charge = $this->childBenefit->calculateHICBC($ani, $benefit);
        $excess = max(0.0, $ani - $threshold);
        $mechanism = $this->mechanismFor($context, $excess);
        $cost = ($excess > 0 ? $this->costs->delta($context, $excess, $mechanism) : new ThresholdCost)
            ->withBenefit('Child Benefit charge', sprintf('%d%% of your %s Child Benefit repaid', (int) $charge['clawback_percentage'], ThresholdCopy::pounds($benefit)), (float) $charge['charge']);

        $lever = null;
        if ($excess > 0) {
            $lever = $this->incomeLever($context, min($excess, $cost->applied), $cost, $mechanism);
            if ($mechanism === 'pension' && $cost->applied < $excess) {
                $lever['downside'] .= sprintf(' A pension contribution can only take %s off this year: tax relief is limited to your earnings from work.', ThresholdCopy::pounds($cost->applied));
            }
        }

        return new ThresholdResult(
            key: $this->key(),
            title: 'High Income Child Benefit Charge',
            range: ['from' => $threshold, 'to' => $top],
            position: $this->position($ani, $threshold),
            headline: ThresholdCopy::into($ani - $threshold, 'Child Benefit charge band'),
            body: sprintf('Between %s and %s you repay 1%% of your Child Benefit for every %s of income. A pension contribution brings you back under.', ThresholdCopy::pounds($threshold), ThresholdCopy::pounds($top), ThresholdCopy::pounds((float) ($config['clawback_increment'] ?? 200))),
            explanation: sprintf('The charge is collected through your tax return. At %s it is %s of the %s you receive.', ThresholdCopy::pounds($ani), ThresholdCopy::pounds((float) $charge['charge']), ThresholdCopy::pounds($benefit)),
            cost: $cost,
            lever: $lever,
            incomeMix: $this->costs->mix($context),
        );
    }
}
