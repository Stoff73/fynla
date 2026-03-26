<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\RetirementActionDefinition;
use Illuminate\Database\Seeder;

/**
 * Seed the retirement_action_definitions table with all action types.
 *
 * Seeds 15 agent-sourced and 3 goal-sourced action definitions.
 * Uses updateOrCreate on `key` for idempotency.
 *
 * Run: php artisan db:seed --class=RetirementActionDefinitionSeeder --force
 */
class RetirementActionDefinitionSeeder extends Seeder
{
    public function run(): void
    {
        $definitions = $this->getDefinitions();

        foreach ($definitions as $definition) {
            RetirementActionDefinition::updateOrCreate(
                ['key' => $definition['key']],
                $definition
            );
        }
    }

    private function getDefinitions(): array
    {
        return [
            // ── Agent-sourced actions (7) ──────────────────────────

            [
                'key' => 'employer_match',
                'source' => 'agent',
                'title_template' => 'Maximise Employer Pension Match',
                'description_template' => 'Increase your contribution by {additional_percent}% to maximise employer match on {scheme_name}. This is free money!',
                'action_template' => 'Review your workplace pension contribution level.',
                'category' => 'Employer_match',
                'priority' => 'high',
                'scope' => 'account',
                'what_if_impact_type' => 'contribution',
                'trigger_config' => [
                    'condition' => 'employee_contribution_percent_below',
                    'threshold' => 5.0,
                ],
                'is_enabled' => true,
                'sort_order' => 10,
                'notes' => 'Triggers when employee contribution is below threshold on workplace pensions.',
            ],

            [
                'key' => 'start_contributions',
                'source' => 'agent',
                'title_template' => 'Start Pension Contributions',
                'description_template' => 'Your {scheme_name} has no ongoing contributions. Regular contributions would benefit from compound growth over your remaining years to retirement.',
                'action_template' => 'Set up regular contributions to your pension.',
                'category' => 'Start_contributions',
                'priority' => 'high',
                'scope' => 'account',
                'what_if_impact_type' => 'contribution',
                'trigger_config' => [
                    'condition' => 'zero_contribution_with_fund_value',
                ],
                'is_enabled' => true,
                'sort_order' => 20,
                'notes' => 'Triggers when a pension has fund value but zero contributions.',
            ],

            [
                'key' => 'contribution_increase',
                'source' => 'agent',
                'title_template' => 'Increase Pension Contributions',
                'description_template' => 'To meet your retirement income target, consider contributing an additional {monthly_amount} per month across your pensions.',
                'action_template' => 'Review your budget to find additional pension capacity.',
                'category' => 'Contribution_increase',
                'priority' => 'medium',
                'scope' => 'portfolio',
                'what_if_impact_type' => 'contribution',
                'trigger_config' => [
                    'condition' => 'income_gap_positive_and_additional_contribution_required',
                ],
                'is_enabled' => true,
                'sort_order' => 30,
                'notes' => 'Triggers when income gap exists and additional contributions would help.',
            ],

            [
                'key' => 'tax_relief',
                'source' => 'agent',
                'title_template' => 'Optimise Pension Tax Relief',
                'description_template' => 'As a higher-rate taxpayer, you can save {tax_saving} in tax by contributing an additional {additional_contribution} to your pension.',
                'action_template' => 'Consider increasing pension contributions for tax efficiency.',
                'category' => 'Tax Planning',
                'priority' => 'medium',
                'scope' => 'portfolio',
                'what_if_impact_type' => 'tax_optimisation',
                'trigger_config' => [
                    'condition' => 'higher_rate_taxpayer_below_allowance',
                    'threshold' => 40000,
                ],
                'is_enabled' => true,
                'sort_order' => 40,
                'notes' => 'Triggers for higher-rate taxpayers with contribution capacity below threshold.',
            ],

            [
                'key' => 'annual_allowance_exceeded',
                'source' => 'agent',
                'title_template' => 'Annual Allowance Exceeded',
                'description_template' => 'You have exceeded your annual allowance by {excess_amount}. This may result in tax charges.',
                'action_template' => 'Consult with a financial adviser to minimise tax charges.',
                'category' => 'Tax Planning',
                'priority' => 'critical',
                'scope' => 'portfolio',
                'what_if_impact_type' => 'tax_optimisation',
                'trigger_config' => [
                    'condition' => 'annual_allowance_has_excess',
                ],
                'is_enabled' => true,
                'sort_order' => 5,
                'notes' => 'Triggers when annual allowance has been exceeded.',
            ],

            [
                'key' => 'ni_gaps',
                'source' => 'agent',
                'title_template' => 'National Insurance Gaps',
                'description_template' => 'You need {years_short} more qualifying years but only have {years_until_spa} years until State Pension age. Consider voluntary contributions to fill the gap.',
                'action_template' => 'Check your NI record and consider making voluntary contributions if cost-effective.',
                'category' => 'State Pension',
                'priority' => 'high',
                'scope' => 'portfolio',
                'what_if_impact_type' => 'default',
                'trigger_config' => [
                    'condition' => 'ni_years_wont_reach_required_by_spa',
                ],
                'is_enabled' => true,
                'sort_order' => 50,
                'notes' => 'Triggers when NI years won\'t reach requirement by state pension age.',
            ],

            [
                'key' => 'adjust_retirement_age',
                'source' => 'agent',
                'title_template' => 'Consider Adjusting Retirement Age',
                'description_template' => 'Retiring at {suggested_age} instead of {current_age} would allow additional years of contributions and growth, significantly reducing your income shortfall.',
                'action_template' => 'Review scenarios for retiring at {suggested_age}.',
                'category' => 'Retirement Planning',
                'priority' => 'medium',
                'scope' => 'portfolio',
                'what_if_impact_type' => 'default',
                'trigger_config' => [
                    'condition' => 'income_gap_exceeds_percentage_of_target',
                    'threshold' => 0.10,
                    'max_suggested_age' => 70,
                    'age_increase' => 3,
                ],
                'is_enabled' => true,
                'sort_order' => 60,
                'notes' => 'Triggers when income gap exceeds threshold percentage of target income.',
            ],

            // ── Engine expansion actions (8) ─────────────────────

            [
                'key' => 'salary_sacrifice_available',
                'source' => 'agent',
                'title_template' => 'Consider Salary Sacrifice Arrangement',
                'description_template' => 'Your workplace pension {scheme_name} could benefit from a salary sacrifice arrangement. This could save you {employee_ni_saving} per year in National Insurance contributions, with your employer also saving {employer_ni_saving}.',
                'action_template' => 'Speak to your employer about setting up a salary sacrifice arrangement for your pension contributions.',
                'category' => 'Salary Sacrifice',
                'priority' => 'high',
                'scope' => 'account',
                'what_if_impact_type' => 'contribution',
                'trigger_config' => [
                    'condition' => 'workplace_pension_no_salary_sacrifice',
                ],
                'is_enabled' => true,
                'sort_order' => 15,
                'notes' => 'Triggers for employed users with workplace pensions who could benefit from salary sacrifice.',
            ],

            [
                'key' => 'salary_sacrifice_floor_warning',
                'source' => 'agent',
                'title_template' => 'Salary Sacrifice Floor Warning',
                'description_template' => 'A salary sacrifice arrangement on {scheme_name} would reduce your pay to {post_sacrifice_salary}, which is below the safe floor of {proxy_floor}. This could breach National Minimum Wage or National Living Wage requirements.',
                'action_template' => 'Review your salary sacrifice amount carefully and seek advice before proceeding.',
                'category' => 'Salary Sacrifice',
                'priority' => 'critical',
                'scope' => 'account',
                'what_if_impact_type' => 'default',
                'trigger_config' => [
                    'condition' => 'salary_sacrifice_below_proxy_floor',
                ],
                'is_enabled' => true,
                'sort_order' => 4,
                'notes' => 'Triggers when salary sacrifice would reduce pay below the conservative proxy floor.',
            ],

            [
                'key' => 'auto_enrolment_below_minimum',
                'source' => 'agent',
                'title_template' => 'Pension Contributions Below Auto-Enrolment Minimum',
                'description_template' => 'Your total pension contribution rate of {total_percent}% is below the auto-enrolment minimum of 8% of qualifying earnings. You may be missing out on {shortfall_annual} per year.',
                'action_template' => 'Check with your employer that your pension contributions meet the legal minimum and consider increasing your contribution.',
                'category' => 'Auto-enrolment',
                'priority' => 'high',
                'scope' => 'portfolio',
                'what_if_impact_type' => 'contribution',
                'trigger_config' => [
                    'condition' => 'auto_enrolment_below_minimum_total',
                ],
                'is_enabled' => true,
                'sort_order' => 12,
                'notes' => 'Triggers when total contributions are below the 8% auto-enrolment minimum.',
            ],

            [
                'key' => 'enhanced_annuity_eligible',
                'source' => 'agent',
                'title_template' => 'You May Qualify for Enhanced Annuity Rates',
                'description_template' => 'Based on your health profile, you may qualify for enhanced annuity rates which could provide significantly higher retirement income than standard rates. Enhanced annuities typically pay 15-25% more.',
                'action_template' => 'When approaching retirement, request enhanced annuity quotes from providers — do not accept a standard annuity rate without checking.',
                'category' => 'Annuity',
                'priority' => 'medium',
                'scope' => 'portfolio',
                'what_if_impact_type' => 'default',
                'trigger_config' => [
                    'condition' => 'smoker_or_health_condition_enhanced_annuity',
                ],
                'is_enabled' => true,
                'sort_order' => 55,
                'notes' => 'Triggers when smoker status or health condition qualifies for enhanced annuity rates.',
            ],

            [
                'key' => 'care_costs_not_modelled',
                'source' => 'agent',
                'title_template' => 'Care Costs Not Included in Retirement Plan',
                'description_template' => 'You have not entered any projected care cost assumptions. Care costs can significantly reduce your retirement income — the average annual cost of residential care in the UK exceeds £35,000.',
                'action_template' => 'Add care cost assumptions to your retirement profile for more realistic planning.',
                'category' => 'Care Costs',
                'priority' => 'low',
                'scope' => 'portfolio',
                'what_if_impact_type' => 'default',
                'trigger_config' => [
                    'condition' => 'no_care_costs_entered_over_50',
                    'age_threshold' => 50,
                ],
                'is_enabled' => true,
                'sort_order' => 95,
                'notes' => 'Triggers when user is over 50 and has no care cost assumptions entered.',
            ],

            [
                'key' => 'state_pension_no_forecast',
                'source' => 'agent',
                'title_template' => 'No State Pension Forecast Entered',
                'description_template' => 'You have not entered a State Pension forecast. The State Pension can provide up to {full_state_pension} per year and is a key component of retirement income planning.',
                'action_template' => 'Request your State Pension forecast from gov.uk and add it to your retirement profile.',
                'category' => 'State Pension',
                'priority' => 'medium',
                'scope' => 'portfolio',
                'what_if_impact_type' => 'default',
                'trigger_config' => [
                    'condition' => 'no_state_pension_forecast',
                ],
                'is_enabled' => true,
                'sort_order' => 48,
                'notes' => 'Triggers when no State Pension forecast has been entered.',
            ],

            [
                'key' => 'approaching_decumulation',
                'source' => 'agent',
                'title_template' => 'Approaching Retirement — Review Decumulation Strategy',
                'description_template' => 'You are within {years_to_retirement} years of your target retirement age. Now is the time to review your drawdown strategy, annuity options, and Pension Commencement Lump Sum entitlement.',
                'action_template' => 'Review the decumulation analysis in your retirement dashboard and consider seeking regulated financial advice.',
                'category' => 'Decumulation',
                'priority' => 'high',
                'scope' => 'portfolio',
                'what_if_impact_type' => 'default',
                'trigger_config' => [
                    'condition' => 'within_years_of_retirement',
                    'years_threshold' => 10,
                ],
                'is_enabled' => true,
                'sort_order' => 8,
                'notes' => 'Triggers when user is within the configurable transition period of retirement age.',
            ],

            [
                'key' => 'pension_consolidation_opportunity',
                'source' => 'agent',
                'title_template' => 'Consider Consolidating Your Defined Contribution Pensions',
                'description_template' => 'You have {pension_count} Defined Contribution pensions. Consolidating into fewer schemes could reduce fees, simplify management, and make retirement planning easier.',
                'action_template' => 'Compare fees and features across your pensions before consolidating. Consider seeking advice as some pensions may have valuable guarantees.',
                'category' => 'Pension Management',
                'priority' => 'medium',
                'scope' => 'portfolio',
                'what_if_impact_type' => 'default',
                'trigger_config' => [
                    'condition' => 'multiple_dc_pensions',
                    'min_pension_count' => 3,
                ],
                'is_enabled' => true,
                'sort_order' => 65,
                'notes' => 'Triggers when user has 3 or more DC pensions, suggesting consolidation.',
            ],

            [
                'key' => 'high_pension_total_fees',
                'source' => 'agent',
                'title_template' => 'Review total fees on {pension_name}',
                'description_template' => 'Total annual fees on {pension_name} are {total_fee_percent}% ({annual_fees} per year). Reducing fees could significantly improve your retirement pot over time.',
                'action_template' => 'Compare your pension provider\'s charges with lower-cost alternatives. Even a small reduction compounds significantly over decades.',
                'category' => 'Pension Fees',
                'priority' => 'high',
                'scope' => 'account',
                'what_if_impact_type' => 'default',
                'trigger_config' => [
                    'condition' => 'pension_total_fee_percent_above',
                    'threshold' => 1.0,
                ],
                'is_enabled' => true,
                'sort_order' => 66,
                'notes' => 'Triggers per DC pension when platform + advisor + weighted OCF exceeds threshold.',
            ],

            [
                'key' => 'high_pension_platform_fees',
                'source' => 'agent',
                'title_template' => 'Platform fees are high on {pension_name}',
                'description_template' => 'The platform fee on {pension_name} is {platform_fee_percent}%. Consider transferring to a lower-cost provider.',
                'action_template' => 'Compare platform fees across providers. Platforms like Vanguard and Fidelity offer competitive rates for pension holders.',
                'category' => 'Pension Fees',
                'priority' => 'medium',
                'scope' => 'account',
                'what_if_impact_type' => 'default',
                'trigger_config' => [
                    'condition' => 'pension_platform_fee_percent_above',
                    'threshold' => 0.8,
                ],
                'is_enabled' => true,
                'sort_order' => 67,
                'notes' => 'Triggers per DC pension when platform fee alone exceeds threshold.',
            ],

            [
                'key' => 'high_pension_fund_fees',
                'source' => 'agent',
                'title_template' => 'Fund charges are high on {pension_name}',
                'description_template' => 'The weighted average fund charge on {pension_name} is {weighted_ocf}%. Switching to lower-cost index funds could save {potential_saving} per year.',
                'action_template' => 'Review your fund selection and consider index tracker funds with ongoing charges below 0.25%.',
                'category' => 'Pension Fees',
                'priority' => 'medium',
                'scope' => 'account',
                'what_if_impact_type' => 'default',
                'trigger_config' => [
                    'condition' => 'pension_weighted_ocf_above',
                    'threshold' => 0.5,
                ],
                'is_enabled' => true,
                'sort_order' => 68,
                'notes' => 'Triggers per DC pension when weighted average OCF from holdings exceeds threshold.',
            ],

            // ── Goal-sourced actions (3) ──────────────────────────

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
                'sort_order' => 70,
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
                'sort_order' => 80,
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
                'sort_order' => 90,
                'notes' => 'Triggers when goal deadline is near and progress is below threshold.',
            ],
        ];
    }
}
