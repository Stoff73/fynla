<?php

declare(strict_types=1);

namespace App\Services\Tax\Thresholds\Lines;

use App\Services\Tax\Thresholds\ThresholdContext;
use App\Services\Tax\Thresholds\ThresholdCopy;
use App\Services\Tax\Thresholds\ThresholdCost;
use App\Services\Tax\Thresholds\ThresholdResult;

final class ResidenceBandTaperLine extends MoneyLine
{
    public function key(): string
    {
        return 'rnrb_taper';
    }

    public function evaluate(ThresholdContext $context): ?ThresholdResult
    {
        if (! $this->hasEstate($context)) {
            return null;
        }

        $ihtConfig = $this->taxConfig->getInheritanceTax();
        $threshold = (float) ($ihtConfig['rnrb_taper_threshold'] ?? 2000000);
        $iht = $context->iht();
        $net = (float) $iht['total_net_estate'];
        $reduction = (float) $iht['rnrb_taper_reduction'];
        // No approach window here, and no "over the threshold" test either: the only
        // thing that makes this line worth showing is that residence band has actually
        // been taken away. Below the threshold nothing is lost, and above it a
        // household with no residence band to start with loses nothing either — both
        // rendered a card reading "You lose £0 of residence nil rate band".
        if ($reduction <= 0) {
            return null;
        }
        $cost = (new ThresholdCost)->withBenefit(
            'Residence nil rate band lost',
            sprintf('Reduced by £1 for every %s over %s', ThresholdCopy::perPoundLost((float) ($ihtConfig['rnrb_taper_rate'] ?? 0.5)), ThresholdCopy::pounds($threshold)),
            $reduction * (float) $iht['iht_rate'],
        );

        return new ThresholdResult(
            key: $this->key(),
            title: 'Residence nil rate band taper',
            range: ['from' => $threshold, 'to' => null],
            position: $this->position($net, $threshold),
            headline: ThresholdCopy::into($net - $threshold, 'residence band taper'),
            body: sprintf('Above %s the residence nil rate band tapers away.', ThresholdCopy::pounds($threshold)),
            explanation: sprintf('You lose %s of residence nil rate band at this estate value.', ThresholdCopy::pounds($reduction)),
            cost: $cost,
            lever: null,
            incomeMix: [],
        );
    }
}
