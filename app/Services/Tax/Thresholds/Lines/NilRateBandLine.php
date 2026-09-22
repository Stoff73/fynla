<?php

declare(strict_types=1);

namespace App\Services\Tax\Thresholds\Lines;

use App\Services\Tax\Thresholds\ThresholdContext;
use App\Services\Tax\Thresholds\ThresholdCopy;
use App\Services\Tax\Thresholds\ThresholdCost;
use App\Services\Tax\Thresholds\ThresholdResult;

final class NilRateBandLine extends MoneyLine
{
    public function key(): string
    {
        return 'nil_rate_band';
    }

    public function evaluate(ThresholdContext $context): ?ThresholdResult
    {
        // A household with nothing recorded has no estate to place, and the guard
        // keeps the IHT computation off the hot path for everyone who has not
        // reached the estate module yet.
        if (! $this->hasEstate($context)) {
            return null;
        }

        $iht = $context->iht();
        $net = (float) $iht['total_net_estate'];
        $allowances = (float) $iht['total_allowances'];
        if (! $this->within($net, $allowances)) {
            return null;
        }

        $rate = (float) $iht['iht_rate'];
        $cost = (new ThresholdCost)->withBenefit(
            'Inheritance tax',
            sprintf('%d%% on %s', (int) round($rate * 100), ThresholdCopy::pounds((float) $iht['taxable_estate'])),
            (float) $iht['iht_liability'],
        );

        return new ThresholdResult(
            key: $this->key(),
            title: 'Inheritance tax nil rate band',
            range: ['from' => $allowances, 'to' => null],
            position: $this->position($net, $allowances),
            headline: ThresholdCopy::estate($net - $allowances),
            body: sprintf('Everything above your allowances of %s is taxed at %d%%.', ThresholdCopy::pounds($allowances), (int) round($rate * 100)),
            explanation: sprintf('Your allowances combine the nil rate band of %s and the residence nil rate band of %s.', ThresholdCopy::pounds((float) $iht['nrb_available']), ThresholdCopy::pounds((float) $iht['rnrb_available'])),
            cost: $cost,
            lever: null,
            incomeMix: [],
        );
    }
}
