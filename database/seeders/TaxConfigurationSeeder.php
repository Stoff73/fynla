<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\TaxConfiguration;
use Illuminate\Database\Seeder;

class TaxConfigurationSeeder extends Seeder
{
    /**
     * The tax year that should be marked active after seeding.
     *
     * Change this single value to switch which year the app uses by default.
     * The seeder is idempotent — re-running it will restore this year as active.
     */
    private const ACTIVE_TAX_YEAR = '2026/27';

    /**
     * Run the database seeds.
     *
     * Seeds 6 UK tax years (2021/22 through 2026/27) with comprehensive tax configuration.
     * The year defined by self::ACTIVE_TAX_YEAR is set as the active tax year.
     */
    public function run(): void
    {
        $this->command->info('');
        $this->command->info('Seeding UK tax configurations for 6 tax years...');

        $taxYears = [
            '2021/22' => $this->getTaxConfig202122(),
            '2022/23' => $this->getTaxConfig202223(),
            '2023/24' => $this->getTaxConfig202324(),
            '2024/25' => $this->getTaxConfig202425(),
            '2025/26' => $this->getTaxConfig202526(),
            '2026/27' => $this->getTaxConfig202627(),
        ];

        foreach ($taxYears as $taxYear => $config) {
            $isActive = ($taxYear === self::ACTIVE_TAX_YEAR);

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

        // Ensure only the designated active year is marked active.
        // Admins can change the active year at runtime via the Tax Settings admin UI
        // without re-running this seeder; re-seeding will reset to self::ACTIVE_TAX_YEAR.
        TaxConfiguration::where('tax_year', '!=', self::ACTIVE_TAX_YEAR)
            ->update(['is_active' => false]);

        $this->command->info('');
        $this->command->info('✓ All 6 tax years seeded successfully. '.self::ACTIVE_TAX_YEAR.' is the active tax year.');
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

                // Personal Savings Allowance (PSA) — tax-free savings interest
                'personal_savings_allowance' => [
                    'basic' => 1000,        // Basic rate taxpayers: £1,000
                    'higher' => 500,        // Higher rate taxpayers: £500
                    'additional' => 0,      // Additional rate taxpayers: £0
                ],

                // Starting Rate for Savings — 0% band for low earners
                'starting_rate_for_savings' => [
                    'band' => 5000,         // £5,000 starting rate band
                    'rate' => 0,            // 0% rate
                ],

                // Blind Person's Allowance
                'blind_persons_allowance' => 3130,
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
                        'secondary_threshold' => 5000,   // Reduced from £9,100 to £5,000 from April 2025 (Autumn Budget 2024)
                        'rate' => 0.15,                  // Increased from 13.8% to 15.0% from April 2025 (+1.2pp)
                    ],
                ],
                'class_2' => [
                    'abolished' => true,
                ],
                'class_4' => [
                    'lower_profits_limit' => 12570,
                    'upper_profits_limit' => 50270,
                    'main_rate' => 0.06,             // Cut from 9% to 6% from April 2024
                    'additional_rate' => 0.02,
                ],
            ],

            'capital_gains_tax' => [
                // Individual rates - Annual exempt amount
                'annual_exempt_amount' => 3000,

                // Non-residential rates (shares, chattels, business assets, other assets)
                // From 30 Oct 2024, aligned with residential rates (18%/24%)
                'basic_rate' => 0.18,                            // Decimal format (18%)
                'higher_rate' => 0.24,                           // Decimal format (24%)

                // Residential property rates (higher rates for property)
                'residential_property_basic_rate' => 0.18,       // Decimal format (18%)
                'residential_property_higher_rate' => 0.24,      // Decimal format (24%)

                // Trust rates (2025/26 - verified from gov.uk)
                'trust_rate' => 0.24,                            // Decimal format (24%)
                'trust_annual_exempt_amount' => 1500,            // Standard trusts
                'trust_vulnerable_beneficiary_exempt_amount' => 3000,  // Vulnerable beneficiary trusts

                // Chattels (personal property) special rules
                'chattel_exemption_threshold' => 6000,           // Exempt if proceeds <= £6,000
                'chattel_marginal_relief_limit' => 15000,        // Marginal relief applies up to £15,000
                'chattel_marginal_relief_multiplier' => 1.6667,  // 5/3 rule for marginal relief

                // Business Asset Disposal Relief (formerly Entrepreneurs' Relief)
                'business_asset_disposal_relief_rate' => 0.14,   // 14% for 2025/26, rising to 18% in 2026/27
                'business_asset_disposal_relief_lifetime_limit' => 1000000, // £1m lifetime limit
                'business_asset_disposal_relief_min_ownership_years' => 2,  // 2 years minimum ownership
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

            // Savings-specific constants (FSCS, Premium Bonds, etc.)
            'savings' => [
                'fscs_deposit_protection' => 85000,              // £85,000 per eligible person per institution
                'fscs_joint_protection' => 170000,               // £170,000 for joint accounts
                'fscs_temporary_high_balance' => 1000000,        // £1,000,000 temporary high balance protection
                'fscs_temporary_high_balance_months' => 6,       // 6-month protection period
                'premium_bonds_max_holding' => 50000,            // £50,000 maximum holding
                'premium_bonds_min_purchase' => 25,              // £25 minimum purchase
                'premium_bonds_min_age_self' => 16,              // Must be 16+ to buy for self
                'premium_bonds_prize_fund_rate' => 0.044,        // 4.4% prize fund rate
                'parental_settlement_threshold' => 100,          // £100/year threshold for parental gifts
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
                    'current_spa' => 66,                         // Current State Pension Age
                    'future_spa' => 67,                          // Rising to 67 between April 2026 and April 2028; further rise to 68 planned 2044-2046
                ],

                // Salary sacrifice configuration
                'salary_sacrifice' => [
                    'nlw_hourly' => 12.21,                       // National Living Wage (21+) 2025/26
                    'nmw_hourly' => [
                        '21_plus' => 12.21,                      // National Living Wage
                        '18_to_20' => 10.00,
                        'under_18' => 7.55,
                        'apprentice' => 7.55,
                    ],
                    'conservative_proxy_floor' => 10000,         // Use auto-enrolment earnings trigger as proxy
                    'nic_exemption_cap' => 2000,                 // From April 2029: only first £2,000 of employee salary sacrifice exempt from NICs
                    'nic_exemption_cap_effective_date' => '2029-04-06', // When the cap takes effect
                ],

                // Auto-enrolment thresholds
                'auto_enrolment' => [
                    'earnings_trigger' => 10000,                 // £10,000 — must auto-enrol above this
                    'lower_qualifying_earnings' => 6240,         // £6,240 — lower limit of qualifying earnings band
                    'upper_qualifying_earnings' => 50270,        // £50,270 — upper limit of qualifying earnings band
                    'minimum_total_contribution' => 0.08,        // 8% minimum total contribution
                    'minimum_employer_contribution' => 0.03,     // 3% minimum employer contribution
                    'minimum_employee_contribution' => 0.05,     // 5% minimum employee contribution
                ],
            ],

            'inheritance_tax' => [
                // =================================================================
                // Core Thresholds and Rates
                // =================================================================
                'nil_rate_band' => 325000,                       // £325,000 - frozen until April 2031
                'residence_nil_rate_band' => 175000,             // £175,000 - for main residence left to direct descendants
                'rnrb_taper_threshold' => 2000000,               // RNRB tapers away if estate exceeds £2m
                'rnrb_taper_rate' => 0.5,                        // £1 lost per £2 over threshold
                'standard_rate' => 0.40,                         // 40% on taxable estate
                'reduced_rate_charity' => 0.36,                  // 36% if 10%+ of net estate left to charity
                'charity_threshold_percent' => 0.10,             // 10% of baseline amount required for reduced rate
                'spouse_exemption' => true,                      // Unlimited transfers to UK-domiciled spouse/civil partner
                'transferable_nil_rate_band' => true,            // Unused NRB can transfer to surviving spouse
                'transferable_rnrb' => true,                     // Unused RNRB can also transfer to surviving spouse

                // =================================================================
                // Potentially Exempt Transfers (PETs)
                // Gifts to individuals that become exempt if donor survives 7 years
                // =================================================================
                'potentially_exempt_transfers' => [
                    'years_to_exemption' => 7,                   // Fully exempt after 7 years
                    'immediate_charge' => false,                 // No tax on gift when made
                    'becomes_chargeable_on_death' => true,       // Becomes chargeable if donor dies within 7 years
                    'uses_donor_nrb' => true,                    // Uses donor's NRB when calculating tax
                    'cumulation_period' => 7,                    // PETs in 7 years before death are cumulated

                    // Taper relief - reduces tax if donor survives 3-7 years
                    // Note: Only applies if gift itself exceeds available NRB
                    'taper_relief' => [
                        ['min_years' => 0, 'max_years' => 3, 'tax_rate' => 0.40, 'description' => 'Full 40% tax'],
                        ['min_years' => 3, 'max_years' => 4, 'tax_rate' => 0.32, 'description' => '80% of 40%'],
                        ['min_years' => 4, 'max_years' => 5, 'tax_rate' => 0.24, 'description' => '60% of 40%'],
                        ['min_years' => 5, 'max_years' => 6, 'tax_rate' => 0.16, 'description' => '40% of 40%'],
                        ['min_years' => 6, 'max_years' => 7, 'tax_rate' => 0.08, 'description' => '20% of 40%'],
                        ['min_years' => 7, 'max_years' => null, 'tax_rate' => 0.00, 'description' => 'Fully exempt'],
                    ],

                    // Failed PET impact
                    'failed_pet_rules' => [
                        'becomes_chargeable_transfer' => true,   // Failed PET becomes chargeable on death
                        'affects_later_clt_nrb' => true,         // Failed PET reduces NRB available for later CLTs
                        'affects_estate_nrb' => true,            // Failed PET reduces NRB available for estate
                        'calculation_order' => 'chronological',  // Gifts charged in date order
                    ],
                ],

                // =================================================================
                // Chargeable Lifetime Transfers (CLTs)
                // Gifts to most trusts - immediately chargeable
                // =================================================================
                'chargeable_lifetime_transfers' => [
                    'lookback_period' => 7,                      // CLTs in 7 years before this CLT use up NRB
                    'cumulation_period' => 7,                    // Rolling 7-year cumulation for NRB
                    'lifetime_rate' => 0.20,                     // 20% immediate charge on excess over NRB
                    'lifetime_rate_grossed_up' => 0.25,          // 25% if settlor pays the tax (grossing up)
                    'death_rate' => 0.40,                        // 40% rate on death within 7 years
                    'additional_death_charge' => 0.20,           // Extra 20% due if death within 7 years (40% - 20% already paid)

                    // CLT taper relief - applies to additional death charge
                    'taper_relief_applies' => true,              // Taper relief reduces additional death charge
                    'taper_relief' => [
                        ['min_years' => 0, 'max_years' => 3, 'relief_percent' => 0, 'tax_percent' => 100],
                        ['min_years' => 3, 'max_years' => 4, 'relief_percent' => 20, 'tax_percent' => 80],
                        ['min_years' => 4, 'max_years' => 5, 'relief_percent' => 40, 'tax_percent' => 60],
                        ['min_years' => 5, 'max_years' => 6, 'relief_percent' => 60, 'tax_percent' => 40],
                        ['min_years' => 6, 'max_years' => 7, 'relief_percent' => 80, 'tax_percent' => 20],
                        ['min_years' => 7, 'max_years' => null, 'relief_percent' => 100, 'tax_percent' => 0],
                    ],
                ],

                // =================================================================
                // 14-Year Rule (Extended Cumulation)
                // Failed PETs can affect CLT tax even beyond 7 years
                // =================================================================
                'fourteen_year_rule' => [
                    'applies_to' => 'clt_with_prior_failed_pet', // When calculating CLT tax at death
                    'lookback_for_failed_pets' => 7,             // Look back 7 years from CLT for failed PETs
                    'lookback_for_clts' => 7,                    // Look back 7 years from death for CLTs
                    'maximum_window' => 14,                      // Total maximum window is 14 years
                    'description' => 'When calculating IHT on a CLT made within 7 years of death, any failed PETs made in the 7 years before that CLT reduce the NRB available',
                    'calculation_steps' => [
                        '1. Identify all CLTs made within 7 years of death',
                        '2. For each CLT, identify any failed PETs made in the 7 years before that CLT',
                        '3. Failed PETs reduce the NRB available for the CLT',
                        '4. This can result in additional tax on the CLT even if it was within NRB when made',
                    ],
                ],

                // =================================================================
                // Trust IHT Charges
                // Entry, periodic (10-year), and exit charges
                // =================================================================
                'trust_charges' => [
                    // Entry charge (when assets transferred into trust)
                    'entry' => [
                        'rate' => 0.20,                          // 20% on value exceeding NRB
                        'rate_grossed_up' => 0.25,               // 25% if settlor pays
                        'nrb_available' => true,                 // NRB applies (less previous 7-year CLTs)
                        'exemptions' => [
                            'bare_trusts' => true,               // No entry charge (treated as PET)
                            'disabled_person_trusts' => true,    // No entry charge
                            'bereaved_minor_trusts' => true,     // Created by will - no lifetime charge
                        ],
                    ],

                    // Periodic charge (10-year anniversary)
                    'periodic' => [
                        'interval_years' => 10,                  // Every 10 years from trust creation
                        'max_rate' => 0.06,                      // Maximum 6% of trust value
                        'calculation_formula' => '30% of lifetime rate', // 30% × 20% = 6%
                        'lifetime_rate_multiplier' => 0.30,      // 30% multiplier
                        'base_rate' => 0.20,                     // Base lifetime rate
                        'nrb_available' => true,                 // NRB applies to reduce charge
                        'exemptions' => [
                            'disabled_person_trusts' => true,
                            'bereaved_minor_trusts' => true,
                            'age_18_to_25_trusts' => true,
                            'interest_in_possession_pre_2006' => true,
                        ],
                    ],

                    // Exit charge (when assets leave trust)
                    'exit' => [
                        'max_rate' => 0.06,                      // Maximum 6%
                        'calculation_basis' => 'proportionate',  // Based on time since last periodic charge
                        'quarters_in_period' => 40,              // 40 complete quarters in 10 years
                        'formula' => 'effective_rate × 30% × (complete_quarters / 40)',
                        'no_charge_periods' => [
                            'first_quarter' => 3,                // No charge in first 3 months after setup
                            'post_anniversary_months' => 3,      // No charge in first 3 months after anniversary
                            'will_trust_months' => 24,           // Discretionary will trust: 2 years from death
                        ],
                        'exemptions' => [
                            'costs_and_expenses' => true,        // Payment of trust costs/expenses
                            'advancement_to_bereaved_minor' => true,
                            'excluded_property' => true,         // Certain foreign assets
                        ],
                    ],
                ],

                // Agricultural Property Relief for IHT
                'agricultural_relief' => [
                    'min_ownership_years' => 2,                  // Must own for 2 years (or 7 if let)
                    'min_occupation_years' => 2,                 // If let, must be used for agriculture
                    'rates' => [
                        'vacant_possession' => 1.0,              // 100% relief
                        'let_tenancy' => 1.0,                    // 100% relief (post-Sept 1995 tenancies)
                        'pre_1995_tenancy' => 0.5,               // 50% relief (pre-Sept 1995 tenancies)
                    ],
                    'allowance_cap' => 2500000,                  // £2.5m cap from April 2026 (Dec 2025 announcement raised from £1m)
                    'allowance_cap_effective_date' => '2026-04-06',
                    'notes' => 'APR is being reformed. From April 2026, combined APR/BPR capped at £2.5m at 100%, then 50%. Cap transferable between spouses.',
                ],

                // Business Relief (formerly Business Property Relief) for IHT
                'business_relief' => [
                    'min_ownership_years' => 2,                  // Must own for 2 years to qualify
                    'rates' => [
                        // 100% relief
                        'trading_business' => 1.0,               // Sole trader/partnership trading business
                        'unquoted_shares' => 1.0,                // Shares in unquoted trading company
                        'aim_shares' => 1.0,                     // AIM-listed shares (treated as unquoted)
                        'controlling_holding_quoted' => 1.0,     // Controlling holding in quoted company

                        // 50% relief
                        'land_used_by_partnership' => 0.5,       // Land/buildings used by partnership you control
                        'land_used_by_company' => 0.5,           // Land/buildings used by company you control
                        'assets_used_by_partnership' => 0.5,     // Assets used by partnership you're a partner in
                        'assets_used_by_company' => 0.5,         // Assets used by company you control

                        // 0% relief (not qualifying)
                        'investment_company' => 0.0,             // Investment holding companies
                        'excepted_assets' => 0.0,                // Assets not used for business
                    ],
                    'excluded_businesses' => [
                        'dealing_in_securities',
                        'dealing_in_stocks_shares',
                        'dealing_in_land_or_buildings',
                        'making_or_holding_investments',
                    ],
                    'allowance_cap' => 2500000,                  // £2.5m cap from April 2026 (Dec 2025 announcement raised from £1m)
                    'allowance_cap_effective_date' => '2026-04-06',
                    'notes' => 'BPR is being reformed. From April 2026, combined APR/BPR capped at £2.5m at 100%, then 50%. AIM shares drop to 50% relief (outside the cap).',
                ],

                // Quick Death Rules (death shortly after gift)
                'quick_succession_relief' => [
                    'applies_when' => 'beneficiary_dies_after_receiving_gift',
                    'max_years' => 5,                            // Relief reduces over 5 years
                    'relief_rates' => [
                        ['max_years' => 1, 'relief' => 1.0],     // 100% relief if death within 1 year
                        ['max_years' => 2, 'relief' => 0.8],     // 80% relief
                        ['max_years' => 3, 'relief' => 0.6],     // 60% relief
                        ['max_years' => 4, 'relief' => 0.4],     // 40% relief
                        ['max_years' => 5, 'relief' => 0.2],     // 20% relief
                    ],
                ],

                // 2027 Pension IHT Inclusion (Budget 2024 amendment)
                'pension_iht_inclusion' => [
                    'effective_date' => '2027-04-06',            // From April 2027
                    'description' => 'Unused pension funds and death benefits will be included in the estate for IHT purposes',
                    'applies_to' => ['defined_contribution', 'death_benefits'],
                    'notes' => 'Major change from Budget 2024. Currently pension funds pass outside the estate. From April 2027, they will be subject to IHT.',
                ],
            ],

            'gifting_exemptions' => [
                // Annual Exemption - £3,000 per year, carry forward 1 year
                'annual_exemption' => 3000,
                'annual_exemption_can_carry_forward' => true,
                'carry_forward_years' => 1,
                'annual_exemption_notes' => 'Can carry forward one unused year only. Used before other exemptions.',

                // Small Gifts Exemption - £250 per recipient, unlimited recipients
                'small_gifts_limit' => 250,
                'small_gifts_unlimited_recipients' => true,
                'small_gifts_notes' => 'Cannot use on same person who receives annual exemption gift.',

                // Wedding/Civil Partnership Gifts
                'wedding_gifts' => [
                    'parent_to_child' => 5000,
                    'grandparent_to_grandchild' => 2500,
                    'great_grandparent' => 2500,
                    'other_person' => 1000,
                    'must_be_given_before_ceremony' => true,
                    'must_be_conditional_on_marriage' => true,
                    // Aliases for backward compatibility
                    'child' => 5000,
                    'grandchild_great_grandchild' => 2500,
                    'other' => 1000,
                ],

                // Normal Expenditure from Income - UNLIMITED if conditions met
                'normal_expenditure_from_income' => [
                    'limit' => null,                             // Unlimited
                    'immediately_exempt' => true,
                    'conditions' => [
                        'from_income_not_capital' => true,       // Must be from income, not capital
                        'regular_pattern' => true,               // Must be regular/habitual
                        'maintain_standard_of_living' => true,   // Must not affect donor's lifestyle
                    ],
                    'evidence_required' => [
                        'Income and expenditure records',
                        'Pattern of regular giving (typically 3+ years)',
                        'Proof that standard of living maintained',
                    ],
                    'examples' => [
                        'Regular pension contributions for family member',
                        'School/university fees',
                        'Regular savings for children/grandchildren',
                        'Premium payments on life insurance for another',
                    ],
                    'notes' => 'Most powerful exemption but requires clear evidence. HMRC scrutinise carefully.',
                ],

                // Maintenance Gifts - for family members
                'maintenance_exemptions' => [
                    'spouse_civil_partner' => true,              // Unlimited
                    'ex_spouse_maintenance' => true,             // Court-ordered or reasonable
                    'minor_children' => true,                    // Under 18
                    'adult_children_in_education' => true,       // In full-time education
                    'dependent_relatives' => true,               // Elderly/infirm relatives
                ],

                // Charity and Political Party Exemptions
                'charity_exemption' => true,                     // Unlimited to UK charities
                'political_party_exemption' => true,             // To qualifying political parties

                // Gifts to Housing Associations
                'housing_association_exemption' => true,

                // National Purposes Exemption (museums, universities, etc.)
                'national_purposes_exemption' => true,
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
                'inflation' => 0.025,  // 2.5% — single source of truth
                'salary_growth' => 0.03,

                // Growth rates mapped to risk profile levels
                'growth_by_risk' => [
                    'very_low' => 0.02,      // Cash-like: 2%
                    'low' => 0.035,          // Conservative: 3.5%
                    'low_medium' => 0.04,    // Cautious: 4%
                    'medium' => 0.05,        // Balanced: 5%
                    'medium_high' => 0.06,   // Growth: 6%
                    'high' => 0.07,          // Aggressive: 7%
                ],

                'property_growth' => 0.03,   // Long-term UK property growth assumption
            ],

            // Benefits and allowances
            'benefits' => [
                'child_benefit' => [
                    'eldest_child_weekly' => 26.05,              // Weekly rate for eldest/only child
                    'additional_child_weekly' => 17.25,          // Weekly rate for additional children
                    'eldest_child_annual' => 1354.60,            // Annual amount (52 weeks)
                    'additional_child_annual' => 897.00,         // Annual amount (52 weeks)
                    'high_income_charge_threshold' => 60000,     // HICBC starts at this income level (adjusted net income)
                    'high_income_full_clawback' => 80000,        // HICBC claws back 100% at this level
                    'clawback_increment' => 200,                 // 1% clawed back per £200 over threshold
                    'age_limit_standard' => 16,                  // Stops when child turns 16
                    'age_limit_education' => 20,                 // Extends to 20 if in approved education/training
                    'guardian_allowance_weekly' => 21.75,         // For those caring for an orphaned child
                    'warnings' => [
                        'does_not_auto_stop' => 'Child Benefit does not stop automatically when your income exceeds the threshold. You must either opt out of receiving it, or continue claiming and pay back the High Income Child Benefit Charge through your Self Assessment tax return.',
                        'hicbc_both_parents' => 'The High Income Child Benefit Charge is based on the income of the higher earner. If either parent earns over £60,000, the charge applies regardless of who claims the benefit.',
                        'still_claim_ni_credits' => 'Even if you opt out of receiving Child Benefit payments, you should still fill in the claim form to protect your National Insurance record. The parent who stays at home receives National Insurance credits towards their State Pension.',
                        'two_child_limit' => 'For Universal Credit and tax credit claims made after 6 April 2017, the child element is limited to the first two children. Child Benefit itself is not subject to the two-child limit.',
                    ],
                ],

                // Tax-Free Childcare
                'tax_free_childcare' => [
                    'government_top_up_rate' => 0.25,            // 25% top-up (20p per 80p)
                    'max_government_contribution' => 2000,       // £2,000/year per child (£500/quarter)
                    'max_disabled_contribution' => 4000,          // £4,000/year per disabled child
                    'quarterly_limit' => 500,                    // £500 per quarter per child
                    'quarterly_limit_disabled' => 1000,           // £1,000 per quarter per disabled child
                    'child_age_limit' => 11,                     // Under 12 (stops 1 September after 11th birthday)
                    'disabled_child_age_limit' => 16,             // Under 17 for disabled children
                    'min_income_threshold' => 'national_minimum_wage_16hrs', // Each parent must earn at least NMW × 16 hours/week
                    'min_weekly_earnings' => 183.04,             // £11.44 × 16 hours (2025/26 NMW for 21+)
                    'min_quarterly_earnings' => 2388.52,         // Approx quarterly minimum
                    'max_income_threshold' => 100000,            // Neither parent can earn over £100,000 adjusted net income
                    'warnings' => [
                        'cannot_combine' => 'You cannot use Tax-Free Childcare at the same time as Universal Credit childcare costs, childcare vouchers, or tax credits for childcare.',
                        'both_parents_working' => 'Both parents must be working to qualify (or one working and one receiving certain benefits like Carer\'s Allowance, or incapacitated).',
                        'income_limit' => 'If either parent earns over £100,000 adjusted net income, you cannot use Tax-Free Childcare. Consider claiming Child Benefit and paying the HICBC charge instead, as this may still be beneficial.',
                        'reconfirm' => 'You must reconfirm your eligibility every 3 months or your account will be suspended.',
                    ],
                ],

                // Free Early Years Education and Childcare
                'early_years_funding' => [
                    // Universal entitlement (all families)
                    'universal_15hrs' => [
                        'hours_per_week' => 15,
                        'weeks_per_year' => 38,
                        'total_hours_per_year' => 570,
                        'eligible_age_from' => 3,                // From the term after child turns 3
                        'eligible_age_to' => 4,                  // Until they start reception
                        'income_test' => false,                  // No income test — available to ALL families
                    ],
                    // Extended entitlement for working parents
                    'working_parents_30hrs' => [
                        'hours_per_week' => 30,
                        'weeks_per_year' => 38,
                        'total_hours_per_year' => 1140,
                        'eligible_age_from' => 3,
                        'eligible_age_to' => 4,
                        'income_test' => true,
                        'min_weekly_earnings' => 183.04,         // NMW × 16 hours
                        'max_income_threshold' => 100000,        // Neither parent over £100k adjusted net income
                    ],
                    // Working parents of 2 year olds
                    'working_parents_2yr' => [
                        'hours_per_week' => 15,
                        'weeks_per_year' => 38,
                        'total_hours_per_year' => 570,
                        'eligible_age_from' => 2,
                        'income_test' => true,
                        'min_weekly_earnings' => 183.04,
                        'max_income_threshold' => 100000,
                    ],
                    // Working parents of 9 months to 2 years
                    'working_parents_under_2' => [
                        'hours_per_week' => 15,                  // Expanding to 30 from September 2025
                        'weeks_per_year' => 38,
                        'total_hours_per_year' => 570,
                        'eligible_age_from_months' => 9,
                        'eligible_age_to' => 2,
                        'income_test' => true,
                        'min_weekly_earnings' => 183.04,
                        'max_income_threshold' => 100000,
                    ],
                    // Disadvantaged 2 year olds (income-based)
                    'disadvantaged_2yr' => [
                        'hours_per_week' => 15,
                        'weeks_per_year' => 38,
                        'total_hours_per_year' => 570,
                        'eligible_criteria' => 'On Universal Credit/tax credits with household income under £15,400, or child receives Disability Living Allowance, or child has an Education Health and Care plan, or child is looked after by the local authority.',
                    ],
                    'warnings' => [
                        'term_start_dates' => 'Free hours start from the term after your child reaches the eligible age. Term start dates are 1 January, 1 April, and 1 September.',
                        'stretched_hours' => 'You can choose to spread the hours across more weeks per year (up to 52 weeks) by taking fewer hours per week. This is called "stretched" funding.',
                        'top_up_costs' => 'Childcare providers can charge for additional hours, meals, nappies, and activities on top of the funded hours. These costs are not covered by the government funding.',
                        'income_threshold' => 'For working parent entitlements, each parent must earn at least the National Minimum Wage for 16 hours per week, and neither parent can earn over £100,000 adjusted net income per year.',
                    ],
                ],

                // Statutory Sick Pay
                'ssp' => [
                    'weekly_rate' => 118.75,                     // £118.75 per week (2025/26)
                    'max_weeks' => 28,                           // Maximum 28 weeks
                    'qualifying_days' => 4,                      // Payable from 4th qualifying day (abolished from April 2026)
                    'lower_earnings_limit' => 125,               // Must earn at least £125/week (abolished from April 2026)
                    'not_available_for' => ['self_employed'],     // Self-employed get no SSP
                ],

                // Employment and Support Allowance
                'esa' => [
                    'assessment_rate_under_25' => 71.70,         // Weekly during assessment
                    'assessment_rate_25_plus' => 90.50,          // Weekly during assessment
                    'support_group_supplement' => 44.70,         // Weekly supplement for support group
                    'wrag_supplement' => 33.70,                  // Work-related activity group (legacy claims)
                ],

                // Universal Credit (monthly rates)
                'universal_credit' => [
                    'standard_allowance_single_under_25' => 316.98,
                    'standard_allowance_single_25_plus' => 400.14,
                    'standard_allowance_couple_both_under_25' => 497.55,
                    'standard_allowance_couple_one_25_plus' => 628.15,
                    'child_element_first' => 333.33,
                    'child_element_subsequent' => 287.92,
                    'disabled_child_lower' => 156.11,
                    'disabled_child_higher' => 487.58,
                    'lcwra_element' => 416.19,                   // Limited capability for work-related activity
                    'carer_element' => 198.31,
                    'childcare_max_one_child' => 1014.63,
                    'childcare_max_two_plus' => 1739.37,
                    'work_allowance_housing' => 404.00,          // If receiving housing element
                    'work_allowance_no_housing' => 673.00,       // If not receiving housing element
                    'taper_rate' => 0.55,                        // 55p reduction per £1 earned
                ],

                // Personal Independence Payment (weekly)
                'pip' => [
                    'daily_living_standard' => 73.90,
                    'daily_living_enhanced' => 110.40,
                    'mobility_standard' => 29.20,
                    'mobility_enhanced' => 77.05,
                ],

                // Bereavement Support Payment
                'bereavement_support' => [
                    'higher_rate_lump_sum' => 3500,              // If partner died leaving dependent children
                    'higher_rate_monthly' => 350,                // Monthly for 18 months
                    'lower_rate_lump_sum' => 2500,               // If no dependent children
                    'lower_rate_monthly' => 100,                 // Monthly for 18 months
                    'payment_months' => 18,                      // Paid for up to 18 months
                ],
            ],

            // Estate planning constants
            'estate' => [
                'onboarding_estimates' => [
                    'property' => 300000,                        // Default property estimate
                    'investment' => 50000,                       // Default investment estimate
                    'savings' => 25000,                          // Default savings estimate
                    'business' => 100000,                        // Default business estimate
                ],
                'insurance_premium_estimates' => [
                    // Per £1,000 cover per month — by age band and gender
                    'per_thousand_monthly' => [
                        '30_male' => 0.30,
                        '30_female' => 0.25,
                        '40_male' => 0.50,
                        '40_female' => 0.40,
                        '50_male' => 1.00,
                        '50_female' => 0.80,
                        '60_male' => 2.50,
                        '60_female' => 2.00,
                        '70_male' => 6.00,
                        '70_female' => 5.00,
                    ],
                ],
            ],

            // Investment engine constants
            'investment' => [
                // Asset class expected yields for tax drag calculations
                'asset_class_yields' => [
                    'uk_equity' => ['income_yield' => 0.035, 'growth' => 0.04],
                    'global_equity' => ['income_yield' => 0.02, 'growth' => 0.05],
                    'bonds' => ['income_yield' => 0.04, 'growth' => 0.005],
                    'property' => ['income_yield' => 0.03, 'growth' => 0.03],
                    'cash' => ['income_yield' => 0.04, 'growth' => 0.0],
                ],

                // Fee benchmarks
                'fee_benchmarks' => [
                    'low_cost_ocf' => 0.0015,                   // 0.15% — below this is low-cost
                    'high_cost_ocf' => 0.0075,                  // 0.75% — above this is high-cost
                    'platform_fee_typical' => 0.0025,            // 0.25% typical platform fee
                ],

                // Portfolio thresholds
                'portfolio_thresholds' => [
                    'rebalancing_drift_percent' => 5,            // Rebalance when >5% drift
                    'concentration_warning_percent' => 25,       // Warn if single holding >25%
                    'minimum_diversification_holdings' => 5,     // Minimum number of holdings
                ],

                // Contribution waterfall limits
                'waterfall' => [
                    'premium_bonds_max' => 50000,                // £50,000 NS&I Premium Bonds cap
                    'offshore_bond_minimum' => 5000,             // Minimum for offshore bond recommendation
                    'onshore_bond_minimum' => 5000,              // Minimum for onshore bond recommendation
                    'ns_i_income_bonds_max' => 1000000,          // £1,000,000 NS&I Income Bonds cap
                    'ns_i_direct_saver_max' => 2000000,          // £2,000,000 NS&I Direct Saver cap
                ],

                // Venture capital schemes
                'venture_capital' => [
                    'seis' => [
                        'annual_limit' => 200000,                // £200,000 SEIS annual limit
                        'income_tax_relief' => 0.50,             // 50% income tax relief
                        'cgt_exempt_after_years' => 3,           // CGT exempt after 3 years
                    ],
                    'eis' => [
                        'annual_limit' => 1000000,               // £1,000,000 EIS annual limit (£2m for knowledge-intensive)
                        'knowledge_intensive_limit' => 2000000,
                        'income_tax_relief' => 0.30,             // 30% income tax relief
                        'cgt_exempt_after_years' => 3,
                        'loss_relief' => true,                   // Loss relief available
                        'carry_back_years' => 1,                 // Can carry back to previous tax year
                    ],
                    'vct' => [
                        'annual_limit' => 200000,                // £200,000 VCT annual limit
                        'income_tax_relief' => 0.30,             // 30% income tax relief
                        'dividend_exempt' => true,               // Dividends tax-free
                        'cgt_exempt' => true,                    // No CGT on disposal
                        'min_holding_years' => 5,                // Must hold for 5 years for relief
                    ],
                ],

                // Safety check thresholds
                'safety' => [
                    'critical_debt_rate' => 0.15,                // Debts above 15% APR are critical
                    'emergency_fund_months' => 6,                // Universal baseline for investment surplus calc
                    'emergency_fund_minimum' => 1000,            // Minimum emergency fund before investing
                ],

                // Transfer scan thresholds
                'transfers' => [
                    'cash_excess_buffer' => 1000,                // Buffer above emergency fund before suggesting transfer
                    'consolidation_min_accounts' => 3,           // Suggest consolidation if 3+ accounts of same type
                    'bed_and_isa_min_gain' => 500,               // Minimum CGT saving to recommend bed and ISA
                ],
            ],

            // Protection module constants
            'protection' => [
                // Income replacement multipliers
                'income_multipliers' => [
                    'life_cover' => 10,                          // 10x annual income for life cover
                    'critical_illness' => 3,                     // 3x annual income for CI
                    'income_protection_max_benefit' => 0.60,     // 60% of gross income cap
                ],

                // Cost estimates
                'education_cost_per_year' => 9000,               // £9,000 university tuition per year
                'final_expenses' => 7500,                        // £7,500 funeral + admin costs

                // Affordability thresholds
                'affordability' => [
                    'max_premium_percent_of_income' => 0.10,     // Max 10% of gross income on premiums
                    'comfortable_premium_percent' => 0.05,       // 5% is comfortable
                ],

                // Premium estimation factors
                'premium_factors' => [
                    'base_rate' => 0.50,                         // Base rate per £1,000 cover per month
                    'smoker_loading' => 1.5,                     // 50% loading for smokers
                    'ci_ratio' => 2.5,                           // CI costs 2.5x life cover
                    'ip_rate' => 0.02,                           // IP rate as % of benefit per month
                ],

                // Withdrawal rates for capital calculations
                'withdrawal_rates' => [
                    'human_capital' => 0.047,                    // Sustainable withdrawal rate for lump sum needs
                    'scenario' => 0.03,                          // Conservative rate for scenario projections
                ],

                // Insurance Premium Tax
                'ipt' => [
                    'standard_rate' => 0.12,                     // 12% standard IPT rate
                    'higher_rate' => 0.20,                       // 20% higher rate (travel, electrical goods)
                    'exempt' => ['life', 'critical_illness', 'income_protection', 'pmi'],
                ],
            ],

            // Retirement module constants
            'retirement' => [
                'accumulation_to_decumulation_years' => 10,      // Transition period years
                'withdrawal_rates' => [
                    'sustainable' => 0.047,                      // 4.7% sustainable withdrawal rate
                    'safe' => 0.04,                              // 4.0% safe withdrawal rate (Trinity study)
                    'gia' => 0.04,                               // 4.0% for GIA decumulation
                ],
                'target_income_percent' => 0.75,                 // Target 75% of pre-retirement income
                'projection_end_age' => 100,                     // Project to age 100
                'monte_carlo_iterations' => 1000,                // Monte Carlo simulation iterations
                'compounding_periods' => 4,                      // Quarterly compounding
                'employer_match_threshold' => 0.05,              // 5% employer match threshold

                // Annuity rate estimates by age (single life, level, no guarantee)
                'annuity_rate_estimates' => [
                    '55' => ['single' => 0.048, 'joint' => 0.042],
                    '60' => ['single' => 0.053, 'joint' => 0.047],
                    '65' => ['single' => 0.060, 'joint' => 0.053],
                    '70' => ['single' => 0.069, 'joint' => 0.061],
                    '75' => ['single' => 0.081, 'joint' => 0.072],
                ],
            ],

            // Domicile rules
            'domicile' => [
                'uk_domiciled' => [
                    'worldwide_assets_subject_to_iht' => true,
                    'spouse_exemption_unlimited' => true,
                ],
                'non_uk_domiciled' => [
                    'uk_assets_only_subject_to_iht' => true,
                    'spouse_exemption_limit' => 325000,          // £325,000 limit for non-dom spouse
                    'deemed_domicile_years' => 15,               // Deemed UK domiciled after 15 of last 20 years
                ],
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

                // IHT Charges for Trusts (detailed config in inheritance_tax.trust_charges)
                // This section provides simplified access for trust-specific calculations
                'inheritance_tax' => [
                    // Entry charge
                    'entry_charge_rate' => 0.20,           // 20% on CLTs exceeding NRB
                    'entry_charge_grossed_up' => 0.25,     // 25% if settlor pays

                    // Periodic (10-year) charge
                    'periodic_charge_max' => 0.06,         // Max 6% (30% of 20%)
                    'periodic_charge_interval' => 10,      // Years between charges
                    'periodic_rate_multiplier' => 0.30,    // 30% of lifetime rate
                    'periodic_base_rate' => 0.20,          // Lifetime rate base

                    // Exit charge
                    'exit_charge_max' => 0.06,             // Max 6%
                    'exit_quarters_per_period' => 40,      // 40 complete quarters in 10 years
                    'exit_rate_multiplier' => 0.30,        // 30% of effective rate
                    'no_exit_charge_months' => 3,          // No charge in first 3 months
                    'will_trust_no_exit_months' => 24,     // Will trust: 2 years from death

                    // Death within 7 years of CLT
                    'additional_death_charge' => 0.20,     // Extra 20% (40% - 20% already paid)
                    'death_charge_taper_applies' => true,  // Taper relief reduces additional charge

                    'notes' => 'Full calculation details in inheritance_tax.trust_charges. Rates: Entry 20%, Periodic max 6% (30% × 20%), Exit proportionate up to 6%.',
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
     * Get tax configuration for 2026/27
     *
     * Derives from 2025/26 and applies all documented changes for the UK tax year
     * running 6 April 2026 - 5 April 2027.
     *
     * Key changes from 2025/26:
     * - Dividend ordinary rate: 8.75% → 10.75% (+2pp)
     * - Dividend upper rate: 33.75% → 35.75% (+2pp)
     * - Business Asset Disposal Relief: 14% → 18% (+4pp)
     * - APR/BPR: £2.5m combined cap at 100%, 50% above (now in effect)
     * - AIM shares: 100% → 50% Business Relief (outside the cap)
     * - State pension: £11,973 → £12,547.60/year (+4.8%)
     * - National Living Wage (21+): £12.21 → £12.71/hour
     * - Child Benefit eldest: £26.05 → £27.05/week
     * - Statutory Sick Pay: £118.75 → £123.25/week; LEL abolished, day-one payment
     * - Premium Bonds prize fund rate: 3.6% → 3.3%
     * - Universal Credit LCWRA (new claims only): halved to £217.26
     * - Two-child limit on UC child element: removed
     */
    private function getTaxConfig202627(): array
    {
        $config = $this->getTaxConfig202526();
        $config['tax_year'] = '2026/27';
        $config['effective_from'] = '2026-04-06';
        $config['effective_to'] = '2027-04-05';
        $config['notes'] = 'UK Tax Year 2026/27 - Active configuration';

        // ==============================================================
        // Income Tax - frozen until April 2031 (no changes)
        // Blind Person's Allowance estimated from CPI uprating
        // ==============================================================
        $config['income_tax']['blind_persons_allowance'] = 3250;

        // ==============================================================
        // Capital Gains Tax - Business Asset Disposal Relief rises to 18%
        // ==============================================================
        $config['capital_gains_tax']['business_asset_disposal_relief_rate'] = 0.18;

        // ==============================================================
        // Dividend Tax - +2pp on basic and higher rates
        // ==============================================================
        $config['dividend_tax']['basic_rate'] = 0.1075;                              // 10.75% (was 8.75%)
        $config['dividend_tax']['higher_rate'] = 0.3575;                             // 35.75% (was 33.75%)
        // Additional rate unchanged at 39.35%
        // Trust dividend rate unchanged (already at additional rate 39.35%)
        $config['dividend_tax']['trust_management_expenses_dividend_rate'] = 0.1075; // Aligned with new ordinary rate

        // ==============================================================
        // Savings - Premium Bonds prize fund rate reduced to 3.3%
        // ==============================================================
        $config['savings']['premium_bonds_prize_fund_rate'] = 0.033;

        // ==============================================================
        // Pension - State Pension uprated 4.8%, NLW/NMW uprated
        // ==============================================================
        $config['pension']['state_pension']['full_new_state_pension'] = 12547.60;    // £241.30/week × 52
        $config['pension']['salary_sacrifice']['nlw_hourly'] = 12.71;
        $config['pension']['salary_sacrifice']['nmw_hourly']['21_plus'] = 12.71;
        $config['pension']['salary_sacrifice']['nmw_hourly']['18_to_20'] = 10.85;
        $config['pension']['salary_sacrifice']['nmw_hourly']['under_18'] = 8.00;
        $config['pension']['salary_sacrifice']['nmw_hourly']['apprentice'] = 8.00;

        // ==============================================================
        // Inheritance Tax - APR/BPR reform now IN EFFECT (£2.5m cap, 50% above)
        // AIM shares drop to 50% relief (outside the cap)
        // ==============================================================
        // Agricultural Property Relief
        $config['inheritance_tax']['agricultural_relief']['allowance_cap'] = 2500000;
        $config['inheritance_tax']['agricultural_relief']['relief_above_cap'] = 0.5;
        $config['inheritance_tax']['agricultural_relief']['cap_shared_with_bpr'] = true;
        $config['inheritance_tax']['agricultural_relief']['cap_transferable_to_spouse'] = true;
        $config['inheritance_tax']['agricultural_relief']['cap_in_effect'] = true;
        $config['inheritance_tax']['agricultural_relief']['notes'] =
            'APR reform in effect. 100% relief on first £2.5m of combined APR/BPR, then 50%. Cap transferable between spouses (£5m combined).';

        // Business Relief
        $config['inheritance_tax']['business_relief']['rates']['aim_shares'] = 0.5;   // Was 1.0 — now 50%
        $config['inheritance_tax']['business_relief']['allowance_cap'] = 2500000;
        $config['inheritance_tax']['business_relief']['relief_above_cap'] = 0.5;
        $config['inheritance_tax']['business_relief']['aim_shares_outside_cap'] = true;
        $config['inheritance_tax']['business_relief']['cap_transferable_to_spouse'] = true;
        $config['inheritance_tax']['business_relief']['cap_in_effect'] = true;
        $config['inheritance_tax']['business_relief']['notes'] =
            'BPR reform in effect. 100% relief on first £2.5m of combined APR/BPR, then 50%. AIM shares: always 50% (outside the cap). Cap transferable between spouses (£5m combined).';

        // ==============================================================
        // Benefits - uprated per DWP/HMRC announcements
        // ==============================================================
        // Child Benefit
        $config['benefits']['child_benefit']['eldest_child_weekly'] = 27.05;
        $config['benefits']['child_benefit']['additional_child_weekly'] = 17.90;
        $config['benefits']['child_benefit']['eldest_child_annual'] = 1406.60;
        $config['benefits']['child_benefit']['additional_child_annual'] = 930.80;
        $config['benefits']['child_benefit']['guardian_allowance_weekly'] = 22.60;    // Estimated from CPI uprating
        $config['benefits']['child_benefit']['two_child_limit_lifted'] = true;
        $config['benefits']['child_benefit']['warnings']['two_child_limit'] =
            'The two-child limit on the child element of Universal Credit and tax credits has been removed from April 2026. All children now qualify for the child element.';

        // Tax-Free Childcare - min earnings updated for new NLW (£12.71 × 16 = £203.36)
        $config['benefits']['tax_free_childcare']['min_weekly_earnings'] = 203.36;
        $config['benefits']['tax_free_childcare']['min_quarterly_earnings'] = 2660.96;

        // Early Years Funding - min earnings updated; under-2 expanded to 30hrs (Sept 2025)
        $config['benefits']['early_years_funding']['working_parents_30hrs']['min_weekly_earnings'] = 203.36;
        $config['benefits']['early_years_funding']['working_parents_2yr']['min_weekly_earnings'] = 203.36;
        $config['benefits']['early_years_funding']['working_parents_under_2']['hours_per_week'] = 30;
        $config['benefits']['early_years_funding']['working_parents_under_2']['total_hours_per_year'] = 1140;
        $config['benefits']['early_years_funding']['working_parents_under_2']['min_weekly_earnings'] = 203.36;

        // Statutory Sick Pay - rate up, LEL abolished, waiting days abolished
        $config['benefits']['ssp']['weekly_rate'] = 123.25;
        $config['benefits']['ssp']['qualifying_days'] = 0;                            // Waiting days abolished
        $config['benefits']['ssp']['lower_earnings_limit'] = null;                    // LEL abolished
        $config['benefits']['ssp']['lower_earner_rate'] = 0.80;                       // 80% of weekly earnings for lower earners
        $config['benefits']['ssp']['notes'] =
            'From April 2026: LEL abolished (all employees qualify), waiting days abolished (payable from day 1), lower earners receive the lesser of flat rate or 80% of normal weekly earnings.';

        // ESA (estimates where not yet published)
        $config['benefits']['esa']['assessment_rate_under_25'] = 75.65;
        $config['benefits']['esa']['assessment_rate_25_plus'] = 95.55;
        $config['benefits']['esa']['support_group_supplement'] = 47.40;              // Estimated from CPI uprating
        $config['benefits']['esa']['wrag_supplement'] = 35.40;                       // Estimated from CPI uprating

        // Universal Credit - LCWRA halved for new claims, two-child limit lifted
        $config['benefits']['universal_credit']['standard_allowance_single_under_25'] = 338.58;
        $config['benefits']['universal_credit']['standard_allowance_single_25_plus'] = 424.90;
        $config['benefits']['universal_credit']['standard_allowance_couple_both_under_25'] = 528.34;
        $config['benefits']['universal_credit']['standard_allowance_couple_one_25_plus'] = 666.97;
        $config['benefits']['universal_credit']['child_element_first'] = 346.06;     // Estimated from CPI uprating
        $config['benefits']['universal_credit']['child_element_subsequent'] = 298.87;
        $config['benefits']['universal_credit']['disabled_child_lower'] = 162.04;
        $config['benefits']['universal_credit']['disabled_child_higher'] = 506.14;
        $config['benefits']['universal_credit']['lcwra_element'] = 423.27;           // Existing claimants keep higher rate
        $config['benefits']['universal_credit']['lcwra_element_new_claims'] = 217.26; // New claims from April 2026
        $config['benefits']['universal_credit']['carer_element'] = 201.68;
        $config['benefits']['universal_credit']['childcare_max_one_child'] = 1053.19;
        $config['benefits']['universal_credit']['childcare_max_two_plus'] = 1805.49;
        $config['benefits']['universal_credit']['work_allowance_housing'] = 419.39;
        $config['benefits']['universal_credit']['work_allowance_no_housing'] = 698.66;
        $config['benefits']['universal_credit']['two_child_limit_abolished'] = true;

        // PIP - uprated
        $config['benefits']['pip']['daily_living_standard'] = 76.70;
        $config['benefits']['pip']['daily_living_enhanced'] = 114.60;
        $config['benefits']['pip']['mobility_standard'] = 30.30;
        $config['benefits']['pip']['mobility_enhanced'] = 80.00;

        // ==============================================================
        // Trusts - IIP dividend rate and mgmt expenses aligned with new 10.75%
        // ==============================================================
        $config['trusts']['income_tax']['interest_in_possession']['dividend_rate'] = 0.1075;
        $config['trusts']['management_expenses']['dividend_relief_rate'] = 0.1075;
        $config['trusts']['notes'] =
            'UK trust taxation is complex. The rates above apply to 2026/27. Note: from 2027/28, trust income tax rates change significantly (discretionary 47%, IIP 22%). Professional advice recommended.';

        return $config;
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

        // 2024/25 Blind Person's Allowance was £3,070
        $config['income_tax']['blind_persons_allowance'] = 3070;

        // 2024/25 benefit rates (gov.uk verified)
        $config['benefits']['ssp']['weekly_rate'] = 116.75;
        $config['benefits']['pip']['daily_living_standard'] = 72.65;
        $config['benefits']['pip']['daily_living_enhanced'] = 108.55;
        $config['benefits']['pip']['mobility_standard'] = 28.70;
        $config['benefits']['pip']['mobility_enhanced'] = 75.75;

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

        // 2023/24 Blind Person's Allowance was £2,600
        $config['income_tax']['blind_persons_allowance'] = 2600;

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

        // 2022/23 Blind Person's Allowance was £2,600
        $config['income_tax']['blind_persons_allowance'] = 2600;

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

        // 2021/22 Blind Person's Allowance was £2,600
        $config['income_tax']['blind_persons_allowance'] = 2600;

        return $config;
    }
}
