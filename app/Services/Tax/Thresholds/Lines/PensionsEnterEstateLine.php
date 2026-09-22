<?php

declare(strict_types=1);

namespace App\Services\Tax\Thresholds\Lines;

use App\Services\Tax\Thresholds\ThresholdContext;
use App\Services\Tax\Thresholds\ThresholdCopy;
use App\Services\Tax\Thresholds\ThresholdCost;
use App\Services\Tax\Thresholds\ThresholdLine;
use App\Services\Tax\Thresholds\ThresholdResult;
use App\Services\TaxConfigService;
use Carbon\Carbon;

final class PensionsEnterEstateLine implements ThresholdLine
{
    public function __construct(private readonly TaxConfigService $taxConfig) {}

    public function key(): string
    {
        return 'pensions_in_estate';
    }

    public function evaluate(ThresholdContext $context): ?ThresholdResult
    {
        $pot = (float) $context->user->dcPensions()->sum('current_fund_value');
        $date = Carbon::parse((string) ($this->taxConfig->getInheritanceTax()['pension_iht_inclusion']['effective_date'] ?? '2027-04-06'));
        if ($pot <= 0 || $date->isPast()) {
            return null;
        }

        $iht = $context->iht();
        // Allowances the estate does not already use are the room the pension drops
        // into; only what sits above them would ever be taxed.
        $room = max(0.0, (float) $iht['total_allowances'] - (float) $iht['total_net_estate']);
        $exposed = max(0.0, $pot - $room);
        $rate = (float) $iht['iht_rate'];
        $days = (int) Carbon::today()->diffInDays($date);

        $cost = (new ThresholdCost)->withBenefit(
            'Inheritance tax on your pension',
            sprintf('%s in defined contribution pensions at %d%%', ThresholdCopy::pounds($pot), (int) round($rate * 100)),
            $exposed * $rate,
        );

        return new ThresholdResult(
            key: $this->key(),
            title: 'Pensions enter your estate',
            range: null,
            position: ['value' => (float) $days, 'distance' => (float) $days, 'unit' => 'days', 'over' => false],
            headline: sprintf('%s · %d days', $date->format('j F Y'), $days),
            body: sprintf('From %s unused pension pots count towards inheritance tax.', $date->format('j F Y')),
            explanation: sprintf('Your estate is %s against allowances of %s, so %s of your pension would be taxed.', ThresholdCopy::pounds((float) $iht['total_net_estate']), ThresholdCopy::pounds((float) $iht['total_allowances']), ThresholdCopy::pounds($exposed)),
            cost: $cost,
            lever: null,
        );
    }
}
