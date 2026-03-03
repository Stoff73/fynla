<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\InvestmentActionDefinition;
use Illuminate\Database\Seeder;

/**
 * Seed the investment_action_definitions table with all action types.
 *
 * Seeds 18 agent-sourced and 3 goal-sourced action definitions.
 * Uses updateOrCreate on `key` for idempotency.
 *
 * Run: php artisan db:seed --class=InvestmentActionDefinitionSeeder --force
 */
class InvestmentActionDefinitionSeeder extends Seeder
{
    public function run(): void
    {
        $definitions = $this->getDefinitions();

        foreach ($definitions as $definition) {
            InvestmentActionDefinition::updateOrCreate(
                ['key' => $definition['key']],
                $definition
            );
        }
    }

    private function getDefinitions(): array
    {
        return [
            // ── Agent-sourced: Investment actions (8) ──────────────────

            [
                'key' => 'risk_profile_missing',
                'source' => 'agent',
                'title_template' => 'Provide Your Investment Preferences',
                'description_template' => 'To personalise your investment plan, we need three key pieces of information: your investment time horizon, your capacity for loss, and your risk tolerance. This allows us to recommend an appropriate asset allocation.',
                'action_template' => 'Complete the risk questionnaire in the Risk Profile section to provide this information.',
                'category' => 'Risk Profile',
                'priority' => 'high',
                'scope' => 'portfolio',
                'what_if_impact_type' => 'default',
                'trigger_config' => [
                    'condition' => 'risk_profile_not_set',
                ],
                'is_enabled' => true,
                'sort_order' => 10,
                'notes' => 'Triggers when no risk profile / allocation deviation is available.',
            ],

            [
                'key' => 'no_holdings',
                'source' => 'agent',
                'title_template' => 'Add Your Fund Holdings',
                'description_template' => 'Without your fund holdings, this plan uses risk-based fee-optimised allocations as a benchmark. Adding your actual holdings will give you a more accurate analysis of your fees, diversification, and tax efficiency.',
                'action_template' => 'Click on your investment account and add your fund holdings for a personalised analysis.',
                'category' => 'Portfolio Setup',
                'priority' => 'medium',
                'scope' => 'portfolio',
                'what_if_impact_type' => 'default',
                'trigger_config' => [
                    'condition' => 'accounts_exist_but_no_holdings',
                ],
                'is_enabled' => true,
                'sort_order' => 20,
                'notes' => 'Triggers when accounts exist but total holdings count is zero.',
            ],

            [
                'key' => 'low_diversification',
                'source' => 'agent',
                'title_template' => 'Improve Portfolio Diversification',
                'description_template' => 'Your portfolio is concentrated in a limited number of asset types. Consider spreading investments across more asset classes to reduce risk.',
                'action_template' => 'Review asset allocation and consider adding different asset classes.',
                'category' => 'Diversification',
                'priority' => 'medium',
                'scope' => 'portfolio',
                'what_if_impact_type' => 'default',
                'trigger_config' => [
                    'condition' => 'diversification_score_below',
                    'threshold' => 70,
                ],
                'is_enabled' => true,
                'sort_order' => 30,
                'notes' => 'Triggers when diversification score is below threshold. Only fires when holdings exist.',
            ],

            [
                'key' => 'high_total_fees',
                'source' => 'agent',
                'title_template' => 'Review fees on {account_name}',
                'description_template' => 'Total fees on this account are {total_fee_percent}% ({annual_fees} per year). Reducing fees could significantly improve long-term returns.',
                'action_template' => 'Review platform and fund fees on this account.',
                'category' => 'Fees',
                'priority' => 'high',
                'scope' => 'account',
                'what_if_impact_type' => 'fee_reduction',
                'trigger_config' => [
                    'condition' => 'total_fee_percent_above',
                    'threshold' => 1.0,
                ],
                'is_enabled' => true,
                'sort_order' => 40,
                'notes' => 'Triggers per-account when total fee percentage exceeds threshold.',
            ],

            [
                'key' => 'high_fund_fees',
                'source' => 'agent',
                'title_template' => 'Review high-fee holdings in {account_name}',
                'description_template' => 'The weighted fund charge on this account is {weighted_ocf}%. Consider switching to lower-cost index funds.',
                'action_template' => 'Compare fund fees and switch to low-cost index alternatives where appropriate.',
                'category' => 'High Fees',
                'priority' => 'medium',
                'scope' => 'account',
                'what_if_impact_type' => 'fee_reduction',
                'trigger_config' => [
                    'condition' => 'weighted_ocf_above',
                    'threshold' => 0.5,
                ],
                'is_enabled' => true,
                'sort_order' => 50,
                'notes' => 'Triggers per-account when weighted ongoing charge figure exceeds threshold.',
            ],

            [
                'key' => 'high_platform_fees',
                'source' => 'agent',
                'title_template' => 'Review platform fees on {account_name}',
                'description_template' => 'Platform fees on this account are {platform_fee_percent}%. Consider switching to a lower-cost platform.',
                'action_template' => 'Compare platform fees across providers.',
                'category' => 'Platform Fees',
                'priority' => 'medium',
                'scope' => 'account',
                'what_if_impact_type' => 'fee_reduction',
                'trigger_config' => [
                    'condition' => 'platform_fee_percent_above',
                    'threshold' => 0.8,
                ],
                'is_enabled' => true,
                'sort_order' => 60,
                'notes' => 'Triggers per-account when platform fee percentage exceeds threshold.',
            ],

            [
                'key' => 'rebalance_portfolio',
                'source' => 'agent',
                'title_template' => 'Rebalance Portfolio',
                'description_template' => 'Your current allocation deviates significantly from your risk profile. Rebalancing will bring your portfolio back in line with your target allocation.',
                'action_template' => 'Consider rebalancing to match your target allocation.',
                'category' => 'Asset Allocation',
                'priority' => 'medium',
                'scope' => 'portfolio',
                'what_if_impact_type' => 'default',
                'trigger_config' => [
                    'condition' => 'allocation_needs_rebalancing',
                ],
                'is_enabled' => true,
                'sort_order' => 70,
                'notes' => 'Triggers when allocation deviation indicates rebalancing is needed. Only fires when holdings exist.',
            ],

            [
                'key' => 'tax_loss_harvesting',
                'source' => 'agent',
                'title_template' => 'Tax Loss Harvesting Opportunity',
                'description_template' => '{opportunities_count} holdings have unrealised losses. Potential tax saving: {potential_saving}.',
                'action_template' => 'Consider selling losing positions to offset capital gains.',
                'category' => 'Tax Planning',
                'priority' => 'medium',
                'scope' => 'portfolio',
                'what_if_impact_type' => 'tax_optimisation',
                'trigger_config' => [
                    'condition' => 'has_harvesting_opportunities',
                ],
                'is_enabled' => true,
                'sort_order' => 80,
                'notes' => 'Triggers when tax loss harvesting opportunities exist.',
            ],

            // ── Agent-sourced: Tax efficiency actions (3) ──────────────

            [
                'key' => 'open_isa',
                'source' => 'agent',
                'title_template' => 'Open a Stocks & Shares ISA',
                'description_template' => 'Your investments are in a General Investment Account where gains and dividends are taxable. An ISA shelters up to {isa_allowance} per year from income tax and capital gains tax.',
                'action_template' => 'Open an ISA and transfer or contribute up to the annual allowance.',
                'category' => 'Tax Efficiency',
                'priority' => 'high',
                'scope' => 'portfolio',
                'what_if_impact_type' => 'tax_optimisation',
                'trigger_config' => [
                    'condition' => 'has_gia_no_isa',
                ],
                'is_enabled' => true,
                'sort_order' => 90,
                'notes' => 'Triggers when user has General Investment Account but no ISA.',
            ],

            [
                'key' => 'use_isa_allowance',
                'source' => 'agent',
                'title_template' => 'Use Your ISA Allowance',
                'description_template' => 'You have {isa_remaining} ISA allowance remaining this tax year. Consider moving General Investment Account holdings ({gia_value}) into your ISA before 5 April.',
                'action_template' => 'Transfer or contribute General Investment Account funds into your ISA to shelter from tax.',
                'category' => 'Tax Efficiency',
                'priority' => 'medium',
                'scope' => 'portfolio',
                'what_if_impact_type' => 'tax_optimisation',
                'trigger_config' => [
                    'condition' => 'has_isa_remaining_and_gia',
                ],
                'is_enabled' => true,
                'sort_order' => 100,
                'notes' => 'Triggers when ISA has remaining allowance and GIA holdings exist.',
            ],

            [
                'key' => 'consider_bonds',
                'source' => 'agent',
                'title_template' => 'Consider Tax-Efficient Bonds',
                'description_template' => 'With {gia_value} in your General Investment Account, consider onshore bonds (tax-deferred growth, 5% annual tax-free withdrawal) or offshore bonds (gross roll-up, no annual UK tax on gains) for additional tax efficiency.',
                'action_template' => 'Speak to your adviser about investment bonds for tax-deferred growth.',
                'category' => 'Tax Efficiency',
                'priority' => 'low',
                'scope' => 'portfolio',
                'what_if_impact_type' => 'tax_optimisation',
                'trigger_config' => [
                    'condition' => 'gia_value_above_and_no_bonds',
                    'threshold' => 50000,
                ],
                'is_enabled' => true,
                'sort_order' => 110,
                'notes' => 'Triggers when GIA value exceeds threshold and no bond accounts exist.',
            ],

            // ── Agent-sourced: Savings actions (4) ──────────────────────

            [
                'key' => 'emergency_fund_critical',
                'source' => 'agent',
                'title_template' => 'Build Emergency Fund to 3 Months',
                'description_template' => 'Your emergency fund is critically low at {runway_months} months. Prioritise building savings to cover at least 3 months of expenses.',
                'action_template' => 'Set up a monthly standing order to your savings account.',
                'category' => 'Emergency Fund',
                'priority' => 'critical',
                'scope' => 'portfolio',
                'what_if_impact_type' => 'savings_increase',
                'trigger_config' => [
                    'condition' => 'emergency_runway_below',
                    'threshold' => 3,
                ],
                'is_enabled' => true,
                'sort_order' => 120,
                'notes' => 'Triggers when emergency fund runway is below threshold months.',
            ],

            [
                'key' => 'emergency_fund_grow',
                'source' => 'agent',
                'title_template' => 'Grow Emergency Fund to 6 Months',
                'description_template' => 'Your emergency fund covers {runway_months} months. The recommended target is 6 months of expenses.',
                'action_template' => 'Continue building your emergency fund alongside other goals.',
                'category' => 'Emergency Fund',
                'priority' => 'high',
                'scope' => 'portfolio',
                'what_if_impact_type' => 'savings_increase',
                'trigger_config' => [
                    'condition' => 'emergency_runway_between',
                    'low' => 3,
                    'high' => 6,
                ],
                'is_enabled' => true,
                'sort_order' => 130,
                'notes' => 'Triggers when emergency fund runway is between low and high thresholds.',
            ],

            [
                'key' => 'switch_savings_rate',
                'source' => 'agent',
                'title_template' => 'Switch to Higher-Rate Savings Accounts',
                'description_template' => 'You could earn an additional {potential_gain} per year by moving to better-rate accounts.',
                'action_template' => 'Compare savings rates and switch accounts with below-market rates.',
                'category' => 'Interest Rate',
                'priority' => 'medium',
                'scope' => 'portfolio',
                'what_if_impact_type' => 'savings_increase',
                'trigger_config' => [
                    'condition' => 'has_poor_rate_accounts',
                ],
                'is_enabled' => true,
                'sort_order' => 140,
                'notes' => 'Triggers when poor-rated savings accounts exist with meaningful potential gain.',
            ],

            [
                'key' => 'isa_allowance_remaining',
                'source' => 'agent',
                'title_template' => 'Use Remaining ISA Allowance',
                'description_template' => 'You have {isa_remaining} of ISA allowance remaining this tax year. Use it for tax-free growth.',
                'action_template' => 'Transfer savings into a Cash ISA or Stocks & Shares ISA.',
                'category' => 'ISA Allowance',
                'priority' => 'medium',
                'scope' => 'portfolio',
                'what_if_impact_type' => 'contribution',
                'trigger_config' => [
                    'condition' => 'isa_remaining_and_runway_above',
                    'threshold' => 6,
                ],
                'is_enabled' => true,
                'sort_order' => 150,
                'notes' => 'Triggers when ISA allowance remaining and emergency fund runway exceeds threshold.',
            ],

            // ── Agent-sourced: Surplus waterfall actions (3) ────────────

            [
                'key' => 'surplus_to_isa',
                'source' => 'agent',
                'title_template' => 'Move Surplus to ISA',
                'description_template' => 'Your emergency fund exceeds 6 months. Move {isa_amount} into an ISA for tax-free growth. You have {isa_remaining} of ISA allowance remaining this tax year.',
                'action_template' => 'Transfer surplus cash into a Cash ISA or Stocks & Shares ISA.',
                'category' => 'Emergency Fund Surplus',
                'priority' => 'medium',
                'scope' => 'portfolio',
                'what_if_impact_type' => 'contribution',
                'trigger_config' => [
                    'condition' => 'surplus_exists_and_isa_remaining',
                ],
                'is_enabled' => true,
                'sort_order' => 160,
                'notes' => 'Triggers when surplus exists and ISA allowance remaining.',
            ],

            [
                'key' => 'surplus_to_pension',
                'source' => 'agent',
                'title_template' => 'Contribute Surplus to Pension',
                'description_template' => 'Consider contributing {pension_amount} to your pension. You will receive tax relief, boosting the value of your contribution.',
                'action_template' => 'Make an additional pension contribution via your Self-Invested Personal Pension or workplace scheme.',
                'category' => 'Emergency Fund Surplus',
                'priority' => 'low',
                'scope' => 'portfolio',
                'what_if_impact_type' => 'contribution',
                'trigger_config' => [
                    'condition' => 'surplus_exceeds_isa',
                ],
                'is_enabled' => true,
                'sort_order' => 170,
                'notes' => 'Triggers when surplus exceeds ISA capacity.',
            ],

            [
                'key' => 'surplus_to_bond',
                'source' => 'agent',
                'title_template' => 'Consider an Investment Bond',
                'description_template' => 'You could place {bond_amount} in an investment bond. Bonds offer tax-deferred growth and can be useful for estate planning.',
                'action_template' => 'Speak to a financial adviser about investment bond options.',
                'category' => 'Emergency Fund Surplus',
                'priority' => 'low',
                'scope' => 'portfolio',
                'what_if_impact_type' => 'default',
                'trigger_config' => [
                    'condition' => 'surplus_exceeds_pension',
                ],
                'is_enabled' => true,
                'sort_order' => 180,
                'notes' => 'Triggers when surplus exceeds pension capacity.',
            ],

            // ── Goal-sourced actions (3) ────────────────────────────────

            [
                'key' => 'goal_no_contribution',
                'source' => 'goal',
                'title_template' => 'Start contributing to {goal_name}',
                'description_template' => 'You have not set a monthly contribution for {goal_name}. Contributing {required_monthly} per month would help you reach your target of {target_amount}.',
                'action_template' => 'Set up a monthly contribution for this goal.',
                'category' => 'Goal',
                'priority' => 'high',
                'scope' => 'portfolio',
                'what_if_impact_type' => 'contribution',
                'trigger_config' => [
                    'condition' => 'linked_goal_no_monthly_contribution',
                ],
                'is_enabled' => true,
                'sort_order' => 190,
                'notes' => 'Triggers when a linked goal has no monthly contribution set.',
            ],

            [
                'key' => 'goal_behind_schedule',
                'source' => 'goal',
                'title_template' => '{goal_name} is behind schedule',
                'description_template' => '{goal_name} is currently {progress}% complete but behind schedule. Increasing your monthly contribution by {shortfall} would bring it back on track.',
                'action_template' => 'Increase monthly contributions to get back on track.',
                'category' => 'Goal',
                'priority' => 'high',
                'scope' => 'portfolio',
                'what_if_impact_type' => 'contribution',
                'trigger_config' => [
                    'condition' => 'linked_goal_off_track',
                ],
                'is_enabled' => true,
                'sort_order' => 200,
                'notes' => 'Triggers when a linked goal is not on track.',
            ],

            [
                'key' => 'goal_deadline_approaching',
                'source' => 'goal',
                'title_template' => '{goal_name} target date is approaching',
                'description_template' => '{goal_name} is only {progress}% complete with {months_remaining} months remaining. Consider increasing your contributions to reach your target of {target_amount} on time.',
                'action_template' => 'Review and increase contributions before the deadline.',
                'category' => 'Goal',
                'priority' => 'medium',
                'scope' => 'portfolio',
                'what_if_impact_type' => 'contribution',
                'trigger_config' => [
                    'condition' => 'goal_months_remaining_below_and_progress_below',
                    'months_threshold' => 6,
                    'progress_threshold' => 75,
                ],
                'is_enabled' => true,
                'sort_order' => 210,
                'notes' => 'Triggers when goal deadline is near and progress is below threshold.',
            ],
        ];
    }
}
