<?php

declare(strict_types=1);

use App\Agents\GoalsAgent;
use App\Models\Goal;
use App\Models\User;
use App\Services\Goals\GoalAffordabilityService;
use App\Services\Goals\GoalAssignmentService;
use App\Services\Goals\GoalProgressService;
use App\Services\Goals\GoalRiskService;
use Illuminate\Support\Facades\Cache;

beforeEach(function () {
    $this->user = User::factory()->create();

    // Mock dependencies
    $this->assignmentService = Mockery::mock(GoalAssignmentService::class);
    $this->affordabilityService = Mockery::mock(GoalAffordabilityService::class);
    $this->progressService = Mockery::mock(GoalProgressService::class);
    $this->riskService = Mockery::mock(GoalRiskService::class);

    $this->agent = new GoalsAgent(
        $this->assignmentService,
        $this->affordabilityService,
        $this->progressService,
        $this->riskService
    );

    // Clear cache before each test
    Cache::flush();
});

afterEach(function () {
    Mockery::close();
});

/**
 * Helper function to create a Goal with specific attributes.
 */
function createGoal(User $user, array $attributes = []): Goal
{
    $defaults = [
        'user_id' => $user->id,
        'goal_name' => 'Test Goal',
        'goal_type' => 'emergency_fund',
        'target_amount' => 10000.00,
        'current_amount' => 5000.00,
        'target_date' => now()->addMonths(12),
        'start_date' => now()->subMonths(6),
        'assigned_module' => 'savings',
        'priority' => 'medium',
        'status' => 'active',
        'monthly_contribution' => 500.00,
        'contribution_streak' => 3,
        'longest_streak' => 5,
    ];

    return Goal::create(array_merge($defaults, $attributes));
}

// =============================================================================
// analyze() method tests
// =============================================================================

describe('analyze', function () {
    it('returns no goals message when user has no goals', function () {
        $result = $this->agent->analyze($this->user->id);

        expect($result)->toHaveKey('has_goals');
        expect($result['has_goals'])->toBeFalse();
        expect($result['message'])->toContain('No goals found');
        expect($result['summary']['total_goals'])->toBe(0);
    });

    it('returns complete analysis when user has goals', function () {
        // Create a goal for the user
        createGoal($this->user);

        // Mock the affordability service
        $this->affordabilityService->shouldReceive('analyzeAllGoals')
            ->once()
            ->andReturn([
                'status' => 'sustainable',
                'monthly_surplus' => 1000.00,
            ]);

        $result = $this->agent->analyze($this->user->id);

        expect($result['has_goals'])->toBeTrue();
        expect($result)->toHaveKey('summary');
        expect($result)->toHaveKey('by_module');
        expect($result)->toHaveKey('top_goals');
        expect($result)->toHaveKey('affordability');
        expect($result)->toHaveKey('streaks');
        expect($result['goals_count'])->toBe(1);
    });

    it('analyzes goals by module correctly', function () {
        // Create goals in different modules
        createGoal($this->user, [
            'goal_name' => 'Savings Goal',
            'assigned_module' => 'savings',
        ]);
        createGoal($this->user, [
            'goal_name' => 'Investment Goal',
            'assigned_module' => 'investment',
        ]);
        createGoal($this->user, [
            'goal_name' => 'Property Goal',
            'assigned_module' => 'property',
        ]);

        $this->affordabilityService->shouldReceive('analyzeAllGoals')
            ->once()
            ->andReturn(['status' => 'sustainable']);

        $result = $this->agent->analyze($this->user->id);

        expect($result['by_module'])->toHaveKey('savings');
        expect($result['by_module'])->toHaveKey('investment');
        expect($result['by_module'])->toHaveKey('property');
        expect($result['by_module'])->toHaveKey('retirement');
        expect($result['by_module']['savings']['count'])->toBe(1);
        expect($result['by_module']['investment']['count'])->toBe(1);
        expect($result['by_module']['property']['count'])->toBe(1);
        expect($result['by_module']['retirement']['count'])->toBe(0);
    });

    it('calculates summary correctly', function () {
        // Create multiple goals with different statuses
        createGoal($this->user, [
            'goal_name' => 'Active Goal 1',
            'status' => 'active',
            'target_amount' => 10000,
            'current_amount' => 6000,
        ]);
        createGoal($this->user, [
            'goal_name' => 'Active Goal 2',
            'status' => 'active',
            'target_amount' => 5000,
            'current_amount' => 2000,
        ]);
        createGoal($this->user, [
            'goal_name' => 'Completed Goal',
            'status' => 'completed',
            'target_amount' => 3000,
            'current_amount' => 3000,
        ]);

        $this->affordabilityService->shouldReceive('analyzeAllGoals')
            ->once()
            ->andReturn(['status' => 'sustainable']);

        $result = $this->agent->analyze($this->user->id);

        expect($result['summary']['total_goals'])->toBe(2); // Only active goals
        expect($result['summary']['total_target'])->toBe(15000.00);
        expect($result['summary']['total_current'])->toBe(8000.00);
        expect($result['completed_count'])->toBe(1);
        expect($result['goals_count'])->toBe(3); // Total including completed
    });
});

// =============================================================================
// generateRecommendations() method tests
// =============================================================================

describe('generateRecommendations', function () {
    it('returns Set Your First Goal when user has no goals', function () {
        $analysisData = ['has_goals' => false];

        $result = $this->agent->generateRecommendations($analysisData);

        expect($result['recommendation_count'])->toBe(1);
        expect($result['recommendations'][0]['title'])->toBe('Set Your First Financial Goal');
        expect($result['recommendations'][0]['category'])->toBe('Getting Started');
    });

    it('generates behind schedule recommendation', function () {
        $analysisData = [
            'has_goals' => true,
            'summary' => [
                'behind_count' => 2,
            ],
            'affordability' => ['status' => 'sustainable'],
            'by_module' => [
                'savings' => ['goals' => [['goal_type' => 'emergency_fund']]],
            ],
            'streaks' => ['best_current_streak' => 0],
        ];

        $result = $this->agent->generateRecommendations($analysisData);

        $behindRec = collect($result['recommendations'])->first(
            fn ($r) => str_contains($r['title'], 'falling behind schedule')
        );

        expect($behindRec)->not->toBeNull();
        expect($behindRec['title'])->toContain('2 goal(s) falling behind');
        expect($behindRec['category'])->toBe('Progress');
    });

    it('generates affordability recommendation', function () {
        $analysisData = [
            'has_goals' => true,
            'summary' => ['behind_count' => 0],
            'affordability' => ['status' => 'overcommitted'],
            'by_module' => [
                'savings' => ['goals' => [['goal_type' => 'emergency_fund']]],
            ],
            'streaks' => ['best_current_streak' => 0],
        ];

        $result = $this->agent->generateRecommendations($analysisData);

        $affordabilityRec = collect($result['recommendations'])->first(
            fn ($r) => $r['category'] === 'Affordability'
        );

        expect($affordabilityRec)->not->toBeNull();
        expect($affordabilityRec['title'])->toContain('exceed available surplus');
    });

    it('generates emergency fund recommendation', function () {
        $analysisData = [
            'has_goals' => true,
            'summary' => ['behind_count' => 0],
            'affordability' => ['status' => 'sustainable'],
            'by_module' => [
                'savings' => ['goals' => [['goal_type' => 'holiday']]], // Not emergency fund
            ],
            'streaks' => ['best_current_streak' => 0],
        ];

        $result = $this->agent->generateRecommendations($analysisData);

        $emergencyRec = collect($result['recommendations'])->first(
            fn ($r) => $r['category'] === 'Safety Net'
        );

        expect($emergencyRec)->not->toBeNull();
        expect($emergencyRec['title'])->toBe('No Emergency Fund Goal');
    });

    it('generates streak achievement recommendation', function () {
        $analysisData = [
            'has_goals' => true,
            'summary' => ['behind_count' => 0],
            'affordability' => ['status' => 'sustainable'],
            'by_module' => [
                'savings' => ['goals' => [['goal_type' => 'emergency_fund']]],
            ],
            'streaks' => ['best_current_streak' => 5],
        ];

        $result = $this->agent->generateRecommendations($analysisData);

        $streakRec = collect($result['recommendations'])->first(
            fn ($r) => $r['category'] === 'Momentum'
        );

        expect($streakRec)->not->toBeNull();
        expect($streakRec['title'])->toContain('5-month contribution streak');
    });
});

// =============================================================================
// buildScenarios() method tests
// =============================================================================

describe('buildScenarios', function () {
    it('returns error when no goal_id provided', function () {
        $result = $this->agent->buildScenarios($this->user->id, []);

        expect($result['scenario_count'])->toBe(0);
        expect($result['message'])->toBe('Please specify a goal_id to generate scenarios.');
    });

    it('returns error when goal not found', function () {
        $result = $this->agent->buildScenarios($this->user->id, ['goal_id' => 99999]);

        expect($result['scenario_count'])->toBe(0);
        expect($result['message'])->toBe('Goal not found.');
    });

    it('generates increase contribution scenario', function () {
        $goal = createGoal($this->user, [
            'target_amount' => 12000,
            'current_amount' => 6000,
            'monthly_contribution' => 500,
        ]);

        $result = $this->agent->buildScenarios($this->user->id, ['goal_id' => $goal->id]);

        $increaseScenario = collect($result['scenarios'])->first(
            fn ($s) => $s['name'] === 'Increase Contribution by 20%'
        );

        expect($increaseScenario)->not->toBeNull();
        expect($increaseScenario['parameters']['monthly_contribution'])->toBe(600.00);
    });

    it('generates reach goal earlier scenario', function () {
        $goal = createGoal($this->user, [
            'target_amount' => 12000,
            'current_amount' => 6000,
            'target_date' => now()->addMonths(12),
        ]);

        $result = $this->agent->buildScenarios($this->user->id, ['goal_id' => $goal->id]);

        $earlierScenario = collect($result['scenarios'])->first(
            fn ($s) => $s['name'] === 'Reach Goal 6 Months Earlier'
        );

        expect($earlierScenario)->not->toBeNull();
        expect($earlierScenario['parameters'])->toHaveKey('monthly_contribution');
        expect($earlierScenario['parameters'])->toHaveKey('months_to_goal');
    });

    it('generates reduce target scenario', function () {
        $goal = createGoal($this->user, [
            'target_amount' => 10000,
            'current_amount' => 4000,
        ]);

        $result = $this->agent->buildScenarios($this->user->id, ['goal_id' => $goal->id]);

        $reduceScenario = collect($result['scenarios'])->first(
            fn ($s) => $s['name'] === 'Reduce Target by 20%'
        );

        expect($reduceScenario)->not->toBeNull();
        expect($reduceScenario['parameters']['target_amount'])->toBe(8000.00);
    });

    it('generates lump sum scenario', function () {
        $goal = createGoal($this->user, [
            'target_amount' => 10000,
            'current_amount' => 5000,
        ]);

        $result = $this->agent->buildScenarios($this->user->id, ['goal_id' => $goal->id]);

        $lumpSumScenario = collect($result['scenarios'])->first(
            fn ($s) => $s['name'] === 'Add £1,000 Lump Sum'
        );

        expect($lumpSumScenario)->not->toBeNull();
        expect($lumpSumScenario['parameters']['lump_sum'])->toBe(1000);
        expect($lumpSumScenario['parameters']['new_current_amount'])->toBe(6000.00);
    });
});

// =============================================================================
// getDashboardOverview() method tests
// =============================================================================

describe('getDashboardOverview', function () {
    it('returns empty state when no goals', function () {
        $result = $this->agent->getDashboardOverview($this->user->id);

        expect($result['has_goals'])->toBeFalse();
        expect($result['total_goals'])->toBe(0);
        expect($result['on_track_count'])->toBe(0);
        expect($result['total_target'])->toBe(0);
        expect($result['total_current'])->toBe(0);
        expect($result['overall_progress'])->toBe(0);
        expect($result['top_goals'])->toBeEmpty();
        expect($result['best_streak'])->toBe(0);
    });

    it('returns overview with goals', function () {
        // Create active goals
        createGoal($this->user, [
            'goal_name' => 'Goal 1',
            'target_amount' => 10000,
            'current_amount' => 6000,
            'contribution_streak' => 5,
            'status' => 'active',
        ]);
        createGoal($this->user, [
            'goal_name' => 'Goal 2',
            'target_amount' => 5000,
            'current_amount' => 2500,
            'contribution_streak' => 3,
            'status' => 'active',
        ]);

        $result = $this->agent->getDashboardOverview($this->user->id);

        expect($result['has_goals'])->toBeTrue();
        expect($result['total_goals'])->toBe(2);
        expect($result['total_target'])->toBe(15000.00);
        expect($result['total_current'])->toBe(8500.00);
        expect($result['overall_progress'])->toBeGreaterThan(0);
        expect($result['top_goals'])->toHaveCount(2);
        expect($result['best_streak'])->toBe(5);
    });
});

// =============================================================================
// clearCache() method tests
// =============================================================================

describe('clearCache', function () {
    it('clears user cache', function () {
        // First, populate cache by calling analyze
        createGoal($this->user);

        $this->affordabilityService->shouldReceive('analyzeAllGoals')
            ->andReturn(['status' => 'sustainable']);

        // Call analyze to populate cache
        $this->agent->analyze($this->user->id);

        // Verify cache is populated by checking the cache key exists
        $cacheKey = "v1_goalsagent_{$this->user->id}_analysis";
        expect(Cache::has($cacheKey))->toBeTrue();

        // Clear the cache
        $this->agent->clearCache($this->user->id);

        // Verify cache is cleared
        expect(Cache::has($cacheKey))->toBeFalse();
    });
});
