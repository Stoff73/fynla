<?php

declare(strict_types=1);

namespace App\Services\Property;

use App\Models\Property;
use App\Services\TaxConfigService;

class PropertyCalculationService
{
    public function __construct(private readonly TaxConfigService $taxConfig) {}

    /**
     * Check if property is leasehold and approaching end of term.
     *
     * W-0533 — the threshold was the literal `80`, which is the configured
     * `property_ownership.leasehold_reform.valuation_thresholds.difficult_to_mortgage`
     * copied into code. Rule 2: the number has one home, and it is the tax
     * configuration. The old docblock named both bands in prose as well; it does
     * not any more, for the same reason.
     */
    public function isLeaseholdExpiring(Property $property): bool
    {
        return $this->leaseholdWarnings($property)['has_warnings'] ?? false;
    }

    /**
     * The lease warnings for a property, or null where the question does not
     * arise — a freehold, or a leasehold whose remaining term was never recorded.
     *
     * Returns whatever `TaxConfigService` publishes, so both configured bands
     * (difficult to mortgage, significant value loss) reach the caller rather than
     * only the one the old boolean could express.
     *
     * @return array{has_warnings: bool, warnings: list<array{level: string, message: string}>, thresholds: array, remaining_years: int}|null
     */
    public function leaseholdWarnings(Property $property): ?array
    {
        if ($property->tenure_type !== 'leasehold' || $property->lease_remaining_years === null) {
            return null;
        }

        return $this->taxConfig->getLeaseholdValuationWarnings((int) $property->lease_remaining_years);
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
