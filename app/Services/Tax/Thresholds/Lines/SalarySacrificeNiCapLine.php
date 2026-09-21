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

final class SalarySacrificeNiCapLine implements ThresholdLine
{
    public function __construct(private readonly TaxConfigService $taxConfig) {}

    public function key(): string
    {
        return 'ni_cap';
    }

    public function evaluate(ThresholdContext $context): ?ThresholdResult
    {
        $sacrificed = (float) ($context->definitions['deductions']['salary_sacrificed'] ?? 0);
        $cap = (float) $this->taxConfig->get('pension.salary_sacrifice.nic_exemption_cap', 2000);
        $date = Carbon::parse((string) $this->taxConfig->get('pension.salary_sacrifice.nic_exemption_cap_effective_date', '2027-04-06'));
        if ($sacrificed <= $cap || $date->isPast()) {
            return null;
        }
        $rate = (float) $this->taxConfig->get('national_insurance.class_1.employee.main_rate', 0.08);
        $days = (int) Carbon::today()->diffInDays($date);
        $cost = (new ThresholdCost)->withBenefit(
            'National Insurance on sacrifice above the cap',
            sprintf('%s above the %s cap at %d%%', ThresholdCopy::pounds($sacrificed - $cap), ThresholdCopy::pounds($cap), (int) round($rate * 100)),
            ($sacrificed - $cap) * $rate,
        );

        return new ThresholdResult(
            key: $this->key(),
            title: 'Salary sacrifice National Insurance cap',
            range: null,
            position: ['value' => (float) $days, 'distance' => (float) $days, 'unit' => 'days', 'over' => false],
            headline: sprintf('%s · %d days', $date->format('j F Y'), $days),
            body: sprintf('From %s only the first %s of salary sacrifice each year is free of National Insurance. You sacrifice %s.', $date->format('j F Y'), ThresholdCopy::pounds($cap), ThresholdCopy::pounds($sacrificed)),
            explanation: 'Employer contributions and income tax relief are unchanged. Only employee National Insurance on the excess is affected.',
            cost: $cost,
            lever: null,
        );
    }
}
