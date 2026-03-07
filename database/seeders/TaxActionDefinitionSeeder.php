<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\TaxActionDefinition;
use Illuminate\Database\Seeder;

/**
 * Seed the tax_action_definitions table with all action types.
 *
 * Seeds 5 agent-sourced tax optimisation action definitions.
 * Uses updateOrCreate on `key` for idempotency.
 *
 * Run: php artisan db:seed --class=TaxActionDefinitionSeeder --force
 */
class TaxActionDefinitionSeeder extends Seeder
{
    public function run(): void
    {
        $definitions = $this->getDefinitions();

        foreach ($definitions as $definition) {
            TaxActionDefinition::updateOrCreate(
                ['key' => $definition['key']],
                $definition
            );
        }
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
