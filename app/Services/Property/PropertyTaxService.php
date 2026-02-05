<?php

declare(strict_types=1);

namespace App\Services\Property;

use App\Models\Property;
use App\Models\User;
use App\Services\TaxConfigService;

class PropertyTaxService
{
    public function __construct(
        private readonly TaxConfigService $taxConfig
    ) {}

    /**
     * Calculate Stamp Duty Land Tax (SDLT) for UK property purchase
     * Uses active tax year rates from TaxConfigService
     *
     * @param  string  $propertyType  ('main_residence', 'secondary_residence', 'buy_to_let')
     */
    public function calculateSDLT(float $purchasePrice, string $propertyType, bool $isFirstHome = false): array
    {
        // Get SDLT configuration from service
        $sdltConfig = $this->taxConfig->getStampDuty();
        $residential = $sdltConfig['residential'];

        $bands = [];
        $totalSDLT = 0;

        // First-time buyer relief
        if ($isFirstHome) {
            $ftbConfig = $residential['first_time_buyers'];
            $maxPropertyValue = $ftbConfig['max_property_value'];
            $nilRateThreshold = $ftbConfig['nil_rate_threshold'];

            if ($purchasePrice <= $maxPropertyValue) {
                // Apply first-time buyer bands
                $ftbBands = $ftbConfig['bands'];
                $totalSDLT = $this->calculateBandedTax($purchasePrice, $ftbBands, $bands);
            } else {
                // Property too expensive for FTB relief, use standard rates
                $standardBands = $residential['standard']['bands'];
                $totalSDLT = $this->calculateBandedTax($purchasePrice, $standardBands, $bands);
            }
        } else {
            // Standard or additional property rates
            $isAdditional = in_array($propertyType, ['secondary_residence', 'buy_to_let']);

            if ($isAdditional) {
                $additionalBands = $residential['additional_properties']['bands'];
                $totalSDLT = $this->calculateBandedTax($purchasePrice, $additionalBands, $bands);
            } else {
                $standardBands = $residential['standard']['bands'];
                $totalSDLT = $this->calculateBandedTax($purchasePrice, $standardBands, $bands);
            }
        }

        $effectiveRate = $purchasePrice > 0 ? ($totalSDLT / $purchasePrice) * 100 : 0;

        return [
            'purchase_price' => $purchasePrice,
            'property_type' => $propertyType,
            'is_first_home' => $isFirstHome,
            'total_sdlt' => round($totalSDLT, 2),
            'effective_rate' => round($effectiveRate, 2),
            'bands' => $bands,
        ];
    }

    /**
     * Calculate tax based on banded thresholds
     *
     * @param  float  $amount  Purchase price or income
     * @param  array  $configBands  Array of ['threshold' => X, 'rate' => Y] from config
     * @param  array  &$outputBands  Reference to array to populate with detailed band info
     * @return float Total tax calculated
     */
    private function calculateBandedTax(float $amount, array $configBands, array &$outputBands): float
    {
        $totalTax = 0;

        for ($i = 0; $i < count($configBands); $i++) {
            $currentBand = $configBands[$i];
            $threshold = $currentBand['threshold'];
            $rate = $currentBand['rate'];

            // Determine upper limit of this band
            $nextThreshold = isset($configBands[$i + 1]) ? $configBands[$i + 1]['threshold'] : $amount;

            // Only process if amount exceeds this band's threshold
            if ($amount > $threshold) {
                $bandValue = min($amount - $threshold, $nextThreshold - $threshold);
                $tax = $bandValue * $rate;
                $totalTax += $tax;

                $outputBands[] = [
                    'from' => $threshold,
                    'to' => min($amount, $nextThreshold),
                    'rate' => $rate * 100, // Convert to percentage for display
                    'tax' => $tax,
                ];
            }
        }

        return $totalTax;
    }

    /**
     * Calculate Capital Gains Tax (CGT) on property disposal
     * Uses active tax year rates from TaxConfigService
     */
    public function calculateCGT(Property $property, float $disposalPrice, float $disposalCosts, User $user): array
    {
        // Get CGT configuration from service
        $cgtConfig = $this->taxConfig->getCapitalGainsTax();
        $incomeTaxConfig = $this->taxConfig->getIncomeTax();

        $purchasePrice = $property->purchase_price ?? 0;
        $sdltPaid = $property->sdlt_paid ?? 0;

        // Calculate total acquisition costs
        $acquisitionCosts = $purchasePrice + $sdltPaid;

        // Calculate gain
        $gain = $disposalPrice - $acquisitionCosts - $disposalCosts;

        // Apply annual exempt amount
        $annualExemptAmount = $cgtConfig['annual_exempt_amount'];
        $taxableGain = (float) max(0, $gain - $annualExemptAmount);

        // Determine CGT rate based on user's income
        $totalIncome = $user->annual_employment_income +
            $user->annual_self_employment_income +
            $user->annual_rental_income +
            $user->annual_dividend_income +
            $user->annual_other_income;

        // Get basic rate threshold from income tax config
        $incomeTaxBands = $incomeTaxConfig['bands'];
        $personalAllowance = $incomeTaxConfig['personal_allowance'];
        $basicRateThreshold = $personalAllowance + $incomeTaxBands[0]['max'];

        // Get CGT rates for residential property (stored as decimals, e.g., 0.18 for 18%)
        $basicCgtRate = $cgtConfig['residential_property_basic_rate'] ?? $cgtConfig['basic_rate'] ?? 0.18;
        $higherCgtRate = $cgtConfig['residential_property_higher_rate'] ?? $cgtConfig['higher_rate'] ?? 0.24;
        $cgtRate = $totalIncome > $basicRateThreshold ? $higherCgtRate : $basicCgtRate;
        $cgtLiability = $taxableGain * $cgtRate;

        $effectiveRate = $gain > 0 ? ($cgtLiability / $gain) * 100 : 0;

        return [
            'disposal_price' => $disposalPrice,
            'acquisition_cost' => $acquisitionCosts,
            'disposal_costs' => $disposalCosts,
            'gain' => $gain,  // Alias
            'gross_gain' => $gain,
            'annual_exempt_amount' => $annualExemptAmount,
            'taxable_gain' => $taxableGain,
            'cgt_rate' => (float) ($cgtRate * 100), // Convert decimal to percentage for display
            'cgt_liability' => round($cgtLiability, 2),
            'effective_rate' => round($effectiveRate, 2),
        ];
    }

    /**
     * Calculate rental income tax liability
     */
    public function calculateRentalIncomeTax(Property $property, User $user): array
    {
        // Rental income - calculate annual from monthly
        $monthlyRentalIncome = $property->monthly_rental_income ?? 0;
        $annualRentalIncome = $monthlyRentalIncome * 12;
        // Occupancy rate defaults to 100% if not set
        $occupancyRate = 1.0;
        $actualIncome = $annualRentalIncome * $occupancyRate;

        // Allowable expenses
        $allowableExpenses = 0;
        $allowableExpenses += $property->annual_service_charge ?? 0;
        $allowableExpenses += $property->annual_ground_rent ?? 0;
        $allowableExpenses += $property->annual_insurance ?? 0;
        $allowableExpenses += $property->annual_maintenance_reserve ?? 0;
        $allowableExpenses += $property->other_annual_costs ?? 0;

        // Mortgage interest (20% tax relief from 2020/21 onwards)
        $mortgageInterest = 0;
        foreach ($property->mortgages as $mortgage) {
            $annualPayment = ($mortgage->monthly_payment ?? 0) * 12;
            $interestRate = ($mortgage->interest_rate ?? 0) / 100;
            $outstandingBalance = $mortgage->outstanding_balance ?? 0;
            $annualInterest = $outstandingBalance * $interestRate;
            $mortgageInterest += $annualInterest;
        }

        // Mortgage interest tax credit (basic rate relief)
        $incomeTaxConfig = $this->taxConfig->getIncomeTax();
        $basicRateOfRelief = $incomeTaxConfig['bands'][0]['rate']; // Basic rate (e.g., 0.20)
        $mortgageInterestCredit = $mortgageInterest * $basicRateOfRelief;

        // Calculate taxable profit (cannot deduct mortgage interest directly)
        $taxableProfit = max(0, $actualIncome - $allowableExpenses);

        // Determine user's marginal tax rate
        $totalIncome = $user->annual_employment_income +
            $user->annual_self_employment_income +
            $user->annual_rental_income +
            $user->annual_dividend_income +
            $user->annual_other_income;

        // Get tax bands and thresholds from config
        $personalAllowance = $incomeTaxConfig['personal_allowance'];
        $bands = $incomeTaxConfig['bands'];

        // Calculate absolute thresholds
        $basicRateThreshold = $personalAllowance + $bands[0]['max'];
        $higherRateThreshold = $personalAllowance + $bands[1]['max'];

        $marginalTaxRate = 0;
        if ($totalIncome > $higherRateThreshold) {
            $marginalTaxRate = $bands[2]['rate'] * 100; // Additional rate
        } elseif ($totalIncome > $basicRateThreshold) {
            $marginalTaxRate = $bands[1]['rate'] * 100; // Higher rate
        } elseif ($totalIncome > $personalAllowance) {
            $marginalTaxRate = $bands[0]['rate'] * 100; // Basic rate
        }

        // Tax liability before mortgage interest credit
        $taxBeforeCredit = $taxableProfit * ($marginalTaxRate / 100);

        // Apply mortgage interest tax credit
        $taxLiability = max(0, $taxBeforeCredit - $mortgageInterestCredit);

        return [
            // Flat keys for quick access
            'gross_income' => $actualIncome,
            'allowable_expenses' => $allowableExpenses,
            'mortgage_interest_relief' => round($mortgageInterestCredit, 2),
            'taxable_profit' => $taxableProfit,
            'marginal_tax_rate' => $marginalTaxRate,
            'tax_before_credit' => round($taxBeforeCredit, 2),
            'tax_liability' => round($taxLiability, 2),
            'net_rental_profit' => round($actualIncome - $allowableExpenses - $taxLiability, 2),

            // Detailed nested structures
            'rental_income' => [
                'gross_annual' => $annualRentalIncome,
                'monthly_rental_income' => $monthlyRentalIncome,
                'occupancy_rate_percent' => 100, // Always 100% now
                'actual_income' => $actualIncome,
            ],
            'allowable_expenses_detail' => [
                'service_charge' => $property->annual_service_charge ?? 0,
                'ground_rent' => $property->annual_ground_rent ?? 0,
                'insurance' => $property->annual_insurance ?? 0,
                'maintenance' => $property->annual_maintenance_reserve ?? 0,
                'other_costs' => $property->other_annual_costs ?? 0,
                'total' => $allowableExpenses,
            ],
            'mortgage_interest' => [
                'annual_interest' => round($mortgageInterest, 2),
                'tax_credit_20_percent' => round($mortgageInterestCredit, 2),
            ],
        ];
    }
}
