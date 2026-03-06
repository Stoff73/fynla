<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\TaxConfiguration;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\TaxConfiguration>
 */
class TaxConfigurationFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = TaxConfiguration::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $year = fake()->numberBetween(2021, 2026);
        $nextYear = $year + 1;
        $taxYear = sprintf('%04d/%02d', $year, $nextYear % 100);

        return [
            'tax_year' => $taxYear,
            'effective_from' => sprintf('%04d-04-06', $year),
            'effective_to' => sprintf('%04d-04-05', $nextYear),
            'is_active' => false,
            'notes' => "Test UK Tax Year {$taxYear}",
            'config_data' => $this->getDefaultConfigData($taxYear),
        ];
    }

    /**
     * Indicate that the tax configuration is active.
     */
    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => true,
        ]);
    }

    /**
     * Set a specific tax year.
     */
    public function forTaxYear(string $taxYear): static
    {
        [$year, $shortYear] = explode('/', $taxYear);
        $nextYear = (int) $year + 1;

        return $this->state(fn (array $attributes) => [
            'tax_year' => $taxYear,
            'effective_from' => sprintf('%s-04-06', $year),
            'effective_to' => sprintf('%04d-04-05', $nextYear),
            'notes' => "Test UK Tax Year {$taxYear}",
        ]);
    }

    /**
     * Get default config data structure for testing.
     */
    private function getDefaultConfigData(string $taxYear): array
    {
        return [
            'tax_year' => $taxYear,
            'effective_from' => $this->attributes['effective_from'] ?? date('Y-04-06'),
            'effective_to' => $this->attributes['effective_to'] ?? date('Y-04-05'),
            'notes' => "Test configuration for {$taxYear}",

            'income_tax' => [
                'personal_allowance' => 12570,
                'personal_allowance_taper_threshold' => 100000,
                'personal_allowance_taper_rate' => 0.5,
                'bands' => [
                    [
                        'name' => 'Basic Rate',
                        'lower_limit' => 12570,
                        'upper_limit' => 50270,
                        'min' => 0,
                        'max' => 37700,
                        'rate' => 20,
                    ],
                    [
                        'name' => 'Higher Rate',
                        'lower_limit' => 50270,
                        'upper_limit' => 125140,
                        'min' => 37700,
                        'max' => 125140,
                        'rate' => 40,
                    ],
                    [
                        'name' => 'Additional Rate',
                        'lower_limit' => 125140,
                        'upper_limit' => null,
                        'min' => 125140,
                        'max' => null,
                        'rate' => 45,
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
                    'main_rate' => 0.06,
                    'additional_rate' => 0.02,
                ],
            ],

            'capital_gains_tax' => [
                // Individual rates
                'annual_exempt_amount' => 3000,
                'basic_rate' => 18,
                'higher_rate' => 24,
                'residential_property_basic_rate' => 18,
                'residential_property_higher_rate' => 24,

                // Trust rates
                'trust_rate' => 24,
                'trust_annual_exempt_amount' => 1500,
                'trust_vulnerable_beneficiary_exempt_amount' => 3000,
            ],

            'dividend_tax' => [
                // Individual rates
                'allowance' => 500,
                'basic_rate' => 8.75,
                'higher_rate' => 33.75,
                'additional_rate' => 39.35,

                // Trust rates
                'trust_dividend_rate' => 39.35,
                'trust_other_income_rate' => 45,
                'trust_de_minimis_allowance' => 500,
                'trust_management_expenses_dividend_rate' => 8.75,
                'trust_management_expenses_other_rate' => 20,
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
                'mpaa' => 10000, // Required by AnnualAllowanceChecker
                'lifetime_allowance_abolished' => true,
                'carry_forward_years' => 3,
                'tapered_annual_allowance' => [
                    'threshold_income' => 200000,
                    'adjusted_income' => 260000,
                    'adjusted_income_threshold' => 260000, // Required by AnnualAllowanceChecker
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

                // Trust IHT charges
                'trust_entry_charge' => 0.20,
                'trust_periodic_charge_max' => 0.06,
                'trust_exit_charge_max' => 0.06,
                'trust_no_exit_charge_period' => 3,
                'trust_will_no_exit_charge_period' => 24,
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
                            ['threshold' => 250000, 'rate' => 0.05],
                            ['threshold' => 925000, 'rate' => 0.10],
                            ['threshold' => 1500000, 'rate' => 0.12],
                        ],
                    ],
                    'additional_properties' => [
                        'surcharge' => 0.03,
                        'bands' => [
                            ['threshold' => 0, 'rate' => 0.03],
                            ['threshold' => 250000, 'rate' => 0.08],
                            ['threshold' => 925000, 'rate' => 0.13],
                            ['threshold' => 1500000, 'rate' => 0.15],
                        ],
                    ],
                    'first_time_buyers' => [
                        'nil_rate_threshold' => 425000,
                        'max_property_value' => 625000,
                        'bands' => [
                            ['threshold' => 0, 'rate' => 0.00],
                            ['threshold' => 425000, 'rate' => 0.05],
                        ],
                    ],
                ],
                'non_residential' => [
                    'bands' => [
                        ['threshold' => 0, 'rate' => 0.00],
                        ['threshold' => 150000, 'rate' => 0.02],
                        ['threshold' => 250000, 'rate' => 0.05],
                    ],
                ],
            ],

            'trusts' => [
                'entry_charge' => 0.20,
                'exit_charge' => [
                    'max_rate' => 0.06,
                    'calculation' => 'Proportional to time in trust',
                ],
                'periodic_charge' => [
                    'frequency_years' => 10,
                    'max_rate' => 0.06,
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

            'domicile' => [
                'uk_domiciled' => [
                    'iht_on_worldwide_assets' => true,
                ],
                'non_uk_domiciled' => [
                    'iht_on_uk_assets_only' => true,
                    'remittance_basis_available' => true,
                ],
            ],
        ];
    }
}
