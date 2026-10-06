<?php

declare(strict_types=1);

use App\Models\InvestmentActionDefinition;
use App\Models\User;
use App\Services\Investment\FeeAnalyzer;
use App\Services\Investment\InvestmentActionDefinitionService;
use App\Services\Plans\PlanConfigService;
use App\Services\TaxConfigService;
use Database\Seeders\InvestmentActionDefinitionSeeder;
use Database\Seeders\PlanConfigurationSeeder;
use Database\Seeders\TaxConfigurationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(TaxConfigurationSeeder::class);
    $this->seed(InvestmentActionDefinitionSeeder::class);
    $this->seed(PlanConfigurationSeeder::class);

    $feeAnalyzer = app(FeeAnalyzer::class);
    $taxConfig = app(TaxConfigService::class);
    $planConfig = app(PlanConfigService::class);
    $this->service = new InvestmentActionDefinitionService($feeAnalyzer, $taxConfig, $planConfig);

    $this->user = User::factory()->create([
        'annual_employment_income' => 55000,
        'is_preview_user' => true,
    ]);
});

// =========================================================================
// evaluateAgentActions — Investment triggers
// =========================================================================

describe('evaluateAgentActions — investment triggers', function () {
    it('fires risk_profile_missing when allocation_deviation is absent', function () {
        $investmentAnalysis = [
            'portfolio_summary' => ['accounts_count' => 1, 'holdings_count' => 3],
            // no allocation_deviation key
        ];

        $result = $this->service->evaluateAgentActions(
            $investmentAnalysis, [], collect(), collect(), $this->user->id, []
        );

        $rec = collect($result['recommendations'])->first(fn ($r) => ($r['definition_key'] ?? '') === 'risk_profile_missing');
        expect($rec)->not->toBeNull();
    });

    it('does NOT fire risk_profile_missing when allocation_deviation exists', function () {
        $investmentAnalysis = [
            'portfolio_summary' => ['accounts_count' => 1, 'holdings_count' => 3],
            'allocation_deviation' => ['needs_rebalancing' => false],
        ];

        $result = $this->service->evaluateAgentActions(
            $investmentAnalysis, [], collect(), collect(), $this->user->id, []
        );

        $rec = collect($result['recommendations'])->first(fn ($r) => ($r['definition_key'] ?? '') === 'risk_profile_missing');
        expect($rec)->toBeNull();
    });

    it('fires no_holdings when accounts exist but total holdings is zero', function () {
        $investmentAnalysis = [
            'portfolio_summary' => ['accounts_count' => 2, 'holdings_count' => 0],
            'allocation_deviation' => ['needs_rebalancing' => false],
        ];

        $result = $this->service->evaluateAgentActions(
            $investmentAnalysis, [], collect(), collect(), $this->user->id, []
        );

        $rec = collect($result['recommendations'])->first(fn ($r) => ($r['definition_key'] ?? '') === 'no_holdings');
        expect($rec)->not->toBeNull();
    });

    it('does NOT fire no_holdings when accounts have holdings', function () {
        $investmentAnalysis = [
            'portfolio_summary' => ['accounts_count' => 2, 'holdings_count' => 5],
            'allocation_deviation' => ['needs_rebalancing' => false],
        ];

        $result = $this->service->evaluateAgentActions(
            $investmentAnalysis, [], collect(), collect(), $this->user->id, []
        );

        $rec = collect($result['recommendations'])->first(fn ($r) => ($r['definition_key'] ?? '') === 'no_holdings');
        expect($rec)->toBeNull();
    });

    it('fires neither rule it folded into allocation_position (item 8 D3)', function () {
        $investmentAnalysis = [
            'portfolio_summary' => ['accounts_count' => 1, 'holdings_count' => 3],
            'allocation_deviation' => ['needs_rebalancing' => true],
            'diversification_score' => 40,
        ];

        $result = $this->service->evaluateAgentActions(
            $investmentAnalysis, [], collect(), collect(), $this->user->id, []
        );

        $keys = collect($result['recommendations'])->pluck('definition_key');
        expect($keys)->not->toContain('low_diversification')
            ->and($keys)->not->toContain('rebalance_portfolio');
    });

    it('does NOT fire low_diversification when score meets threshold', function () {
        $investmentAnalysis = [
            'portfolio_summary' => ['accounts_count' => 1, 'holdings_count' => 3],
            'allocation_deviation' => ['needs_rebalancing' => false],
            'diversification_score' => 85,
        ];

        $result = $this->service->evaluateAgentActions(
            $investmentAnalysis, [], collect(), collect(), $this->user->id, []
        );

        $rec = collect($result['recommendations'])->first(fn ($r) => ($r['definition_key'] ?? '') === 'low_diversification');
        expect($rec)->toBeNull();
    });

    it('fires one account_charges card per account, with every charge in pounds (item 8 D4)', function () {
        // Total (1.5%) and platform (£500 on £50,000 = 1.0%) both cross: one card.
        $feeAnalyses = [[
            'account_id' => 1,
            'account_name' => 'Test ISA',
            'account_value' => 50000,
            'total_fee_percent' => 1.5,
            'total_annual_fees' => 750,
            'weighted_ocf' => 0.3,
            'holdings_count' => 3,
            'fees' => ['platform_fee' => 500, 'fund_ocf' => 150, 'transaction_costs' => 0, 'advisory_fee' => 100],
        ]];

        $investmentAnalysis = [
            'portfolio_summary' => ['accounts_count' => 1, 'holdings_count' => 3],
            'allocation_deviation' => ['needs_rebalancing' => false],
        ];

        $result = $this->service->evaluateAgentActions(
            $investmentAnalysis, [], collect(), collect(), $this->user->id, $feeAnalyses
        );

        $recs = collect($result['recommendations'])->where('definition_key', 'account_charges')->values();
        expect($recs)->toHaveCount(1)
            ->and($recs[0]['scope'])->toBe('account')
            ->and($recs[0]['account_id'])->toBe(1)
            ->and($recs[0]['title'])->toBe('Review the charges on Test ISA')
            ->and($recs[0]['description'])->toBe('Test ISA costs £750 a year in charges, 1.50% of its value: adviser £100, platform £500, fund charges £150.')
            ->and($recs[0]['figures']['annual_fees'])->toBe('£750')
            ->and(collect($result['recommendations'])->pluck('definition_key'))->not->toContain('high_total_fees');
    });

    it('does NOT fire account_charges when every charge is below its threshold', function () {
        $feeAnalyses = [[
            'account_id' => 1,
            'account_name' => 'Low Fee ISA',
            'account_value' => 50000,
            'total_fee_percent' => 0.5,
            'total_annual_fees' => 250,
            'weighted_ocf' => 0.2,
            'holdings_count' => 3,
            'fees' => ['platform_fee' => 150, 'fund_ocf' => 100, 'transaction_costs' => 0, 'advisory_fee' => 0],
        ]];

        $investmentAnalysis = [
            'portfolio_summary' => ['accounts_count' => 1, 'holdings_count' => 3],
            'allocation_deviation' => ['needs_rebalancing' => false],
        ];

        $result = $this->service->evaluateAgentActions(
            $investmentAnalysis, [], collect(), collect(), $this->user->id, $feeAnalyses
        );

        $rec = collect($result['recommendations'])->first(fn ($r) => ($r['definition_key'] ?? '') === 'account_charges');
        expect($rec)->toBeNull();
    });

    it('fires one allocation_position card per account outside its threshold (item 8 D3)', function () {
        $investmentAnalysis = [
            'portfolio_summary' => ['accounts_count' => 1, 'holdings_count' => 3],
            'account_drift' => [[
                'account_id' => 7,
                'account_name' => 'Test GIA',
                'risk_label' => 'Upper-Medium',
                'needs_rebalancing' => true,
                'unrecorded_percent' => 0.0,
                'drifts_by_asset' => [
                    'equities' => ['current' => 60.0, 'target' => 75.0, 'drift' => -15.0],
                    'bonds' => ['current' => 35.0, 'target' => 20.0, 'drift' => 15.0],
                    'alternatives' => ['current' => 5.0, 'target' => 5.0, 'drift' => 0.0],
                    'cash' => ['current' => 0.0, 'target' => 0.0, 'drift' => 0.0],
                ],
            ], [
                'account_id' => 8,
                'account_name' => 'Balanced ISA',
                'risk_label' => 'Medium',
                'needs_rebalancing' => false,
                'unrecorded_percent' => 0.0,
                'drifts_by_asset' => [],
            ]],
        ];

        $result = $this->service->evaluateAgentActions(
            $investmentAnalysis, [], collect(), collect(), $this->user->id, []
        );

        $recs = collect($result['recommendations'])->where('definition_key', 'allocation_position')->values();
        expect($recs)->toHaveCount(1)
            ->and($recs[0]['account_id'])->toBe(7)
            ->and($recs[0]['title'])->toBe('Test GIA holds 35% in bonds against 20% for its risk level')
            ->and($recs[0]['description'])->toBe('Test GIA is outside its rebalancing threshold for the upper-medium risk level: shares 60% against 75%, bonds 35% against 20%, alternatives 5% against 5%.');
    });

    it('says at least and at most where part of the account has no recorded mix', function () {
        $investmentAnalysis = [
            'portfolio_summary' => ['accounts_count' => 1, 'holdings_count' => 3],
            'account_drift' => [[
                'account_id' => 7,
                'account_name' => 'Test GIA',
                'risk_label' => 'Medium',
                'needs_rebalancing' => true,
                'unrecorded_percent' => 40.0,
                'drifts_by_asset' => [
                    'equities' => ['current' => 50.0, 'target' => 60.0, 'drift' => -10.0],
                    'alternatives' => ['current' => 21.0, 'target' => 5.0, 'drift' => 16.0],
                ],
            ]],
        ];

        $result = $this->service->evaluateAgentActions(
            $investmentAnalysis, [], collect(), collect(), $this->user->id, []
        );

        $rec = collect($result['recommendations'])->firstWhere('definition_key', 'allocation_position');
        expect($rec['title'])->toBe('Test GIA holds at least 21% in alternatives against 5% for its risk level')
            ->and($rec['description'])->toContain('shares at most 50% against 60%')
            ->and($rec['description'])->toContain('40% is in funds whose mix is not recorded');
    });

    it('fires tax_loss_harvesting when opportunities exist', function () {
        $investmentAnalysis = [
            'portfolio_summary' => ['accounts_count' => 1, 'holdings_count' => 3],
            'allocation_deviation' => ['needs_rebalancing' => false],
            'tax_efficiency' => [
                'harvesting_opportunities' => [
                    'opportunities_count' => 2,
                    'total_harvestable_losses' => 1200,
                    'potential_tax_saving' => 0,
                ],
            ],
        ];

        $result = $this->service->evaluateAgentActions(
            $investmentAnalysis, [], collect(), collect(), $this->user->id, []
        );

        // The losses, at the user's share, and no saving: no gains are recorded
        // (item 8; it said "Potential tax saving: £0" on every card before).
        $rec = collect($result['recommendations'])->first(fn ($r) => ($r['definition_key'] ?? '') === 'tax_loss_harvesting');
        expect($rec)->not->toBeNull()
            ->and($rec['description'])->toStartWith('2 holdings outside an ISA are worth £1,200 less than you paid.')
            ->and($rec['description'])->not->toContain('saving');
    });
});

// =========================================================================
// evaluateAgentActions — Tax efficiency triggers
// =========================================================================

describe('evaluateAgentActions — tax efficiency triggers', function () {
    it('fires open_isa when user has GIA but no ISA', function () {
        $investmentAnalysis = [
            'portfolio_summary' => ['accounts_count' => 1, 'holdings_count' => 3],
            'allocation_deviation' => ['needs_rebalancing' => false],
            'tax_wrappers' => [
                'has_gia' => true,
                'has_isa' => false,
                'gia_value' => 50000,
            ],
        ];

        $result = $this->service->evaluateAgentActions(
            $investmentAnalysis, [], collect(), collect(), $this->user->id, []
        );

        $rec = collect($result['recommendations'])->first(fn ($r) => ($r['definition_key'] ?? '') === 'open_isa');
        expect($rec)->not->toBeNull();
    });

    it('does NOT fire open_isa when user already has an ISA', function () {
        $investmentAnalysis = [
            'portfolio_summary' => ['accounts_count' => 1, 'holdings_count' => 3],
            'allocation_deviation' => ['needs_rebalancing' => false],
            'tax_wrappers' => [
                'has_gia' => true,
                'has_isa' => true,
                'gia_value' => 50000,
            ],
        ];

        $result = $this->service->evaluateAgentActions(
            $investmentAnalysis, [], collect(), collect(), $this->user->id, []
        );

        $rec = collect($result['recommendations'])->first(fn ($r) => ($r['definition_key'] ?? '') === 'open_isa');
        expect($rec)->toBeNull();
    });

    it('writes the money on the tax wrapper cards as pounds', function () {
        // csjones 2026-10-02, Mitchell demo: "You have 10,000 ISA allowance
        // remaining … holdings (47,500)", with no pound sign.
        $investmentAnalysis = [
            'portfolio_summary' => ['accounts_count' => 2, 'holdings_count' => 3],
            'allocation_deviation' => ['needs_rebalancing' => false],
            'tax_wrappers' => [
                'has_gia' => true,
                'has_isa' => true,
                'gia_value' => 75000,
                'isa_remaining' => 10000,
                'isa_used_this_year' => 10000,
                'isa_allowance' => 20000,
            ],
        ];

        $result = $this->service->evaluateAgentActions(
            $investmentAnalysis, [], collect(), collect(), $this->user->id, []
        );
        $recs = collect($result['recommendations'])->keyBy('definition_key');

        expect($recs['use_isa_allowance']['description'])
            ->toContain('£10,000 ISA allowance')
            ->toContain('(£75,000)')
            ->and($recs['consider_bonds']['description'])->toContain('£75,000');
    });
});

// =========================================================================
// evaluateAgentActions — Savings triggers
// =========================================================================

describe('evaluateAgentActions — savings triggers', function () {
    it('fires none of the savings and surplus rules: Savings carries them (item 8 D1)', function () {
        $investmentAnalysis = [
            'portfolio_summary' => ['accounts_count' => 1, 'holdings_count' => 3],
            'allocation_deviation' => ['needs_rebalancing' => false],
        ];

        foreach ([1.5, 4, 12] as $runway) {
            $savingsAnalysis = [
                'emergency_fund' => ['runway_months' => $runway],
                'summary' => ['total_savings' => 2000 * $runway, 'monthly_expenditure' => 2000],
                'isa_allowance' => ['remaining' => 15000],
            ];

            $result = $this->service->evaluateAgentActions(
                $investmentAnalysis, $savingsAnalysis, collect(), collect(), $this->user->id, []
            );

            expect(collect($result['recommendations'])->pluck('definition_key')->all())
                ->not->toContain('emergency_fund_critical')
                ->not->toContain('emergency_fund_grow')
                ->not->toContain('switch_savings_rate')
                ->not->toContain('isa_allowance_remaining')
                ->not->toContain('surplus_to_isa')
                ->not->toContain('surplus_to_pension')
                ->not->toContain('surplus_to_bond');
        }
    });

    it('says nothing about the emergency fund when no savings analysis is given', function () {
        // InvestmentAgent::generateRecommendations (the dashboard and Fyn path)
        // passes no savings analysis. The runway is unknown there, not 0: on
        // fynla.org the Mitchell demo was told "critically low at 0 months"
        // beside the Savings figure of 14 months (CSJ 2026-10-01, one figure).
        $investmentAnalysis = [
            'portfolio_summary' => ['accounts_count' => 1, 'holdings_count' => 3],
            'allocation_deviation' => ['needs_rebalancing' => false],
        ];

        $result = $this->service->evaluateAgentActions(
            $investmentAnalysis, [], collect(), collect(), $this->user->id, []
        );

        $keys = collect($result['recommendations'])->pluck('definition_key');
        expect($keys)->not->toContain('emergency_fund_critical')
            ->and($keys)->not->toContain('emergency_fund_grow');
    });

});

// =========================================================================
// evaluateAgentActions — Surplus waterfall triggers
// =========================================================================

describe('evaluateAgentActions — surplus waterfall triggers', function () {
    it('does NOT fire surplus actions when runway is below target months', function () {
        $savingsAnalysis = [
            'emergency_fund' => ['runway_months' => 4],
            'summary' => [
                'total_savings' => 8000,
                'monthly_expenditure' => 2000,
            ],
        ];

        $investmentAnalysis = [
            'portfolio_summary' => ['accounts_count' => 1, 'holdings_count' => 3],
            'allocation_deviation' => ['needs_rebalancing' => false],
        ];

        $result = $this->service->evaluateAgentActions(
            $investmentAnalysis, $savingsAnalysis, collect(), collect(), $this->user->id, []
        );

        $surplusRecs = collect($result['recommendations'])->filter(fn ($r) => str_starts_with($r['definition_key'] ?? '', 'surplus_to_')
        );

        expect($surplusRecs)->toBeEmpty();
    });
});

// =========================================================================
// Disabled definitions
// =========================================================================

describe('disabled definitions', function () {
    it('skips disabled definitions', function () {
        InvestmentActionDefinition::where('key', 'risk_profile_missing')->update(['is_enabled' => false]);

        $investmentAnalysis = [
            'portfolio_summary' => ['accounts_count' => 1, 'holdings_count' => 3],
            // no allocation_deviation — would normally fire risk_profile_missing
        ];

        $result = $this->service->evaluateAgentActions(
            $investmentAnalysis, [], collect(), collect(), $this->user->id, []
        );

        $rec = collect($result['recommendations'])->first(fn ($r) => ($r['definition_key'] ?? '') === 'risk_profile_missing');
        expect($rec)->toBeNull();
    });
});

// =========================================================================
// Custom threshold overrides
// =========================================================================

describe('custom threshold overrides', function () {
    it('uses custom threshold when set in trigger_config', function () {
        InvestmentActionDefinition::where('key', 'account_charges')
            ->update(['trigger_config' => json_encode([
                'condition' => 'account_charges_above',
                'total_threshold' => 0.4,
                'fund_threshold' => 0.5,
                'platform_threshold' => 0.8,
            ])]);

        $feeAnalyses = [[
            'account_id' => 1,
            'account_name' => 'Low Fee ISA',
            'account_value' => 50000,
            'total_fee_percent' => 0.5, // above the custom 0.4
            'total_annual_fees' => 250,
            'weighted_ocf' => 0.2,
            'holdings_count' => 3,
            'fees' => ['platform_fee' => 150, 'fund_ocf' => 100],
        ]];

        $result = $this->service->evaluateAgentActions(
            ['portfolio_summary' => ['accounts_count' => 1, 'holdings_count' => 3]], [], collect(), collect(), $this->user->id, $feeAnalyses
        );

        expect(collect($result['recommendations'])->pluck('definition_key'))->toContain('account_charges');
    });
});

// =========================================================================
// evaluateGoalActions
// =========================================================================

describe('evaluateGoalActions', function () {
    it('fires goal_no_contribution when monthly_contribution is zero', function () {
        $goals = [
            [
                'id' => 1,
                'name' => 'House Deposit',
                'progress_percentage' => 30,
                'monthly_contribution' => 0,
                'required_monthly_contribution' => 200,
                'target_amount' => 50000,
                'is_on_track' => false,
                'months_remaining' => 60,
            ],
        ];

        $result = $this->service->evaluateGoalActions($goals);

        $rec = collect($result)->first(fn ($r) => str_contains($r['title'] ?? '', 'Start contributing') || str_contains($r['title'] ?? '', 'contribution'));
        expect($rec)->not->toBeNull()
            ->and($rec['source'])->toBe('goal')
            ->and($rec['goal_id'])->toBe(1);
    });

    it('fires goal_behind_schedule when goal is off track', function () {
        $goals = [
            [
                'id' => 2,
                'name' => 'Emergency Fund',
                'progress_percentage' => 40,
                'monthly_contribution' => 100,
                'required_monthly_contribution' => 250,
                'target_amount' => 20000,
                'is_on_track' => false,
                'months_remaining' => 24,
            ],
        ];

        $result = $this->service->evaluateGoalActions($goals);

        $rec = collect($result)->first(fn ($r) => str_contains($r['title'] ?? '', 'behind schedule'));
        expect($rec)->not->toBeNull();
    });

    it('fires goal_deadline_approaching when near deadline with low progress', function () {
        $goals = [
            [
                'id' => 3,
                'name' => 'Holiday Fund',
                'progress_percentage' => 50,
                'monthly_contribution' => 100,
                'required_monthly_contribution' => 100,
                'target_amount' => 5000,
                'is_on_track' => true,
                'months_remaining' => 4,
            ],
        ];

        $result = $this->service->evaluateGoalActions($goals);

        // The goal is on track, months_remaining=4 < 6, progress=50 < 75
        $rec = collect($result)->first(fn ($r) => str_contains($r['title'] ?? '', 'target date') ||
            str_contains($r['title'] ?? '', 'deadline') ||
            str_contains($r['title'] ?? '', 'approaching')
        );
        expect($rec)->not->toBeNull();
    });

    it('skips completed goals', function () {
        $goals = [
            [
                'id' => 4,
                'name' => 'Completed Goal',
                'progress_percentage' => 100,
                'monthly_contribution' => 200,
                'required_monthly_contribution' => 200,
                'target_amount' => 50000,
                'is_on_track' => true,
                'months_remaining' => 0,
            ],
        ];

        $result = $this->service->evaluateGoalActions($goals);

        expect($result)->toBeEmpty();
    });
});

// =========================================================================
// getWhatIfImpactType
// =========================================================================

describe('getWhatIfImpactType', function () {
    it('returns fee_reduction for Fees category', function () {
        expect($this->service->getWhatIfImpactType('Fees'))->toBe('fee_reduction');
    });

    it('returns savings_increase for Emergency Fund category', function () {
        expect($this->service->getWhatIfImpactType('Emergency Fund'))->toBe('savings_increase');
    });

    it('returns default for unknown category', function () {
        expect($this->service->getWhatIfImpactType('Unknown Category'))->toBe('default');
    });
});

// =========================================================================
// Template rendering
// =========================================================================

describe('template rendering', function () {
    it('renders title with placeholders', function () {
        $definition = InvestmentActionDefinition::findByKey('high_total_fees');

        $rendered = $definition->renderTitle([
            'account_name' => 'Hargreaves ISA',
            'total_fee' => '1.5%',
        ]);

        expect($rendered)->toContain('Hargreaves ISA');
    });

    it('renders description with multiple placeholders', function () {
        $definition = InvestmentActionDefinition::findByKey('goal_no_contribution');

        $rendered = $definition->renderDescription([
            'goal_name' => 'Holiday Fund',
            'required_monthly' => '£200',
            'target_amount' => '£5,000',
        ]);

        expect($rendered)->toContain('Holiday Fund')
            ->and($rendered)->toContain('£200');
    });

    it('handles missing placeholders gracefully', function () {
        $definition = InvestmentActionDefinition::findByKey('high_total_fees');

        $rendered = $definition->renderTitle([]);

        expect($rendered)->toBeString();
    });
});

// =========================================================================
// userId guard
// =========================================================================

describe('userId guard', function () {
    it('returns zero surplus when userId is zero', function () {
        $savingsAnalysis = [
            'emergency_fund' => ['runway_months' => 12],
            'summary' => ['total_savings' => 30000, 'monthly_expenditure' => 2000],
            'isa_allowance' => ['remaining' => 15000],
        ];

        $investmentAnalysis = [
            'portfolio_summary' => ['accounts_count' => 1, 'holdings_count' => 3],
            'allocation_deviation' => ['needs_rebalancing' => false],
        ];

        $result = $this->service->evaluateAgentActions(
            $investmentAnalysis, $savingsAnalysis, collect(), collect(), 0, []
        );

        $surplusRecs = collect($result['recommendations'])->filter(fn ($r) => str_starts_with($r['definition_key'] ?? '', 'surplus_to_')
        );

        expect($surplusRecs)->toBeEmpty();
    });
});
