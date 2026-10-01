<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\RetirementActionDefinition;
use Illuminate\Database\Seeder;

/**
 * Seed the retirement_action_definitions table with all action types.
 *
 * Seeds the agent-sourced and goal-sourced action definitions and the
 * strategy catalogue rows.
 * Uses updateOrCreate on `key` for idempotency.
 *
 * Run: php artisan db:seed --class=RetirementActionDefinitionSeeder --force
 */
class RetirementActionDefinitionSeeder extends Seeder
{
    public function run(): void
    {
        $definitions = array_merge($this->getDefinitions(), $this->getStrategyDefinitions());

        foreach ($definitions as $definition) {
            RetirementActionDefinition::updateOrCreate(
                ['key' => $definition['key']],
                $definition
            );
        }
    }

    /**
     * source='strategy' catalogue rows for the cross-module plan composer.
     * strategy_type matches the slugs RetirementRecommendationAdapter emits;
     * required_data keys are drawn from ModuleAvailabilityProvider::forModule('retirement').
     *
     * @return array<int, array<string, mixed>>
     */
    private function getStrategyDefinitions(): array
    {
        $rows = [
            [
                'strategy_type' => 'increase_pension_contribution',
                'category' => 'Contribution_increase',
                'priority' => 'high',
                'claim_tier' => 'mechanical',
                'required_data' => ['dc_pension_exists'],
                'sequencing' => ['do_before' => [], 'conflicts_with' => []],
            ],
            [
                'strategy_type' => 'salary_sacrifice_pension',
                'category' => 'Salary Sacrifice',
                'priority' => 'high',
                'claim_tier' => 'mechanical',
                'required_data' => ['dc_pension_exists'],
                'sequencing' => ['do_before' => [], 'conflicts_with' => []],
            ],
            [
                'strategy_type' => 'carry_forward_unused_allowance',
                'category' => 'Tax Planning',
                'priority' => 'medium',
                'claim_tier' => 'judgement',
                'required_data' => ['pension_input_history'],
                'sequencing' => ['do_before' => [], 'conflicts_with' => []],
            ],
            [
                'strategy_type' => 'plan_retirement_income',
                'category' => 'Retirement Planning',
                'priority' => 'medium',
                'claim_tier' => 'judgement',
                'required_data' => ['retirement_age_set'],
                'sequencing' => ['do_before' => [], 'conflicts_with' => []],
            ],
        ];

        return array_map(static function (array $row): array {
            return array_merge([
                'key' => 'strategy_'.$row['strategy_type'],
                'source' => 'strategy',
                'title_template' => $row['strategy_type'],
                'description_template' => 'Computed by the retirement plan source.',
                'action_template' => null,
                'scope' => 'portfolio',
                'what_if_impact_type' => 'contribution',
                'trigger_config' => [],
                'is_enabled' => true,
                'sort_order' => 100,
                'notes' => 'Strategy catalogue row for the cross-module plan composer.',
            ], $row);
        }, $rows);
    }

    private function getDefinitions(): array
    {
        return [
            // ── Agent-sourced actions ─────────────────────────────

            [
                'key' => 'employer_match',
                'source' => 'agent',
                'title_template' => 'Check your employer match on {scheme_name}',
                'description_template' => 'You pay {employee_percent}% of your salary into {scheme_name}. Your employer may add more if you pay more; the scheme\'s rules say how much.',
                'action_template' => 'Ask your employer how their contributions to {scheme_name} change with yours.',
                'category' => 'Employer_match',
                'priority' => 'high',
                'scope' => 'account',
                'what_if_impact_type' => 'contribution',
                'trigger_config' => [
                    'condition' => 'employee_contribution_percent_below',
                    // Threshold: the auto-enrolment minimum employee share, from tax config.
                ],
                'is_enabled' => true,
                'sort_order' => 10,
                'notes' => 'Triggers when employee contribution is below threshold on workplace pensions.',
            ],

            [
                'key' => 'pension_value_unknown',
                'source' => 'agent',
                'title_template' => 'Add the current value of your {scheme_name}',
                'description_template' => 'You told Fyn about your {scheme_name} but not what it is worth today. A rough figure from your latest annual statement or provider app lets your retirement projection use the real pot.',
                'action_template' => 'Add the current value from your latest statement.',
                'category' => 'Pension_value',
                'priority' => 'medium',
                'scope' => 'account',
                'what_if_impact_type' => 'contribution',
                'trigger_config' => [
                    'condition' => 'dc_pension_value_missing',
                ],
                'is_enabled' => true,
                'sort_order' => 15,
                'notes' => 'Triggers for each DC pension whose current_fund_value is not entered (0). CSJ 2026-09-15: a value not known at onboarding is fine — it is asked for here.',
            ],

            [
                'key' => 'start_contributions',
                'source' => 'agent',
                'title_template' => 'Start paying into {scheme_name}',
                'description_template' => 'Nothing is being paid into your {scheme_name}.',
                'action_template' => 'Set up regular payments into your pension.',
                'category' => 'Start_contributions',
                'priority' => 'high',
                'scope' => 'account',
                'what_if_impact_type' => 'contribution',
                'trigger_config' => [
                    'condition' => 'zero_contribution_with_fund_value',
                ],
                'is_enabled' => true,
                'sort_order' => 20,
                'notes' => 'Folded (CSJ 2026-10-01): a reason on retirement_income_position, never a card. Triggers when a pension has fund value but zero contributions.',
            ],

            [
                'key' => 'contribution_increase',
                'source' => 'agent',
                'title_template' => 'Pay more into your pensions',
                'description_template' => 'Your projected retirement income is below your target.',
                'action_template' => 'Work out what you could pay in each month.',
                'category' => 'Contribution_increase',
                'priority' => 'medium',
                'scope' => 'portfolio',
                'what_if_impact_type' => 'contribution',
                'trigger_config' => [
                    'condition' => 'income_gap_positive_and_additional_contribution_required',
                ],
                'is_enabled' => true,
                'sort_order' => 30,
                'notes' => 'Folded (CSJ 2026-10-01): a reason on retirement_income_position, never a card. Triggers when income gap exists and additional contributions would help.',
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
                'is_enabled' => false,
                'sort_order' => 40,
                'notes' => 'Disabled (CSJ 2026-10-01, D1): the Tax plan carries this action (pension_tax_relief, pa_taper_rescue and additional_rate_avoidance), with the affordability check and approved how-tos. Two engines for one piece of advice broke Rule 20.',
            ],

            [
                'key' => 'annual_allowance_exceeded',
                'source' => 'agent',
                'title_template' => 'You have paid {excess_amount} more into pensions than your allowance this year',
                'description_template' => 'That is more than your Annual Allowance and the unused allowance from earlier years recorded. You or your pension provider must pay tax on the excess, and you report it on a Self Assessment tax return.',
                'action_template' => 'Check whether unused allowance from the last {carry_forward_years} tax years is all recorded.',
                'category' => 'Tax Planning',
                'priority' => 'critical',
                'scope' => 'portfolio',
                'what_if_impact_type' => 'tax_optimisation',
                'trigger_config' => [
                    'condition' => 'annual_allowance_has_excess',
                ],
                'is_enabled' => true,
                'sort_order' => 5,
                'notes' => 'Kept (2026-10-01): the Tax plan has no card for an allowance already exceeded (tapered_annual_allowance covers only the taper), so D1 leaves this one here. Source: https://www.gov.uk/tax-on-your-private-pension/annual-allowance.',
            ],

            [
                'key' => 'ni_gaps',
                'source' => 'agent',
                'title_template' => 'Fill the gaps in your National Insurance record',
                'description_template' => 'You need {years_short} more qualifying years for the full State Pension and have {years_until_spa} years until State Pension age. Voluntary contributions can fill some gaps from earlier years.',
                'action_template' => 'Check your National Insurance record and what filling a gap would cost.',
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
                'title_template' => 'Think about retiring later',
                'description_template' => 'Retiring at {suggested_age} instead of {current_age} gives more years of saving and growth.',
                'action_template' => 'Compare your income at {suggested_age} on the Retirement page.',
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
                'notes' => 'Folded (CSJ 2026-10-01): a reason on retirement_income_position, never a card. Triggers when income gap exceeds threshold percentage of target income.',
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
                'is_enabled' => false,
                'sort_order' => 15,
                'notes' => 'Disabled (CSJ 2026-10-01, D1): the Tax plan carries this action (salary_sacrifice_ni), with the affordability check and approved how-tos. Two engines for one piece of advice broke Rule 20.',
            ],

            [
                'key' => 'salary_sacrifice_floor_warning',
                'source' => 'agent',
                'title_template' => 'Check salary sacrifice on {scheme_name} against the National Minimum Wage',
                'description_template' => 'Salary sacrifice on {scheme_name} would leave pay of {post_sacrifice_salary}. A salary sacrifice arrangement must not take cash pay below the National Minimum Wage, which depends on your age and hours.',
                'action_template' => 'Check the amount with your employer before you agree to it.',
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
                'title_template' => 'Your pension contributions are below the auto-enrolment minimum',
                'description_template' => 'You and your employer pay {total_percent}% in total. The auto-enrolment minimum is {minimum_percent}% of qualifying earnings, so about {shortfall_annual} a year is missing.',
                'action_template' => 'Ask your employer to check your contributions against the minimum.',
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
                'title_template' => 'Ask for enhanced annuity quotes',
                'description_template' => 'What an annuity pays can depend on your health, so an annuity provider may offer you more than its standard rates.',
                'action_template' => 'Give every provider your health details when you ask for annuity quotes.',
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
                'title_template' => 'Your retirement plan leaves out care costs',
                'description_template' => 'You have not added any care costs to your retirement plan.',
                'action_template' => 'Add an amount for care to your retirement plan.',
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
                'title_template' => 'Add your State Pension forecast',
                'description_template' => 'You have not added a State Pension forecast. The full new State Pension is {full_state_pension} a year.',
                'action_template' => 'Get your forecast from gov.uk and add it on the Retirement page.',
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
                'title_template' => 'Plan how you will take your pension',
                'description_template' => 'You are {years_to_retirement} years from your target retirement age. This is the time to compare drawdown, an annuity and your tax-free lump sum.',
                'action_template' => 'Compare the ways of taking your pension on the Retirement page.',
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
                'title_template' => 'Think about combining your pensions',
                'description_template' => 'You have {pension_count} defined contribution pensions. Combining some could mean fewer charges and less to keep track of.',
                'action_template' => 'Check each pension for guarantees and exit charges before moving it.',
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
                'title_template' => 'Review the charges on {pension_name}',
                'description_template' => 'Total charges on {pension_name} are {total_fee_percent}% a year ({annual_fees}).',
                'action_template' => 'Compare these charges with what other providers charge.',
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
                'notes' => 'Folded (CSJ 2026-10-01): a reason on pension_charges_review, never a card. Triggers per DC pension when platform + advisor + weighted OCF exceeds threshold.',
            ],

            [
                'key' => 'high_pension_platform_fees',
                'source' => 'agent',
                'title_template' => 'Review the platform fee on {pension_name}',
                'description_template' => 'The platform fee on {pension_name} is {platform_fee_percent}% a year.',
                'action_template' => 'Compare this fee with what other providers charge.',
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
                'notes' => 'Folded (CSJ 2026-10-01): a reason on pension_charges_review, never a card. Triggers per DC pension when platform fee alone exceeds threshold.',
            ],

            [
                'key' => 'high_pension_fund_fees',
                'source' => 'agent',
                'title_template' => 'Review the fund charges on {pension_name}',
                'description_template' => 'The funds in {pension_name} charge {weighted_ocf}% a year on average.',
                'action_template' => 'Compare these charges with other funds your provider offers.',
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
                'notes' => 'Folded (CSJ 2026-10-01): a reason on pension_charges_review, never a card. Triggers per DC pension when weighted average OCF from holdings exceeds threshold.',
            ],

            // ── Consolidated cards (CSJ 2026-10-01, D2 and D3) ────

            [
                'key' => 'retirement_income_position',
                'source' => 'agent',
                'title_template' => 'Your retirement income is about {shortfall} a year short of your target',
                'description_template' => '{summary}',
                'action_template' => 'See what would close the gap.',
                'category' => 'Retirement Income',
                'priority' => 'high',
                'scope' => 'portfolio',
                'what_if_impact_type' => 'contribution',
                'trigger_config' => ['condition' => 'income_position'],
                'is_enabled' => true,
                'sort_order' => 25,
                'notes' => 'CSJ 2026-10-01 (D2): one card for the shortfall the Retirement page shows (RetirementIncomePosition). Folds contribution_increase, adjust_retirement_age and start_contributions as reasons.',
            ],

            [
                'key' => 'pension_charges_review',
                'source' => 'agent',
                'title_template' => 'Review the charges on {pension_name}',
                'description_template' => '{pension_name} has {charges_list}.',
                'action_template' => 'Compare these charges with what other providers charge.',
                'category' => 'Pension Fees',
                'priority' => 'medium',
                'scope' => 'account',
                'what_if_impact_type' => 'default',
                'trigger_config' => ['condition' => 'charges_position'],
                'is_enabled' => true,
                'sort_order' => 66,
                'notes' => 'CSJ 2026-10-01 (D3): one card per pension. Folds high_pension_total_fees, high_pension_platform_fees and high_pension_fund_fees as reasons.',
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
