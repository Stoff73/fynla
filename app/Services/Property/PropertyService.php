<?php

declare(strict_types=1);

namespace App\Services\Property;

use App\Models\Property;
use App\Traits\CalculatesOwnershipShare;

/**
 * Property Service
 *
 * Single-Record Architecture:
 * - ONE database record stores the FULL property value in current_value
 * - Calculate user's share using ownership_percentage
 */
class PropertyService
{
    use CalculatesOwnershipShare;

    /**
     * Calculate property equity (current value - outstanding mortgage balance)
     *
     * Single-record pattern: Returns FULL equity (not user's share).
     * Use calculateUserEquity() for user's share.
     */
    public function calculateEquity(Property $property): float
    {
        $currentValue = $property->current_value ?? 0;

        // Get total outstanding mortgage balance from detailed records
        $mortgageBalance = $property->mortgages()
            ->sum('outstanding_balance');

        // Fall back to simple outstanding_mortgage field if no detailed records exist
        if ($mortgageBalance == 0 && $property->outstanding_mortgage > 0) {
            $mortgageBalance = $property->outstanding_mortgage;
        }

        // Single-record pattern: Both current_value and mortgageBalance are FULL values
        return max(0, $currentValue - $mortgageBalance);
    }

    /**
     * Calculate user's share of property equity.
     *
     * Single-record pattern: Applies ownership percentage to calculate
     * the user's share of the total equity.
     */
    public function calculateUserEquity(Property $property, int $userId): float
    {
        $fullEquity = $this->calculateEquity($property);

        // Apply ownership percentage to get user's share
        $ownershipType = $property->ownership_type ?? 'individual';

        if ($ownershipType === 'individual' || $ownershipType === 'trust') {
            return $property->user_id === $userId ? $fullEquity : 0.0;
        }

        $percentage = (float) ($property->ownership_percentage ?? 50);

        if ($property->user_id === $userId) {
            return $fullEquity * ($percentage / 100);
        }

        if (($property->joint_owner_id ?? null) === $userId) {
            return $fullEquity * ((100 - $percentage) / 100);
        }

        return 0.0;
    }

    /**
     * Calculate tax position for a BTL property.
     * Single source of truth for taxable rental income and Section 24 credit.
     * Used by both the property financials tab and the income tax calculator.
     *
     * @param  Property  $property  The BTL property
     * @param  int|null  $userId  Optional user ID to calculate their specific share.
     *                            If null, calculates primary owner's share.
     */
    public function calculateTaxPosition(Property $property, ?int $userId = null): array
    {
        $monthlyRental = (float) ($property->monthly_rental_income ?? 0);

        // Non-mortgage allowable costs (deductible from rental income for tax)
        $monthlyAllowableCosts = (float) ($property->monthly_gas ?? 0)
            + (float) ($property->monthly_electricity ?? 0)
            + (float) ($property->monthly_water ?? 0)
            + (float) ($property->monthly_building_insurance ?? 0)
            + (float) ($property->monthly_contents_insurance ?? 0)
            + (float) ($property->monthly_service_charge ?? 0)
            + (float) ($property->monthly_ground_rent ?? 0)
            + (float) ($property->managing_agent_fee ?? 0);

        $monthlyTaxableIncome = $monthlyRental - $monthlyAllowableCosts;

        // Calculate mortgage interest for Section 24
        $monthlyMortgageInterest = 0;
        $mortgages = $property->mortgages;

        foreach ($mortgages as $mortgage) {
            $payment = (float) ($mortgage->monthly_payment ?? 0);
            $type = $mortgage->mortgage_type;

            if ($type === 'interest_only') {
                $monthlyMortgageInterest += $payment;
            } elseif ($type === 'repayment') {
                $monthlyMortgageInterest += (float) ($mortgage->monthly_interest_portion ?? 0);
            } elseif ($type === 'mixed') {
                $ioPercent = (float) ($mortgage->interest_only_percentage ?? 0);
                $ioPortion = $payment * ($ioPercent / 100);
                $repaymentPortion = (float) ($mortgage->monthly_interest_portion ?? 0);
                $monthlyMortgageInterest += $ioPortion + $repaymentPortion;
            }
        }

        // Calculate ownership percentage based on user's role (primary or joint owner)
        $ownershipMultiplier = 1.0;
        if ($property->ownership_type === 'joint' || $property->ownership_type === 'tenants_in_common') {
            $primaryOwnerPercentage = (float) ($property->ownership_percentage ?? 50);

            if ($userId !== null) {
                // Determine if user is primary owner or joint owner
                if ($property->user_id === $userId) {
                    // User is primary owner - use their percentage
                    $ownershipMultiplier = $primaryOwnerPercentage / 100;
                } elseif ($property->joint_owner_id === $userId) {
                    // User is joint owner - use remaining percentage
                    $ownershipMultiplier = (100 - $primaryOwnerPercentage) / 100;
                } else {
                    // User is neither owner - return zero
                    $ownershipMultiplier = 0.0;
                }
            } else {
                // No user specified - default to primary owner's share (backwards compatible)
                $ownershipMultiplier = $primaryOwnerPercentage / 100;
            }
        }

        $userMonthlyTaxable = $monthlyTaxableIncome * $ownershipMultiplier;
        $userMonthlyInterest = $monthlyMortgageInterest * $ownershipMultiplier;
        $monthlySection24Credit = $userMonthlyInterest * 0.20;

        $propertyName = $property->name ?: ($property->address_line_1 ?: 'BTL Property');

        return [
            'property_name' => $propertyName,
            'monthly_taxable_income' => round($userMonthlyTaxable, 2),
            'annual_taxable_income' => round($userMonthlyTaxable * 12, 2),
            'monthly_mortgage_interest' => round($userMonthlyInterest, 2),
            'section_24_monthly_credit' => round($monthlySection24Credit, 2),
            'section_24_annual_credit' => round($monthlySection24Credit * 12, 2),
            'monthly_allowable_costs' => round($monthlyAllowableCosts * $ownershipMultiplier, 2),
            'ownership_percentage' => $ownershipMultiplier < 1.0 ? round($ownershipMultiplier * 100) : null,
            'has_interest_portion_missing' => $this->hasMissingInterestPortion($mortgages),
        ];
    }

    /**
     * Check if any repayment/mixed mortgage is missing the interest portion.
     */
    private function hasMissingInterestPortion($mortgages): bool
    {
        foreach ($mortgages as $mortgage) {
            $type = $mortgage->mortgage_type;
            if ($type === 'repayment' || $type === 'mixed') {
                if (empty($mortgage->monthly_interest_portion) || (float) $mortgage->monthly_interest_portion === 0.0) {
                    return true;
                }
            }
        }

        return false;
    }

    public function calculateTotalMonthlyCosts(Property $property): float
    {
        $costs = 0;

        // Mortgage costs (monthly) - FULL amounts
        $mortgages = $property->mortgages;
        foreach ($mortgages as $mortgage) {
            $costs += $mortgage->monthly_payment ?? 0;
        }

        // Property-specific costs (monthly) - FULL amounts
        $costs += $property->monthly_council_tax ?? 0;
        $costs += $property->monthly_gas ?? 0;
        $costs += $property->monthly_electricity ?? 0;
        $costs += $property->monthly_water ?? 0;
        $costs += $property->monthly_building_insurance ?? 0;
        $costs += $property->monthly_contents_insurance ?? 0;
        $costs += $property->monthly_service_charge ?? 0;
        $costs += $property->monthly_maintenance_reserve ?? 0;
        $costs += $property->other_monthly_costs ?? 0;
        $costs += $property->managing_agent_fee ?? 0;

        return $costs;
    }

    /**
     * Calculate net rental yield (%)
     */
    public function calculateNetRentalYield(Property $property): float
    {
        $currentValue = $property->current_value ?? 0;

        if ($currentValue == 0) {
            return 0;
        }

        // Monthly rental income (adjusted for occupancy)
        $monthlyRentalIncome = $property->monthly_rental_income ?? 0;
        $occupancyRate = ($property->occupancy_rate_percent ?? 100) / 100;
        $actualMonthlyIncome = $monthlyRentalIncome * $occupancyRate;

        // Monthly costs
        $monthlyCosts = $this->calculateTotalMonthlyCosts($property);

        // Net monthly income
        $netMonthlyIncome = $actualMonthlyIncome - $monthlyCosts;

        // Calculate annual net income for yield calculation
        $netAnnualIncome = $netMonthlyIncome * 12;

        // Calculate yield as percentage
        $yield = ($netAnnualIncome / $currentValue) * 100;

        return round($yield, 2);
    }

    /**
     * Get comprehensive property summary
     *
     * Single-record pattern: All values are FULL values.
     * Includes user_share and full_value fields for frontend display.
     */
    public function getPropertySummary(Property $property): array
    {
        $property->load(['mortgages', 'user', 'household', 'trust']);

        $equity = $this->calculateEquity($property);
        $monthlyCosts = $this->calculateTotalMonthlyCosts($property);
        $rentalYield = $this->calculateNetRentalYield($property);
        $taxPosition = $property->property_type === 'buy_to_let' ? $this->calculateTaxPosition($property) : null;

        // Calculate loan-to-value ratio
        $currentValue = $property->current_value ?? 0;
        // Get mortgage balance from detailed records, or fall back to simple outstanding_mortgage field
        $mortgageBalance = $property->mortgages()->sum('outstanding_balance');
        if ($mortgageBalance == 0 && $property->outstanding_mortgage > 0) {
            $mortgageBalance = $property->outstanding_mortgage;
        }
        $ltv = $currentValue > 0 ? ($mortgageBalance / $currentValue) * 100 : 0;

        // Calculate total return (capital growth + rental yield)
        $purchasePrice = $property->purchase_price ?? 0;
        $capitalGrowth = $currentValue - $purchasePrice;
        $capitalGrowthPercent = $purchasePrice > 0 ? ($capitalGrowth / $purchasePrice) * 100 : 0;

        return [
            // Top-level commonly accessed fields
            'id' => $property->id,
            'property_id' => $property->id,  // Alias for backward compatibility
            'property_type' => $property->property_type,
            'ownership_type' => $property->ownership_type,
            'ownership_percentage' => (float) $property->ownership_percentage,
            'joint_owner_id' => $property->joint_owner_id,
            'household_id' => $property->household_id,
            'trust_id' => $property->trust_id,
            'current_value' => (float) $currentValue,
            'purchase_price' => (float) $purchasePrice,  // Add to top level for easier access
            'purchase_date' => $property->purchase_date?->format('Y-m-d'),
            'valuation_date' => $property->valuation_date?->format('Y-m-d'),
            'equity' => (float) $equity,
            'mortgage_balance' => (float) $mortgageBalance,
            'outstanding_mortgage' => (float) ($property->outstanding_mortgage ?? 0),  // Include simple field for reference
            'net_rental_yield' => (float) $rentalYield,  // Top-level for easy access in frontend
            'tax_position' => $taxPosition,  // BTL only: taxable income & Section 24 credit

            // Address fields (flat for form compatibility)
            'address_line_1' => $property->address_line_1,
            'address_line_2' => $property->address_line_2,
            'city' => $property->city,
            'county' => $property->county,
            'postcode' => $property->postcode,
            'country' => $property->country ?? 'United Kingdom',

            // Cost fields (flat for form compatibility) - MONTHLY
            'monthly_council_tax' => (float) ($property->monthly_council_tax ?? 0),
            'monthly_gas' => (float) ($property->monthly_gas ?? 0),
            'monthly_electricity' => (float) ($property->monthly_electricity ?? 0),
            'monthly_water' => (float) ($property->monthly_water ?? 0),
            'monthly_building_insurance' => (float) ($property->monthly_building_insurance ?? 0),
            'monthly_contents_insurance' => (float) ($property->monthly_contents_insurance ?? 0),
            'monthly_service_charge' => (float) ($property->monthly_service_charge ?? 0),
            'monthly_maintenance_reserve' => (float) ($property->monthly_maintenance_reserve ?? 0),
            'other_monthly_costs' => (float) ($property->other_monthly_costs ?? 0),
            'sdlt_paid' => (float) ($property->sdlt_paid ?? 0),

            // Rental fields (flat for form compatibility)
            'monthly_rental_income' => (float) ($property->monthly_rental_income ?? 0),
            'annual_rental_income' => (float) ($property->annual_rental_income ?? 0),
            'occupancy_rate_percent' => (int) ($property->occupancy_rate_percent ?? 100),
            'tenant_name' => $property->tenant_name,
            'tenant_email' => $property->tenant_email,
            'lease_start_date' => $property->lease_start_date?->format('Y-m-d'),
            'lease_end_date' => $property->lease_end_date?->format('Y-m-d'),

            // Managing agent fields (flat for form compatibility)
            'managing_agent_name' => $property->managing_agent_name,
            'managing_agent_company' => $property->managing_agent_company,
            'managing_agent_email' => $property->managing_agent_email,
            'managing_agent_phone' => $property->managing_agent_phone,
            'managing_agent_fee' => (float) ($property->managing_agent_fee ?? 0),

            // Detailed nested structures
            'address' => [
                'line_1' => $property->address_line_1,
                'line_2' => $property->address_line_2,
                'city' => $property->city,
                'county' => $property->county,
                'postcode' => $property->postcode,
                'full_address' => trim(implode(', ', array_filter([
                    $property->address_line_1,
                    $property->address_line_2,
                    $property->city,
                    $property->county,
                    $property->postcode,
                ]))),
            ],
            'valuation' => [
                'purchase_price' => $purchasePrice,
                'purchase_date' => $property->purchase_date?->format('Y-m-d'),
                'current_value' => $currentValue,
                'valuation_date' => $property->valuation_date?->format('Y-m-d'),
                'capital_growth' => $capitalGrowth,
                'capital_growth_percent' => round($capitalGrowthPercent, 2),
            ],
            'financial' => [
                'equity' => $equity,
                'mortgage_balance' => $mortgageBalance,
                'loan_to_value_percent' => round($ltv, 2),
                'monthly_costs' => $monthlyCosts,
                'annual_costs' => $monthlyCosts * 12,
            ],
            'rental' => [
                'annual_rental_income' => (float) ($property->annual_rental_income ?? 0),
                'monthly_rental_income' => (float) ($property->monthly_rental_income ?? 0),
                'occupancy_rate_percent' => (int) ($property->occupancy_rate_percent ?? 100),
                'net_rental_yield_percent' => (float) $rentalYield,
                'tenant_name' => $property->tenant_name,
                'lease_start_date' => $property->lease_start_date?->format('Y-m-d'),
                'lease_end_date' => $property->lease_end_date?->format('Y-m-d'),
            ],
            'costs' => [
                'monthly_council_tax' => (float) ($property->monthly_council_tax ?? 0),
                'monthly_gas' => (float) ($property->monthly_gas ?? 0),
                'monthly_electricity' => (float) ($property->monthly_electricity ?? 0),
                'monthly_water' => (float) ($property->monthly_water ?? 0),
                'monthly_building_insurance' => (float) ($property->monthly_building_insurance ?? 0),
                'monthly_contents_insurance' => (float) ($property->monthly_contents_insurance ?? 0),
                'monthly_service_charge' => (float) ($property->monthly_service_charge ?? 0),
                'monthly_maintenance_reserve' => (float) ($property->monthly_maintenance_reserve ?? 0),
                'other_monthly_costs' => (float) ($property->other_monthly_costs ?? 0),
                'total_monthly_costs' => (float) $monthlyCosts,
                'total_annual_costs' => (float) ($monthlyCosts * 12),
            ],
            'mortgages' => $property->mortgages->map(function ($mortgage) {
                return [
                    'id' => $mortgage->id,
                    'lender_name' => $mortgage->lender_name,
                    'mortgage_account_number' => $mortgage->mortgage_account_number,
                    'mortgage_type' => $mortgage->mortgage_type,
                    'country' => $mortgage->country,
                    'repayment_percentage' => (float) ($mortgage->repayment_percentage ?? 0),
                    'interest_only_percentage' => (float) ($mortgage->interest_only_percentage ?? 0),
                    'original_loan_amount' => (float) ($mortgage->original_loan_amount ?? 0),
                    'outstanding_balance' => (float) ($mortgage->outstanding_balance ?? 0),
                    'interest_rate' => (float) ($mortgage->interest_rate ?? 0),
                    'rate_type' => $mortgage->rate_type,
                    'fixed_rate_percentage' => (float) ($mortgage->fixed_rate_percentage ?? 0),
                    'variable_rate_percentage' => (float) ($mortgage->variable_rate_percentage ?? 0),
                    'fixed_interest_rate' => (float) ($mortgage->fixed_interest_rate ?? 0),
                    'variable_interest_rate' => (float) ($mortgage->variable_interest_rate ?? 0),
                    'rate_fix_end_date' => $mortgage->rate_fix_end_date?->format('Y-m-d'),
                    'monthly_payment' => (float) ($mortgage->monthly_payment ?? 0),
                    'start_date' => $mortgage->start_date?->format('Y-m-d'),
                    'maturity_date' => $mortgage->maturity_date?->format('Y-m-d'),
                    'remaining_term_months' => (int) ($mortgage->remaining_term_months ?? 0),
                    'ownership_type' => $mortgage->ownership_type,
                    'ownership_percentage' => (float) ($mortgage->ownership_percentage ?? 100),
                    'joint_owner_id' => $mortgage->joint_owner_id,
                    'joint_owner_name' => $mortgage->joint_owner_name,
                    'notes' => $mortgage->notes,
                ];
            })->toArray(),
        ];
    }
}
