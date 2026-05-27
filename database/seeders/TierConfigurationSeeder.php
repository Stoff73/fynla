<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\TierConfiguration;
use Illuminate\Database\Seeder;

class TierConfigurationSeeder extends Seeder
{
    /**
     * Capability verbs: full | none | limited | teaser.
     * count_caps: int = cap, null = unlimited, absent = not count-gated.
     * Prices are render-only placeholders (spec §22 A8) — CSJ sets real
     * prices via the admin screen; nothing here maps to legacy plan prices.
     */
    public function run(): void
    {
        foreach ($this->rows() as $row) {
            TierConfiguration::updateOrCreate(['tier' => $row['tier']], $row);
        }
    }

    private function rows(): array
    {
        return [
            [
                'tier' => 'free',
                'display_name' => 'Free',
                'price_monthly_pence' => 0,
                'price_annual_pence' => 0,
                'revolut_plan_variation_id' => null,
                'capability_matrix' => [
                    'dashboard' => 'full', 'letter_to_spouse' => 'none',
                    'goals' => 'full', 'protection' => 'full', 'property' => 'full',
                    'liabilities' => 'full', 'income' => 'full', 'expenditure' => 'full',
                    'estate' => 'teaser', 'chattels' => 'full',
                    'benefits_child' => 'full', 'family_module' => 'full',
                    'investments_exotic' => 'none', 'retirement_decumulation' => 'none',
                    'savings_account' => 'limited', 'investment' => 'limited',
                    'pension_account' => 'limited',
                ],
                'count_caps' => ['savings_account' => 3, 'investment' => 2, 'pension_account' => 5, 'property' => 3],
                'document_upload_allowance' => 3,   // §22 A6
                'document_storage_gb' => null,
                'fyn_weekly_token_budget' => 100_000,
                'fyn_daily_hard_backstop' => 500_000, // §22 A10 generous
                'currency_display_mode' => 'gbp_only',
                'snapshot_surfacing_window_days' => 90,
                'open_api_affordance' => false,
                'is_active' => true,
                'updated_by' => null,
            ],
            [
                'tier' => 'tier1',
                'display_name' => 'Tier 1',
                'price_monthly_pence' => 499,   // §22 A8 placeholder
                'price_annual_pence' => 4990,
                'revolut_plan_variation_id' => null,
                'capability_matrix' => [
                    'dashboard' => 'full', 'letter_to_spouse' => 'full',
                    'goals' => 'full', 'protection' => 'full', 'property' => 'full',
                    'liabilities' => 'full', 'income' => 'full', 'expenditure' => 'full',
                    'estate' => 'teaser', 'chattels' => 'full',          // §22 A2
                    'benefits_child' => 'full', 'family_module' => 'full',
                    'investments_exotic' => 'none',                       // §22 A1
                    'retirement_decumulation' => 'none',
                    'savings_account' => 'full', 'investment' => 'full',
                    'pension_account' => 'full',
                ],
                'count_caps' => ['savings_account' => null, 'investment' => null, 'pension_account' => null, 'property' => null],
                'document_upload_allowance' => 4,   // §22 A6
                'document_storage_gb' => null,      // §22 A3
                'fyn_weekly_token_budget' => 250_000,
                'fyn_daily_hard_backstop' => 1_000_000,
                'currency_display_mode' => 'gbp_only',
                'snapshot_surfacing_window_days' => 365,
                'open_api_affordance' => false,
                'is_active' => true,
                'updated_by' => null,
            ],
            [
                'tier' => 'tier2',
                'display_name' => 'Tier 2',
                'price_monthly_pence' => 1499,  // §22 A8 placeholder
                'price_annual_pence' => 14990,
                'revolut_plan_variation_id' => null,
                'capability_matrix' => [
                    'dashboard' => 'full', 'letter_to_spouse' => 'full',
                    'goals' => 'full', 'protection' => 'full', 'property' => 'full',
                    'liabilities' => 'full', 'income' => 'full', 'expenditure' => 'full',
                    'estate' => 'full', 'chattels' => 'full',
                    'benefits_child' => 'full', 'family_module' => 'full',
                    'investments_exotic' => 'full', 'retirement_decumulation' => 'full',
                    'savings_account' => 'full', 'investment' => 'full',
                    'pension_account' => 'full',
                ],
                'count_caps' => ['savings_account' => null, 'investment' => null, 'pension_account' => null, 'property' => null],
                'document_upload_allowance' => 5,   // §22 A6
                'document_storage_gb' => 5.00,      // §22 A7
                'fyn_weekly_token_budget' => 500_000,
                'fyn_daily_hard_backstop' => 2_000_000,
                'currency_display_mode' => 'user_choice',
                'snapshot_surfacing_window_days' => 1825,
                'open_api_affordance' => true,
                'is_active' => true,
                'updated_by' => null,
            ],
            [
                'tier' => 'tier3',
                'display_name' => 'Tier 3',
                'price_monthly_pence' => 2999,  // §22 A8 placeholder
                'price_annual_pence' => 29990,
                'revolut_plan_variation_id' => null,
                'capability_matrix' => [
                    'dashboard' => 'full', 'letter_to_spouse' => 'full',
                    'goals' => 'full', 'protection' => 'full', 'property' => 'full',
                    'liabilities' => 'full', 'income' => 'full', 'expenditure' => 'full',
                    'estate' => 'full', 'chattels' => 'full',
                    'benefits_child' => 'full', 'family_module' => 'full',
                    'investments_exotic' => 'full', 'retirement_decumulation' => 'full',
                    'savings_account' => 'full', 'investment' => 'full',
                    'pension_account' => 'full',
                ],
                'count_caps' => ['savings_account' => null, 'investment' => null, 'pension_account' => null, 'property' => null],
                'document_upload_allowance' => 6,   // §22 A6
                'document_storage_gb' => 20.00,     // §22 A7
                'fyn_weekly_token_budget' => 1_000_000,
                'fyn_daily_hard_backstop' => 4_000_000,
                'currency_display_mode' => 'user_choice',
                'snapshot_surfacing_window_days' => 2555,
                'open_api_affordance' => true,
                'is_active' => true,
                'updated_by' => null,
            ],
        ];
    }
}
