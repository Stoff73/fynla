<?php

declare(strict_types=1);

namespace App\DataTransferObjects;

/**
 * Full payload returned by TaxStrategyCalculator::calculate().
 *
 * Shape varies by calculation_mode:
 *   - 'single'               → userAllowances populated; spouseAllowances null;
 *                              recommendations + both legacy suggestion arrays empty.
 *   - 'dual_earner'          → both grids populated; crossSpouseSuggestions
 *                              populated; assetShiftingSuggestions empty;
 *                              recommendations = crossSpouseSuggestions.
 *   - 'single_earner_couple' → both grids populated; assetShiftingSuggestions
 *                              populated (Marriage Allowance, savings transfer,
 *                              GIA → spouse, ISA top-up); crossSpouseSuggestions
 *                              empty; recommendations = assetShiftingSuggestions.
 *
 * `recommendations` is the canonical, source-of-truth list of all suggested
 * actions. It supersedes the legacy `assetShiftingSuggestions` and
 * `crossSpouseSuggestions` arrays — both of which remain populated for
 * backward compatibility with the existing frontend (HouseholdView /
 * StrategyRecommendationList still consume them). Phase 2 of the SaveTax
 * strategy expansion (see `April/April30pdates/savetax-strategy-catalogue.md`)
 * migrates the frontend to consume `recommendations` directly and drops the
 * two legacy fields.
 */
final class TaxStrategyOutputDTO
{
    /**
     * @param  list<array{key:string,label:string,amount:float,used:float,remaining:float,utilisation_pct:float,status:string,owner:string}>  $userAllowances
     * @param  list<array{key:string,label:string,amount:float,used:float,remaining:float,utilisation_pct:float,status:string,owner:string}>|null  $spouseAllowances
     * @param  list<array<string,mixed>>  $recommendations  Canonical recommendation list. Each entry carries `category`, `priority`, etc. plus strategy-specific extras.
     * @param  list<array<string,mixed>>  $assetShiftingSuggestions  Legacy view (single_earner_couple mode). Identical content to filtered `recommendations`. Deprecated for Phase 2.
     * @param  list<array<string,mixed>>  $crossSpouseSuggestions  Legacy view (dual_earner mode). Identical content to filtered `recommendations`. Deprecated for Phase 2.
     */
    public function __construct(
        public readonly string $taxYear,
        public readonly string $calculationMode,
        public readonly array $userAllowances,
        public readonly ?array $spouseAllowances,
        public readonly array $recommendations,
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
            'recommendations' => $this->recommendations,
            'asset_shifting_suggestions' => $this->assetShiftingSuggestions,
            'cross_spouse_suggestions' => $this->crossSpouseSuggestions,
            'delta_vs_baseline' => $this->deltaVsBaseline,
        ];
    }
}
