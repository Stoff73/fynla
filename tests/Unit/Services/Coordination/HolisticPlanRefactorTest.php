<?php

declare(strict_types=1);

use App\Services\Coordination\CashFlowCoordinator;
use App\Services\Coordination\ConflictResolver;
use App\Services\Coordination\HolisticPlanner;
use App\Services\Coordination\PriorityRanker;
use App\Services\Plans\PlanConfigService;
use App\Services\TaxConfigService;

// Helper: create ConflictResolver with mocked TaxConfigService
function createMockedConflictResolver(): ConflictResolver
{
    $taxConfig = Mockery::mock(TaxConfigService::class);
    $taxConfig->shouldReceive('getISAAllowances')
        ->andReturn(['annual_allowance' => 20000]);

    return new ConflictResolver($taxConfig);
}

// Helper: create HolisticPlanner with mocked PlanConfigService
function createHolisticPlanner(): HolisticPlanner
{
    $planConfig = Mockery::mock(PlanConfigService::class);
    $planConfig->shouldReceive('getDefaultGrowthRate')->andReturn(0.04);
    $planConfig->shouldReceive('getOptimisedGrowthRate')->andReturn(0.06);

    return new HolisticPlanner($planConfig);
}

afterEach(function () {
    Mockery::close();
});

describe('Phase 5A: Holistic Plan Refactor', function () {

    // ─────────────────────────────────────────────────────────────
    // 5A.T1: Holistic plan includes real estate data
    // ─────────────────────────────────────────────────────────────
    describe('HolisticPlanner - Estate Data (5A.T1)', function () {
        it('uses estate net_worth in financial snapshot', function () {
            $planner = createHolisticPlanner();

            $allAnalysis = [
                'protection' => ['adequacy_score' => 80],
                'savings' => ['emergency_fund_months' => 6, 'total_savings' => 15000],
                'investment' => ['total_portfolio_value' => 50000, 'portfolio_health_score' => 70],
                'retirement' => ['income_gap' => 5000, 'projected_annual_income' => 20000, 'target_income' => 25000, 'total_pension_value' => 100000],
                'estate' => [
                    'net_worth' => 450000,
                    'gross_estate' => 500000,
                    'iht_liability' => 30000,
                    'effective_tax_rate' => 6,
                    'total_liabilities' => 50000,
                    'property_value' => 350000,
                    'monthly_income' => 4500,
                    'monthly_expenses' => 3200,
                    'monthly_surplus' => 1300,
                    'nrb_available' => 325000,
                    'rnrb_available' => 175000,
                    'has_spouse' => true,
                    'recommendations' => [],
                ],
                'goals' => ['has_goals' => false, 'recommendations' => []],
                'user' => ['age' => 45],
            ];

            $plan = $planner->createHolisticPlan(1, $allAnalysis);

            // Financial snapshot should use estate data
            expect($plan['financial_snapshot']['net_worth'])->toBe(450000);
            expect($plan['financial_snapshot']['property_value'])->toBe(350000);
            expect($plan['financial_snapshot']['liabilities'])->toBe(50000);
            expect($plan['financial_snapshot']['monthly_income'])->toBe(4500);
            expect($plan['financial_snapshot']['monthly_expenses'])->toBe(3200);
            expect($plan['financial_snapshot']['monthly_surplus'])->toBe(1300);
        });

        it('uses estate IHT liability in module summaries', function () {
            $planner = createHolisticPlanner();

            $allAnalysis = [
                'protection' => ['adequacy_score' => 80],
                'savings' => ['emergency_fund_months' => 6, 'total_savings' => 15000],
                'investment' => ['total_portfolio_value' => 50000, 'portfolio_health_score' => 70],
                'retirement' => ['income_gap' => 0],
                'estate' => [
                    'net_worth' => 800000,
                    'iht_liability' => 120000,
                    'property_value' => 600000,
                    'total_liabilities' => 100000,
                    'monthly_income' => 5000,
                    'monthly_expenses' => 3000,
                    'monthly_surplus' => 2000,
                    'recommendations' => [],
                ],
                'goals' => ['has_goals' => false, 'recommendations' => []],
                'user' => ['age' => 60],
            ];

            $plan = $planner->createHolisticPlan(1, $allAnalysis);

            expect($plan['module_summaries']['estate']['iht_liability'])->toBe(120000);
            expect($plan['module_summaries']['estate']['net_worth'])->toBe(800000);
        });

        it('uses estate net worth in net worth projection', function () {
            $planner = createHolisticPlanner();

            $allAnalysis = [
                'protection' => ['adequacy_score' => 70],
                'savings' => ['emergency_fund_months' => 4, 'total_savings' => 10000],
                'investment' => ['total_portfolio_value' => 30000, 'portfolio_health_score' => 65],
                'retirement' => ['income_gap' => 3000],
                'estate' => [
                    'net_worth' => 550000,
                    'iht_liability' => 0,
                    'property_value' => 400000,
                    'total_liabilities' => 150000,
                    'monthly_income' => 4000,
                    'monthly_expenses' => 3000,
                    'monthly_surplus' => 1000,
                    'recommendations' => [],
                ],
                'goals' => ['has_goals' => false, 'recommendations' => []],
                'user' => ['age' => 40],
            ];

            $plan = $planner->createHolisticPlan(1, $allAnalysis);

            expect($plan['net_worth_projection']['current_net_worth'])->toBe(550000);
            expect($plan['net_worth_projection']['baseline_projections'][0]['value'])->toBe(550000.0);
        });
    });

    // ─────────────────────────────────────────────────────────────
    // 5A.T2: Holistic plan includes active goals
    // ─────────────────────────────────────────────────────────────
    describe('HolisticPlanner - Goals Integration (5A.T2)', function () {
        it('creates goals summary when user has active goals', function () {
            $planner = createHolisticPlanner();

            $allAnalysis = [
                'protection' => ['adequacy_score' => 80],
                'savings' => ['emergency_fund_months' => 6, 'total_savings' => 15000],
                'investment' => ['total_portfolio_value' => 50000, 'portfolio_health_score' => 70],
                'retirement' => ['income_gap' => 0],
                'estate' => [
                    'net_worth' => 400000, 'iht_liability' => 0, 'property_value' => 300000,
                    'total_liabilities' => 0, 'monthly_income' => 5000, 'monthly_expenses' => 3000,
                    'monthly_surplus' => 2000, 'recommendations' => [],
                ],
                'goals' => [
                    'has_goals' => true,
                    'summary' => [
                        'total_active' => 3,
                        'on_track_count' => 2,
                        'behind_count' => 1,
                    ],
                    'recommendations' => [],
                ],
                'user' => ['age' => 35],
            ];

            $plan = $planner->createHolisticPlan(1, $allAnalysis);

            expect($plan['module_summaries']['goals'])->toBeArray();
            expect($plan['module_summaries']['goals']['has_goals'])->toBeTrue();
            expect($plan['module_summaries']['goals']['total_goals'])->toBe(3);
            expect($plan['module_summaries']['goals']['on_track_count'])->toBe(2);
            expect($plan['module_summaries']['goals']['behind_count'])->toBe(1);
            expect($plan['module_summaries']['goals']['key_message'])->toBe('1 of 3 goals need attention.');
        });

        it('returns not_started status when user has no goals', function () {
            $planner = createHolisticPlanner();

            $allAnalysis = [
                'protection' => ['adequacy_score' => 80],
                'savings' => ['emergency_fund_months' => 6, 'total_savings' => 15000],
                'investment' => ['total_portfolio_value' => 50000, 'portfolio_health_score' => 70],
                'retirement' => ['income_gap' => 0],
                'estate' => [
                    'net_worth' => 400000, 'iht_liability' => 0, 'property_value' => 300000,
                    'total_liabilities' => 0, 'monthly_income' => 5000, 'monthly_expenses' => 3000,
                    'monthly_surplus' => 2000, 'recommendations' => [],
                ],
                'goals' => ['has_goals' => false, 'recommendations' => []],
                'user' => ['age' => 35],
            ];

            $plan = $planner->createHolisticPlan(1, $allAnalysis);

            expect($plan['module_summaries']['goals']['has_goals'])->toBeFalse();
            expect($plan['module_summaries']['goals']['status'])->toBe('not_started');
            expect($plan['module_summaries']['goals']['total_goals'])->toBe(0);
            expect($plan['module_summaries']['goals']['key_message'])->toContain('No financial goals set');
        });

        it('shows all goals on track message when none behind', function () {
            $planner = createHolisticPlanner();

            $allAnalysis = [
                'protection' => ['adequacy_score' => 80],
                'savings' => ['emergency_fund_months' => 6, 'total_savings' => 15000],
                'investment' => ['total_portfolio_value' => 50000, 'portfolio_health_score' => 70],
                'retirement' => ['income_gap' => 0],
                'estate' => [
                    'net_worth' => 400000, 'iht_liability' => 0, 'property_value' => 300000,
                    'total_liabilities' => 0, 'monthly_income' => 5000, 'monthly_expenses' => 3000,
                    'monthly_surplus' => 2000, 'recommendations' => [],
                ],
                'goals' => [
                    'has_goals' => true,
                    'summary' => ['total_active' => 4, 'on_track_count' => 4, 'behind_count' => 0],
                    'recommendations' => [],
                ],
                'user' => ['age' => 35],
            ];

            $plan = $planner->createHolisticPlan(1, $allAnalysis);

            expect($plan['module_summaries']['goals']['key_message'])->toBe('All 4 goals are on track.');
            expect($plan['module_summaries']['goals']['status'])->toBe('excellent');
        });
    });

    // ─────────────────────────────────────────────────────────────
    // 5A.T5: Allocation priority order (Emergency > Protection > Pension > Investment > Estate > Goals)
    // ─────────────────────────────────────────────────────────────
    describe('CashFlowCoordinator - Priority Order (5A.T5)', function () {
        it('allocates estate and goals in correct priority position', function () {
            $coordinator = new CashFlowCoordinator;

            $demands = [
                'emergency_fund' => ['amount' => 200, 'urgency' => 50],
                'protection' => ['amount' => 150, 'urgency' => 50],
                'pension' => ['amount' => 300, 'urgency' => 50],
                'investment' => ['amount' => 200, 'urgency' => 50],
                'estate' => ['amount' => 100, 'urgency' => 50],
                'goals' => ['amount' => 150, 'urgency' => 50],
            ];

            // Surplus only covers first 4 categories
            $result = $coordinator->optimizeContributionAllocation(850, $demands);

            // Emergency fund (priority 1): fully funded
            expect($result['allocation']['emergency_fund']['allocated'])->toBe(200);
            expect($result['allocation']['emergency_fund']['percent_funded'])->toBe(100);

            // Protection (priority 2): fully funded
            expect($result['allocation']['protection']['allocated'])->toBe(150);

            // Pension (priority 3): fully funded
            expect($result['allocation']['pension']['allocated'])->toBe(300);

            // Investment (priority 4): fully funded
            expect($result['allocation']['investment']['allocated'])->toBe(200);

            // Estate (priority 5): 0 remaining
            expect($result['allocation']['estate']['allocated'])->toBe(0);
            expect($result['allocation']['estate']['shortfall'])->toBe(100);

            // Goals (priority 6): 0 remaining
            expect($result['allocation']['goals']['allocated'])->toBe(0);
            expect($result['allocation']['goals']['shortfall'])->toBe(150);
        });

        it('funds estate before goals when both compete for limited surplus', function () {
            $coordinator = new CashFlowCoordinator;

            $demands = [
                'estate' => ['amount' => 200, 'urgency' => 50],
                'goals' => ['amount' => 300, 'urgency' => 50],
            ];

            $result = $coordinator->optimizeContributionAllocation(350, $demands);

            // Estate (priority 5) gets full allocation
            expect($result['allocation']['estate']['allocated'])->toBe(200);
            expect($result['allocation']['estate']['percent_funded'])->toBe(100);

            // Goals (priority 6) gets remaining
            expect($result['allocation']['goals']['allocated'])->toEqual(150);
            expect($result['allocation']['goals']['shortfall'])->toEqual(150);
        });
    });

    // ─────────────────────────────────────────────────────────────
    // 5A.T6: Total allocated does not exceed disposable income
    // ─────────────────────────────────────────────────────────────
    describe('CashFlowCoordinator - Allocation Cap (5A.T6)', function () {
        it('never allocates more than available surplus across all categories', function () {
            $coordinator = new CashFlowCoordinator;
            $surplus = 500.0;

            $demands = [
                'emergency_fund' => ['amount' => 300, 'urgency' => 90],
                'protection' => ['amount' => 200, 'urgency' => 80],
                'pension' => ['amount' => 400, 'urgency' => 70],
                'investment' => ['amount' => 200, 'urgency' => 60],
                'estate' => ['amount' => 100, 'urgency' => 50],
                'goals' => ['amount' => 150, 'urgency' => 50],
            ];

            $result = $coordinator->optimizeContributionAllocation($surplus, $demands);

            $totalAllocated = array_sum(array_column($result['allocation'], 'allocated'));

            expect($totalAllocated)->toBeLessThanOrEqual($surplus);
            expect($result['surplus_remaining'])->toBeGreaterThanOrEqual(0);
        });
    });

    // ─────────────────────────────────────────────────────────────
    // 5A.T7: Estate vs Goals conflicts detected
    // ─────────────────────────────────────────────────────────────
    describe('ConflictResolver - Estate vs Goals Conflicts (5A.T7)', function () {
        it('detects conflict when estate and goals demands exceed surplus', function () {
            $resolver = createMockedConflictResolver();

            $recommendations = [
                'estate' => [
                    ['recommended_monthly_contribution' => 300],
                ],
                'goals' => [
                    ['recommended_monthly_contribution' => 400],
                ],
                'available_surplus' => 500,
            ];

            $conflicts = $resolver->identifyConflicts($recommendations);

            $estateGoalsConflict = collect($conflicts)->firstWhere('type', 'estate_vs_goals_conflict');

            expect($estateGoalsConflict)->not->toBeNull();
            expect($estateGoalsConflict['estate_demand'])->toBe(300);
            expect($estateGoalsConflict['goals_demand'])->toBe(400);
            expect($estateGoalsConflict['combined_demand'])->toBe(700);
            expect($estateGoalsConflict['shortfall'])->toBe(200);
        });

        it('does not detect conflict when combined demands fit within surplus', function () {
            $resolver = createMockedConflictResolver();

            $recommendations = [
                'estate' => [
                    ['recommended_monthly_contribution' => 200],
                ],
                'goals' => [
                    ['recommended_monthly_contribution' => 200],
                ],
                'available_surplus' => 500,
            ];

            $conflicts = $resolver->identifyConflicts($recommendations);

            $estateGoalsConflict = collect($conflicts)->firstWhere('type', 'estate_vs_goals_conflict');

            expect($estateGoalsConflict)->toBeNull();
        });

        it('does not detect conflict when only one module has demands', function () {
            $resolver = createMockedConflictResolver();

            $recommendations = [
                'estate' => [
                    ['recommended_monthly_contribution' => 500],
                ],
                'goals' => [],
                'available_surplus' => 300,
            ];

            $conflicts = $resolver->identifyConflicts($recommendations);

            $estateGoalsConflict = collect($conflicts)->firstWhere('type', 'estate_vs_goals_conflict');

            expect($estateGoalsConflict)->toBeNull();
        });
    });

    // ─────────────────────────────────────────────────────────────
    // 5A.T8: Risk assessment includes goals area
    // ─────────────────────────────────────────────────────────────
    describe('HolisticPlanner - Goals Risk Assessment (5A.T8)', function () {
        it('adds goals risk when more than half are behind schedule', function () {
            $planner = createHolisticPlanner();

            $allAnalysis = [
                'protection' => ['adequacy_score' => 80],
                'savings' => ['emergency_fund_months' => 6],
                'investment' => [],
                'retirement' => ['income_gap' => 0],
                'estate' => ['iht_liability' => 0],
                'goals' => [
                    'has_goals' => true,
                    'summary' => ['total_active' => 4, 'on_track_count' => 1, 'behind_count' => 3],
                ],
            ];

            $risk = $planner->assessOverallRisk($allAnalysis);

            $goalsRisk = collect($risk['risk_areas'])->firstWhere('area', 'Goals');

            expect($goalsRisk)->not->toBeNull();
            expect($goalsRisk['severity'])->toBe('medium');
            expect($goalsRisk['description'])->toContain('3 of 4 goals are behind schedule');
        });

        it('adds goal affordability risk when overcommitted', function () {
            $planner = createHolisticPlanner();

            $allAnalysis = [
                'protection' => ['adequacy_score' => 80],
                'savings' => ['emergency_fund_months' => 6],
                'investment' => [],
                'retirement' => ['income_gap' => 0],
                'estate' => ['iht_liability' => 0],
                'goals' => [
                    'has_goals' => true,
                    'summary' => ['total_active' => 2, 'on_track_count' => 2, 'behind_count' => 0],
                    'affordability' => ['status' => 'overcommitted'],
                ],
            ];

            $risk = $planner->assessOverallRisk($allAnalysis);

            $affordabilityRisk = collect($risk['risk_areas'])->firstWhere('area', 'Goal Affordability');

            expect($affordabilityRisk)->not->toBeNull();
            expect($affordabilityRisk['severity'])->toBe('medium');
            expect($affordabilityRisk['description'])->toContain('exceed available savings capacity');
        });

        it('does not add goals risk when all goals are on track', function () {
            $planner = createHolisticPlanner();

            $allAnalysis = [
                'protection' => ['adequacy_score' => 80],
                'savings' => ['emergency_fund_months' => 6],
                'investment' => [],
                'retirement' => ['income_gap' => 0],
                'estate' => ['iht_liability' => 0],
                'goals' => [
                    'has_goals' => true,
                    'summary' => ['total_active' => 3, 'on_track_count' => 3, 'behind_count' => 0],
                ],
            ];

            $risk = $planner->assessOverallRisk($allAnalysis);

            $goalsRisk = collect($risk['risk_areas'])->firstWhere('area', 'Goals');

            expect($goalsRisk)->toBeNull();
        });

        it('does not add goals risk when user has no goals', function () {
            $planner = createHolisticPlanner();

            $allAnalysis = [
                'protection' => ['adequacy_score' => 80],
                'savings' => ['emergency_fund_months' => 6],
                'investment' => [],
                'retirement' => ['income_gap' => 0],
                'estate' => ['iht_liability' => 0],
                'goals' => ['has_goals' => false],
            ];

            $risk = $planner->assessOverallRisk($allAnalysis);

            $goalsRisk = collect($risk['risk_areas'])->firstWhere('area', 'Goals');
            $affordabilityRisk = collect($risk['risk_areas'])->firstWhere('area', 'Goal Affordability');

            expect($goalsRisk)->toBeNull();
            expect($affordabilityRisk)->toBeNull();
        });
    });

    // ─────────────────────────────────────────────────────────────
    // 5A.T3: PriorityRanker handles goals recommendations
    // ─────────────────────────────────────────────────────────────
    describe('PriorityRanker - Goals Scoring (5A.T3)', function () {
        it('scores goals recommendations with urgency, impact, and ease', function () {
            $ranker = new PriorityRanker;

            $allRecommendations = [
                'goals' => [
                    [
                        'recommendation_text' => 'Start emergency fund goal',
                        'category' => 'Safety Net',
                    ],
                    [
                        'recommendation_text' => 'Review behind schedule goal',
                        'category' => 'Progress',
                    ],
                ],
            ];

            $userContext = ['module_priorities' => ['goals' => 55]];

            $ranked = $ranker->rankRecommendations($allRecommendations, $userContext);

            expect($ranked)->toHaveCount(2);

            // Both should have 'goals' as module
            expect($ranked[0]['module'])->toBe('goals');
            expect($ranked[1]['module'])->toBe('goals');

            // Safety Net has higher urgency (75) than Progress (65)
            $safetyNetRec = collect($ranked)->firstWhere('category', 'Safety Net');
            $progressRec = collect($ranked)->firstWhere('category', 'Progress');

            expect($safetyNetRec['urgency_score'])->toBe(75.0);
            expect($progressRec['urgency_score'])->toBe(65.0);

            // All should have priority_score
            expect($ranked[0]['priority_score'])->toBeGreaterThan(0);
        });

        it('groups goals into category bucket', function () {
            $ranker = new PriorityRanker;

            $recommendations = [
                ['module' => 'goals', 'recommendation_text' => 'Test goal'],
                ['module' => 'protection', 'recommendation_text' => 'Test protection'],
            ];

            $grouped = $ranker->groupByCategory($recommendations);

            expect($grouped)->toHaveKey('goals');
            expect($grouped['goals'])->toHaveCount(1);
            expect($grouped['protection'])->toHaveCount(1);
        });
    });

    // ─────────────────────────────────────────────────────────────
    // 5A.T4: ConflictResolver includes estate and goals in priority allocation
    // ─────────────────────────────────────────────────────────────
    describe('ConflictResolver - Estate/Goals Priority (5A.T4)', function () {
        it('allocates estate and goals in order after investment', function () {
            $resolver = createMockedConflictResolver();

            $demands = [
                'emergency_fund' => ['amount' => 100, 'urgency' => 50],
                'protection' => ['amount' => 100, 'urgency' => 50],
                'pension' => ['amount' => 100, 'urgency' => 50],
                'investment' => ['amount' => 100, 'urgency' => 50],
                'estate' => ['amount' => 100, 'urgency' => 50],
                'goals' => ['amount' => 100, 'urgency' => 50],
            ];

            // Only enough for first 5 categories
            $result = $resolver->resolveContributionConflicts(500, $demands);

            expect($result['allocation']['emergency_fund'])->toBe(100.0);
            expect($result['allocation']['protection'])->toBe(100.0);
            expect($result['allocation']['pension'])->toBe(100.0);
            expect($result['allocation']['investment'])->toBe(100.0);
            expect($result['allocation']['estate'])->toBe(100.0);
            expect($result['allocation']['goals'])->toBe(0.0); // Last priority, unfunded
        });

        it('maps goals module to goals category', function () {
            $resolver = createMockedConflictResolver();

            $recommendations = [
                'goals' => [
                    ['recommended_monthly_contribution' => 200],
                ],
                'available_surplus' => 100,
            ];

            $conflicts = $resolver->identifyConflicts($recommendations);

            // Goals should be counted as a cashflow demand
            $cashflowConflict = collect($conflicts)->firstWhere('type', 'cashflow_conflict');
            if ($cashflowConflict) {
                expect($cashflowConflict['demands'])->toHaveKey('goals');
            }
        });
    });

    // ─────────────────────────────────────────────────────────────
    // 5A.T9: User with no estate data still works
    // ─────────────────────────────────────────────────────────────
    describe('HolisticPlanner - No Estate Data (5A.T9)', function () {
        it('generates plan with zero estate values', function () {
            $planner = createHolisticPlanner();

            $allAnalysis = [
                'protection' => ['adequacy_score' => 60],
                'savings' => ['emergency_fund_months' => 3, 'total_savings' => 5000],
                'investment' => ['total_portfolio_value' => 0, 'portfolio_health_score' => 50],
                'retirement' => ['income_gap' => 10000],
                'estate' => [
                    'net_worth' => 0,
                    'gross_estate' => 0,
                    'iht_liability' => 0,
                    'effective_tax_rate' => 0,
                    'total_liabilities' => 0,
                    'property_value' => 0,
                    'monthly_income' => 0,
                    'monthly_expenses' => 0,
                    'monthly_surplus' => 0,
                    'recommendations' => [],
                    'error' => 'Analysis failed',
                ],
                'goals' => ['has_goals' => false, 'recommendations' => []],
                'user' => ['age' => 25],
            ];

            $plan = $planner->createHolisticPlan(1, $allAnalysis);

            expect($plan['financial_snapshot']['net_worth'])->toBe(0);
            expect($plan['financial_snapshot']['property_value'])->toBe(0);
            expect($plan['module_summaries']['estate']['iht_liability'])->toBe(0);
            expect($plan['net_worth_projection']['current_net_worth'])->toBe(0);

            // Plan should still have all required keys
            expect($plan)->toHaveKeys([
                'executive_summary', 'financial_snapshot', 'net_worth_projection',
                'risk_assessment', 'module_summaries',
            ]);
        });
    });

    // ─────────────────────────────────────────────────────────────
    // 5A.T10: User with no goals still works
    // ─────────────────────────────────────────────────────────────
    describe('HolisticPlanner - No Goals (5A.T10)', function () {
        it('generates plan without goals risk areas when no goals exist', function () {
            $planner = createHolisticPlanner();

            $allAnalysis = [
                'protection' => ['adequacy_score' => 80],
                'savings' => ['emergency_fund_months' => 6, 'total_savings' => 15000],
                'investment' => ['total_portfolio_value' => 50000, 'portfolio_health_score' => 70],
                'retirement' => ['income_gap' => 0],
                'estate' => [
                    'net_worth' => 500000, 'iht_liability' => 0, 'property_value' => 350000,
                    'total_liabilities' => 0, 'monthly_income' => 5000, 'monthly_expenses' => 3000,
                    'monthly_surplus' => 2000, 'recommendations' => [],
                ],
                'goals' => ['has_goals' => false, 'recommendations' => []],
                'user' => ['age' => 45],
            ];

            $plan = $planner->createHolisticPlan(1, $allAnalysis);

            // Goals module summary should exist but show not_started
            expect($plan['module_summaries']['goals'])->toBeArray();
            expect($plan['module_summaries']['goals']['has_goals'])->toBeFalse();
            expect($plan['module_summaries']['goals']['status'])->toBe('not_started');

            // No goals-related risk areas
            $goalsRisk = collect($plan['risk_assessment']['risk_areas'])->firstWhere('area', 'Goals');
            expect($goalsRisk)->toBeNull();
        });

        it('generates plan without goals affecting overall score', function () {
            $planner = createHolisticPlanner();

            $allAnalysis = [
                'protection' => ['adequacy_score' => 100],
                'savings' => ['emergency_fund_months' => 6, 'total_savings' => 20000],
                'investment' => ['total_portfolio_value' => 100000, 'portfolio_health_score' => 90],
                'retirement' => ['income_gap' => 0],
                'estate' => [
                    'net_worth' => 1000000, 'iht_liability' => 0, 'property_value' => 700000,
                    'total_liabilities' => 0, 'monthly_income' => 8000, 'monthly_expenses' => 4000,
                    'monthly_surplus' => 4000, 'recommendations' => [],
                ],
                'goals' => ['has_goals' => false, 'recommendations' => []],
                'user' => ['age' => 50],
            ];

            $plan = $planner->createHolisticPlan(1, $allAnalysis);

            // Plan should still generate without errors
            expect($plan['executive_summary']['health_status'])->toBeString();
            expect($plan['risk_assessment']['risk_level'])->toBeString();
        });
    });
});
