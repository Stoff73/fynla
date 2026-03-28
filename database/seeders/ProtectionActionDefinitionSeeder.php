<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\ProtectionActionDefinition;
use Illuminate\Database\Seeder;

class ProtectionActionDefinitionSeeder extends Seeder
{
    public function run(): void
    {
        $definitions = $this->getDefinitions();

        foreach ($definitions as $definition) {
            ProtectionActionDefinition::updateOrCreate(
                ['key' => $definition['key']],
                $definition
            );
        }
    }

    private function getDefinitions(): array
    {
        return [
            // ==========================================
            // Coverage Gap Actions (source: gap)
            // ==========================================

            [
                'key' => 'life_insurance_gap',
                'source' => 'gap',
                'title_template' => 'Increase life insurance cover by {gap_amount}',
                'description_template' => 'Your current life insurance falls short of your calculated need by {gap_amount}. Closing this gap would protect your dependants financially if something were to happen to you.',
                'action_template' => 'Speak to a protection adviser about increasing your life insurance cover to meet your full need of {need_amount}.',
                'category' => 'Life Insurance',
                'priority' => 'critical',
                'scope' => 'portfolio',
                'what_if_impact_type' => 'coverage_increase',
                'trigger_config' => [
                    'condition' => 'gap_exists',
                    'coverage_type' => 'life_insurance',
                    'threshold' => 0,
                ],
                'is_enabled' => true,
                'sort_order' => 10,
                'notes' => 'Triggers when life insurance coverage gap is greater than zero.',
            ],

            [
                'key' => 'critical_illness_gap',
                'source' => 'gap',
                'title_template' => 'Add critical illness cover for {gap_amount}',
                'description_template' => '{description_text}',
                'action_template' => 'Consider obtaining critical illness cover to protect against the financial impact of a serious diagnosis.',
                'category' => 'Critical Illness',
                'priority' => 'high',
                'scope' => 'portfolio',
                'what_if_impact_type' => 'coverage_increase',
                'trigger_config' => [
                    'condition' => 'gap_exists',
                    'coverage_type' => 'critical_illness',
                    'threshold' => 0,
                ],
                'is_enabled' => true,
                'sort_order' => 20,
                'notes' => 'Triggers when critical illness coverage gap is greater than zero.',
            ],

            [
                'key' => 'income_protection_gap',
                'source' => 'gap',
                'title_template' => 'Add income protection for {gap_amount} per month',
                'description_template' => '{description_text}',
                'action_template' => 'Consider income protection insurance to replace lost earnings if you are unable to work due to illness or injury.',
                'category' => 'Income Protection',
                'priority' => 'high',
                'scope' => 'portfolio',
                'what_if_impact_type' => 'coverage_increase',
                'trigger_config' => [
                    'condition' => 'gap_exists',
                    'coverage_type' => 'income_protection',
                    'threshold' => 0,
                ],
                'is_enabled' => true,
                'sort_order' => 30,
                'notes' => 'Triggers when income protection coverage gap is greater than zero.',
            ],

            // ==========================================
            // Strategy Actions (source: agent)
            // ==========================================

            [
                'key' => 'increase_life_cover',
                'source' => 'agent',
                'title_template' => '{action_text}',
                'description_template' => '{details_text}',
                'action_template' => null,
                'category' => 'Life Insurance',
                'priority' => 'high',
                'scope' => 'portfolio',
                'what_if_impact_type' => 'coverage_increase',
                'trigger_config' => [
                    'condition' => 'strategy_recommendation',
                    'category_match' => 'life',
                ],
                'is_enabled' => false,
                'sort_order' => 40,
                'notes' => 'Disabled: duplicates life_insurance_gap action. Enable only if gap action is disabled.',
            ],

            [
                'key' => 'add_critical_illness',
                'source' => 'agent',
                'title_template' => '{action_text}',
                'description_template' => '{details_text}',
                'action_template' => null,
                'category' => 'Critical Illness',
                'priority' => 'high',
                'scope' => 'portfolio',
                'what_if_impact_type' => 'coverage_increase',
                'trigger_config' => [
                    'condition' => 'strategy_recommendation',
                    'category_match' => 'critical',
                ],
                'is_enabled' => false,
                'sort_order' => 50,
                'notes' => 'Disabled: duplicates critical_illness_gap action. Enable only if gap action is disabled.',
            ],

            [
                'key' => 'add_income_protection',
                'source' => 'agent',
                'title_template' => '{action_text}',
                'description_template' => '{details_text}',
                'action_template' => null,
                'category' => 'Income Protection',
                'priority' => 'high',
                'scope' => 'portfolio',
                'what_if_impact_type' => 'coverage_increase',
                'trigger_config' => [
                    'condition' => 'strategy_recommendation',
                    'category_match' => 'income',
                ],
                'is_enabled' => false,
                'sort_order' => 60,
                'notes' => 'Disabled: duplicates income_protection_gap action. Enable only if gap action is disabled.',
            ],

            [
                'key' => 'review_existing_policies',
                'source' => 'agent',
                'title_template' => 'Review your existing protection policies',
                'description_template' => 'You have {policy_count} existing protection policies. A review could identify whether your current cover is still appropriate for your circumstances and whether you could achieve better value.',
                'action_template' => 'Schedule a review of your existing policies to ensure they still meet your needs.',
                'category' => 'Policy Review',
                'priority' => 'medium',
                'scope' => 'portfolio',
                'what_if_impact_type' => 'default',
                'trigger_config' => [
                    'condition' => 'policies_exist_with_gaps',
                    'threshold' => 0,
                ],
                'is_enabled' => true,
                'sort_order' => 70,
                'notes' => 'Triggers when policies exist but coverage gaps remain.',
            ],

            [
                'key' => 'consolidate_policies',
                'source' => 'agent',
                'title_template' => 'Consider consolidating your protection policies',
                'description_template' => 'You have {policy_count} separate protection policies across multiple providers. Consolidating these could simplify your cover and potentially reduce premiums.',
                'action_template' => 'Speak to a protection adviser about whether consolidating your policies would be beneficial.',
                'category' => 'Policy Review',
                'priority' => 'low',
                'scope' => 'portfolio',
                'what_if_impact_type' => 'default',
                'trigger_config' => [
                    'condition' => 'multiple_policies',
                    'threshold' => 3,
                ],
                'is_enabled' => true,
                'sort_order' => 80,
                'notes' => 'Triggers when the user has 3 or more protection policies.',
            ],

            [
                'key' => 'high_premium_cost',
                'source' => 'agent',
                'title_template' => 'Your protection premiums exceed 5% of income',
                'description_template' => 'Your total annual protection premiums of {annual_premiums} represent {premium_percent}% of your gross income. Consider reviewing your policies for better value or adjusting cover levels.',
                'action_template' => 'Review each policy to check whether the cover level and provider are still appropriate. Shopping around at renewal could reduce costs.',
                'category' => 'Premium Review',
                'priority' => 'medium',
                'scope' => 'portfolio',
                'what_if_impact_type' => 'default',
                'trigger_config' => [
                    'condition' => 'premium_percent_of_income_above',
                    'threshold' => 0.05,
                ],
                'is_enabled' => true,
                'sort_order' => 75,
                'notes' => 'Triggers when total annual premiums exceed 5% of gross income.',
            ],

            [
                'key' => 'premium_affordability_warning',
                'source' => 'agent',
                'title_template' => 'Protection premiums may be unaffordable',
                'description_template' => 'Your total annual protection premiums of {annual_premiums} represent {premium_percent}% of your gross income, exceeding the 10% affordability threshold. You may be over-insured or paying above-market rates.',
                'action_template' => 'Urgently review your protection cover. Consider reducing cover on lower-priority policies, increasing deferred periods on income protection, or seeking competitive quotes.',
                'category' => 'Premium Review',
                'priority' => 'high',
                'scope' => 'portfolio',
                'what_if_impact_type' => 'default',
                'trigger_config' => [
                    'condition' => 'premium_percent_of_income_above',
                    'threshold' => 0.10,
                ],
                'is_enabled' => true,
                'sort_order' => 74,
                'notes' => 'Triggers when total annual premiums exceed 10% of gross income (unaffordable).',
            ],

            [
                'key' => 'protection_profile_missing',
                'source' => 'agent',
                'title_template' => 'Complete your protection profile',
                'description_template' => 'Your protection profile is incomplete. Without details about your income, dependants, and existing cover, we cannot accurately calculate your protection needs.',
                'action_template' => 'Visit the Protection section to complete your profile so we can provide personalised recommendations.',
                'category' => 'Setup',
                'priority' => 'critical',
                'scope' => 'portfolio',
                'what_if_impact_type' => 'default',
                'trigger_config' => [
                    'condition' => 'profile_missing',
                ],
                'is_enabled' => true,
                'sort_order' => 5,
                'notes' => 'Triggers when no protection profile exists for the user.',
            ],

            [
                'key' => 'no_policies_warning',
                'source' => 'agent',
                'title_template' => 'You have no protection policies in place',
                'description_template' => 'Our analysis shows you currently have no life insurance, critical illness, or income protection policies. With coverage gaps totalling {total_gap}, your dependants would have no financial safety net if something were to happen to you.',
                'action_template' => 'Prioritise obtaining at least life insurance and income protection cover as a starting point.',
                'category' => 'General',
                'priority' => 'critical',
                'scope' => 'portfolio',
                'what_if_impact_type' => 'default',
                'trigger_config' => [
                    'condition' => 'no_policies_with_gaps',
                ],
                'is_enabled' => true,
                'sort_order' => 3,
                'notes' => 'Triggers when user has no policies and has coverage gaps.',
            ],

            // ==========================================
            // Employer Benefits Actions
            // ==========================================

            [
                'key' => 'dis_reliance_warning',
                'source' => 'agent',
                'title_template' => 'Reduce reliance on death in service benefit',
                'description_template' => 'Over half of your life cover comes from your employer\'s death in service benefit. This cover would be lost if you left employment, were made redundant, or changed jobs. Consider obtaining personal life insurance to reduce this dependency.',
                'action_template' => 'Obtain a personal life insurance policy to supplement your employer\'s death in service benefit and protect against job changes.',
                'category' => 'Employer Benefits',
                'priority' => 'high',
                'scope' => 'portfolio',
                'what_if_impact_type' => 'default',
                'trigger_config' => [
                    'condition' => 'dis_reliance_warning',
                ],
                'is_enabled' => true,
                'sort_order' => 85,
                'notes' => 'Triggers when death in service benefit exceeds 50% of total life cover.',
            ],

            [
                'key' => 'no_employer_benefits_recorded',
                'source' => 'agent',
                'title_template' => 'Record your employer benefits',
                'description_template' => 'You appear to be employed but have not recorded any employer-provided protection benefits such as death in service, group income protection, or group critical illness cover. Recording these helps us calculate your protection gaps more accurately.',
                'action_template' => 'Check with your employer or review your employee benefits handbook, then update your protection profile with any group cover provided.',
                'category' => 'Employer Benefits',
                'priority' => 'medium',
                'scope' => 'portfolio',
                'what_if_impact_type' => 'default',
                'trigger_config' => [
                    'condition' => 'no_employer_benefits_recorded',
                ],
                'is_enabled' => true,
                'sort_order' => 86,
                'notes' => 'Triggers when user has employment income but no employer benefits recorded on profile.',
            ],

            [
                'key' => 'group_ip_any_occupation',
                'source' => 'agent',
                'title_template' => 'Review your group income protection definition',
                'description_template' => 'Your employer\'s group income protection uses an "any occupation" definition. This means you would only receive benefit if you were unable to perform any job, not just your current role. Consider supplementing with a personal "own occupation" policy for stronger protection.',
                'action_template' => 'Speak to a protection adviser about supplementing your group income protection with a personal policy that uses an "own occupation" definition.',
                'category' => 'Employer Benefits',
                'priority' => 'medium',
                'scope' => 'portfolio',
                'what_if_impact_type' => 'default',
                'trigger_config' => [
                    'condition' => 'group_ip_any_occupation',
                ],
                'is_enabled' => true,
                'sort_order' => 87,
                'notes' => 'Triggers when group income protection uses "any occupation" definition.',
            ],

            // ==========================================
            // State Benefits Actions
            // ==========================================

            [
                'key' => 'ip_gap_after_state_benefits',
                'source' => 'agent',
                'title_template' => 'Income protection shortfall after state benefits',
                'description_template' => 'Even after accounting for Statutory Sick Pay ({ssp_total}), there is a significant shortfall in your income protection. Without adequate cover, your household income would drop substantially if you were unable to work through illness or injury.',
                'action_template' => 'Obtain income protection cover to bridge the gap between state benefits and your required income.',
                'category' => 'State Benefits',
                'priority' => 'high',
                'scope' => 'portfolio',
                'what_if_impact_type' => 'coverage_increase',
                'trigger_config' => [
                    'condition' => 'ip_gap_after_state_benefits',
                ],
                'is_enabled' => true,
                'sort_order' => 90,
                'notes' => 'Triggers when income protection gap remains after state benefit offset.',
            ],

            [
                'key' => 'self_employed_no_ip',
                'source' => 'agent',
                'title_template' => 'Critical: Self-employed with no income protection',
                'description_template' => 'As a self-employed person, you have no entitlement to Statutory Sick Pay. If illness or injury prevented you from working, you would have no replacement income at all. Income protection is essential for self-employed individuals.',
                'action_template' => 'Obtain income protection insurance as a priority. Look for an "own occupation" policy with a short deferred period.',
                'category' => 'Income Protection',
                'priority' => 'critical',
                'scope' => 'portfolio',
                'what_if_impact_type' => 'coverage_increase',
                'trigger_config' => [
                    'condition' => 'self_employed_no_ip',
                ],
                'is_enabled' => true,
                'sort_order' => 91,
                'notes' => 'Triggers when self-employed user has no income protection policies and no SSP entitlement.',
            ],

            // ==========================================
            // Life Insurance Actions
            // ==========================================

            [
                'key' => 'policy_not_in_trust',
                'source' => 'agent',
                'title_template' => 'Place your life insurance policy in trust',
                'description_template' => 'Your life insurance policy with {provider} is not held in trust. Without a trust, the payout could form part of your estate and be subject to Inheritance Tax, and payment to beneficiaries may be delayed while probate is granted.',
                'action_template' => 'Contact your insurer to request a trust form. Placing your policy in trust ensures a faster payout and may reduce your Inheritance Tax liability.',
                'category' => 'Life Insurance',
                'priority' => 'medium',
                'scope' => 'portfolio',
                'what_if_impact_type' => 'default',
                'trigger_config' => [
                    'condition' => 'policy_not_in_trust',
                ],
                'is_enabled' => true,
                'sort_order' => 100,
                'notes' => 'Triggers for each life insurance policy where in_trust is false.',
            ],

            [
                'key' => 'policy_not_joint_married',
                'source' => 'agent',
                'title_template' => 'Consider a joint life policy',
                'description_template' => 'You are married but your life insurance policy with {provider} is a single-life policy. A joint life policy can provide cover for both partners, often at a lower combined premium than two separate single-life policies.',
                'action_template' => 'Speak to a protection adviser about whether a joint life policy would be more cost-effective for your situation.',
                'category' => 'Life Insurance',
                'priority' => 'medium',
                'scope' => 'portfolio',
                'what_if_impact_type' => 'default',
                'trigger_config' => [
                    'condition' => 'policy_not_joint_married',
                ],
                'is_enabled' => true,
                'sort_order' => 101,
                'notes' => 'Triggers when married user has a life policy that is not joint life.',
            ],

            [
                'key' => 'policy_expiring_soon',
                'source' => 'agent',
                'title_template' => 'Life insurance policy approaching end of term',
                'description_template' => 'Your life insurance policy with {provider} ends on {end_date}. If you still have dependants or outstanding debts at that point, you will need to arrange replacement cover. Premiums increase with age, so acting sooner will secure better rates.',
                'action_template' => 'Begin researching replacement life insurance cover now, before your current policy ends, to avoid a gap in protection.',
                'category' => 'Life Insurance',
                'priority' => 'high',
                'scope' => 'portfolio',
                'what_if_impact_type' => 'default',
                'trigger_config' => [
                    'condition' => 'policy_expiring_soon',
                    'months_threshold' => 24,
                ],
                'is_enabled' => true,
                'sort_order' => 102,
                'notes' => 'Triggers when a life insurance policy end date is within 24 months.',
            ],

            [
                'key' => 'policy_expired',
                'source' => 'agent',
                'title_template' => 'Life insurance policy has expired',
                'description_template' => 'Your life insurance policy with {provider} ended on {end_date}. You currently have no cover from this policy. If you still have protection needs, arrange replacement cover urgently.',
                'action_template' => 'Obtain replacement life insurance cover immediately to close this gap in your protection.',
                'category' => 'Life Insurance',
                'priority' => 'critical',
                'scope' => 'portfolio',
                'what_if_impact_type' => 'default',
                'trigger_config' => [
                    'condition' => 'policy_expired',
                ],
                'is_enabled' => true,
                'sort_order' => 103,
                'notes' => 'Triggers when a life insurance policy end date is in the past.',
            ],

            [
                'key' => 'mortgage_no_decreasing_term',
                'source' => 'agent',
                'title_template' => 'Consider decreasing term life insurance for your mortgage',
                'description_template' => 'You have an outstanding mortgage of {mortgage_amount} but no decreasing term life insurance policy linked to it. A decreasing term policy is specifically designed to cover a repayment mortgage and is typically cheaper than level term cover.',
                'action_template' => 'Speak to a protection adviser about a decreasing term life insurance policy to match your mortgage repayment schedule.',
                'category' => 'Life Insurance',
                'priority' => 'high',
                'scope' => 'portfolio',
                'what_if_impact_type' => 'coverage_increase',
                'trigger_config' => [
                    'condition' => 'mortgage_no_decreasing_term',
                ],
                'is_enabled' => true,
                'sort_order' => 104,
                'notes' => 'Triggers when user has a mortgage but no decreasing term or mortgage protection life policy.',
            ],

            // ==========================================
            // Income Protection Actions
            // ==========================================

            [
                'key' => 'ip_any_occupation_definition',
                'source' => 'agent',
                'title_template' => 'Upgrade your income protection to "own occupation"',
                'description_template' => 'Your income protection policy uses an "any occupation" definition, which means you would only receive benefit if unable to perform any job whatsoever. An "own occupation" policy pays out if you cannot perform your specific job, providing much stronger protection.',
                'action_template' => 'Review your income protection policy and consider switching to one with an "own occupation" definition for better protection.',
                'category' => 'Income Protection',
                'priority' => 'medium',
                'scope' => 'portfolio',
                'what_if_impact_type' => 'default',
                'trigger_config' => [
                    'condition' => 'ip_any_occupation_definition',
                ],
                'is_enabled' => true,
                'sort_order' => 110,
                'notes' => 'Triggers when an income protection policy uses "any occupation" definition.',
            ],

            [
                'key' => 'ip_short_benefit_period',
                'source' => 'agent',
                'title_template' => 'Consider extending your income protection benefit period',
                'description_template' => 'Your income protection policy has a benefit period of only {benefit_months} months. A longer benefit period, ideally until your retirement age, would provide better protection against prolonged illness or disability.',
                'action_template' => 'Review whether extending your income protection benefit period to retirement age would be affordable for more comprehensive cover.',
                'category' => 'Income Protection',
                'priority' => 'medium',
                'scope' => 'portfolio',
                'what_if_impact_type' => 'default',
                'trigger_config' => [
                    'condition' => 'ip_short_benefit_period',
                    'months_threshold' => 24,
                ],
                'is_enabled' => true,
                'sort_order' => 111,
                'notes' => 'Triggers when income protection benefit period is less than 24 months.',
            ],

            [
                'key' => 'ip_long_deferred_period',
                'source' => 'agent',
                'title_template' => 'Review your income protection deferred period',
                'description_template' => 'Your income protection policy has a deferred period of {deferred_weeks} weeks. Without employer sick pay to bridge this gap, you could face a significant period with no income before your policy pays out.',
                'action_template' => 'Consider reducing your deferred period or ensuring you have sufficient savings to cover the waiting period.',
                'category' => 'Income Protection',
                'priority' => 'high',
                'scope' => 'portfolio',
                'what_if_impact_type' => 'default',
                'trigger_config' => [
                    'condition' => 'ip_long_deferred_period',
                    'weeks_threshold' => 26,
                ],
                'is_enabled' => true,
                'sort_order' => 112,
                'notes' => 'Triggers when income protection deferred period exceeds 26 weeks and no employer sick pay cover.',
            ],

            // ==========================================
            // Critical Illness Actions
            // ==========================================

            [
                'key' => 'no_ci_with_mortgage',
                'source' => 'agent',
                'title_template' => 'Add critical illness cover for your mortgage',
                'description_template' => 'You have an outstanding mortgage of {mortgage_amount} but no critical illness cover. A serious diagnosis could prevent you from working, making mortgage payments unaffordable. Critical illness cover provides a lump sum to clear debts or fund adaptations.',
                'action_template' => 'Consider critical illness cover of at least your outstanding mortgage balance to protect against serious illness.',
                'category' => 'Critical Illness',
                'priority' => 'high',
                'scope' => 'portfolio',
                'what_if_impact_type' => 'coverage_increase',
                'trigger_config' => [
                    'condition' => 'no_ci_with_mortgage',
                ],
                'is_enabled' => true,
                'sort_order' => 120,
                'notes' => 'Triggers when user has a mortgage but no critical illness cover.',
            ],

            [
                'key' => 'ci_combined_risk',
                'source' => 'agent',
                'title_template' => 'Be aware your life and critical illness cover is combined',
                'description_template' => 'Your policy with {provider} combines life insurance and critical illness cover. This means only one claim can be paid: if you claim for critical illness, the life cover element ends. Consider whether separate policies would provide better overall protection.',
                'action_template' => 'Review whether separate life and critical illness policies would better suit your needs, particularly if you have dependants.',
                'category' => 'Critical Illness',
                'priority' => 'low',
                'scope' => 'portfolio',
                'what_if_impact_type' => 'default',
                'trigger_config' => [
                    'condition' => 'ci_combined_risk',
                ],
                'is_enabled' => true,
                'sort_order' => 121,
                'notes' => 'Triggers when a critical illness policy type is "combined" (life + critical illness).',
            ],

            // ==========================================
            // Dependants and Spouse Actions
            // ==========================================

            [
                'key' => 'dependants_no_life_cover',
                'source' => 'agent',
                'title_template' => 'Urgent: You have dependants but no life cover',
                'description_template' => 'You have {dependant_count} dependant(s) relying on your income, but no life insurance in place. If something were to happen to you, your family would face serious financial hardship without any replacement income or lump sum to draw on.',
                'action_template' => 'Obtain life insurance cover urgently. As a starting point, consider cover of at least ten times your annual income to protect your dependants.',
                'category' => 'Life Insurance',
                'priority' => 'critical',
                'scope' => 'portfolio',
                'what_if_impact_type' => 'coverage_increase',
                'trigger_config' => [
                    'condition' => 'dependants_no_life_cover',
                ],
                'is_enabled' => true,
                'sort_order' => 130,
                'notes' => 'Triggers when user has dependants but zero life insurance coverage.',
            ],

            [
                'key' => 'education_funding_gap',
                'source' => 'agent',
                'title_template' => 'Education funding shortfall of {gap_amount}',
                'description_template' => 'If something were to happen to you, there would be a shortfall of {gap_amount} to fund your children\'s education through to age twenty-one. Consider whether your current life insurance cover is sufficient to meet this need.',
                'action_template' => 'Ensure your life insurance cover accounts for your children\'s future education costs.',
                'category' => 'Life Insurance',
                'priority' => 'medium',
                'scope' => 'portfolio',
                'what_if_impact_type' => 'coverage_increase',
                'trigger_config' => [
                    'condition' => 'education_funding_gap',
                ],
                'is_enabled' => true,
                'sort_order' => 131,
                'notes' => 'Triggers when education funding gap exists for dependant children.',
            ],

            [
                'key' => 'non_earning_spouse_no_cover',
                'source' => 'agent',
                'title_template' => 'Consider life cover for your non-earning spouse',
                'description_template' => 'Your spouse does not have earned income but provides valuable services such as childcare and household management. If they were no longer able to do so, you would face significant replacement costs. Consider life insurance for your spouse to cover these expenses.',
                'action_template' => 'Speak to a protection adviser about obtaining life cover for your spouse, particularly if they provide childcare for your dependants.',
                'category' => 'Life Insurance',
                'priority' => 'medium',
                'scope' => 'portfolio',
                'what_if_impact_type' => 'default',
                'trigger_config' => [
                    'condition' => 'non_earning_spouse_no_cover',
                ],
                'is_enabled' => true,
                'sort_order' => 132,
                'notes' => 'Triggers when spouse has no earned income, user has dependants, but spouse has no separate cover.',
            ],
        ];
    }
}
