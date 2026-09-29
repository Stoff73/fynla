<?php

declare(strict_types=1);

use App\Constants\QuerySchemas;
use App\Models\User;
use App\Services\AI\AdvicePromptBuilder;
use Database\Seeders\TaxConfigurationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * B3 — module-scoped financial context.
 *
 * Contract:
 *   - Retirement-scoped classification → context renders retirement data, the
 *     ranked-recommendations block (always rendered), net worth totals, and
 *     monthly surplus/shortfall. Protection data ("Total life cover", "Coverage gap")
 *     must NOT appear.
 *   - Holistic classification (holistic_health) → all module blocks render.
 *   - Null classification → all module blocks render (legacy callers).
 *
 * The test drives buildFinancialContext directly with a mock orchestrateAnalysis
 * callable so it does not require live agent services.
 */
uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(TaxConfigurationSeeder::class);
    $this->builder = app(AdvicePromptBuilder::class);

    // Mock orchestrateAnalysis callable returning stubbed module data for all
    // modules so the filter has something to include/exclude.
    $this->mockAnalysis = function (int $userId): array {
        return [
            'available_surplus' => 500,
            'module_analysis' => [
                'retirement' => [
                    'total_pension_value' => 120_000,
                    'projected_annual_income' => 18_000,
                    'income_gap' => 5_000,
                ],
                'protection' => [
                    'full_analysis' => ['total_cover' => 300_000],
                    'coverage_gap' => 50_000,
                ],
                'savings' => [
                    'total_savings' => 25_000,
                    'emergency_fund_months' => 6,
                ],
                'investment' => [
                    'total_portfolio_value' => 80_000,
                ],
                'estate' => [
                    'iht_liability' => 40_000,
                ],
            ],
            'ranked_recommendations' => [
                [
                    'title' => 'Increase pension contributions',
                    'module' => 'retirement',
                    'urgency_score' => 80,
                    'description' => 'You are behind target for retirement.',
                    'action' => 'Contribute £200 more per month.',
                ],
                [
                    'title' => 'Review life cover',
                    'module' => 'protection',
                    'urgency_score' => 60,
                    'description' => 'Your cover does not meet the recommended level.',
                    'action' => 'Get a term policy for £100,000.',
                ],
            ],
            'cashflow_allocation' => [],
            'shortfall_analysis' => ['has_shortfall' => false],
            'conflicts' => [],
            'cross_module_strategies' => [],
        ];
    };
});

describe('ModuleScopedFinancialContext — retirement-scoped classification', function () {

    it('includes retirement section data and the recommendations block', function () {
        $user = User::factory()->create();

        $classification = [
            'primary' => QuerySchemas::RETIREMENT_CONTRIBUTION,
            'related' => [],
            'modules' => ['retirement'],
        ];

        $context = $this->builder->buildFinancialContext($user, $this->mockAnalysis, $classification);

        // Retirement data must be present
        expect($context)
            ->toContain('Total pension value')
            ->toContain('Projected retirement income')
            ->toContain('Retirement income gap');

        // Recommendations block is always rendered
        expect($context)->toContain('Top ranked recommendations');
    });

    it('excludes the protection section from a retirement-scoped context', function () {
        $user = User::factory()->create();

        $classification = [
            'primary' => QuerySchemas::RETIREMENT_CONTRIBUTION,
            'related' => [],
            'modules' => ['retirement'],
        ];

        $context = $this->builder->buildFinancialContext($user, $this->mockAnalysis, $classification);

        // Protection-specific lines must be absent
        expect($context)
            ->not->toContain('Total life cover')
            ->not->toContain('Coverage gap');
    });

    it('always renders net worth and monthly surplus regardless of classification scope', function () {
        $user = User::factory()->create();

        $classification = [
            'primary' => QuerySchemas::RETIREMENT_CONTRIBUTION,
            'related' => [],
            'modules' => ['retirement'],
        ];

        $context = $this->builder->buildFinancialContext($user, $this->mockAnalysis, $classification);

        // Net worth totals are cross-module — always rendered
        expect($context)->toContain('Total net worth');
        // Monthly surplus is always rendered
        expect($context)->toContain('Monthly surplus');
    });
});

describe('ModuleScopedFinancialContext — holistic classification renders all modules', function () {

    it('contains both retirement and protection data for a holistic_health classification', function () {
        $user = User::factory()->create();

        $classification = [
            'primary' => QuerySchemas::HOLISTIC_HEALTH,
            'related' => [],
            'modules' => ['savings', 'investment', 'retirement', 'protection', 'estate'],
        ];

        $context = $this->builder->buildFinancialContext($user, $this->mockAnalysis, $classification);

        expect($context)
            ->toContain('Total pension value')
            ->toContain('Total life cover')
            ->toContain('Total savings')
            ->toContain('Investment portfolio')
            ->toContain('Estimated Inheritance Tax liability')
            ->toContain('Top ranked recommendations');
    });
});

describe('ModuleScopedFinancialContext — null classification renders all modules', function () {

    it('renders all module blocks when classification is null (legacy callers)', function () {
        $user = User::factory()->create();

        $context = $this->builder->buildFinancialContext($user, $this->mockAnalysis, null);

        expect($context)
            ->toContain('Total pension value')
            ->toContain('Total life cover')
            ->toContain('Total savings')
            ->toContain('Investment portfolio')
            ->toContain('Estimated Inheritance Tax liability');
    });
});

describe('ModuleScopedFinancialContext — protection-scoped classification', function () {

    it('includes protection data and excludes retirement section data', function () {
        $user = User::factory()->create();

        $classification = [
            'primary' => QuerySchemas::PROTECTION_COVER,
            'related' => [],
            'modules' => ['protection'],
        ];

        $context = $this->builder->buildFinancialContext($user, $this->mockAnalysis, $classification);

        expect($context)->toContain('Total life cover');
        expect($context)->not->toContain('Total pension value');
        expect($context)->not->toContain('Total savings');
    });
});
