<?php

declare(strict_types=1);

namespace App\Services\Estate;

use App\Constants\TaxDefaults;
use App\Models\ActuarialLifeTable;
use App\Models\Estate\Trust;
use App\Services\TaxConfigService;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class TrustService
{
    public function __construct(
        private readonly TaxConfigService $taxConfig
    ) {}

    /**
     * Calculate the next periodic charge date for a relevant property trust
     */
    public function calculateNextPeriodicChargeDate(Trust $trust): ?Carbon
    {
        if (! $trust->isRelevantPropertyTrust()) {
            return null;
        }

        $creationDate = Carbon::parse($trust->trust_creation_date);
        $lastChargeDate = $trust->last_periodic_charge_date
            ? Carbon::parse($trust->last_periodic_charge_date)
            : null;

        if ($lastChargeDate) {
            return $lastChargeDate->copy()->addYears(10);
        }

        // First charge is 10 years after creation
        return $creationDate->copy()->addYears(10);
    }

    /**
     * Calculate periodic charge for a relevant property trust (10-year anniversary)
     *
     * Charge is up to 6% of trust value, based on cumulative transfers
     */
    public function calculatePeriodicCharge(Trust $trust): array
    {
        if (! $trust->isRelevantPropertyTrust()) {
            return [
                'charge_amount' => 0,
                'effective_rate' => 0,
                'next_charge_date' => null,
            ];
        }

        $trustsConfig = $this->taxConfig->getTrusts();
        $ihtConfig = $this->taxConfig->getInheritanceTax();
        $nrb = $ihtConfig['nil_rate_band'];

        // Get max periodic charge rate from trusts config (defaults to 6%)
        $maxRate = $trustsConfig['periodic_charges']['max_rate'] ?? 0.06;

        // Simplified calculation - in practice this is complex
        // Rate is up to 6% based on how much trust exceeds NRB
        $trustValue = (float) $trust->current_value;
        $excessOverNRB = max(0, $trustValue - $nrb);

        // Effective rate calculation (simplified)
        // If trust value is zero, avoid division by zero
        $effectiveRate = $trustValue > 0
            ? min($maxRate, ($excessOverNRB / $trustValue) * 0.06)
            : 0;

        $chargeAmount = $trustValue * $effectiveRate;

        return [
            'charge_amount' => round($chargeAmount, 2),
            'effective_rate' => round($effectiveRate, 4),
            'trust_value' => round($trustValue, 2),
            'nrb' => round($nrb, 2),
            'excess_over_nrb' => round($excessOverNRB, 2),
            'next_charge_date' => $this->calculateNextPeriodicChargeDate($trust)?->format('Y-m-d'),
        ];
    }

    /**
     * Calculate the IHT value of all trusts for a user
     * Returns the amount that should be included in estate value
     */
    public function calculateTotalIHTValue(Collection $trusts): float
    {
        return $trusts->sum(function ($trust) {
            return $trust->getIHTValue();
        });
    }

    /**
     * Analyze trust efficiency for IHT planning
     */
    public function analyzeTrustEfficiency(Trust $trust): array
    {
        $trustsConfig = $this->taxConfig->getTrusts();
        $trustTypeConfig = $trustsConfig['types'][$trust->trust_type] ?? null;

        $valueInEstate = $trust->getIHTValue();
        $currentValue = (float) $trust->current_value;
        $initialValue = (float) $trust->initial_value;
        $valueOutsideEstate = $currentValue - $valueInEstate;
        $efficiencyPercent = $currentValue > 0
            ? ($valueOutsideEstate / $currentValue) * 100
            : 0;

        $growth = $currentValue - $initialValue;
        $growthRate = $initialValue > 0
            ? (($currentValue - $initialValue) / $initialValue) * 100
            : 0;

        $yearsActive = Carbon::parse($trust->trust_creation_date)->diffInYears(Carbon::now());

        // Get tax rates for this trust type
        $taxRates = $this->getTrustTaxRates($trust->trust_type);

        return [
            'trust_id' => $trust->id,
            'trust_name' => $trust->trust_name,
            'trust_type' => $trust->trust_type,
            'trust_type_name' => $trustTypeConfig['name'] ?? ucwords(str_replace('_', ' ', $trust->trust_type)),
            'trust_type_description' => $trustTypeConfig['description'] ?? null,
            'initial_value' => round($initialValue, 2),
            'current_value' => round($currentValue, 2),
            'growth' => round($growth, 2),
            'growth_rate_percent' => round($growthRate, 2),
            'value_in_estate' => round($valueInEstate, 2),
            'value_outside_estate' => round($valueOutsideEstate, 2),
            'iht_efficiency_percent' => round($efficiencyPercent, 2),
            'years_active' => $yearsActive,
            'is_relevant_property_trust' => $trust->isRelevantPropertyTrust(),
            'tax_rates' => $taxRates,
            'key_features' => $trustTypeConfig['key_features'] ?? [],
            'suitable_for' => $trustTypeConfig['suitable_for'] ?? [],
            'periodic_charge_info' => $trust->isRelevantPropertyTrust()
                ? $this->calculatePeriodicCharge($trust)
                : null,
        ];
    }

    /**
     * Get income and CGT tax rates for a specific trust type
     */
    public function getTrustTaxRates(string $trustType): array
    {
        $trustsConfig = $this->taxConfig->getTrusts();
        $trustTypeConfig = $trustsConfig['types'][$trustType] ?? null;

        // Default rates for discretionary trusts (from config, with fallbacks)
        $discretionaryConfig = $trustsConfig['income_tax']['discretionary'] ?? [];
        $incomeTaxRates = [
            'standard_rate' => (float) ($discretionaryConfig['standard_rate'] ?? $this->taxConfig->getIncomeTax()['additional_rate'] ?? 0.45),
            'dividend_rate' => (float) ($discretionaryConfig['dividend_rate'] ?? TaxDefaults::DIVIDEND_ADDITIONAL_RATE),
        ];

        // Determine income tax treatment based on trust type
        $incomeTaxTreatment = $trustTypeConfig['income_tax_treatment'] ?? 'trust_discretionary';

        if ($incomeTaxTreatment === 'trust_iip' || $trustType === 'interest_in_possession') {
            // Interest in Possession trusts have lower rates — fallback to basic rate from TaxConfigService
            $basicRate = (float) ($this->taxConfig->getIncomeTax()['bands'][0]['rate'] ?? 0.20);
            $incomeTaxRates = $trustsConfig['income_tax']['interest_in_possession'] ?? [
                'standard_rate' => $basicRate,
                'dividend_rate' => 0.0875,
            ];
        } elseif ($incomeTaxTreatment === 'beneficiary' || $trustType === 'bare') {
            // Bare trusts - taxed as beneficiary's income
            $incomeTaxRates = [
                'standard_rate' => null,
                'dividend_rate' => null,
                'note' => 'Taxed as beneficiary\'s income using their personal rates',
            ];
        } elseif ($incomeTaxTreatment === 'settlor' || $trustType === 'settlor_interested') {
            // Settlor-interested trusts - taxed on settlor
            $incomeTaxRates = [
                'standard_rate' => null,
                'dividend_rate' => null,
                'note' => 'Taxed as settlor\'s income using their personal rates',
            ];
        } elseif ($incomeTaxTreatment === 'none' || $trustType === 'life_insurance') {
            // Life insurance trusts - no regular income
            $incomeTaxRates = [
                'standard_rate' => null,
                'dividend_rate' => null,
                'note' => 'No regular income - policy proceeds on death',
            ];
        } else {
            // Discretionary and accumulation trusts
            $incomeTaxRates = $trustsConfig['income_tax']['discretionary'] ?? [
                'standard_rate' => $this->taxConfig->getIncomeTax()['additional_rate'] ?? 0.45,
                'dividend_rate' => TaxDefaults::DIVIDEND_ADDITIONAL_RATE,
            ];
        }

        // Get CGT rates
        $cgtConfig = $trustsConfig['capital_gains_tax'] ?? [];
        $cgtTreatment = $trustTypeConfig['cgt_treatment'] ?? 'trust';

        $cgtRates = match ($cgtTreatment) {
            'beneficiary' => [
                'rate' => null,
                'annual_exempt_amount' => null,
                'note' => 'Uses beneficiary\'s CGT allowance and rates',
            ],
            'settlor' => [
                'rate' => null,
                'annual_exempt_amount' => null,
                'note' => 'Uses settlor\'s CGT allowance and rates',
            ],
            'none' => [
                'rate' => null,
                'annual_exempt_amount' => null,
                'note' => 'No CGT on life policy proceeds',
            ],
            default => [
                'rate' => $cgtConfig['rate'] ?? 0.24,
                'annual_exempt_amount' => $cgtConfig['annual_exempt_amount'] ?? 1500,
                'vulnerable_beneficiary_exempt_amount' => $cgtConfig['vulnerable_beneficiary_exempt_amount'] ?? 3000,
            ],
        };

        return [
            'income_tax' => $incomeTaxRates,
            'capital_gains_tax' => $cgtRates,
            'tax_free_allowance' => $trustsConfig['income_tax']['tax_free_allowance'] ?? 500,
            'iht_treatment' => $trustTypeConfig['iht_treatment'] ?? 'unknown',
        ];
    }

    /**
     * Get trust recommendations based on user's estate and circumstances
     */
    public function getTrustRecommendations(float $estateValue, float $ihtLiability, array $circumstances = []): array
    {
        $recommendations = [];
        $trustsConfig = $this->taxConfig->getTrusts();
        $trustTypes = $trustsConfig['types'] ?? [];

        // Default descriptions if not in database
        $defaultDescriptions = [
            'life_insurance' => 'Life insurance policy written in trust to pay IHT liability',
            'discounted_gift' => 'Gift assets to trust while retaining income rights',
            'loan' => 'Loan assets to trust, freezing estate value',
            'bare' => 'Simple trust giving child absolute entitlement at age 18',
            'discretionary' => 'Flexible trust allowing trustees to decide distributions',
        ];

        // If significant IHT liability
        if ($ihtLiability > 50000) {
            // Recommend life insurance trust to cover liability
            $recommendations[] = [
                'trust_type' => 'life_insurance',
                'priority' => 'high',
                'reason' => 'Cover IHT liability of £'.number_format($ihtLiability, 0).' without depleting estate assets',
                'description' => $trustTypes['life_insurance']['description'] ?? $defaultDescriptions['life_insurance'],
                'benefits' => [
                    'Policy proceeds paid outside estate',
                    'Provides liquid funds to pay IHT',
                    'Beneficiaries inherit estate intact',
                ],
            ];

            // If estate is large, consider discounted gift trust
            if ($estateValue > 1000000) {
                $recommendations[] = [
                    'trust_type' => 'discounted_gift',
                    'priority' => 'high',
                    'reason' => 'Reduce estate value while retaining income',
                    'description' => $trustTypes['discounted_gift']['description'] ?? $defaultDescriptions['discounted_gift'],
                    'benefits' => [
                        'Immediate IHT reduction (30-60% discount typical)',
                        'Retain regular income stream',
                        'Growth outside estate from day one',
                    ],
                ];
            }

            // Loan trust for flexibility
            $recommendations[] = [
                'trust_type' => 'loan',
                'priority' => 'medium',
                'reason' => 'Freeze estate value while maintaining access',
                'description' => $trustTypes['loan']['description'] ?? $defaultDescriptions['loan'],
                'benefits' => [
                    'No 7-year wait for original loan',
                    'Growth accrues outside estate immediately',
                    'Can repay loan if need capital',
                ],
            ];
        }

        // If have children
        if ($circumstances['has_children'] ?? false) {
            $recommendations[] = [
                'trust_type' => 'bare',
                'priority' => 'medium',
                'reason' => 'Pass assets to children with certainty',
                'description' => $trustTypes['bare']['description'] ?? $defaultDescriptions['bare'],
                'benefits' => [
                    'Simple and low-cost',
                    'PET treatment (7-year rule)',
                    'Child entitled at age 18',
                ],
            ];
        }

        // If want flexibility for beneficiaries
        if ($circumstances['needs_flexibility'] ?? false) {
            $recommendations[] = [
                'trust_type' => 'discretionary',
                'priority' => 'medium',
                'reason' => 'Maximum flexibility for trustees',
                'description' => $trustTypes['discretionary']['description'] ?? $defaultDescriptions['discretionary'],
                'benefits' => [
                    'Trustees decide distributions',
                    'Protect vulnerable beneficiaries',
                    'Assets outside settlor estate',
                ],
                'drawbacks' => [
                    'Periodic charges every 10 years (up to 6%)',
                    'Entry charge (20% over NRB)',
                    'Exit charges on distributions',
                ],
            ];
        }

        return $recommendations;
    }

    /**
     * Calculate discounted gift trust discount based on age, income, and gender
     * Uses actuarial tables when gender is provided for more accurate estimates
     */
    public function estimateDiscountedGiftDiscount(int $age, float $giftValue, float $annualIncome, ?string $gender = null): array
    {
        // Use actuarial tables when gender is available
        if ($gender) {
            $actuarialExpectancy = ActuarialLifeTable::where('gender', $gender)
                ->where('age', '<=', $age)
                ->where('table_year', '2020-2022')
                ->orderBy('age', 'desc')
                ->value('life_expectancy_years');

            $lifeExpectancy = $actuarialExpectancy
                ? max(5, (int) ceil((float) $actuarialExpectancy))
                : max(5, 90 - $age);
        } else {
            $lifeExpectancy = max(5, 90 - $age);
        }

        // Total expected income payments
        $totalExpectedIncome = $annualIncome * $lifeExpectancy;

        // Discount is NPV of future income stream (simplified - no discounting applied)
        // In practice, this uses actuarial tables and discount rates
        $discount = min($giftValue * 0.60, $totalExpectedIncome * 0.8); // Max 60% discount

        $giftedValue = $giftValue - $discount;
        $discountPercent = ($discount / $giftValue) * 100;

        return [
            'gift_value' => round($giftValue, 2),
            'discount_amount' => round($discount, 2),
            'discount_percent' => round($discountPercent, 2),
            'gifted_value' => round($giftedValue, 2), // This is the PET
            'retained_value' => round($discount, 2), // This stays in estate
            'annual_income' => round($annualIncome, 2),
            'estimated_life_expectancy' => $lifeExpectancy,
        ];
    }
}
