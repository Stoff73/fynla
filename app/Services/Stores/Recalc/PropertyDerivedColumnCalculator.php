<?php

declare(strict_types=1);

namespace App\Services\Stores\Recalc;

use App\Models\Property;
use App\Models\User;

/**
 * Derives canonical columns from raw property data.
 *
 * Pass 4 stores all property values in GBP — currency conversion lands in a
 * later sub-project pass. `equity_gbp` uses the denormalised `outstanding_mortgage`
 * column on `properties`; Pass 5 will reconcile this against MortgageStore reads
 * on a transaction boundary.
 */
class PropertyDerivedColumnCalculator
{
    /**
     * @return array{
     *     current_value_gbp: ?float,
     *     equity_gbp: ?float,
     *     loan_to_value_pct: ?float
     * }
     */
    public function calculate(Property $property, User $user): array
    {
        $currentValue = $property->current_value !== null ? (float) $property->current_value : null;
        $mortgage = $property->outstanding_mortgage !== null ? (float) $property->outstanding_mortgage : 0.0;

        $equity = $currentValue !== null ? round($currentValue - $mortgage, 2) : null;

        $ltv = null;
        if ($currentValue !== null && $currentValue > 0) {
            $ltv = round($mortgage / $currentValue * 100, 2);
        }

        return [
            'current_value_gbp' => $currentValue !== null ? round($currentValue, 2) : null,
            'equity_gbp' => $equity,
            'loan_to_value_pct' => $ltv,
        ];
    }
}
