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
     * Prices and limits are the approved Free and Premium commercial contract.
     */
    public function run(): void
    {
        foreach (self::rows() as $row) {
            TierConfiguration::firstOrCreate(['tier' => $row['tier']], $row);
        }
    }

    /** @return list<array<string, mixed>> */
    public static function rows(): array
    {
        return [
            [
                'tier' => 'free',
                'display_name' => 'Free',
                'price_monthly_pence' => 0,
                'price_annual_pence' => 0,
                'revolut_plan_variation_id' => null,
                'capability_matrix' => [
                    'dashboard' => 'full', 'protection' => 'full',
                    'income' => 'full', 'liabilities' => 'full', 'expenditure' => 'full',
                    'expenditure_detailed' => 'none', 'tax_strategy' => 'full',
                    'risk_profile' => 'full', 'future_value_projections' => 'full',
                    'savings_account' => 'limited', 'investment' => 'limited',
                    'pension_account' => 'limited', 'property' => 'limited',
                    'goals' => 'limited', 'life_events' => 'limited',
                    'chattels' => 'full', 'estate' => 'teaser',
                    'joint_household_view' => 'none', 'letter_to_spouse' => 'none',
                    'investments_exotic' => 'none', 'property_buy_to_let_analysis' => 'none',
                    'retirement_decumulation' => 'none', 'what_if' => 'none',
                    'holistic_plan' => 'none', 'document_upload' => 'none',
                    'statement_upload' => 'none', 'advisor_export' => 'none',
                    'investment_cost_analysis' => 'none',
                    'benefits_child' => 'full', 'family_module' => 'full',
                ],
                'count_caps' => [
                    'savings_account' => 2,
                    'investment' => 2,
                    'pension_account' => 2,
                    'property' => 1,
                    'mortgage' => 10,
                    'goal' => 2,
                    'life_event' => 1,
                ],
                'document_upload_allowance' => 0,
                'document_storage_gb' => null,
                'fyn_weekly_token_budget' => 100_000,
                'fyn_daily_hard_backstop' => 500_000,
                'currency_display_mode' => 'gbp_only',
                'snapshot_surfacing_window_days' => 90,
                'open_api_affordance' => false,
                'is_active' => true,
                'updated_by' => null,
            ],
            [
                'tier' => 'premium',
                'display_name' => 'Premium',
                'price_monthly_pence' => 699,
                'price_annual_pence' => 5999,
                'revolut_plan_variation_id' => null,
                'capability_matrix' => [
                    'dashboard' => 'full', 'letter_to_spouse' => 'full',
                    'protection' => 'full', 'income' => 'full', 'liabilities' => 'full',
                    'expenditure' => 'full', 'expenditure_detailed' => 'full',
                    'tax_strategy' => 'full', 'risk_profile' => 'full',
                    'future_value_projections' => 'full',
                    'savings_account' => 'full', 'investment' => 'full',
                    'pension_account' => 'full', 'property' => 'full',
                    'goals' => 'full', 'life_events' => 'full',
                    'chattels' => 'full', 'estate' => 'full',
                    'joint_household_view' => 'full',
                    'investments_exotic' => 'full', 'retirement_decumulation' => 'full',
                    'property_buy_to_let_analysis' => 'full', 'what_if' => 'full',
                    'holistic_plan' => 'full', 'document_upload' => 'full',
                    'statement_upload' => 'full', 'advisor_export' => 'full',
                    'investment_cost_analysis' => 'full',
                    'benefits_child' => 'full', 'family_module' => 'full',
                ],
                'count_caps' => [
                    'savings_account' => null,
                    'investment' => null,
                    'pension_account' => null,
                    'property' => null,
                    'mortgage' => null,
                    'goal' => null,
                    'life_event' => null,
                ],
                'document_upload_allowance' => null,
                'document_storage_gb' => 1.00,
                'fyn_weekly_token_budget' => 500_000,
                'fyn_daily_hard_backstop' => 2_000_000,
                'currency_display_mode' => 'user_choice',
                'snapshot_surfacing_window_days' => null,
                'open_api_affordance' => true,
                'is_active' => true,
                'updated_by' => null,
            ],
        ];
    }
}
