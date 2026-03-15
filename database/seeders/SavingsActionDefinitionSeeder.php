<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\SavingsActionDefinition;
use Illuminate\Database\Seeder;

/**
 * Seed the savings_action_definitions table with all action types.
 *
 * Seeds 36 agent-sourced and 5 goal-sourced action definitions across 10 categories.
 * Uses updateOrCreate on `key` for idempotency.
 *
 * Run: php artisan db:seed --class=SavingsActionDefinitionSeeder --force
 */
class SavingsActionDefinitionSeeder extends Seeder
{
    public function run(): void
    {
        $definitions = $this->getDefinitions();

        foreach ($definitions as $definition) {
            SavingsActionDefinition::updateOrCreate(
                ['key' => $definition['key']],
                $definition
            );
        }

        // LAUNCH GATE: Disable overlapping Investment engine savings triggers
        \App\Models\InvestmentActionDefinition::whereIn('key', [
            'emergency_fund_critical',
            'emergency_fund_grow',
            'switch_savings_rate',
            'isa_allowance_remaining',
            'surplus_to_isa',
            'surplus_to_pension',
            'surplus_to_bond',
        ])->update(['is_enabled' => false]);

        $this->command->info('Savings action definitions seeded. 7 Investment savings triggers disabled.');
    }

    private function getDefinitions(): array
    {
        return [
            // ── Data Readiness (4) ────────────────────────────────────

            [
                'key' => 'missing_date_of_birth',
                'source' => 'agent',
                'title_template' => 'Provide Your Date of Birth',
                'description_template' => 'Your date of birth is needed to provide personalised savings guidance.',
                'action_template' => 'Update your profile with your date of birth.',
                'category' => 'Data Readiness',
                'priority' => 'critical',
                'scope' => 'portfolio',
                'what_if_impact_type' => 'default',
                'trigger_config' => [
                    'condition' => 'date_of_birth_missing',
                ],
                'is_enabled' => true,
                'sort_order' => 10,
                'notes' => 'Triggers when date of birth is not set on user profile.',
            ],

            [
                'key' => 'missing_income',
                'source' => 'agent',
                'title_template' => 'Provide Your Income Details',
                'description_template' => 'Your income details are needed to calculate tax-efficient savings recommendations.',
                'action_template' => 'Add your income in the Income section.',
                'category' => 'Data Readiness',
                'priority' => 'critical',
                'scope' => 'portfolio',
                'what_if_impact_type' => 'default',
                'trigger_config' => [
                    'condition' => 'income_missing',
                ],
                'is_enabled' => true,
                'sort_order' => 11,
                'notes' => 'Triggers when no income records exist for the user.',
            ],

            [
                'key' => 'missing_expenditure',
                'source' => 'agent',
                'title_template' => 'Provide Your Monthly Expenditure',
                'description_template' => 'Your monthly expenditure is needed to calculate emergency fund targets.',
                'action_template' => 'Add your expenditure in the Expenditure section.',
                'category' => 'Data Readiness',
                'priority' => 'critical',
                'scope' => 'portfolio',
                'what_if_impact_type' => 'default',
                'trigger_config' => [
                    'condition' => 'expenditure_missing',
                ],
                'is_enabled' => true,
                'sort_order' => 12,
                'notes' => 'Triggers when no expenditure records exist for the user.',
            ],

            [
                'key' => 'missing_employment_status',
                'source' => 'agent',
                'title_template' => 'Provide Your Employment Status',
                'description_template' => 'Your employment status helps us tailor emergency fund recommendations.',
                'action_template' => 'Update your employment status in your profile.',
                'category' => 'Data Readiness',
                'priority' => 'medium',
                'scope' => 'portfolio',
                'what_if_impact_type' => 'default',
                'trigger_config' => [
                    'condition' => 'employment_status_missing',
                ],
                'is_enabled' => true,
                'sort_order' => 13,
                'notes' => 'Triggers when employment status is not set. Affects emergency fund target months.',
            ],

            // ── Emergency Fund (5) ────────────────────────────────────

            [
                'key' => 'emergency_fund_critical',
                'source' => 'agent',
                'title_template' => 'Build Your Emergency Fund Urgently',
                'description_template' => 'Your emergency fund covers less than 1 month of expenses.',
                'action_template' => 'Set up a monthly standing order to an easy access savings account immediately.',
                'category' => 'Emergency Fund',
                'priority' => 'critical',
                'scope' => 'portfolio',
                'what_if_impact_type' => 'savings_increase',
                'trigger_config' => [
                    'condition' => 'emergency_runway_below',
                    'threshold' => 1,
                ],
                'is_enabled' => true,
                'sort_order' => 20,
                'notes' => 'Triggers when emergency fund runway is below 1 month.',
            ],

            [
                'key' => 'emergency_fund_low',
                'source' => 'agent',
                'title_template' => 'Increase Your Emergency Fund',
                'description_template' => 'Your emergency fund covers {runway_months} months — below the recommended {target_months} months.',
                'action_template' => 'Prioritise building your emergency fund to at least {target_months} months of expenses.',
                'category' => 'Emergency Fund',
                'priority' => 'high',
                'scope' => 'portfolio',
                'what_if_impact_type' => 'savings_increase',
                'trigger_config' => [
                    'condition' => 'emergency_runway_between',
                    'low' => 1,
                    'high' => 3,
                ],
                'is_enabled' => true,
                'sort_order' => 21,
                'notes' => 'Triggers when emergency fund runway is between 1 and 3 months.',
            ],

            [
                'key' => 'emergency_fund_building',
                'source' => 'agent',
                'title_template' => 'Continue Building Your Emergency Fund',
                'description_template' => 'Your emergency fund is building — {runway_months} months towards your {target_months} month target.',
                'action_template' => 'Continue your regular savings to reach your {target_months} month target.',
                'category' => 'Emergency Fund',
                'priority' => 'medium',
                'scope' => 'portfolio',
                'what_if_impact_type' => 'savings_increase',
                'trigger_config' => [
                    'condition' => 'emergency_runway_between',
                    'low' => 3,
                    'high' => 6,
                ],
                'is_enabled' => true,
                'sort_order' => 22,
                'notes' => 'Triggers when emergency fund runway is between 3 and target months.',
            ],

            [
                'key' => 'emergency_fund_excess',
                'source' => 'agent',
                'title_template' => 'Review Your Excess Emergency Fund',
                'description_template' => 'Your emergency fund of {runway_months} months exceeds your target by {excess_months} months.',
                'action_template' => 'Consider moving excess savings to a higher-growth account such as an ISA or investment.',
                'category' => 'Emergency Fund',
                'priority' => 'low',
                'scope' => 'portfolio',
                'what_if_impact_type' => 'default',
                'trigger_config' => [
                    'condition' => 'emergency_runway_above',
                    'threshold' => 6,
                ],
                'is_enabled' => true,
                'sort_order' => 23,
                'notes' => 'Triggers when emergency fund runway exceeds target months plus buffer.',
            ],

            [
                'key' => 'emergency_fund_no_data',
                'source' => 'agent',
                'title_template' => 'Emergency Fund Cannot Be Assessed',
                'description_template' => 'We cannot assess your emergency fund without expenditure data.',
                'action_template' => 'Add your monthly expenditure so we can calculate your emergency fund target.',
                'category' => 'Emergency Fund',
                'priority' => 'high',
                'scope' => 'portfolio',
                'what_if_impact_type' => 'default',
                'trigger_config' => [
                    'condition' => 'emergency_fund_expenditure_missing',
                ],
                'is_enabled' => true,
                'sort_order' => 24,
                'notes' => 'Triggers when savings accounts exist but expenditure data is missing.',
            ],

            // ── Tax Efficiency (6) ────────────────────────────────────

            [
                'key' => 'psa_breached',
                'source' => 'agent',
                'title_template' => 'Personal Savings Allowance Exceeded',
                'description_template' => 'Your estimated annual savings interest of {annual_interest} exceeds your {psa_amount} Personal Savings Allowance.',
                'action_template' => 'Move savings into a Cash ISA to shelter interest from tax.',
                'category' => 'Tax Efficiency',
                'priority' => 'high',
                'scope' => 'portfolio',
                'what_if_impact_type' => 'tax_optimisation',
                'trigger_config' => [
                    'condition' => 'psa_exceeded',
                ],
                'is_enabled' => true,
                'sort_order' => 30,
                'notes' => 'Triggers when estimated annual interest exceeds the Personal Savings Allowance for the user\'s tax band.',
            ],

            [
                'key' => 'psa_approaching',
                'source' => 'agent',
                'title_template' => 'Personal Savings Allowance Limit Approaching',
                'description_template' => 'Your savings interest is approaching your Personal Savings Allowance limit.',
                'action_template' => 'Consider sheltering future savings in a Cash ISA to avoid exceeding your allowance.',
                'category' => 'Tax Efficiency',
                'priority' => 'medium',
                'scope' => 'portfolio',
                'what_if_impact_type' => 'tax_optimisation',
                'trigger_config' => [
                    'condition' => 'psa_usage_above',
                    'threshold' => 80,
                ],
                'is_enabled' => true,
                'sort_order' => 31,
                'notes' => 'Triggers when estimated interest usage exceeds threshold percentage of Personal Savings Allowance.',
            ],

            [
                'key' => 'psa_additional_rate',
                'source' => 'agent',
                'title_template' => 'No Personal Savings Allowance Available',
                'description_template' => 'As an additional rate taxpayer, you have no Personal Savings Allowance — all savings interest is taxable.',
                'action_template' => 'Maximise your Cash ISA allowance to shelter savings interest from income tax.',
                'category' => 'Tax Efficiency',
                'priority' => 'critical',
                'scope' => 'portfolio',
                'what_if_impact_type' => 'tax_optimisation',
                'trigger_config' => [
                    'condition' => 'additional_rate_taxpayer_with_taxable_savings',
                ],
                'is_enabled' => true,
                'sort_order' => 32,
                'notes' => 'Triggers when user is an additional rate taxpayer with non-ISA savings generating interest.',
            ],

            [
                'key' => 'starting_rate_unused',
                'source' => 'agent',
                'title_template' => 'Starting Rate for Savings May Apply',
                'description_template' => 'You may qualify for the starting rate for savings, providing up to £5,000 of tax-free interest.',
                'action_template' => 'Review your non-savings income to confirm your eligibility for the starting rate.',
                'category' => 'Tax Efficiency',
                'priority' => 'medium',
                'scope' => 'portfolio',
                'what_if_impact_type' => 'tax_optimisation',
                'trigger_config' => [
                    'condition' => 'eligible_for_starting_rate',
                ],
                'is_enabled' => true,
                'sort_order' => 33,
                'notes' => 'Triggers when non-savings income is below the personal allowance plus £5,000 starting rate band.',
            ],

            [
                'key' => 'cash_isa_recommended',
                'source' => 'agent',
                'title_template' => 'Consider a Cash ISA',
                'description_template' => 'Moving savings to a Cash ISA would shelter interest from tax.',
                'action_template' => 'Open a Cash ISA and transfer existing savings to use your ISA allowance.',
                'category' => 'Tax Efficiency',
                'priority' => 'high',
                'scope' => 'portfolio',
                'what_if_impact_type' => 'tax_optimisation',
                'trigger_config' => [
                    'condition' => 'has_taxable_savings_no_cash_isa',
                ],
                'is_enabled' => true,
                'sort_order' => 34,
                'notes' => 'Triggers when user has taxable savings interest and no Cash ISA.',
            ],

            [
                'key' => 'cash_isa_not_needed',
                'source' => 'agent',
                'title_template' => 'Cash ISA Not a Priority',
                'description_template' => 'As a basic rate taxpayer with comfortable Personal Savings Allowance headroom, a Cash ISA is not a priority.',
                'action_template' => null,
                'category' => 'Tax Efficiency',
                'priority' => 'low',
                'scope' => 'portfolio',
                'what_if_impact_type' => 'default',
                'trigger_config' => [
                    'condition' => 'basic_rate_with_psa_headroom',
                    'headroom_threshold' => 50,
                ],
                'is_enabled' => true,
                'sort_order' => 35,
                'notes' => 'Triggers when basic rate taxpayer has significant Personal Savings Allowance headroom remaining.',
            ],

            // ── Rate Optimisation (6) ─────────────────────────────────

            [
                'key' => 'rate_below_market',
                'source' => 'agent',
                'title_template' => 'Better Rate Available for {account_name}',
                'description_template' => 'The interest rate on {account_name} ({account_rate}%) is {rate_gap}% below the best available rate.',
                'action_template' => 'Compare rates and consider switching to a higher-paying account.',
                'category' => 'Rate Optimisation',
                'priority' => 'medium',
                'scope' => 'account',
                'what_if_impact_type' => 'savings_increase',
                'trigger_config' => [
                    'condition' => 'rate_below_market_best',
                    'gap_threshold' => 0.5,
                ],
                'is_enabled' => true,
                'sort_order' => 40,
                'notes' => 'Triggers per-account when rate gap to market best exceeds threshold.',
            ],

            [
                'key' => 'rate_poor',
                'source' => 'agent',
                'title_template' => 'Significantly Below-Market Rate on {account_name}',
                'description_template' => 'The interest rate on {account_name} ({account_rate}%) is significantly below market rates.',
                'action_template' => 'Switch this account to a competitive provider as a priority.',
                'category' => 'Rate Optimisation',
                'priority' => 'high',
                'scope' => 'account',
                'what_if_impact_type' => 'savings_increase',
                'trigger_config' => [
                    'condition' => 'rate_significantly_below_market',
                    'gap_threshold' => 1.5,
                ],
                'is_enabled' => true,
                'sort_order' => 41,
                'notes' => 'Triggers per-account when rate gap is significantly below market rates.',
            ],

            [
                'key' => 'fixed_maturity_warning',
                'source' => 'agent',
                'title_template' => 'Fixed Rate Maturing Soon on {account_name}',
                'description_template' => '{account_name} matures in {days_to_maturity} days — review your reinvestment options.',
                'action_template' => 'Research the best available fixed and easy access rates before maturity.',
                'category' => 'Rate Optimisation',
                'priority' => 'medium',
                'scope' => 'account',
                'what_if_impact_type' => 'savings_increase',
                'trigger_config' => [
                    'condition' => 'fixed_term_maturing_within',
                    'days_threshold' => 90,
                ],
                'is_enabled' => true,
                'sort_order' => 42,
                'notes' => 'Triggers per-account when a fixed rate account matures within threshold days.',
            ],

            [
                'key' => 'fixed_maturity_urgent',
                'source' => 'agent',
                'title_template' => 'Urgent: {account_name} Maturing Imminently',
                'description_template' => '{account_name} matures in {days_to_maturity} days — act now to avoid defaulting to a lower rate.',
                'action_template' => 'Contact your provider immediately to arrange reinvestment or transfer.',
                'category' => 'Rate Optimisation',
                'priority' => 'high',
                'scope' => 'account',
                'what_if_impact_type' => 'savings_increase',
                'trigger_config' => [
                    'condition' => 'fixed_term_maturing_within',
                    'days_threshold' => 30,
                ],
                'is_enabled' => true,
                'sort_order' => 43,
                'notes' => 'Triggers per-account when a fixed rate account matures within urgent threshold.',
            ],

            [
                'key' => 'promo_rate_expiring',
                'source' => 'agent',
                'title_template' => 'Promotional Rate Expiring on {account_name}',
                'description_template' => 'The promotional rate on {account_name} expires in {days_to_expiry} days.',
                'action_template' => 'Compare rates and prepare to switch before the promotional rate ends.',
                'category' => 'Rate Optimisation',
                'priority' => 'high',
                'scope' => 'account',
                'what_if_impact_type' => 'savings_increase',
                'trigger_config' => [
                    'condition' => 'promo_rate_expiring_within',
                    'days_threshold' => 30,
                ],
                'is_enabled' => true,
                'sort_order' => 44,
                'notes' => 'Triggers per-account when a promotional rate expires within threshold days.',
            ],

            [
                'key' => 'regular_saver_opportunity',
                'source' => 'agent',
                'title_template' => 'Consider a Regular Saver Account',
                'description_template' => 'A regular saver account could earn significantly more than your current easy access rate.',
                'action_template' => 'Check if your bank offers a regular saver account with a higher rate.',
                'category' => 'Rate Optimisation',
                'priority' => 'medium',
                'scope' => 'portfolio',
                'what_if_impact_type' => 'savings_increase',
                'trigger_config' => [
                    'condition' => 'has_easy_access_with_regular_contributions',
                ],
                'is_enabled' => true,
                'sort_order' => 45,
                'notes' => 'Triggers when user has easy access accounts with regular contributions that could benefit from a regular saver.',
            ],

            // ── Financial Services Compensation Scheme Protection (2) ─

            [
                'key' => 'fscs_breach',
                'source' => 'agent',
                'title_template' => 'Financial Services Compensation Scheme Limit Exceeded',
                'description_template' => 'You have {breach_amount} above the Financial Services Compensation Scheme protection limit at {institution_name}.',
                'action_template' => 'Spread savings across multiple institutions to ensure full protection.',
                'category' => 'Financial Services Compensation Scheme',
                'priority' => 'high',
                'scope' => 'portfolio',
                'what_if_impact_type' => 'default',
                'trigger_config' => [
                    'condition' => 'institution_balance_above_fscs',
                    'threshold' => 85000,
                ],
                'is_enabled' => true,
                'sort_order' => 50,
                'notes' => 'Triggers when total balance at a single institution exceeds £85,000 Financial Services Compensation Scheme limit.',
            ],

            [
                'key' => 'fscs_approaching',
                'source' => 'agent',
                'title_template' => 'Approaching Financial Services Compensation Scheme Limit',
                'description_template' => 'Your balance at {institution_name} is approaching the Financial Services Compensation Scheme protection limit.',
                'action_template' => 'Plan to open an account with a different institution for future deposits.',
                'category' => 'Financial Services Compensation Scheme',
                'priority' => 'medium',
                'scope' => 'portfolio',
                'what_if_impact_type' => 'default',
                'trigger_config' => [
                    'condition' => 'institution_balance_approaching_fscs',
                    'threshold' => 75000,
                ],
                'is_enabled' => true,
                'sort_order' => 51,
                'notes' => 'Triggers when total balance at a single institution approaches the Financial Services Compensation Scheme limit.',
            ],

            // ── Debt vs Savings (2) ──────────────────────────────────

            [
                'key' => 'debt_rate_exceeds_savings',
                'source' => 'agent',
                'title_template' => 'Repay High-Interest Debt Before Saving',
                'description_template' => 'You have debt at {debt_rate}% whilst earning {savings_rate}% on savings — repaying the debt gives a guaranteed {rate_difference}% return.',
                'action_template' => 'Consider using savings above your emergency fund to repay high-interest debt.',
                'category' => 'Debt vs Savings',
                'priority' => 'high',
                'scope' => 'portfolio',
                'what_if_impact_type' => 'savings_increase',
                'trigger_config' => [
                    'condition' => 'debt_rate_exceeds_savings_rate',
                    'min_rate_difference' => 2.0,
                ],
                'is_enabled' => true,
                'sort_order' => 60,
                'notes' => 'Triggers when debt interest rate exceeds savings rate by minimum threshold.',
            ],

            [
                'key' => 'offset_mortgage_better',
                'source' => 'agent',
                'title_template' => 'Consider an Offset Mortgage Arrangement',
                'description_template' => 'Your mortgage rate of {mortgage_rate}% exceeds your after-tax savings rate — consider an offset mortgage arrangement.',
                'action_template' => 'Speak to your mortgage provider about offset options.',
                'category' => 'Debt vs Savings',
                'priority' => 'medium',
                'scope' => 'portfolio',
                'what_if_impact_type' => 'savings_increase',
                'trigger_config' => [
                    'condition' => 'mortgage_rate_exceeds_after_tax_savings_rate',
                ],
                'is_enabled' => true,
                'sort_order' => 61,
                'notes' => 'Triggers when mortgage rate exceeds the after-tax return on savings.',
            ],

            // ── Cash vs Investment (4) ────────────────────────────────

            [
                'key' => 'excess_cash_isa_available',
                'source' => 'agent',
                'title_template' => 'Use Your ISA Allowance for Excess Savings',
                'description_template' => 'You have {excess_amount} above your emergency fund target — consider using your remaining ISA allowance.',
                'action_template' => 'Transfer excess savings into a Cash ISA or Stocks & Shares ISA.',
                'category' => 'Cash vs Investment',
                'priority' => 'high',
                'scope' => 'portfolio',
                'what_if_impact_type' => 'contribution',
                'trigger_config' => [
                    'condition' => 'excess_cash_and_isa_remaining',
                ],
                'is_enabled' => true,
                'sort_order' => 70,
                'notes' => 'Triggers when savings exceed emergency fund target and ISA allowance remains.',
            ],

            [
                'key' => 'excess_cash_pension',
                'source' => 'agent',
                'title_template' => 'Consider Pension Contributions for Excess Savings',
                'description_template' => 'With your ISA allowance fully used, consider additional pension contributions for tax relief.',
                'action_template' => 'Make an additional pension contribution via your Self-Invested Personal Pension or workplace scheme.',
                'category' => 'Cash vs Investment',
                'priority' => 'high',
                'scope' => 'portfolio',
                'what_if_impact_type' => 'contribution',
                'trigger_config' => [
                    'condition' => 'excess_cash_isa_full_pension_remaining',
                ],
                'is_enabled' => true,
                'sort_order' => 71,
                'notes' => 'Triggers when excess cash exists and ISA is fully used but pension Annual Allowance remains.',
            ],

            [
                'key' => 'excess_cash_bond',
                'source' => 'agent',
                'title_template' => 'Consider an Investment Bond',
                'description_template' => 'Consider an investment bond for tax-deferred growth on your excess savings.',
                'action_template' => 'Speak to your adviser about onshore or offshore investment bond options.',
                'category' => 'Cash vs Investment',
                'priority' => 'medium',
                'scope' => 'portfolio',
                'what_if_impact_type' => 'default',
                'trigger_config' => [
                    'condition' => 'excess_cash_isa_and_pension_full',
                    'min_excess' => 50000,
                ],
                'is_enabled' => true,
                'sort_order' => 72,
                'notes' => 'Triggers when excess cash exists and both ISA and pension are fully used.',
            ],

            [
                'key' => 'excess_cash_gia',
                'source' => 'agent',
                'title_template' => 'Consider a General Investment Account',
                'description_template' => 'Consider a general investment account for your remaining excess savings.',
                'action_template' => 'Open a general investment account to put excess savings to work.',
                'category' => 'Cash vs Investment',
                'priority' => 'low',
                'scope' => 'portfolio',
                'what_if_impact_type' => 'default',
                'trigger_config' => [
                    'condition' => 'excess_cash_all_wrappers_full',
                ],
                'is_enabled' => true,
                'sort_order' => 73,
                'notes' => 'Triggers when excess cash exists and all tax-efficient wrappers are fully used.',
            ],

            // ── Goal-Linked (5) ──────────────────────────────────────

            [
                'key' => 'goal_wrong_account_type',
                'source' => 'goal',
                'title_template' => 'Review Account Type for \'{goal_name}\'',
                'description_template' => 'The account linked to \'{goal_name}\' may not be optimal for the {timeline} timeline.',
                'action_template' => 'Consider whether a different account type would be more suitable for the remaining time horizon.',
                'category' => 'Goal',
                'priority' => 'medium',
                'scope' => 'account',
                'what_if_impact_type' => 'default',
                'trigger_config' => [
                    'condition' => 'goal_account_type_mismatch',
                ],
                'is_enabled' => true,
                'sort_order' => 80,
                'notes' => 'Triggers when a goal\'s linked account type does not suit its timeline.',
            ],

            [
                'key' => 'goal_off_track',
                'source' => 'goal',
                'title_template' => '\'{goal_name}\' Needs Increased Contributions',
                'description_template' => '\'{goal_name}\' needs {required_monthly} per month but you are contributing {current_monthly}.',
                'action_template' => 'Increase your monthly contribution to get back on track.',
                'category' => 'Goal',
                'priority' => 'high',
                'scope' => 'account',
                'what_if_impact_type' => 'contribution',
                'trigger_config' => [
                    'condition' => 'goal_contribution_shortfall',
                ],
                'is_enabled' => true,
                'sort_order' => 81,
                'notes' => 'Triggers when current contributions are insufficient to meet the goal target by deadline.',
            ],

            [
                'key' => 'goal_nearly_achieved',
                'source' => 'goal',
                'title_template' => '\'{goal_name}\' Almost Complete',
                'description_template' => '\'{goal_name}\' is {progress}% complete — you are almost there.',
                'action_template' => null,
                'category' => 'Goal',
                'priority' => 'low',
                'scope' => 'account',
                'what_if_impact_type' => 'default',
                'trigger_config' => [
                    'condition' => 'goal_progress_above',
                    'threshold' => 90,
                ],
                'is_enabled' => true,
                'sort_order' => 82,
                'notes' => 'Triggers when goal progress exceeds threshold percentage.',
            ],

            [
                'key' => 'goal_no_linked_account',
                'source' => 'goal',
                'title_template' => 'Link an Account to \'{goal_name}\'',
                'description_template' => '\'{goal_name}\' has no linked savings account.',
                'action_template' => 'Link a savings account to this goal to track your progress.',
                'category' => 'Goal',
                'priority' => 'medium',
                'scope' => 'portfolio',
                'what_if_impact_type' => 'default',
                'trigger_config' => [
                    'condition' => 'goal_no_linked_savings_account',
                ],
                'is_enabled' => true,
                'sort_order' => 83,
                'notes' => 'Triggers when a savings goal has no linked account.',
            ],

            [
                'key' => 'goal_multi_account_rebalance',
                'source' => 'goal',
                'title_template' => 'Review Goal Account Allocation',
                'description_template' => 'Multiple goals are sharing accounts — review priority allocation.',
                'action_template' => 'Consider separating goals into dedicated accounts for clearer tracking.',
                'category' => 'Goal',
                'priority' => 'medium',
                'scope' => 'portfolio',
                'what_if_impact_type' => 'default',
                'trigger_config' => [
                    'condition' => 'multiple_goals_sharing_account',
                ],
                'is_enabled' => true,
                'sort_order' => 84,
                'notes' => 'Triggers when multiple goals are linked to the same account.',
            ],

            // ── Children's Savings (5) ───────────────────────────────

            [
                'key' => 'child_no_jisa',
                'source' => 'agent',
                'title_template' => 'Consider a Junior ISA for {child_name}',
                'description_template' => 'Consider opening a Junior ISA for {child_name}.',
                'action_template' => 'Open a Junior ISA to save tax-efficiently for {child_name}\'s future.',
                'category' => 'Children\'s Savings',
                'priority' => 'medium',
                'scope' => 'portfolio',
                'what_if_impact_type' => 'tax_optimisation',
                'trigger_config' => [
                    'condition' => 'child_under_18_no_jisa',
                ],
                'is_enabled' => true,
                'sort_order' => 90,
                'notes' => 'Triggers when a dependent child under 18 has no Junior ISA.',
            ],

            [
                'key' => 'child_jisa_allowance_remaining',
                'source' => 'agent',
                'title_template' => 'Junior ISA Allowance Remaining for {child_name}',
                'description_template' => '{child_name}\'s Junior ISA has {remaining} remaining this tax year.',
                'action_template' => 'Top up {child_name}\'s Junior ISA before the end of the tax year.',
                'category' => 'Children\'s Savings',
                'priority' => 'low',
                'scope' => 'portfolio',
                'what_if_impact_type' => 'contribution',
                'trigger_config' => [
                    'condition' => 'jisa_allowance_remaining',
                ],
                'is_enabled' => true,
                'sort_order' => 91,
                'notes' => 'Triggers when a Junior ISA has unused allowance in the current tax year.',
            ],

            [
                'key' => 'child_jisa_cash_vs_ss',
                'source' => 'agent',
                'title_template' => 'Consider a Stocks & Shares Junior ISA for {child_name}',
                'description_template' => 'With {years_to_18} years until {child_name} turns 18, a Stocks & Shares Junior ISA may offer better growth potential.',
                'action_template' => 'Review whether a Stocks & Shares Junior ISA would be more suitable than cash.',
                'category' => 'Children\'s Savings',
                'priority' => 'low',
                'scope' => 'portfolio',
                'what_if_impact_type' => 'default',
                'trigger_config' => [
                    'condition' => 'child_has_cash_jisa_with_long_horizon',
                    'years_threshold' => 5,
                ],
                'is_enabled' => true,
                'sort_order' => 92,
                'notes' => 'Triggers when a child has a Cash Junior ISA but years to 18 exceeds threshold.',
            ],

            [
                'key' => 'child_parental_settlement',
                'source' => 'agent',
                'title_template' => 'Parental Settlement Rules May Apply for {child_name}',
                'description_template' => 'Interest on savings gifted to {child_name} exceeds £100 per year — this may be taxable as your income.',
                'action_template' => 'Consider using a Junior ISA (exempt from parental settlement rules) or gifting from grandparents.',
                'category' => 'Children\'s Savings',
                'priority' => 'high',
                'scope' => 'portfolio',
                'what_if_impact_type' => 'tax_optimisation',
                'trigger_config' => [
                    'condition' => 'child_parental_interest_above',
                    'threshold' => 100,
                ],
                'is_enabled' => true,
                'sort_order' => 93,
                'notes' => 'Triggers when interest on parental gifts to a child exceeds £100 annual threshold.',
            ],

            [
                'key' => 'child_turning_18',
                'source' => 'agent',
                'title_template' => '{child_name} Turning 18 Soon',
                'description_template' => '{child_name} turns 18 within 12 months — their Junior ISA will convert to an adult ISA.',
                'action_template' => 'Plan for the Junior ISA to adult ISA conversion and discuss savings goals with {child_name}.',
                'category' => 'Children\'s Savings',
                'priority' => 'medium',
                'scope' => 'portfolio',
                'what_if_impact_type' => 'default',
                'trigger_config' => [
                    'condition' => 'child_turning_18_within',
                    'months_threshold' => 12,
                ],
                'is_enabled' => true,
                'sort_order' => 94,
                'notes' => 'Triggers when a dependent child turns 18 within threshold months.',
            ],

            // ── Spouse Optimisation (2) ──────────────────────────────

            [
                'key' => 'spouse_psa_shift',
                'source' => 'agent',
                'title_template' => 'Optimise Personal Savings Allowance Between Partners',
                'description_template' => 'Your partner has unused Personal Savings Allowance — consider holding savings in their name to reduce your tax liability.',
                'action_template' => 'Transfer savings to your partner\'s name to make use of their unused Personal Savings Allowance.',
                'category' => 'Spouse Optimisation',
                'priority' => 'medium',
                'scope' => 'portfolio',
                'what_if_impact_type' => 'tax_optimisation',
                'trigger_config' => [
                    'condition' => 'spouse_has_unused_psa',
                ],
                'is_enabled' => true,
                'sort_order' => 100,
                'notes' => 'Triggers when user is approaching or exceeding Personal Savings Allowance but spouse has headroom.',
            ],

            [
                'key' => 'spouse_isa_coordination',
                'source' => 'agent',
                'title_template' => 'Coordinate ISA Usage with Your Partner',
                'description_template' => 'Coordinate ISA usage with your partner — one partner\'s allowance is fully used while the other has remaining capacity.',
                'action_template' => 'Direct new savings into the partner\'s ISA that still has allowance remaining.',
                'category' => 'Spouse Optimisation',
                'priority' => 'medium',
                'scope' => 'portfolio',
                'what_if_impact_type' => 'tax_optimisation',
                'trigger_config' => [
                    'condition' => 'spouse_isa_allowance_imbalanced',
                ],
                'is_enabled' => true,
                'sort_order' => 101,
                'notes' => 'Triggers when one partner has fully used ISA allowance and the other has remaining.',
            ],
        ];
    }
}
