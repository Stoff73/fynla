<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\EstateActionDefinition;
use Illuminate\Database\Seeder;

/**
 * Seed the estate_action_definitions table with all action types.
 *
 * Seeds 8 agent-sourced estate planning action definitions.
 * Uses updateOrCreate on `key` for idempotency.
 *
 * Run: php artisan db:seed --class=EstateActionDefinitionSeeder --force
 */
class EstateActionDefinitionSeeder extends Seeder
{
    public function run(): void
    {
        $definitions = $this->getDefinitions();

        foreach ($definitions as $definition) {
            EstateActionDefinition::updateOrCreate(
                ['key' => $definition['key']],
                $definition
            );
        }
    }

    private function getDefinitions(): array
    {
        return [
            // ── Will ─────────────────────────────────────────────────────
            [
                'key' => 'no_will',
                'source' => 'agent',
                'title_template' => 'No Will in Place',
                'description_template' => 'There is no will recorded for this estate. Without a valid will, assets will be distributed according to intestacy rules, which may not reflect the individual\'s wishes.',
                'action_template' => 'Arrange a will with a solicitor to ensure assets are distributed according to your wishes.',
                'category' => 'Will',
                'priority' => 'critical',
                'scope' => 'portfolio',
                'what_if_impact_type' => 'estate_protection',
                'trigger_config' => ['condition' => 'no_will'],
                'is_enabled' => true,
                'sort_order' => 10,
                'notes' => 'Triggers when user has no will record.',
            ],

            // ── Trust ────────────────────────────────────────────────────
            [
                'key' => 'policy_not_in_trust',
                'source' => 'agent',
                'title_template' => 'Life Policy Not Held in Trust',
                'description_template' => 'A life insurance policy worth {policy_value} is not held in trust. Policies outside of a trust form part of the taxable estate and may be subject to Inheritance Tax at 40%.',
                'action_template' => 'Consider placing this policy into a trust to remove it from the taxable estate. This is usually straightforward and incurs no immediate tax charge.',
                'category' => 'Trust',
                'priority' => 'high',
                'scope' => 'account',
                'what_if_impact_type' => 'iht_reduction',
                'trigger_config' => ['condition' => 'policy_not_in_trust'],
                'is_enabled' => true,
                'sort_order' => 20,
                'notes' => 'Triggers when life policy value exceeds nil-rate band and policy is not in trust.',
            ],

            // ── Inheritance Tax ──────────────────────────────────────────
            [
                'key' => 'iht_exceeds_nrb',
                'source' => 'agent',
                'title_template' => 'Estate Value Exceeds Nil-Rate Band',
                'description_template' => 'The estimated estate value of {estate_value} exceeds the nil-rate band of {nrb}. The excess of {excess_amount} may be subject to Inheritance Tax at 40%, resulting in an estimated liability of {iht_liability}.',
                'action_template' => 'Review estate planning strategies such as gifting, trust arrangements, or charitable giving to reduce the potential Inheritance Tax liability.',
                'category' => 'Inheritance Tax',
                'priority' => 'high',
                'scope' => 'portfolio',
                'what_if_impact_type' => 'iht_reduction',
                'trigger_config' => ['condition' => 'iht_exceeds_nrb'],
                'is_enabled' => true,
                'sort_order' => 30,
                'notes' => 'Triggers when total estate value exceeds available nil-rate band (NRB + RNRB if applicable).',
            ],

            // ── Lasting Power of Attorney ────────────────────────────────
            [
                'key' => 'no_lpa',
                'source' => 'agent',
                'title_template' => 'No Lasting Power of Attorney (Financial)',
                'description_template' => 'No financial Lasting Power of Attorney is recorded. Without one, managing financial affairs could require a costly and time-consuming Court of Protection application if you lose mental capacity.',
                'action_template' => 'Consider setting up a Property and Financial Affairs Lasting Power of Attorney while you have capacity.',
                'category' => 'Lasting Power of Attorney',
                'priority' => 'high',
                'scope' => 'portfolio',
                'what_if_impact_type' => 'estate_protection',
                'trigger_config' => ['condition' => 'no_lpa'],
                'is_enabled' => true,
                'sort_order' => 40,
                'notes' => 'Triggers when user has no financial LPA record.',
            ],
            [
                'key' => 'no_lpa_health',
                'source' => 'agent',
                'title_template' => 'No Lasting Power of Attorney (Health)',
                'description_template' => 'No health and welfare Lasting Power of Attorney is recorded. This means medical and care decisions may default to healthcare professionals rather than a trusted person of your choosing.',
                'action_template' => 'Consider setting up a Health and Welfare Lasting Power of Attorney to appoint someone you trust to make decisions about your care.',
                'category' => 'Lasting Power of Attorney',
                'priority' => 'medium',
                'scope' => 'portfolio',
                'what_if_impact_type' => 'estate_protection',
                'trigger_config' => ['condition' => 'no_lpa_health'],
                'is_enabled' => true,
                'sort_order' => 50,
                'notes' => 'Triggers when user has no health/welfare LPA record.',
            ],

            // ── Gifts ────────────────────────────────────────────────────
            [
                'key' => 'gifts_pet_window',
                'source' => 'agent',
                'title_template' => 'Gifts Within Seven-Year Window',
                'description_template' => '{gift_count} gift(s) totalling {gift_total} are within the seven-year Potentially Exempt Transfer window. If the individual were to pass away within this period, these gifts may be subject to taper relief and could affect the Inheritance Tax calculation.',
                'action_template' => 'Maintain records of all gifts and review the potential taper relief implications with your adviser.',
                'category' => 'Inheritance Tax',
                'priority' => 'medium',
                'scope' => 'portfolio',
                'what_if_impact_type' => 'iht_reduction',
                'trigger_config' => ['condition' => 'gifts_pet_window'],
                'is_enabled' => true,
                'sort_order' => 60,
                'notes' => 'Triggers when user has gifts within the 7-year PET window.',
            ],

            // ── Trust Review ─────────────────────────────────────────────
            [
                'key' => 'trust_review_due',
                'source' => 'agent',
                'title_template' => 'Trust Arrangement Review Due',
                'description_template' => 'The trust "{trust_name}" was last reviewed on {last_review_date}. Regular review ensures the trust still meets its objectives and complies with current legislation.',
                'action_template' => 'Schedule a review of this trust arrangement with your solicitor or trustee.',
                'category' => 'Trust',
                'priority' => 'medium',
                'scope' => 'account',
                'what_if_impact_type' => 'estate_protection',
                'trigger_config' => ['condition' => 'trust_review_due', 'months_threshold' => 12],
                'is_enabled' => true,
                'sort_order' => 70,
                'notes' => 'Triggers when a trust has not been reviewed in 12+ months.',
            ],

            // ── Beneficiaries ────────────────────────────────────────────
            [
                'key' => 'beneficiary_review',
                'source' => 'agent',
                'title_template' => 'Beneficiary Designations Review',
                'description_template' => 'Beneficiary designations on pension and insurance policies should be reviewed periodically to ensure they reflect current wishes, especially after life events such as marriage, divorce, or the birth of a child.',
                'action_template' => 'Review nomination of beneficiaries on all pension schemes and insurance policies.',
                'category' => 'Beneficiaries',
                'priority' => 'low',
                'scope' => 'portfolio',
                'what_if_impact_type' => 'estate_protection',
                'trigger_config' => ['condition' => 'beneficiary_review'],
                'is_enabled' => true,
                'sort_order' => 80,
                'notes' => 'Periodic reminder to review beneficiary designations.',
            ],
        ];
    }
}
