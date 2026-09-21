<?php

declare(strict_types=1);

namespace App\Services\Tax\Thresholds;

final class ThresholdCost
{
    /** @param list<array{label: string, detail: string, amount: float}> $benefits */
    public function __construct(
        public readonly float $incomeTax = 0.0,
        public readonly float $niClass1 = 0.0,
        public readonly float $niClass4 = 0.0,
        public readonly float $dividendTax = 0.0,
        public readonly float $interestTax = 0.0,
        public readonly array $benefits = [],
    ) {}

    public function withBenefit(string $label, string $detail, float $amount): self
    {
        if ($amount <= 0) {
            return $this;
        }

        return new self($this->incomeTax, $this->niClass1, $this->niClass4, $this->dividendTax, $this->interestTax,
            [...$this->benefits, ['label' => $label, 'detail' => $detail, 'amount' => round($amount, 2)]]);
    }

    public function total(): float
    {
        return round($this->incomeTax + $this->niClass1 + $this->niClass4 + $this->dividendTax + $this->interestTax
            + array_sum(array_column($this->benefits, 'amount')), 2);
    }

    public function toArray(): array
    {
        return [
            'income_tax' => round($this->incomeTax, 2),
            'ni_class_1' => round($this->niClass1, 2),
            'ni_class_4' => round($this->niClass4, 2),
            'dividend_tax' => round($this->dividendTax, 2),
            'interest_tax' => round($this->interestTax, 2),
            'benefits' => $this->benefits,
            'total' => $this->total(),
        ];
    }
}
