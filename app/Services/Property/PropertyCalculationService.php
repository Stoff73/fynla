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
}
