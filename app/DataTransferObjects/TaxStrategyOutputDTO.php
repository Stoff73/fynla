<?php

declare(strict_types=1);

namespace App\DataTransferObjects;

/**
 * Full payload returned by TaxStrategyCalculator::calculate().
 *
 * `recommendations` is the canonical, source-of-truth list of every suggested
 * action surfaced on the SaveTax dashboard. Each entry carries its own
 * `category` (`income_band`, `allowance`, `household`, `lifecycle`,
 * `warning`) and `priority` (`high`, `medium`, `low`). The frontend groups
 * by category for rendering.
 *
 * Shape varies by calculation_mode:
 *   - 'single'               → userAllowances populated; spouseAllowances null;
 *                              recommendations may include user-level entries
 *                              only (no household category).
 *   - 'dual_earner'          → both grids populated; recommendations may
 *                              include household entries (cross-spouse
 *                              coordination) alongside user-level entries.
 *   - 'single_earner_couple' → both grids populated; recommendations include
 *                              household asset-shifting entries alongside
 *                              user-level entries.
 */
final class TaxStrategyOutputDTO
{
    /**
     * @param  list<array{key:string,label:string,amount:float,used:float,remaining:float,utilisation_pct:float,status:string,owner:string}>  $userAllowances
     * @param  list<array{key:string,label:string,amount:float,used:float,remaining:float,utilisation_pct:float,status:string,owner:string}>|null  $spouseAllowances
     * @param  list<array<string,mixed>>  $recommendations  Canonical recommendation list. Each entry carries `category`, `priority`, etc. plus strategy-specific extras.
     */
    public function __construct(
        public readonly string $taxYear,
        public readonly string $calculationMode,
        public readonly array $userAllowances,
        public readonly ?array $spouseAllowances,
        public readonly array $recommendations,
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
            'delta_vs_baseline' => $this->deltaVsBaseline,
        ];
    }
}
