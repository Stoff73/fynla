<?php

declare(strict_types=1);

namespace App\Services\Stores\Recalc;

use App\Models\Property;
use App\Models\User;
use App\Services\Stores\MortgageStore;

/**
 * Derives canonical columns from raw property data.
 *
 * Pass 4 stores all property values in GBP — currency conversion lands in a
 * later sub-project pass. Pass 5 PR 6: canonical source for outstanding mortgage
 * is `MortgageStore::forProperty` (sum of mortgages.outstanding_balance);
 * `properties.outstanding_mortgage` is now a write-only-by-recalc derived column
 * kept for backward compatibility with pre-Pass-5 read consumers.
 */
class PropertyDerivedColumnCalculator
{
    public function __construct(
        private readonly MortgageStore $mortgageStore,
    ) {}

    /**
     * @return array{
     *     current_value_gbp: ?float,
     *     equity_gbp: ?float,
     *     loan_to_value_pct: ?float,
     *     outstanding_mortgage: float
     * }
     */
    public function calculate(Property $property, ?User $user = null): array
    {
        $currentValue = $property->current_value !== null ? (float) $property->current_value : null;

        // Canonical mortgage source: sum of MortgageStore::forProperty.
        // `null` user is the system-context lookup — the recalc is a back-end
        // operation that bypasses user scoping. Pre-Pass-5 callers that still
        // read `properties.outstanding_mortgage` continue to work because the
        // recalc writes it back to the denormalised column.
        $canonicalMortgageSum = (float) $this->mortgageStore
            ->forProperty($property->id, null)
            ->sum('outstanding_balance');

        $equity = $currentValue !== null ? round($currentValue - $canonicalMortgageSum, 2) : null;

        $ltv = null;
        if ($currentValue !== null && $currentValue > 0) {
            $ltv = round($canonicalMortgageSum / $currentValue * 100, 2);
        }

        return [
            'current_value_gbp' => $currentValue !== null ? round($currentValue, 2) : null,
            'equity_gbp' => $equity,
            'loan_to_value_pct' => $ltv,
            'outstanding_mortgage' => round($canonicalMortgageSum, 2),
        ];
    }
}
