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

    it('fires low_diversification when score is below threshold', function () {
        $investmentAnalysis = [
            'portfolio_summary' => ['accounts_count' => 1, 'holdings_count' => 3],
            'allocation_deviation' => ['needs_rebalancing' => false],
            'diversification_score' => 40,
        ];

        $result = $this->service->evaluateAgentActions(
            $investmentAnalysis, [], collect(), collect(), $this->user->id, []
        );

        $rec = collect($result['recommendations'])->first(fn ($r) => ($r['definition_key'] ?? '') === 'low_diversification');
        expect($rec)->not->toBeNull();
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

    it('fires high_total_fees per-account when fee exceeds threshold', function () {
        $feeAnalyses = [
            1 => [
                'account_id' => 1,
                'account_name' => 'Test ISA',
                'total_fee_percent' => 1.5,
                'total_annual_fees' => 750,
                'weighted_ocf' => 0.3,
                'platform_fee' => 0.4,
            ],
        ];

        $investmentAnalysis = [
            'portfolio_summary' => ['accounts_count' => 1, 'holdings_count' => 3],
            'allocation_deviation' => ['needs_rebalancing' => false],
        ];

        $result = $this->service->evaluateAgentActions(
            $investmentAnalysis, [], collect(), collect(), $this->user->id, $feeAnalyses
        );

        $rec = collect($result['recommendations'])->first(fn ($r) => ($r['definition_key'] ?? '') === 'high_total_fees');
        expect($rec)->not->toBeNull()
            ->and($rec['scope'])->toBe('account');
    });

    it('does NOT fire high_total_fees when fee is below threshold', function () {
        $feeAnalyses = [
            1 => [
                'account_id' => 1,
                'account_name' => 'Low Fee ISA',
                'total_fee_percent' => 0.5,
                'total_annual_fees' => 250,
                'weighted_ocf' => 0.2,
                'platform_fee' => 0.3,
            ],
        ];

        $investmentAnalysis = [
            'portfolio_summary' => ['accounts_count' => 1, 'holdings_count' => 3],
            'allocation_deviation' => ['needs_rebalancing' => false],
        ];

        $result = $this->service->evaluateAgentActions(
            $investmentAnalysis, [], collect(), collect(), $this->user->id, $feeAnalyses
        );

        $rec = collect($result['recommendations'])->first(fn ($r) => ($r['definition_key'] ?? '') === 'high_total_fees');
        expect($rec)->toBeNull();
    });

    it('fires rebalance_portfolio when needs_rebalancing is true', function () {
        $investmentAnalysis = [
            'portfolio_summary' => ['accounts_count' => 1, 'holdings_count' => 3],
            'allocation_deviation' => ['needs_rebalancing' => true],
        ];

        $result = $this->service->evaluateAgentActions(
            $investmentAnalysis, [], collect(), collect(), $this->user->id, []
        );

        $rec = collect($result['recommendations'])->first(fn ($r) => ($r['definition_key'] ?? '') === 'rebalance_portfolio');
        expect($rec)->not->toBeNull();
    });

    it('fires tax_loss_harvesting when opportunities exist', function () {
        $investmentAnalysis = [
            'portfolio_summary' => ['accounts_count' => 1, 'holdings_count' => 3],
            'allocation_deviation' => ['needs_rebalancing' => false],
            'tax_efficiency' => [
                'harvesting_opportunities' => [
                    'opportunities_count' => 2,
                    'potential_tax_saving' => 500,
                ],
            ],
        ];

        $result = $this->service->evaluateAgentActions(
            $investmentAnalysis, [], collect(), collect(), $this->user->id, []
        );

        $rec = collect($result['recommendations'])->first(fn ($r) => ($r['definition_key'] ?? '') === 'tax_loss_harvesting');
        expect($rec)->not->toBeNull();
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
});

// =========================================================================
// evaluateAgentActions — Savings triggers
// =========================================================================

describe('evaluateAgentActions — savings triggers', function () {
    it('fires emergency_fund_critical when runway is below 3 months', function () {
        $savingsAnalysis = [
            'emergency_fund' => ['runway_months' => 1.5],
            'summary' => ['total_savings' => 3000, 'monthly_expenditure' => 2000],
        ];

        $investmentAnalysis = [
            'portfolio_summary' => ['accounts_count' => 0, 'holdings_count' => 0],
            'allocation_deviation' => ['needs_rebalancing' => false],
        ];

        $result = $this->service->evaluateAgentActions(
            $investmentAnalysis, $savingsAnalysis, collect(), collect(), $this->user->id, []
        );

        $rec = collect($result['recommendations'])->first(fn ($r) => ($r['definition_key'] ?? '') === 'emergency_fund_critical');
        expect($rec)->not->toBeNull()
            ->and($rec['category'])->toBe('Emergency Fund');
    });

    it('fires emergency_fund_grow when runway is between 3 and 6 months', function () {
        $savingsAnalysis = [
            'emergency_fund' => ['runway_months' => 4],
            'summary' => ['total_savings' => 8000, 'monthly_expenditure' => 2000],
        ];

        $investmentAnalysis = [
            'portfolio_summary' => ['accounts_count' => 0, 'holdings_count' => 0],
            'allocation_deviation' => ['needs_rebalancing' => false],
        ];

        $result = $this->service->evaluateAgentActions(
            $investmentAnalysis, $savingsAnalysis, collect(), collect(), $this->user->id, []
        );

        $rec = collect($result['recommendations'])->first(fn ($r) => ($r['definition_key'] ?? '') === 'emergency_fund_grow');
        expect($rec)->not->toBeNull();
    });

    it('resolves conflict: keeps only critical when both emergency fund actions would fire', function () {
        $savingsAnalysis = [
            'emergency_fund' => ['runway_months' => 2],
            'summary' => ['total_savings' => 4000, 'monthly_expenditure' => 2000],
        ];

        $investmentAnalysis = [
            'portfolio_summary' => ['accounts_count' => 0, 'holdings_count' => 0],
            'allocation_deviation' => ['needs_rebalancing' => false],
        ];

        $result = $this->service->evaluateAgentActions(
            $investmentAnalysis, $savingsAnalysis, collect(), collect(), $this->user->id, []
        );

        $critical = collect($result['recommendations'])->first(fn ($r) => ($r['definition_key'] ?? '') === 'emergency_fund_critical');
        $grow = collect($result['recommendations'])->first(fn ($r) => ($r['definition_key'] ?? '') === 'emergency_fund_grow');

        expect($critical)->not->toBeNull();
        expect($grow)->toBeNull();
    });
});

// =========================================================================
// evaluateAgentActions — Surplus waterfall triggers
// =========================================================================

describe('evaluateAgentActions — surplus waterfall triggers', function () {
    it('fires surplus_to_isa when surplus exists and ISA remaining', function () {
        $savingsAnalysis = [
            'emergency_fund' => ['runway_months' => 12],
            'summary' => [
                'total_savings' => 30000,
                'monthly_expenditure' => 2000,
            ],
            'isa_allowance' => [
                'remaining' => 15000,
            ],
        ];

        $investmentAnalysis = [
            'portfolio_summary' => ['accounts_count' => 1, 'holdings_count' => 3],
            'allocation_deviation' => ['needs_rebalancing' => false],
        ];

        $result = $this->service->evaluateAgentActions(
            $investmentAnalysis, $savingsAnalysis, collect(), collect(), $this->user->id, []
        );

        $rec = collect($result['recommendations'])->first(fn ($r) => ($r['definition_key'] ?? '') === 'surplus_to_isa');
        expect($rec)->not->toBeNull();
    });

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
        InvestmentActionDefinition::where('key', 'low_diversification')
            ->update(['trigger_config' => json_encode([
                'condition' => 'diversification_score_below',
                'threshold' => 90,
            ])]);

        $investmentAnalysis = [
            'portfolio_summary' => ['accounts_count' => 1, 'holdings_count' => 3],
            'allocation_deviation' => ['needs_rebalancing' => false],
            'diversification_score' => 80, // Below custom 90 threshold
        ];

        $result = $this->service->evaluateAgentActions(
            $investmentAnalysis, [], collect(), collect(), $this->user->id, []
        );

        $rec = collect($result['recommendations'])->first(fn ($r) => ($r['definition_key'] ?? '') === 'low_diversification');
        expect($rec)->not->toBeNull();
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
