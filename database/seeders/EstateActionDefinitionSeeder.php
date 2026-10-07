<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\EstateActionDefinition;
use Illuminate\Database\Seeder;

/**
 * Seed the estate_action_definitions table with all action types.
 *
 * Seeds the agent-sourced estate cards (six enabled; five replaced rows kept
 * disabled, item 9) and the four strategy catalogue rows.
 * Uses updateOrCreate on `key` for idempotency.
 *
 * Run: php artisan db:seed --class=EstateActionDefinitionSeeder --force
 */
class EstateActionDefinitionSeeder extends Seeder
{
    public function run(): void
    {
        $definitions = array_merge($this->getDefinitions(), $this->getStrategyDefinitions());

        foreach ($definitions as $definition) {
            EstateActionDefinition::updateOrCreate(
                ['key' => $definition['key']],
                $definition
            );
        }
    }

    /**
     * source='strategy' catalogue rows for the cross-module plan composer.
     * strategy_type matches the slugs EstateRecommendationAdapter emits when
     * definition_key is absent; required_data keys are drawn from
     * ModuleAvailabilityProvider::forModule('estate').
     *
     * Semantic note on required_data and locking:
     * A strategy is "locked" (surfaced as an unlock prompt) when its required_data
     * are NOT all true in the availability map. So required_data = ['will_in_place']
     * means the strategy locks when the user has NO will — i.e. when we most want
     * to surface it, the data point being absent IS the signal. This mirrors how
     * Retirement treats required_data and is intentional.
     *
     * @return array<int, array<string, mixed>>
     */
    private function getStrategyDefinitions(): array
    {
        $rows = [
            [
                'strategy_type' => 'make_a_will',
                'category' => 'Will',
                'priority' => 'high',
                'claim_tier' => 'mechanical',
                'required_data' => ['will_in_place'],
                'sequencing' => ['do_before' => ['register_lpa', 'reduce_iht_exposure'], 'conflicts_with' => []],
            ],
            [
                'strategy_type' => 'register_lpa',
                'category' => 'Lasting Power of Attorney',
                'priority' => 'high',
                'claim_tier' => 'mechanical',
                'required_data' => ['lpa_registered'],
                'sequencing' => ['do_before' => [], 'conflicts_with' => []],
            ],
            [
                'strategy_type' => 'reduce_iht_exposure',
                'category' => 'Inheritance Tax',
                'priority' => 'medium',
                'claim_tier' => 'judgement',
                'required_data' => ['estate_value_known'],
                'sequencing' => ['do_before' => [], 'conflicts_with' => []],
            ],
            [
                'strategy_type' => 'gift_to_reduce_estate',
                'category' => 'Inheritance Tax',
                'priority' => 'medium',
                'claim_tier' => 'judgement',
                'required_data' => ['estate_value_known'],
                'sequencing' => ['do_before' => [], 'conflicts_with' => []],
            ],
        ];

        return array_map(static function (array $row): array {
            return array_merge([
                'key' => 'strategy_'.$row['strategy_type'],
                'source' => 'strategy',
                'title_template' => $row['strategy_type'],
                'description_template' => 'Computed by the estate plan source.',
                'action_template' => null,
                'scope' => 'portfolio',
                'what_if_impact_type' => 'estate_protection',
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
            // ── Will ─────────────────────────────────────────────────────
            [
                'key' => 'no_will',
                'source' => 'agent',
                'title_template' => 'You have no will recorded',
                'description_template' => 'The intestacy rules would decide who inherits your estate (Administration of Estates Act 1925, section 46).',
                'action_template' => 'Make a will, then record it on the Estate page.',
                'category' => 'Will',
                'priority' => 'critical',
                'scope' => 'portfolio',
                'what_if_impact_type' => 'estate_protection',
                'trigger_config' => ['condition' => 'no_will'],
                'is_enabled' => true,
                'sort_order' => 10,
                'notes' => 'Triggers when the user has no will recorded, or recorded that they have none.',
            ],

            // ── Trust ────────────────────────────────────────────────────
            [
                'key' => 'policy_not_in_trust',
                'source' => 'agent',
                'title_template' => 'Life Policy Not Held in Trust',
                'description_template' => 'A life insurance policy worth {policy_value} is not held in trust.',
                'action_template' => null,
                'category' => 'Trust',
                'priority' => 'high',
                'scope' => 'account',
                'what_if_impact_type' => 'iht_reduction',
                'trigger_config' => ['condition' => 'policy_not_in_trust'],
                'is_enabled' => false,
                'sort_order' => 20,
                'notes' => 'Disabled (item 9 D2, CSJ 2026-10-07): the life policy trust card is Protection\'s policy_not_in_trust alone.',
            ],

            // ── Inheritance Tax ──────────────────────────────────────────
            [
                'key' => 'iht_exceeds_nrb',
                'source' => 'agent',
                'title_template' => 'Estate Value Exceeds Nil-Rate Band',
                'description_template' => 'The estimated estate value of {estate_value} exceeds the allowances of {nrb}.',
                'action_template' => null,
                'category' => 'Inheritance Tax',
                'priority' => 'high',
                'scope' => 'portfolio',
                'what_if_impact_type' => 'iht_reduction',
                'trigger_config' => ['condition' => 'iht_exceeds_nrb'],
                'is_enabled' => false,
                'sort_order' => 30,
                'notes' => 'Disabled (item 9 D1, CSJ 2026-10-07): replaced by iht_position.',
            ],
            [
                'key' => 'iht_position',
                'source' => 'agent',
                'title_template' => 'Your Inheritance Tax would be {iht_liability}',
                'description_template' => '{estate_text} of {net_estate} is above its allowances of {allowances}.',
                'action_template' => 'See the steps that reduce or pay it.',
                'category' => 'Inheritance Tax',
                'priority' => 'high',
                'scope' => 'portfolio',
                'what_if_impact_type' => 'iht_reduction',
                'trigger_config' => ['condition' => 'iht_position'],
                'is_enabled' => true,
                'sort_order' => 30,
                'notes' => 'Item 9 D1: the tax today and the Estate plan page\'s steps with their figures (EstateAgent::generateRecommendations). Fires when tax is due.',
            ],

            // ── Lasting Power of Attorney ────────────────────────────────
            [
                'key' => 'no_lpa',
                'source' => 'agent',
                'title_template' => 'No registered Lasting Power of Attorney',
                'description_template' => 'One only exists once it is registered (Mental Capacity Act 2005, section 9).',
                'action_template' => 'Make and register one, or record the one you have.',
                'category' => 'Lasting Power of Attorney',
                'priority' => 'high',
                'scope' => 'portfolio',
                'what_if_impact_type' => 'estate_protection',
                'trigger_config' => ['condition' => 'lpa_not_registered'],
                'is_enabled' => true,
                'sort_order' => 40,
                'notes' => 'Item 9 D3: one card naming each kind (property and financial affairs, health and welfare) not recorded as registered.',
            ],
            [
                'key' => 'no_lpa_health',
                'source' => 'agent',
                'title_template' => 'No Lasting Power of Attorney (Health)',
                'description_template' => 'No health and welfare Lasting Power of Attorney is recorded.',
                'action_template' => null,
                'category' => 'Lasting Power of Attorney',
                'priority' => 'medium',
                'scope' => 'portfolio',
                'what_if_impact_type' => 'estate_protection',
                'trigger_config' => ['condition' => 'no_lpa_health'],
                'is_enabled' => false,
                'sort_order' => 50,
                'notes' => 'Disabled (item 9 D3, CSJ 2026-10-07): folded into no_lpa.',
            ],

            // ── Gifts ────────────────────────────────────────────────────
            [
                'key' => 'gifts_pet_window',
                'source' => 'agent',
                'title_template' => 'Gifts still inside the seven years',
                'description_template' => '{gifts_text} totalling {gift_total}; the first leaves the seven years on {next_clear_date}.',
                'action_template' => 'Keep a record of each gift.',
                'category' => 'Inheritance Tax',
                'priority' => 'medium',
                'scope' => 'portfolio',
                'what_if_impact_type' => 'iht_reduction',
                'trigger_config' => ['condition' => 'gifts_pet_window'],
                'is_enabled' => true,
                'sort_order' => 60,
                'notes' => 'Potentially Exempt Transfers and chargeable lifetime transfers within seven years, from FailedGiftTaxCalculator; exempt gifts never count (item 9).',
            ],

            // ── Trust ────────────────────────────────────────────────────
            [
                'key' => 'trust_review_due',
                'source' => 'agent',
                'title_template' => 'Trust Arrangement Review Due',
                'description_template' => 'The trust "{trust_name}" is due a review.',
                'action_template' => null,
                'category' => 'Trust',
                'priority' => 'medium',
                'scope' => 'account',
                'what_if_impact_type' => 'estate_protection',
                'trigger_config' => ['condition' => 'trust_review_due', 'months_threshold' => 12],
                'is_enabled' => false,
                'sort_order' => 70,
                'notes' => 'Disabled (item 9 D5, CSJ 2026-10-07): the 12-month review had no source; replaced by trust_anniversary_due.',
            ],
            [
                'key' => 'trust_anniversary_due',
                'source' => 'agent',
                'title_template' => '{trust_name} reaches ten years on {anniversary_date}',
                'description_template' => 'On {anniversary_date} {trust_name} reaches its ten-year anniversary, when the trust pays Inheritance Tax of up to {max_rate_percent}% of what it holds (Inheritance Tax Act 1984, sections 64 and 66).',
                'action_template' => 'Value what the trust holds before the anniversary.',
                'category' => 'Trust',
                'priority' => 'medium',
                'scope' => 'account',
                'what_if_impact_type' => 'estate_protection',
                'trigger_config' => ['condition' => 'trust_anniversary_due', 'years_before' => 2],
                'is_enabled' => true,
                'sort_order' => 70,
                'notes' => 'Item 9 D5: a relevant property trust within years_before of its ten-year anniversary (TrustService::calculateNextPeriodicChargeDate).',
            ],

            // ── Beneficiaries ────────────────────────────────────────────
            [
                'key' => 'beneficiary_review',
                'source' => 'agent',
                'title_template' => 'Beneficiary Designations Review',
                'description_template' => 'Review beneficiary designations on pensions and policies.',
                'action_template' => null,
                'category' => 'Beneficiaries',
                'priority' => 'low',
                'scope' => 'portfolio',
                'what_if_impact_type' => 'estate_protection',
                'trigger_config' => ['condition' => 'beneficiary_review'],
                'is_enabled' => false,
                'sort_order' => 80,
                'notes' => 'Disabled (item 9 D4, CSJ 2026-10-07): fired for everyone; replaced by pension_no_beneficiary.',
            ],
            [
                'key' => 'pension_no_beneficiary',
                'source' => 'agent',
                'title_template' => 'No beneficiary recorded for {pension_name}',
                'description_template' => '{pension_name} has no beneficiary recorded. A nomination tells the scheme whom you would like to receive the money when you die.',
                'action_template' => 'Send the scheme a nomination, then record the beneficiary on the pension.',
                'category' => 'Beneficiaries',
                'priority' => 'medium',
                'scope' => 'account',
                'what_if_impact_type' => 'estate_protection',
                'trigger_config' => ['condition' => 'pension_no_beneficiary'],
                'is_enabled' => true,
                'sort_order' => 80,
                'notes' => 'Item 9 D4: one card per defined contribution pension with no beneficiary recorded.',
            ],
        ];
    }
}
