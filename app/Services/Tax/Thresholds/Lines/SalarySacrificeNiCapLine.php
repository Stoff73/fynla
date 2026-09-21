<?php

declare(strict_types=1);

namespace App\Services\Tax\Thresholds\Lines;

use App\Services\Tax\Thresholds\ThresholdContext;
use App\Services\Tax\Thresholds\ThresholdCopy;
use App\Services\Tax\Thresholds\ThresholdCost;
use App\Services\Tax\Thresholds\ThresholdCostCalculator;
use App\Services\Tax\Thresholds\ThresholdLine;
use App\Services\Tax\Thresholds\ThresholdResult;
use App\Services\TaxConfigService;
use App\Services\UKTaxCalculator;
use Carbon\Carbon;

final class SalarySacrificeNiCapLine implements ThresholdLine
{
    public function __construct(
        private readonly TaxConfigService $taxConfig,
        private readonly ThresholdCostCalculator $costs,
        private readonly UKTaxCalculator $calculator,
    ) {}

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
        $days = (int) Carbon::today()->diffInDays($date);
        $cost = (new ThresholdCost)->withBenefit(
            'National Insurance on sacrifice above the cap',
            sprintf('National Insurance on the %s above the %s cap', ThresholdCopy::pounds($sacrificed - $cap), ThresholdCopy::pounds($cap)),
            $this->extraNationalInsurance($context, $sacrificed, $cap),
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

    /**
     * The National Insurance the cap will cost, as the difference between two runs of
     * the one calculator, never a rate applied to the excess (Rule 20).
     *
     * A rate is the wrong instrument here: which rate applies depends where the pay
     * sits. A £145,000 earner sacrificing £30,000 has all of it above the upper
     * earnings limit, where the employee rate is 2%, not the 8% main rate — a rate
     * multiplication overstated this fourfold. Taking pay from (P − sacrificed) up to
     * (P − cap) and asking the calculator what Class 1 falls on each lets the bands,
     * the thresholds and the limit do their own work.
     */
    private function extraNationalInsurance(ThresholdContext $context, float $sacrificed, float $cap): float
    {
        $mix = $this->costs->mix($context);
        $prePay = (float) $mix['employment'] + $sacrificed;

        return round($this->classOne($prePay - $cap) - $this->classOne($prePay - $sacrificed), 2);
    }

    private function classOne(float $employment): float
    {
        return (float) ($this->calculator->calculateNetIncome(max(0.0, $employment))['breakdown']['class_1_ni'] ?? 0);
    }
}
