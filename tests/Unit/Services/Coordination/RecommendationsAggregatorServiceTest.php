<?php

declare(strict_types=1);

use App\Agents\GoalsAgent;
use App\Agents\InvestmentAgent;
use App\Agents\ProtectionAgent;
use App\Agents\RetirementAgent;
use App\Agents\SavingsAgent;
use App\Models\User;
use App\Services\Coordination\ComposedTaxPlanService;
use App\Services\Coordination\RecommendationPersonaliser;
use App\Services\Coordination\RecommendationsAggregatorService;
use App\Services\Estate\ComprehensiveEstatePlanService;
use App\Services\PrerequisiteGateService;

beforeEach(function () {
    $this->user = User::factory()->create();

    // These cases assert the raw per-agent aggregation path (mocked agents).
    // The composed module-plan path (default on in production) has its own
    // coverage in RecommendationsAggregatorComposedModulesTest.
    config(['coordination.composed_module_plans' => false]);

    // Mock all the services with correct types matching the constructor
    $this->protectionEngine = Mockery::mock(ProtectionAgent::class);
    $this->savingsCalculator = Mockery::mock(SavingsAgent::class);
    $this->investmentAgent = Mockery::mock(InvestmentAgent::class);
    $this->retirementAgent = Mockery::mock(RetirementAgent::class);
    $this->estatePlanService = Mockery::mock(ComprehensiveEstatePlanService::class);
    $this->goalsAgent = Mockery::mock(GoalsAgent::class);
    $this->personaliser = Mockery::mock(RecommendationPersonaliser::class);
    $this->personaliser->shouldReceive('personaliseRecommendations')->andReturnUsing(fn ($recs, $user) => $recs);

    // Gate open for every module so existing module assertions remain valid
    $this->gate = Mockery::mock(PrerequisiteGateService::class);
    $this->gate->shouldReceive('enforce')->andReturn(['can_proceed' => true]);

    // Investment and goals agents return no recommendations by default
    $this->investmentAgent->shouldReceive('analyze')->andReturn(['data' => []]);
    $this->investmentAgent->shouldReceive('generateRecommendations')->andReturn(['recommendations' => []]);
    $this->goalsAgent->shouldReceive('analyze')->andReturn(['data' => []]);
    $this->goalsAgent->shouldReceive('generateRecommendations')->andReturn(['recommendations' => []]);

    // Retirement recommendations come from generateRecommendations(analyze data);
    // default to none — tests that need retirement recs override this stub.
    $this->retirementAgent->shouldReceive('generateRecommendations')->andReturn(['recommendations' => []])->byDefault();

    // ComposedTaxPlanService is final and cannot be Mockery-mocked — use the
    // real service. The gate mock opens every module (including
    // tax_optimisation), but a bare factory user (no income, no accounts,
    // empty tax_action_definitions table) fires no strategies, so the tax
    // block contributes no recommendations to these fixtures.
    $this->taxPlan = app(ComposedTaxPlanService::class);

    $this->service = new RecommendationsAggregatorService(
        $this->protectionEngine,
        $this->savingsCalculator,
        $this->investmentAgent,
        $this->retirementAgent,
        $this->estatePlanService,
        $this->goalsAgent,
        $this->personaliser,
        $this->gate,
        $this->taxPlan
    );
});

afterEach(function () {
    Mockery::close();
});

// Helper to set up empty mocks for all modules
function setupEmptyMocks($context): void
{
    $context->protectionEngine->shouldReceive('analyze')->andReturn(['data' => ['recommendations' => [], 'gaps' => []]]);
    $context->savingsCalculator->shouldReceive('analyze')->andReturn(['emergency_fund' => [], 'isa_allowance' => []]);
    $context->user->setRelation('investmentAccounts', collect([]));
    $context->retirementAgent->shouldReceive('analyze')->andReturn(['data' => ['recommendations' => [], 'summary' => []]]);
    $context->estatePlanService->shouldReceive('generateComprehensiveEstatePlan')->andReturn(['implementation_timeline' => []]);
}

it('returns recommendations from all modules via aggregateRecommendations', function () {
    // Protection returns recommendation via data.recommendations
    $this->protectionEngine->shouldReceive('analyze')->andReturn([
        'data' => [
            'recommendations' => [
                [
                    'recommendation_id' => 'prot_1',
                    'recommendation_text' => 'Increase life insurance coverage',
                    'priority_score' => 85.0,
                    'category' => 'risk_mitigation',
                ],
            ],
            'gaps' => [],
        ],
    ]);

    // Savings returns emergency fund recommendation
    $this->savingsCalculator->shouldReceive('analyze')->andReturn([
        'emergency_fund' => [
            'recommendation' => 'Build 6-month emergency fund',
            'runway_months' => 0.5,
            'target_months' => 6,
        ],
        'isa_allowance' => [],
    ]);

    $this->user->setRelation('investmentAccounts', collect([]));

    $this->retirementAgent->shouldReceive('analyze')->andReturn([
        'data' => ['recommendations' => [], 'summary' => []],
    ]);

    $this->estatePlanService->shouldReceive('generateComprehensiveEstatePlan')->andReturn([
        'implementation_timeline' => [],
    ]);

    $recommendations = $this->service->aggregateRecommendations($this->user->id);

    expect($recommendations)->toHaveCount(2);
    expect($recommendations[0]['module'])->toBe('savings'); // Higher priority (90)
    expect($recommendations[1]['module'])->toBe('protection'); // Lower priority (85)
});

it('sorts aggregated recommendations by priority score descending', function () {
    $this->protectionEngine->shouldReceive('analyze')->andReturn([
        'data' => [
            'recommendations' => [
                ['recommendation_id' => 'prot_1', 'recommendation_text' => 'Test 1', 'priority_score' => 50.0],
            ],
            'gaps' => [],
        ],
    ]);

    $this->savingsCalculator->shouldReceive('analyze')->andReturn([
        'emergency_fund' => [
            'recommendation' => 'Test 2',
            'runway_months' => 0.5,
            'target_months' => 6,
        ],
        'isa_allowance' => [],
    ]);

    $this->user->setRelation('investmentAccounts', collect([]));
    $this->retirementAgent->shouldReceive('analyze')->andReturn([
        'data' => [
            'recommendations' => [],
            'summary' => ['shortfall' => 5000],
        ],
    ]);
    // Retirement recs come from the action-definition engine via
    // generateRecommendations; priority 2 maps to score 80.
    $this->retirementAgent->shouldReceive('generateRecommendations')->andReturn([
        'recommendations' => [
            ['title' => 'Increase Pension Contributions', 'priority' => 2],
        ],
    ]);

    $this->estatePlanService->shouldReceive('generateComprehensiveEstatePlan')->andReturn([
        'implementation_timeline' => [],
    ]);

    $recommendations = $this->service->aggregateRecommendations($this->user->id);

    // One ranking (Batch B): savings critical (95 band) > retirement int 2 (high, 85) > protection unlabelled (medium, 60).
    expect($recommendations)->toHaveCount(3);
    expect(array_column($recommendations, 'module'))->toBe(['savings', 'retirement', 'protection']);
    expect(array_column($recommendations, 'impact'))->toBe(['high', 'high', 'medium']);
    expect($recommendations[0]['priority_score'])->toBeGreaterThan($recommendations[1]['priority_score']);
});

it('normalizes different recommendation formats', function () {
    $this->protectionEngine->shouldReceive('analyze')->andReturn([
        'data' => [
            'recommendations' => [
                [
                    'recommendation_id' => 'prot_1',
                    'recommendation_text' => 'Test recommendation',
                    'priority_score' => 75.0,
                ],
            ],
            'gaps' => [],
        ],
    ]);

    $this->savingsCalculator->shouldReceive('analyze')->andReturn([
        'emergency_fund' => [
            'recommendation' => 'Different format',
            'runway_months' => 2.0,
            'target_months' => 6,
        ],
        'isa_allowance' => [],
    ]);

    $this->user->setRelation('investmentAccounts', collect([]));
    $this->retirementAgent->shouldReceive('analyze')->andReturn(['data' => ['recommendations' => [], 'summary' => []]]);
    $this->estatePlanService->shouldReceive('generateComprehensiveEstatePlan')->andReturn(['implementation_timeline' => []]);

    $recommendations = $this->service->aggregateRecommendations($this->user->id);

    expect($recommendations)->toHaveCount(2);
    expect($recommendations[0])->toHaveKey('recommendation_id');
    expect($recommendations[0])->toHaveKey('recommendation_text');
    expect($recommendations[0])->toHaveKey('priority_score');
    expect($recommendations[0])->toHaveKey('module');
    expect($recommendations[0])->toHaveKey('timeline');
    expect($recommendations[0])->toHaveKey('category');
});

it('assigns the timeline from the seeded priority', function () {
    $this->protectionEngine->shouldReceive('analyze')->andReturn([
        'data' => [
            'recommendations' => [
                ['recommendation_id' => 'p1', 'recommendation_text' => 'Critical', 'priority' => 'critical'],
                ['recommendation_id' => 'p2', 'recommendation_text' => 'High', 'priority' => 'high'],
                ['recommendation_id' => 'p3', 'recommendation_text' => 'Medium', 'priority' => 'medium'],
                ['recommendation_id' => 'p4', 'recommendation_text' => 'Low', 'priority' => 'low'],
            ],
            'gaps' => [],
        ],
    ]);

    $this->savingsCalculator->shouldReceive('analyze')->andReturn(['emergency_fund' => [], 'isa_allowance' => []]);
    $this->user->setRelation('investmentAccounts', collect([]));
    $this->retirementAgent->shouldReceive('analyze')->andReturn(['data' => ['recommendations' => [], 'summary' => []]]);
    $this->estatePlanService->shouldReceive('generateComprehensiveEstatePlan')->andReturn(['implementation_timeline' => []]);

    $recommendations = $this->service->aggregateRecommendations($this->user->id);

    expect(array_column($recommendations, 'timeline'))->toBe(['immediate', 'immediate', 'short_term', 'medium_term']);
});

it('assigns the impact label from the seeded priority', function () {
    $this->protectionEngine->shouldReceive('analyze')->andReturn([
        'data' => [
            'recommendations' => [
                ['recommendation_id' => 'p1', 'recommendation_text' => 'High', 'priority' => 1, 'impact' => 'High'],
                ['recommendation_id' => 'p2', 'recommendation_text' => 'Medium', 'priority' => 3, 'impact' => 'Medium'],
                ['recommendation_id' => 'p3', 'recommendation_text' => 'Low', 'priority' => 4, 'impact' => 'Low'],
            ],
            'gaps' => [],
        ],
    ]);

    $this->savingsCalculator->shouldReceive('analyze')->andReturn(['emergency_fund' => [], 'isa_allowance' => []]);
    $this->user->setRelation('investmentAccounts', collect([]));
    $this->retirementAgent->shouldReceive('analyze')->andReturn(['data' => ['recommendations' => [], 'summary' => []]]);
    $this->estatePlanService->shouldReceive('generateComprehensiveEstatePlan')->andReturn(['implementation_timeline' => []]);

    $recommendations = $this->service->aggregateRecommendations($this->user->id);

    expect($recommendations[0]['impact'])->toBe('high');
    expect($recommendations[1]['impact'])->toBe('medium');
    expect($recommendations[2]['impact'])->toBe('low');
});

it('filters recommendations by module correctly', function () {
    $this->protectionEngine->shouldReceive('analyze')->andReturn([
        'data' => [
            'recommendations' => [
                ['recommendation_id' => 'p1', 'recommendation_text' => 'Protection rec', 'priority_score' => 75.0],
            ],
            'gaps' => [],
        ],
    ]);

    $this->savingsCalculator->shouldReceive('analyze')->andReturn([
        'emergency_fund' => [
            'recommendation' => 'Savings rec',
            'runway_months' => 2.0,
            'target_months' => 6,
        ],
        'isa_allowance' => [],
    ]);

    $this->user->setRelation('investmentAccounts', collect([]));
    $this->retirementAgent->shouldReceive('analyze')->andReturn(['data' => ['recommendations' => [], 'summary' => []]]);
    $this->estatePlanService->shouldReceive('generateComprehensiveEstatePlan')->andReturn(['implementation_timeline' => []]);

    $protectionRecs = $this->service->getRecommendationsByModule($this->user->id, 'protection');
    $savingsRecs = $this->service->getRecommendationsByModule($this->user->id, 'savings');

    expect($protectionRecs)->toHaveCount(1);
    expect($savingsRecs)->toHaveCount(1);
    expect(array_values($protectionRecs)[0]['module'])->toBe('protection');
    expect(array_values($savingsRecs)[0]['module'])->toBe('savings');
});

it('filters recommendations by priority correctly', function () {
    $this->protectionEngine->shouldReceive('analyze')->andReturn([
        'data' => [
            'recommendations' => [
                ['recommendation_id' => 'p1', 'recommendation_text' => 'High priority', 'priority' => 'high'],
                ['recommendation_id' => 'p2', 'recommendation_text' => 'Low priority', 'priority' => 'low'],
            ],
            'gaps' => [],
        ],
    ]);

    $this->savingsCalculator->shouldReceive('analyze')->andReturn(['emergency_fund' => [], 'isa_allowance' => []]);
    $this->user->setRelation('investmentAccounts', collect([]));
    $this->retirementAgent->shouldReceive('analyze')->andReturn(['data' => ['recommendations' => [], 'summary' => []]]);
    $this->estatePlanService->shouldReceive('generateComprehensiveEstatePlan')->andReturn(['implementation_timeline' => []]);

    $highPriorityRecs = $this->service->getRecommendationsByPriority($this->user->id, 'high');
    $lowPriorityRecs = $this->service->getRecommendationsByPriority($this->user->id, 'low');

    expect($highPriorityRecs)->toHaveCount(1);
    expect($lowPriorityRecs)->toHaveCount(1);
});

it('returns limited results from getTopRecommendations', function () {
    $this->protectionEngine->shouldReceive('analyze')->andReturn([
        'data' => [
            'recommendations' => [
                ['recommendation_id' => 'p1', 'recommendation_text' => 'Rec 1', 'priority' => 'critical'],
                ['recommendation_id' => 'p2', 'recommendation_text' => 'Rec 2', 'priority' => 'high'],
                ['recommendation_id' => 'p3', 'recommendation_text' => 'Rec 3', 'priority' => 'medium'],
                ['recommendation_id' => 'p4', 'recommendation_text' => 'Rec 4', 'priority' => 'low'],
                ['recommendation_id' => 'p5', 'recommendation_text' => 'Rec 5', 'priority' => 'low'],
            ],
            'gaps' => [],
        ],
    ]);

    $this->savingsCalculator->shouldReceive('analyze')->andReturn(['emergency_fund' => [], 'isa_allowance' => []]);
    $this->user->setRelation('investmentAccounts', collect([]));
    $this->retirementAgent->shouldReceive('analyze')->andReturn(['data' => ['recommendations' => [], 'summary' => []]]);
    $this->estatePlanService->shouldReceive('generateComprehensiveEstatePlan')->andReturn(['implementation_timeline' => []]);

    $topRecs = $this->service->getTopRecommendations($this->user->id, 3);

    expect($topRecs)->toHaveCount(3);
    expect(array_column($topRecs, 'recommendation_id'))->toBe(['p1', 'p2', 'p3']);
});

it('calculates correct statistics in getSummary', function () {
    $this->protectionEngine->shouldReceive('analyze')->andReturn([
        'data' => [
            'recommendations' => [
                [
                    'recommendation_id' => 'p1',
                    'recommendation_text' => 'High priority protection',
                    'priority' => 'high',
                    'estimated_cost' => 1000.0,
                    'potential_benefit' => 50000.0,
                ],
            ],
            'gaps' => [],
        ],
    ]);

    $this->savingsCalculator->shouldReceive('analyze')->andReturn([
        'emergency_fund' => [
            'recommendation' => 'Medium priority savings',
            'runway_months' => 2.0,
            'target_months' => 6,
        ],
        'isa_allowance' => [],
    ]);

    $this->user->setRelation('investmentAccounts', collect([]));
    $this->retirementAgent->shouldReceive('analyze')->andReturn(['data' => ['recommendations' => [], 'summary' => []]]);
    $this->estatePlanService->shouldReceive('generateComprehensiveEstatePlan')->andReturn(['implementation_timeline' => []]);

    $summary = $this->service->getSummary($this->user->id);

    expect($summary['total_count'])->toBe(2);
    expect($summary['by_priority']['high'])->toBe(1);
    expect($summary['by_priority']['medium'])->toBe(1);
    expect($summary['by_module']['protection'])->toBe(1);
    expect($summary['by_module']['savings'])->toBe(1);
    expect($summary['total_estimated_cost'])->toBe(1000.0);
    expect($summary['total_potential_benefit'])->toBe(50000.0);
});

it('handles service exceptions gracefully during aggregation', function () {
    $this->protectionEngine->shouldReceive('analyze')->andThrow(new Exception('Protection service error'));

    $this->savingsCalculator->shouldReceive('analyze')->andReturn([
        'emergency_fund' => [
            'recommendation' => 'Savings rec',
            'runway_months' => 2.0,
            'target_months' => 6,
        ],
        'isa_allowance' => [],
    ]);

    $this->user->setRelation('investmentAccounts', collect([]));
    $this->retirementAgent->shouldReceive('analyze')->andReturn(['data' => ['recommendations' => [], 'summary' => []]]);
    $this->estatePlanService->shouldReceive('generateComprehensiveEstatePlan')->andReturn(['implementation_timeline' => []]);

    $recommendations = $this->service->aggregateRecommendations($this->user->id);

    // Should still return savings recommendation despite protection error
    expect($recommendations)->toHaveCount(1);
    expect($recommendations[0]['module'])->toBe('savings');
});

it('assigns correct category based on module', function () {
    $this->protectionEngine->shouldReceive('analyze')->andReturn([
        'data' => [
            'recommendations' => [
                ['recommendation_id' => 'p1', 'recommendation_text' => 'Protection', 'priority_score' => 75.0],
            ],
            'gaps' => [],
        ],
    ]);

    $this->savingsCalculator->shouldReceive('analyze')->andReturn([
        'emergency_fund' => [
            'recommendation' => 'Savings',
            'runway_months' => 2.0,
            'target_months' => 6,
        ],
        'isa_allowance' => [],
    ]);

    $this->user->setRelation('investmentAccounts', collect([]));
    $this->retirementAgent->shouldReceive('analyze')->andReturn([
        'data' => [
            'recommendations' => [],
            'summary' => ['shortfall' => 5000],
        ],
    ]);
    $this->retirementAgent->shouldReceive('generateRecommendations')->andReturn([
        'recommendations' => [
            ['title' => 'Close pension shortfall', 'priority' => 2, 'category' => 'income_shortfall'],
        ],
    ]);

    $this->estatePlanService->shouldReceive('generateComprehensiveEstatePlan')->andReturn([
        'implementation_timeline' => [
            ['action' => 'Estate action', 'priority' => 1, 'category' => 'estate_planning'],
        ],
    ]);

    $recommendations = $this->service->aggregateRecommendations($this->user->id);

    $protectionRec = collect($recommendations)->firstWhere('module', 'protection');
    $savingsRec = collect($recommendations)->firstWhere('module', 'savings');
    $retirementRec = collect($recommendations)->firstWhere('module', 'retirement');
    $estateRec = collect($recommendations)->firstWhere('module', 'estate');

    expect($protectionRec['category'])->toBe('risk_mitigation');
    expect($savingsRec['category'])->toBe('emergency_fund');
    expect($retirementRec['category'])->toBe('income_shortfall');
    expect($estateRec['category'])->toBe('estate_planning');
});

it('handles non-numeric iht_saving gracefully during aggregation', function () {
    setupEmptyMocks($this);

    // Override estate mock with 'Variable' iht_saving
    $this->estatePlanService = Mockery::mock(ComprehensiveEstatePlanService::class);
    $this->estatePlanService->shouldReceive('generateComprehensiveEstatePlan')->andReturn([
        'implementation_timeline' => [
            ['action' => 'Downsize property', 'priority' => 2, 'iht_saving' => 'Variable'],
        ],
    ]);

    $personaliser = Mockery::mock(RecommendationPersonaliser::class);
    $personaliser->shouldReceive('personaliseRecommendations')->andReturnUsing(fn ($recs, $user) => $recs);

    $service = new RecommendationsAggregatorService(
        $this->protectionEngine,
        $this->savingsCalculator,
        $this->investmentAgent,
        $this->retirementAgent,
        $this->estatePlanService,
        $this->goalsAgent,
        $personaliser,
        $this->gate,
        $this->taxPlan
    );

    $recommendations = $service->aggregateRecommendations($this->user->id);
    $estateRec = collect($recommendations)->firstWhere('module', 'estate');

    expect($estateRec)->not->toBeNull();
    expect($estateRec['potential_benefit'])->toBeNull();

    // Verify getSummary doesn't throw TypeError
    $summary = $service->getSummary($this->user->id);
    expect($summary['total_potential_benefit'])->toBe(0);
});
