<?php

declare(strict_types=1);

namespace App\Services\Property;

use App\Models\Property;

class PropertyCalculationService
{
    /**
     * Check if property is leasehold and approaching end of term.
     * UK government phasing out leaseholds for new builds.
     * Properties with less than 80 years are harder to mortgage.
     * Properties with less than 60 years significantly lose value.
     */
    public function isLeaseholdExpiring(Property $property): bool
    {
        if ($property->tenure_type !== 'leasehold') {
            return false;
        }

        return $property->lease_remaining_years !== null && $property->lease_remaining_years < 80;
    }

    /**
     * Calculate equity for this property.
     *
     * IMPORTANT: Both current_value and mortgage balances are already stored as the user's share
     * in the database (divided by ownership_percentage when saving). Therefore, we do NOT
     * multiply by ownership_percentage here - that would divide the equity in half again.
     *
     * Equity = current_value - sum(all mortgages for this property)
     */
    public function calculateEquity(Property $property): float
    {
        $currentValue = (float) ($property->current_value ?? 0);

        // Sum all mortgages for this property (already user's share from database)
        $totalMortgages = (float) $property->mortgages->sum('outstanding_balance');

        // Fallback to outstanding_mortgage field if mortgages relationship not loaded
        if ($totalMortgages === 0.0 && $property->outstanding_mortgage) {
            $totalMortgages = (float) $property->outstanding_mortgage;
        }

        return $currentValue - $totalMortgages;
    }
}
