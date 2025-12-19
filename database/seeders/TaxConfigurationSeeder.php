<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\TaxConfiguration;
use Illuminate\Database\Seeder;

class TaxConfigurationSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * Seeds 5 UK tax years (2021/22 through 2025/26) with comprehensive tax configuration.
     * 2025/26 is set as the active tax year.
     */
    public function run(): void
    {
        $this->command->info('');
        $this->command->info('Seeding UK tax configurations for 5 tax years...');

        $taxYears = [
            '2021/22' => $this->getTaxConfig202122(),
            '2022/23' => $this->getTaxConfig202223(),
            '2023/24' => $this->getTaxConfig202324(),
            '2024/25' => $this->getTaxConfig202425(),
            '2025/26' => $this->getTaxConfig202526(),
        ];

        foreach ($taxYears as $taxYear => $config) {
            $isActive = ($taxYear === '2025/26');

            TaxConfiguration::updateOrCreate(
                ['tax_year' => $taxYear],
                [
                    'effective_from' => $config['effective_from'],
                    'effective_to' => $config['effective_to'],
                    'config_data' => $config,
                    'is_active' => $isActive,
                    'notes' => $config['notes'],
                ]
            );

            $this->command->info("✓ Tax configuration for {$taxYear} seeded successfully.");
        }

        // Ensure only 2025/26 is active
        TaxConfiguration::where('tax_year', '!=', '2025/26')
            ->update(['is_active' => false]);

        $this->command->info('');
        $this->command->info('✓ All 5 tax years seeded successfully. 2025/26 is the active tax year.');
    }

    /**
     * Get tax configuration for 2025/26
     */
    private function getTaxConfig202526(): array
    {
        return [
            'tax_year' => '2025/26',
            'effective_from' => '2025-04-06',
            'effective_to' => '2026-04-05',
            'notes' => 'UK Tax Year 2025/26 - Current active configuration',

            'income_tax' => [
                'personal_allowance' => 12570,
                'personal_allowance_taper_threshold' => 100000,
                'personal_allowance_taper_rate' => 0.5,

                'bands' => [
                    [
                        'name' => 'Basic Rate',
                        'lower_limit' => 12570,    // Display value: absolute threshold
                        'upper_limit' => 50270,    // Display value: absolute threshold
                        'min' => 0,                // Calculator value: band width
                        'max' => 37700,            // Calculator value: band width
                        'rate' => 0.20,            // Decimal format (20%)
                    ],
                    [
                        'name' => 'Higher Rate',
                        'lower_limit' => 50270,
                        'upper_limit' => 125140,
                        'min' => 37700,
                        'max' => 125140,
                        'rate' => 0.40,            // Decimal format (40%)
                    ],
                    [
                        'name' => 'Additional Rate',
                        'lower_limit' => 125140,
                        'upper_limit' => null,
                        'min' => 125140,
                        'max' => null,
                        'rate' => 0.45,            // Decimal format (45%)
                    ],
                ],

                'scotland' => [
                    'enabled' => false,
                    'bands' => [],
                ],
            ],

            'national_insurance' => [
                'class_1' => [
                    'employee' => [
                        'primary_threshold' => 12570,
                        'upper_earnings_limit' => 50270,
                        'main_rate' => 0.08,
                        'additional_rate' => 0.02,
                    ],
                    'employer' => [
                        'secondary_threshold' => 9100,
                        'rate' => 0.138,
                    ],
                ],
                'class_2' => [
                    'abolished' => true,
                ],
                'class_4' => [
                    'lower_profits_limit' => 12570,
                    'upper_profits_limit' => 50270,
                    'main_rate' => 0.09,
                    'additional_rate' => 0.02,
                ],
            ],

            'capital_gains_tax' => [
                // Individual rates
                'annual_exempt_amount' => 3000,
                'basic_rate' => 0.18,                            // Decimal format (18%)
                'higher_rate' => 0.24,                           // Decimal format (24%)
                'residential_property_basic_rate' => 0.18,       // Decimal format (18%)
                'residential_property_higher_rate' => 0.24,      // Decimal format (24%)

                // Trust rates (2025/26 - verified from gov.uk)
                'trust_rate' => 0.24,                            // Decimal format (24%)
                'trust_annual_exempt_amount' => 1500,            // Standard trusts
                'trust_vulnerable_beneficiary_exempt_amount' => 3000,  // Vulnerable beneficiary trusts
            ],

            'dividend_tax' => [
                // Individual rates
                'allowance' => 500,                              // Individuals only (trusts have no allowance)
                'basic_rate' => 0.0875,                          // Decimal format (8.75%)
                'higher_rate' => 0.3375,                         // Decimal format (33.75%)
                'additional_rate' => 0.3935,                     // Decimal format (39.35%)

                // Trust rates (2025/26 - verified from gov.uk)
                'trust_dividend_rate' => 0.3935,                 // Decimal format (39.35%)
                'trust_other_income_rate' => 0.45,               // Decimal format (45%)
                'trust_de_minimis_allowance' => 500,             // If income exceeds £500, ALL income is taxable
                'trust_management_expenses_dividend_rate' => 0.0875,  // Decimal format (8.75%)
                'trust_management_expenses_other_rate' => 0.20,       // Decimal format (20%)
            ],

            'isa' => [
                'annual_allowance' => 20000,
                'lifetime_isa' => [
                    'annual_allowance' => 4000,
                    'max_age_to_open' => 39,
                    'government_bonus_rate' => 0.25,
                    'withdrawal_penalty' => 0.25,
                ],
                'junior_isa' => [
                    'annual_allowance' => 9000,
                    'max_age' => 17,
                ],
            ],

            'pension' => [
                'annual_allowance' => 60000,
                'money_purchase_annual_allowance' => 10000,
                'mpaa' => 10000,
                'lifetime_allowance_abolished' => true,
                'carry_forward_years' => 3,
                'tapered_annual_allowance' => [
                    'threshold_income' => 200000,
                    'adjusted_income' => 260000,
                    'adjusted_income_threshold' => 260000,
                    'minimum_allowance' => 10000,
                    'taper_rate' => 0.5,
                ],
                'tax_relief' => [
                    'basic_rate' => 0.20,
                    'higher_rate' => 0.40,
                    'additional_rate' => 0.45,
                ],
                'state_pension' => [
                    'full_new_state_pension' => 11973.00,
                    'qualifying_years' => 35,
                    'minimum_qualifying_years' => 10,
                ],
            ],

            'inheritance_tax' => [
                'nil_rate_band' => 325000,
                'residence_nil_rate_band' => 175000,
                'rnrb_taper_threshold' => 2000000,
                'rnrb_taper_rate' => 0.5,
                'standard_rate' => 0.40,
                'reduced_rate_charity' => 0.36,
                'spouse_exemption' => true,
                'transferable_nil_rate_band' => true,
                'potentially_exempt_transfers' => [
                    'years_to_exemption' => 7,
                    'taper_relief' => [
                        ['years' => 3, 'rate' => 0.40],
                        ['years' => 4, 'rate' => 0.32],
                        ['years' => 5, 'rate' => 0.24],
                        ['years' => 6, 'rate' => 0.16],
                        ['years' => 7, 'rate' => 0.08],
                    ],
                ],
                'chargeable_lifetime_transfers' => [
                    'lookback_period' => 14,
                    'rate' => 0.20,
                ],

                // Trust IHT charges (2025/26 - verified from gov.uk)
                'trust_entry_charge' => 0.20,                    // 20% on chargeable lifetime transfers into trusts exceeding NRB
                'trust_periodic_charge_max' => 0.06,             // Max 6% on 10-year anniversary
                'trust_exit_charge_max' => 0.06,                 // Max 6% when assets leave trust (pro-rated)
                'trust_no_exit_charge_period' => 3,              // No exit charge if distribution within 3 months of setup
                'trust_will_no_exit_charge_period' => 24,        // Discretionary will trust: no exit charge if distributed within 2 years of death
            ],

            'gifting_exemptions' => [
                'annual_exemption' => 3000,
                'annual_exemption_can_carry_forward' => true,
                'carry_forward_years' => 1,
                'small_gifts_limit' => 250,    // Flattened for Vue component display
                'wedding_gifts' => [
                    'child' => 5000,
                    'grandchild_great_grandchild' => 2500,
                    'other' => 1000,
                ],
            ],

            'stamp_duty' => [
                'residential' => [
                    'standard' => [
                        'bands' => [
                            ['threshold' => 0, 'rate' => 0.00],
                            ['threshold' => 125000, 'rate' => 0.02],
                            ['threshold' => 250000, 'rate' => 0.05],
                            ['threshold' => 925000, 'rate' => 0.10],
                            ['threshold' => 1500000, 'rate' => 0.12],
                        ],
                    ],
                    'additional_properties' => [
                        'surcharge' => 0.05,  // 5% surcharge for additional properties
                        'bands' => [
                            ['threshold' => 0, 'rate' => 0.05],
                            ['threshold' => 125000, 'rate' => 0.07],
                            ['threshold' => 250000, 'rate' => 0.10],
                            ['threshold' => 925000, 'rate' => 0.15],
                            ['threshold' => 1500000, 'rate' => 0.17],
                        ],
                    ],
                    'first_time_buyers' => [
                        'nil_rate_threshold' => 300000,  // Updated to £300k
                        'max_property_value' => 500000,  // Updated to £500k
                        'bands' => [
                            ['threshold' => 0, 'rate' => 0.00],
                            ['threshold' => 300000, 'rate' => 0.05],
                        ],
                    ],
                    'non_resident_surcharge' => 0.02,  // 2% for non-UK residents
                ],
            ],

            'assumptions' => [
                'investment_growth' => [
                    'cash' => 0.01,
                    'bonds' => 0.02,
                    'equities_uk' => 0.05,
                    'equities_global' => 0.055,
                    'property' => 0.03,
                    'balanced_portfolio' => 0.04,
                ],
                'inflation' => 0.02,
                'salary_growth' => 0.03,
            ],

            // Property ownership and leasehold information
            'property_ownership' => [
                'joint_ownership_types' => [
                    'joint_tenancy' => [
                        'name' => 'Joint Tenancy',
                        'description' => 'Equal rights to whole property',
                        'survivorship' => true,
                        'will_override' => false,
                        'notes' => 'Property automatically passes to surviving owner(s), bypassing will',
                    ],
                    'tenants_in_common' => [
                        'name' => 'Tenants in Common',
                        'description' => 'Specified shares (may be unequal)',
                        'survivorship' => false,
                        'will_override' => true,
                        'notes' => 'Your share passes according to your will or intestacy rules',
                    ],
                ],

                'leasehold_reform' => [
                    'ground_rent_abolished_date' => '2022-06-30',  // Leasehold Reform (Ground Rent) Act 2022
                    'ground_rent_cap' => 0,  // £0 for new leases from 2022 (2023 for retirement homes)
                    'retirement_homes_date' => '2023-04-01',
                    'commonhold_consultation_year' => 2025,
                    'notes' => 'UK government phasing out leasehold for new builds. Commonhold will become default tenure.',
                    'valuation_thresholds' => [
                        'difficult_to_mortgage' => 80,  // Years remaining - harder to get mortgage
                        'significant_value_loss' => 60,  // Years remaining - property value significantly affected
                    ],
                ],

                'tenure_types' => [
                    'freehold' => [
                        'name' => 'Freehold',
                        'description' => 'Outright ownership of property and land',
                        'ground_rent' => false,
                        'lease_expiry' => false,
                    ],
                    'leasehold' => [
                        'name' => 'Leasehold',
                        'description' => 'Long-term rental of property (typically 99-999 years)',
                        'ground_rent' => true,  // Abolished for new leases from 2022
                        'lease_expiry' => true,
                        'notes' => 'Being phased out for new builds. Ground rent eliminated 2022.',
                    ],
                ],
            ],

            // UK Trusts - Tax Rates and Types (2025/26 verified from gov.uk)
            'trusts' => [
                // Income Tax for Trusts
                'income_tax' => [
                    // Discretionary and Accumulation trusts (Relevant Property Trusts)
                    'discretionary' => [
                        'standard_rate' => 0.45,           // 45% on non-dividend income
                        'dividend_rate' => 0.3935,         // 39.35% on dividends
                    ],
                    // Interest in Possession trusts
                    'interest_in_possession' => [
                        'standard_rate' => 0.20,           // 20% on non-dividend income
                        'dividend_rate' => 0.0875,         // 8.75% on dividends
                    ],
                    // Tax-free allowance (standard rate band)
                    'tax_free_allowance' => 500,           // £500 de minimis - if income exceeds this, ALL is taxable
                    'tax_free_allowance_minimum' => 100,   // Minimum when multiple trusts share allowance
                    'notes' => 'The £500 allowance is divided equally between trusts created by the same settlor. Minimum £100 per trust.',
                ],

                // Capital Gains Tax for Trusts
                'capital_gains_tax' => [
                    'rate' => 0.24,                        // 24% flat rate (no basic/higher split for trusts)
                    'annual_exempt_amount' => 1500,        // £1,500 standard
                    'vulnerable_beneficiary_exempt_amount' => 3000, // £3,000 for vulnerable beneficiaries
                    'notes' => 'Trusts pay CGT at a flat 24% rate. The annual exempt amount is half the individual allowance.',
                ],

                // IHT Charges for Trusts
                'inheritance_tax' => [
                    'entry_charge' => 0.20,                // 20% on CLTs exceeding NRB (paid at time of gift)
                    'periodic_charge_max' => 0.06,         // Max 6% on 10-year anniversary
                    'exit_charge_max' => 0.06,             // Max 6% when assets leave trust (proportionate)
                    'periodic_charge_interval' => 10,      // Years between periodic charges
                    'no_exit_charge_period' => 3,          // Months - no exit charge if distributed within 3 months of setup
                    'will_trust_no_exit_charge_period' => 24, // Months - discretionary will trust: no exit charge within 2 years of death
                    'notes' => 'Relevant Property Trusts face IHT charges every 10 years. Exit charges are proportionate based on time since last periodic charge.',
                ],

                // Management Expenses Relief
                'management_expenses' => [
                    'dividend_relief_rate' => 0.0875,      // 8.75% credit for expenses against dividends
                    'other_income_relief_rate' => 0.20,    // 20% credit for expenses against other income
                    'notes' => 'Trustees can claim relief for legitimate trust management expenses.',
                ],

                // Trust Type Definitions
                'types' => [
                    'bare' => [
                        'name' => 'Bare Trust',
                        'description' => 'Assets held by trustees for a beneficiary who has an absolute right to capital and income. Simple and tax-efficient.',
                        'income_tax_treatment' => 'beneficiary',  // Taxed as beneficiary\'s income
                        'cgt_treatment' => 'beneficiary',         // Uses beneficiary\'s CGT allowance
                        'iht_treatment' => 'pet',                 // Potentially Exempt Transfer (7-year rule)
                        'is_relevant_property_trust' => false,
                        'suitable_for' => ['Gifts to children/grandchildren', 'Simple inheritance planning'],
                        'key_features' => [
                            'Beneficiary absolutely entitled at age 18',
                            'No trustee discretion over distributions',
                            'Tax efficient - uses beneficiary\'s allowances',
                            'PET treatment - exempt after 7 years',
                        ],
                    ],
                    'interest_in_possession' => [
                        'name' => 'Interest in Possession Trust',
                        'description' => 'Beneficiary (life tenant) has right to trust income. Capital passes to remainder beneficiaries on life tenant\'s death.',
                        'income_tax_treatment' => 'trust_iip',    // 20%/8.75% rates
                        'cgt_treatment' => 'trust',               // Trust rates apply
                        'iht_treatment' => 'life_tenant_estate',  // Counts in life tenant\'s estate if qualifying
                        'is_relevant_property_trust' => false,    // Pre-2006 IIP trusts are not RPTs
                        'suitable_for' => ['Providing for spouse while preserving capital for children', 'Second marriages'],
                        'key_features' => [
                            'Life tenant receives all income',
                            'Capital preserved for remainder beneficiaries',
                            'Lower trust tax rates than discretionary',
                            'May be part of life tenant\'s estate for IHT',
                        ],
                    ],
                    'discretionary' => [
                        'name' => 'Discretionary Trust',
                        'description' => 'Trustees have full discretion over income and capital distributions among beneficiaries. Maximum flexibility.',
                        'income_tax_treatment' => 'trust_discretionary', // 45%/39.35% rates
                        'cgt_treatment' => 'trust',
                        'iht_treatment' => 'relevant_property',   // Subject to 10-year and exit charges
                        'is_relevant_property_trust' => true,
                        'suitable_for' => ['Protecting vulnerable beneficiaries', 'Flexibility for changing circumstances', 'Tax planning'],
                        'key_features' => [
                            'Maximum flexibility for trustees',
                            'Protects assets from beneficiary creditors',
                            'Higher income tax rates (45%/39.35%)',
                            '10-year periodic charges (up to 6%)',
                            'Exit charges when capital distributed',
                        ],
                    ],
                    'accumulation_maintenance' => [
                        'name' => 'Accumulation & Maintenance Trust',
                        'description' => 'Trust where income is accumulated for beneficiaries who will become entitled at a specified age (now maximum 18).',
                        'income_tax_treatment' => 'trust_discretionary',
                        'cgt_treatment' => 'trust',
                        'iht_treatment' => 'relevant_property',
                        'is_relevant_property_trust' => true,
                        'suitable_for' => ['Education planning', 'Gifts to minor children'],
                        'key_features' => [
                            'Income accumulated until beneficiary reaches specified age',
                            'Post-2006 trusts: beneficiary must be entitled by age 18',
                            'Same tax treatment as discretionary trusts',
                        ],
                    ],
                    'life_insurance' => [
                        'name' => 'Life Insurance Trust',
                        'description' => 'Trust holding life insurance policy proceeds, keeping them outside the estate for IHT purposes.',
                        'income_tax_treatment' => 'none',         // No income (payout on death)
                        'cgt_treatment' => 'none',                // No CGT on life policy proceeds
                        'iht_treatment' => 'outside_estate',      // Outside settlor\'s estate
                        'is_relevant_property_trust' => false,    // Not RPT if no trust assets other than policy
                        'suitable_for' => ['IHT planning', 'Providing liquidity to pay IHT', 'Business protection'],
                        'key_features' => [
                            'Policy proceeds paid outside estate',
                            'Provides liquid funds to pay IHT',
                            'Beneficiaries receive proceeds directly',
                            'No IHT on policy proceeds (if written correctly)',
                        ],
                    ],
                    'discounted_gift' => [
                        'name' => 'Discounted Gift Trust',
                        'description' => 'Settlor gifts capital but retains right to regular income. Immediate IHT reduction based on actuarial calculation.',
                        'income_tax_treatment' => 'settlor',      // Settlor taxed on retained income
                        'cgt_treatment' => 'trust',
                        'iht_treatment' => 'partial_pet',         // Discounted value is a PET
                        'is_relevant_property_trust' => false,
                        'suitable_for' => ['Those needing income but wanting to reduce estate', 'Older settlors (better discount)'],
                        'key_features' => [
                            'Immediate IHT reduction (30-60% typical)',
                            'Retain regular income stream for life',
                            'Growth outside estate from day one',
                            'Discount based on age and health at setup',
                        ],
                    ],
                    'loan' => [
                        'name' => 'Loan Trust',
                        'description' => 'Settlor loans money to trust (interest-free). Loan can be repaid but growth accrues outside estate.',
                        'income_tax_treatment' => 'trust_discretionary',
                        'cgt_treatment' => 'trust',
                        'iht_treatment' => 'loan_in_estate',      // Outstanding loan remains in estate
                        'is_relevant_property_trust' => true,
                        'suitable_for' => ['Those wanting access to capital', 'Flexible IHT planning'],
                        'key_features' => [
                            'No 7-year wait for original loan amount',
                            'Growth accrues outside estate immediately',
                            'Can repay loan if capital needed',
                            'Outstanding loan counts in estate at death',
                        ],
                    ],
                    'mixed' => [
                        'name' => 'Mixed Trust',
                        'description' => 'Trust with elements of different trust types, e.g., part discretionary and part interest in possession.',
                        'income_tax_treatment' => 'mixed',
                        'cgt_treatment' => 'trust',
                        'iht_treatment' => 'mixed',
                        'is_relevant_property_trust' => true,     // Usually treated as RPT
                        'suitable_for' => ['Complex family situations', 'Tailored estate planning'],
                        'key_features' => [
                            'Combines features of different trust types',
                            'Complex tax treatment',
                            'Professional advice essential',
                        ],
                    ],
                    'settlor_interested' => [
                        'name' => 'Settlor-Interested Trust',
                        'description' => 'Trust where settlor or spouse can benefit. Income and gains taxed on settlor.',
                        'income_tax_treatment' => 'settlor',      // Settlor taxed on all income
                        'cgt_treatment' => 'settlor',             // Settlor taxed on gains
                        'iht_treatment' => 'in_estate',           // Remains in settlor\'s estate
                        'is_relevant_property_trust' => false,
                        'suitable_for' => ['Very limited use cases'],
                        'key_features' => [
                            'Settlor taxed on all income (even if not received)',
                            'Settlor taxed on capital gains',
                            'Assets remain in estate for IHT',
                            'Limited tax planning benefit',
                        ],
                    ],
                ],

                // Periodic Charges Configuration
                'periodic_charges' => [
                    'max_rate' => 0.06,                    // Maximum 6% of trust value
                    'calculation_method' => 'cumulative', // Based on cumulative transfers in 7 years before setup
                    'nrb_applies' => true,                // NRB available against trust value
                    'notes' => 'Effective rate depends on how much of the NRB has been used by previous CLTs.',
                ],

                // General Notes
                'notes' => 'UK trust taxation is complex. The rates above apply to 2025/26. Trusts must file annual self-assessment returns if they have taxable income or gains. Professional advice recommended for trust planning.',
            ],
        ];
    }

    /**
     * Get tax configuration for 2024/25
     */
    private function getTaxConfig202425(): array
    {
        $config = $this->getTaxConfig202526();
        $config['tax_year'] = '2024/25';
        $config['effective_from'] = '2024-04-06';
        $config['effective_to'] = '2025-04-05';
        $config['notes'] = 'UK Tax Year 2024/25 - Historical configuration';

        // Same rates as 2025/26
        return $config;
    }

    /**
     * Get tax configuration for 2023/24
     */
    private function getTaxConfig202324(): array
    {
        $config = $this->getTaxConfig202526();
        $config['tax_year'] = '2023/24';
        $config['effective_from'] = '2023-04-06';
        $config['effective_to'] = '2024-04-05';
        $config['notes'] = 'UK Tax Year 2023/24 - Historical configuration';

        // 2023/24 had higher CGT allowance
        $config['capital_gains_tax']['annual_exempt_amount'] = 6000;

        return $config;
    }

    /**
     * Get tax configuration for 2022/23
     */
    private function getTaxConfig202223(): array
    {
        $config = $this->getTaxConfig202526();
        $config['tax_year'] = '2022/23';
        $config['effective_from'] = '2022-04-06';
        $config['effective_to'] = '2023-04-05';
        $config['notes'] = 'UK Tax Year 2022/23 - Historical configuration';

        // 2022/23 had higher CGT allowance
        $config['capital_gains_tax']['annual_exempt_amount'] = 12300;

        return $config;
    }

    /**
     * Get tax configuration for 2021/22
     */
    private function getTaxConfig202122(): array
    {
        $config = $this->getTaxConfig202526();
        $config['tax_year'] = '2021/22';
        $config['effective_from'] = '2021-04-06';
        $config['effective_to'] = '2022-04-05';
        $config['notes'] = 'UK Tax Year 2021/22 - Historical configuration';

        // 2021/22 had different Additional Rate threshold (£150k)
        $config['income_tax']['bands'][1]['upper_limit'] = 150000;
        $config['income_tax']['bands'][1]['max'] = 150000;
        $config['income_tax']['bands'][2]['lower_limit'] = 150000;
        $config['income_tax']['bands'][2]['min'] = 150000;

        // 2021/22 had higher CGT allowance
        $config['capital_gains_tax']['annual_exempt_amount'] = 12300;

        return $config;
    }
}
