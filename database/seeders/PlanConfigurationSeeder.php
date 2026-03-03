<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Constants\TaxDefaults;
use App\Models\PlanConfiguration;
use Illuminate\Database\Seeder;

/**
 * Seed the plan_configurations table with default admin-configurable values.
 *
 * These values were previously hardcoded across plan services.
 * All values can be modified by admin to adjust plan behaviour without code changes.
 *
 * Run: php artisan db:seed --class=PlanConfigurationSeeder --force
 */
class PlanConfigurationSeeder extends Seeder
{
    public function run(): void
    {
        // Deactivate any existing configs
        PlanConfiguration::where('is_active', true)->update(['is_active' => false]);

        PlanConfiguration::updateOrCreate(
            ['version' => '1.0'],
            [
                'config_data' => [
                    'rates' => [
                        // Default annual growth rate for investment and pension projections
                        // Conservative estimate net of inflation
                        'default_growth_rate' => TaxDefaults::DEFAULT_GROWTH_RATE, // 0.05 (5%)

                        // Sustainable withdrawal / annuity rate for retirement income
                        'withdrawal_rate' => TaxDefaults::SAFE_WITHDRAWAL_RATE, // 0.04 (4%)

                        // Pension consolidation efficiency gain (% of projected income)
                        'consolidation_efficiency_gain' => 0.02, // 2%

                        // Tax optimisation gain (% of projected income)
                        'tax_optimisation_gain' => 0.03, // 3%

                        // Default gain for miscellaneous actions (% of projected income)
                        'default_action_gain' => 0.01, // 1%
                    ],
                    'benchmarks' => [
                        // Target platform fee percentage - used for fee reduction calculations
                        'platform_fee_percent' => 0.25, // 0.25%

                        // Target OCF (Ongoing Charges Figure) - used for fund selection
                        'ocf_percent' => 0.15, // 0.15%
                    ],
                    'estate' => [
                        // Minimum age for estate planning features
                        'age_gate' => 35,

                        // Charitable giving threshold for reduced IHT rate (36% vs 40%)
                        'charitable_giving_threshold_percent' => 10.0,
                    ],
                    'emergency_fund' => [
                        // Target emergency fund in months of expenses
                        'target_months' => 6,
                    ],
                    'cache' => [
                        // Cache TTL for plan generation (seconds) - 30 minutes
                        'plan_ttl' => 1800,

                        // Cache TTL for retirement analysis (seconds) - 1 hour
                        'retirement_ttl' => 3600,

                        // Cache TTL for savings analysis (seconds) - 30 minutes
                        'savings_ttl' => 1800,
                    ],
                ],
                'is_active' => true,
                'notes' => 'Default plan configuration v1.0 - migrated from hardcoded values',
            ]
        );
    }
}
