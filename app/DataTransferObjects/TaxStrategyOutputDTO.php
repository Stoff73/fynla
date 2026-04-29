<?php

declare(strict_types=1);

namespace App\DataTransferObjects;

/**
 * Full payload returned by TaxStrategyCalculator::calculate().
 *
 * Shape varies by calculation_mode:
 *   - 'single'               → userAllowances populated; spouseAllowances null;
 *                              both suggestion arrays empty.
 *   - 'dual_earner'          → both grids populated; crossSpouseSuggestions
 *                              populated; assetShiftingSuggestions empty.
 *   - 'single_earner_couple' → both grids populated; assetShiftingSuggestions
 *                              populated (Marriage Allowance, savings transfer,
 *                              GIA → spouse, ISA top-up).
 */
final class TaxStrategyOutputDTO
{
    /**
     * @param  list<array{key:string,label:string,amount:float,used:float,remaining:float,utilisation_pct:float,status:string,owner:string}>  $userAllowances
     * @param  list<array{key:string,label:string,amount:float,used:float,remaining:float,utilisation_pct:float,status:string,owner:string}>|null  $spouseAllowances
     * @param  list<array<string,mixed>>  $assetShiftingSuggestions
     * @param  list<array<string,mixed>>  $crossSpouseSuggestions
     */
    public function __construct(
        public readonly string $taxYear,
        public readonly string $calculationMode,
        public readonly array $userAllowances,
        public readonly ?array $spouseAllowances,
        public readonly array $assetShiftingSuggestions,
        public readonly array $crossSpouseSuggestions,
        public readonly array $deltaVsBaseline = [],
    ) {}

    public function toArray(): array
    {
        return [
            'tax_year' => $this->taxYear,
            'calculation_mode' => $this->calculationMode,
            'user_allowances' => $this->userAllowances,
            'spouse_allowances' => $this->spouseAllowances,
            'asset_shifting_suggestions' => $this->assetShiftingSuggestions,
            'cross_spouse_suggestions' => $this->crossSpouseSuggestions,
            'delta_vs_baseline' => $this->deltaVsBaseline,
        ];
    }
}
