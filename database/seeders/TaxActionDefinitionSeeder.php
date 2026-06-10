<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\TaxActionDefinition;
use Illuminate\Database\Seeder;

/**
 * Seed the tax_action_definitions table with all action types.
 *
 * Seeds 5 agent-sourced tax optimisation action definitions (disabled — orphaned
 * TaxActionDefinitionService has zero callers) and 20 strategy-registry metadata
 * rows (source='strategy') linking to the June Tax/Strategies classes. Priorities
 * follow the canonical catalogue (fynlaBrain April30Updates/savetax-strategy-catalogue.md).
 * Uses updateOrCreate for idempotency.
 *
 * Run: php artisan db:seed --class=TaxActionDefinitionSeeder --force
 */
class TaxActionDefinitionSeeder extends Seeder
{
    public function run(): void
    {
        // ── Legacy agent-sourced definitions (orphaned TaxActionDefinitionService) ──
        $definitions = $this->getDefinitions();

        foreach ($definitions as $definition) {
            TaxActionDefinition::updateOrCreate(
                ['key' => $definition['key']],
                $definition
            );
        }

        // ── Catalogue metadata for the June strategy registry (source: 'strategy') ──
        // One row per distinct StrategyRecommendation::type emitted by the
        // Tax/Strategies classes. The strategy class remains the quantifier; this
        // row carries admin-visible metadata (claim tier, data requirements,
        // sequencing) consumed by the plan composer. Priorities follow the
        // canonical catalogue (fynlaBrain April30Updates/savetax-strategy-catalogue.md).
        foreach ($this->strategyMetadata() as $meta) {
            TaxActionDefinition::updateOrCreate(
                ['strategy_type' => $meta['strategy_type']],
                $meta + [
                    'key' => 'strategy_'.$meta['strategy_type'],
                    'source' => 'strategy',
                    'title_template' => $meta['strategy_type'],   // display copy comes from the strategy DTO
                    'description_template' => 'Computed by the '.$meta['strategy_type'].' strategy.',
                    'scope' => 'portfolio',
                    'is_enabled' => true,
                    'sort_order' => 100,
                    'trigger_config' => [],   // strategy classes are self-triggering; no agent-side config needed
                ]
            );
        }

        // The March 'agent' evaluators duplicate the strategy registry and their
        // service (TaxActionDefinitionService) is orphaned — disable, don't delete.
        TaxActionDefinition::where('source', 'agent')->update(['is_enabled' => false]);
    }

    private function strategyMetadata(): array
    {
        return [
            // ── A. Income-band driven (single-user) ──────────────────────

            [
                'strategy_type' => 'pa_taper_rescue',
                'category' => 'income_band',
                'priority' => 'high',
                'claim_tier' => 'mechanical',
                'required_data' => ['annual_income'],
                'sequencing' => ['do_before' => [], 'conflicts_with' => []],
            ],

            [
                'strategy_type' => 'additional_rate_avoidance',
                'category' => 'income_band',
                'priority' => 'high',
                'claim_tier' => 'mechanical',
                'required_data' => ['annual_income'],
                'sequencing' => ['do_before' => [], 'conflicts_with' => []],
            ],

            [
                'strategy_type' => 'salary_sacrifice_ni',
                'category' => 'income_band',
                'priority' => 'medium',
                'claim_tier' => 'mechanical',
                'required_data' => ['employment_status', 'annual_income', 'workplace_pension'],
                'sequencing' => ['do_before' => [], 'conflicts_with' => []],
            ],

            // ── B. Allowance harvesting (single-user) ─────────────────────

            [
                'strategy_type' => 'isa_topup_vs_psa',
                'category' => 'allowance',
                'priority' => 'high',
                'claim_tier' => 'mechanical',
                'required_data' => ['savings_balances', 'isa_subscriptions_ytd'],
                'sequencing' => ['do_before' => ['savings_to_spouse'], 'conflicts_with' => []],
            ],

            [
                'strategy_type' => 'bed_and_isa',
                'category' => 'allowance',
                'priority' => 'medium',
                'claim_tier' => 'judgement',
                'required_data' => ['gia_holdings', 'isa_subscriptions_ytd', 'annual_income'],
                'sequencing' => ['do_before' => [], 'conflicts_with' => []],
            ],

            [
                'strategy_type' => 'dividend_allowance_harvest',
                'category' => 'allowance',
                'priority' => 'low',
                'claim_tier' => 'mechanical',
                'required_data' => ['dividend_income', 'gia_holdings'],
                'sequencing' => ['do_before' => [], 'conflicts_with' => []],
            ],

            [
                'strategy_type' => 'pension_aa_carry_forward',
                'category' => 'allowance',
                'priority' => 'medium',
                'claim_tier' => 'mechanical',
                'required_data' => ['pension_input_history', 'annual_income', 'pension_contributions'],
                'sequencing' => ['do_before' => [], 'conflicts_with' => []],
            ],

            [
                'strategy_type' => 'gift_aid_higher_rate_relief',
                'category' => 'allowance',
                'priority' => 'medium',
                'claim_tier' => 'mechanical',
                'required_data' => ['charitable_giving', 'annual_income'],
                'sequencing' => ['do_before' => [], 'conflicts_with' => []],
            ],

            // ── C. Household / spouse strategies ─────────────────────────

            [
                'strategy_type' => 'marriage_allowance_transfer',
                'category' => 'household',
                'priority' => 'medium',
                'claim_tier' => 'mechanical',
                'required_data' => ['marital_status', 'annual_income', 'spouse_income'],
                'sequencing' => ['do_before' => [], 'conflicts_with' => []],
            ],

            [
                'strategy_type' => 'savings_to_spouse',
                'category' => 'household',
                'priority' => 'high',
                'claim_tier' => 'mechanical',
                'required_data' => ['marital_status', 'savings_balances', 'spouse_income'],
                'sequencing' => ['do_before' => [], 'conflicts_with' => ['joint_savings_psa_split']],
            ],

            [
                'strategy_type' => 'isa_topup_spouse',
                'category' => 'household',
                'priority' => 'medium',
                'claim_tier' => 'mechanical',
                'required_data' => ['marital_status', 'isa_subscriptions_ytd', 'spouse_income'],
                'sequencing' => ['do_before' => [], 'conflicts_with' => []],
            ],

            [
                'strategy_type' => 'gia_to_spouse',
                'category' => 'household',
                'priority' => 'high',
                'claim_tier' => 'mechanical',
                'required_data' => ['marital_status', 'gia_holdings', 'dividend_income', 'spouse_income'],
                'sequencing' => ['do_before' => [], 'conflicts_with' => []],
            ],

            [
                'strategy_type' => 'gia_rebalance',
                'category' => 'household',
                'priority' => 'high',
                'claim_tier' => 'judgement',
                'required_data' => ['marital_status', 'gia_holdings', 'dividend_income', 'spouse_income'],
                'sequencing' => ['do_before' => [], 'conflicts_with' => []],
            ],

            [
                'strategy_type' => 'isa_coordination',
                'category' => 'household',
                'priority' => 'medium',
                'claim_tier' => 'mechanical',
                'required_data' => ['marital_status', 'isa_subscriptions_ytd', 'spouse_income'],
                'sequencing' => ['do_before' => [], 'conflicts_with' => []],
            ],

            [
                'strategy_type' => 'non_earner_spouse_pension',
                'category' => 'household',
                'priority' => 'medium',
                'claim_tier' => 'mechanical',
                'required_data' => ['marital_status', 'spouse_income', 'pension_contributions'],
                'sequencing' => ['do_before' => [], 'conflicts_with' => []],
            ],

            [
                'strategy_type' => 'joint_savings_psa_split',
                'category' => 'household',
                'priority' => 'low',
                'claim_tier' => 'mechanical',
                'required_data' => ['marital_status', 'savings_balances', 'spouse_income'],
                'sequencing' => ['do_before' => [], 'conflicts_with' => ['savings_to_spouse']],
            ],

            // ── D. Warning strategies ──────────────────────────────────────

            [
                'strategy_type' => 'tapered_annual_allowance',
                'category' => 'warning',
                'priority' => 'high',
                'claim_tier' => 'mechanical',
                'required_data' => ['annual_income', 'dividend_income', 'pension_contributions', 'workplace_pension'],
                'sequencing' => ['do_before' => [], 'conflicts_with' => []],
            ],

            // ── E. Lifecycle / dependant strategies ───────────────────────

            [
                'strategy_type' => 'lifetime_isa',
                'category' => 'lifecycle',
                'priority' => 'medium',
                'claim_tier' => 'judgement',
                'required_data' => ['date_of_birth', 'savings_balances', 'isa_subscriptions_ytd'],
                'sequencing' => ['do_before' => [], 'conflicts_with' => []],
            ],

            [
                'strategy_type' => 'junior_isa',
                'category' => 'lifecycle',
                'priority' => 'medium',
                'claim_tier' => 'judgement',
                'required_data' => ['date_of_birth'],
                'sequencing' => ['do_before' => [], 'conflicts_with' => []],
            ],

            [
                'strategy_type' => 'junior_pension',
                'category' => 'lifecycle',
                'priority' => 'medium',
                'claim_tier' => 'judgement',
                'required_data' => ['date_of_birth'],
                'sequencing' => ['do_before' => [], 'conflicts_with' => []],
            ],
        ];
    }

    private function getDefinitions(): array
    {
        return [
            // ── ISA Allowance ────────────────────────────────────────────

            [
                'key' => 'isa_not_maxed',
                'source' => 'agent',
                'title_template' => 'Use Your Remaining ISA Allowance',
                'description_template' => 'You have used {isa_used} of your {isa_allowance} ISA allowance this tax year, leaving {isa_remaining} unused. Contributions to an ISA are sheltered from income tax and capital gains tax.',
                'action_template' => 'Consider contributing to a Cash ISA or Stocks and Shares ISA before the end of the tax year on 5 April.',
                'category' => 'ISA Allowance',
                'priority' => 'high',
                'scope' => 'portfolio',
                'what_if_impact_type' => 'tax_optimisation',
                'trigger_config' => [
                    'condition' => 'isa_not_maxed',
                ],
                'is_enabled' => true,
                'sort_order' => 10,
                'notes' => 'Triggers when total ISA subscriptions are below the annual allowance.',
            ],

            // ── Pension Carry Forward ────────────────────────────────────

            [
                'key' => 'pension_carry_forward_available',
                'source' => 'agent',
                'title_template' => 'Pension Carry Forward Available',
                'description_template' => 'You have {carry_forward} of unused pension Annual Allowance available from prior years. Additional pension contributions attract tax relief at your marginal rate of {tax_rate}%.',
                'action_template' => 'Speak to your adviser about making additional pension contributions to use your carry forward allowance.',
                'category' => 'Pension Allowance',
                'priority' => 'high',
                'scope' => 'portfolio',
                'what_if_impact_type' => 'tax_optimisation',
                'trigger_config' => [
                    'condition' => 'pension_carry_forward_available',
                ],
                'is_enabled' => true,
                'sort_order' => 20,
                'notes' => 'Triggers when carry forward from prior 3 years is available via AnnualAllowanceChecker.',
            ],

            // ── Spousal Transfer ─────────────────────────────────────────

            [
                'key' => 'spousal_transfer_beneficial',
                'source' => 'agent',
                'title_template' => 'Spousal Tax Optimisation Opportunity',
                'description_template' => 'You are in the {user_band} tax band while your spouse is in the {spouse_band} band. Transferring income-producing assets to your spouse could reduce your household tax bill by an estimated {potential_saving} per year.',
                'action_template' => 'Consider transferring investments or savings to your spouse to take advantage of their lower tax rate.',
                'category' => 'Spousal Optimisation',
                'priority' => 'medium',
                'scope' => 'portfolio',
                'what_if_impact_type' => 'tax_optimisation',
                'trigger_config' => [
                    'condition' => 'spousal_transfer_beneficial',
                ],
                'is_enabled' => true,
                'sort_order' => 30,
                'notes' => 'Triggers when married user and spouse are in different tax bands.',
            ],

            // ── Capital Gains Tax Allowance ──────────────────────────────

            [
                'key' => 'cgt_allowance_unused',
                'source' => 'agent',
                'title_template' => 'Use Your Capital Gains Tax Annual Exemption',
                'description_template' => 'You hold {gia_value} in a General Investment Account with unrealised gains. Your annual Capital Gains Tax exemption of {cgt_exemption} allows you to realise gains tax-free each year.',
                'action_template' => 'Consider realising gains up to your annual exemption and reinvesting via an ISA or pension to shelter future growth.',
                'category' => 'Capital Gains',
                'priority' => 'medium',
                'scope' => 'portfolio',
                'what_if_impact_type' => 'tax_optimisation',
                'trigger_config' => [
                    'condition' => 'cgt_allowance_unused',
                ],
                'is_enabled' => true,
                'sort_order' => 40,
                'notes' => 'Triggers when user has GIA holdings with unrealised gains and has not used CGT annual exemption.',
            ],

            // ── Dividend Income in General Investment Account ─────────────

            [
                'key' => 'high_dividend_in_gia',
                'source' => 'agent',
                'title_template' => 'Move Dividend-Producing Holdings to ISA',
                'description_template' => 'You hold dividend-producing investments worth {gia_value} in a General Investment Account. Dividends above the {dividend_allowance} allowance are taxed at your marginal rate. Moving these holdings into an ISA would shelter all dividend income from tax.',
                'action_template' => 'Consider a Bed and ISA transfer to move dividend-producing holdings from your General Investment Account into your ISA.',
                'category' => 'Dividend Tax',
                'priority' => 'medium',
                'scope' => 'portfolio',
                'what_if_impact_type' => 'tax_optimisation',
                'trigger_config' => [
                    'condition' => 'high_dividend_in_gia',
                    'min_gia_value' => 10000,
                ],
                'is_enabled' => true,
                'sort_order' => 50,
                'notes' => 'Triggers when GIA holdings exceed threshold and could benefit from ISA sheltering for dividends.',
            ],
        ];
    }
}
