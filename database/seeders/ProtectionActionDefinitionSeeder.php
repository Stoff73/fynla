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
                'is_enabled' => true,
                'sort_order' => 40,
                'notes' => 'Passthrough for optimized strategy life insurance recommendations.',
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
                'is_enabled' => true,
                'sort_order' => 50,
                'notes' => 'Passthrough for optimized strategy critical illness recommendations.',
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
                'is_enabled' => true,
                'sort_order' => 60,
                'notes' => 'Passthrough for optimized strategy income protection recommendations.',
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
        ];
    }
}
