<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\RetirementActionDefinition;
use Illuminate\Database\Seeder;

/**
 * Seed the retirement_action_definitions table with all action types.
 *
 * Seeds 7 agent-sourced and 3 goal-sourced action definitions.
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
