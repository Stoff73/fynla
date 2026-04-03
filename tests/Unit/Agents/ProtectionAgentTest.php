<?php

declare(strict_types=1);

use App\Agents\ProtectionAgent;
use App\Models\ProtectionProfile;
use App\Models\User;
use App\Services\Coordination\RecommendationPersonaliser;
use App\Services\Protection\AdequacyScorer;
use App\Services\Protection\CoverageGapAnalyzer;
use App\Services\Protection\ProtectionDataReadinessService;
use App\Services\Protection\RecommendationEngine;
use App\Services\Protection\ScenarioBuilder;
use App\Services\UserProfile\ProfileCompletenessChecker;
use Illuminate\Support\Facades\Cache;

beforeEach(function () {
    // Create mock dependencies
    $this->gapAnalyzer = Mockery::mock(CoverageGapAnalyzer::class);
    $this->adequacyScorer = Mockery::mock(AdequacyScorer::class);
    $this->recommendationEngine = Mockery::mock(RecommendationEngine::class);
    $this->scenarioBuilder = Mockery::mock(ScenarioBuilder::class);
    $this->completenessChecker = Mockery::mock(ProfileCompletenessChecker::class);
    $this->personaliser = Mockery::mock(RecommendationPersonaliser::class);
    $this->personaliser->shouldReceive('personaliseRecommendations')->andReturnUsing(fn ($recs, $user) => $recs);
    $this->readinessService = Mockery::mock(ProtectionDataReadinessService::class);
    $this->readinessService->shouldReceive('assess')->andReturn([
        'can_proceed' => true,
        'blocking' => [],
        'warnings' => [],
        'info' => [],
    ])->byDefault();

    // Create agent with mocked dependencies
    $this->agent = new ProtectionAgent(
        $this->gapAnalyzer,
        $this->adequacyScorer,
        $this->recommendationEngine,
        $this->scenarioBuilder,
        $this->completenessChecker,
        $this->personaliser,
        $this->readinessService
    );
});

afterEach(function () {
    Mockery::close();
});

describe('analyze', function () {
    it('returns error response when user has no protection profile', function () {
        $user = User::factory()->create();

        // Clear any cached data
        Cache::forget("protection_analysis_{$user->id}");

        $result = $this->agent->analyze($user->id);

        expect($result)->toBeArray()
            ->and($result['success'])->toBeFalse()
            ->and($result['message'])->toContain('Protection profile not found')
            ->and($result['data'])->toBeEmpty();
    });

    it('returns success response with complete analysis when user has profile and policies', function () {
        $user = User::factory()->create([
            'date_of_birth' => now()->subYears(35),
        ]);
        $profile = ProtectionProfile::factory()->create([
            'user_id' => $user->id,
            'annual_income' => 50000,
            'monthly_expenditure' => 3000,
            'mortgage_balance' => 200000,
            'other_debts' => 25000,
            'number_of_dependents' => 2,
            'retirement_age' => 67,
        ]);

        // Clear any cached data
        Cache::forget("protection_analysis_{$user->id}");

        // Set up mock expectations
        $mockNeeds = [
            'human_capital' => 500000,
            'debt_protection' => 225000,
            'education_funding' => 100000,
            'final_expenses' => 7500,
            'income_protection_need' => 30000,
            'total_need' => 862500,
        ];

        $mockCoverage = [
            'life_coverage' => 300000,
            'critical_illness_coverage' => 100000,
            'income_protection_coverage' => 24000,
            'disability_coverage' => 18000,
            'sickness_illness_coverage' => 50000,
            'total_coverage' => 400000,
            'total_income_coverage' => 92000,
        ];

        $mockGaps = [
            'total_need' => 862500,
            'total_coverage' => 400000,
            'total_gap' => 462500,
            'coverage_percentage' => 46.4,
            'gaps_by_category' => [
                'human_capital_gap' => 200000,
                'debt_protection_gap' => 0,
                'education_funding_gap' => 0,
                'income_protection_gap' => 6000,
                'disability_coverage_gap' => 0,
                'sickness_illness_gap' => 0,
            ],
        ];

        $mockScoreInsights = [
            'score' => 46,
            'category' => 'Fair',
            'color' => 'orange',
            'insights' => ['Your protection coverage is fair.'],
        ];

        $mockRecommendations = [
            [
                'priority' => 1,
                'category' => 'Life Insurance',
                'action' => 'Increase life insurance coverage',
                'rationale' => 'Coverage gap exists',
                'impact' => 'High',
                'estimated_cost' => 50,
            ],
        ];

        $mockDeathScenario = ['scenario_type' => 'Death', 'payout' => 300000];
        $mockCriticalIllnessScenario = ['scenario_type' => 'Critical Illness', 'payout' => 100000];
        $mockDisabilityScenario = ['scenario_type' => 'Disability', 'annual_benefit' => 24000];

        $mockCompleteness = ['overall_score' => 85, 'missing_fields' => []];

        $this->gapAnalyzer->shouldReceive('calculateProtectionNeeds')
            ->once()
            ->with(Mockery::type(ProtectionProfile::class))
            ->andReturn($mockNeeds);

        $this->gapAnalyzer->shouldReceive('calculateTotalCoverage')
            ->once()
            ->andReturn($mockCoverage);

        $this->gapAnalyzer->shouldReceive('calculateCoverageGap')
            ->once()
            ->with($mockNeeds, $mockCoverage)
            ->andReturn($mockGaps);

        $this->adequacyScorer->shouldReceive('calculateAdequacyScore')
            ->once()
            ->with($mockGaps, $mockNeeds)
            ->andReturn(46);

        $this->adequacyScorer->shouldReceive('generateScoreInsights')
            ->once()
            ->with(46, $mockGaps, Mockery::any(), true)
            ->andReturn($mockScoreInsights);

        $this->recommendationEngine->shouldReceive('generateRecommendations')
            ->once()
            ->with($mockGaps, Mockery::type(ProtectionProfile::class))
            ->andReturn($mockRecommendations);

        $this->scenarioBuilder->shouldReceive('modelDeathScenario')
            ->once()
            ->andReturn($mockDeathScenario);

        $this->scenarioBuilder->shouldReceive('modelCriticalIllnessScenario')
            ->once()
            ->andReturn($mockCriticalIllnessScenario);

        $this->scenarioBuilder->shouldReceive('modelDisabilityScenario')
            ->once()
            ->andReturn($mockDisabilityScenario);

        $this->completenessChecker->shouldReceive('checkCompleteness')
            ->once()
            ->with(Mockery::type(User::class))
            ->andReturn($mockCompleteness);

        $result = $this->agent->analyze($user->id);

        expect($result)->toBeArray()
            ->and($result['success'])->toBeTrue()
            ->and($result['message'])->toContain('Protection analysis completed successfully');
    });

    it('includes all expected keys in response', function () {
        $user = User::factory()->create([
            'date_of_birth' => now()->subYears(40),
        ]);
        $profile = ProtectionProfile::factory()->create([
            'user_id' => $user->id,
            'annual_income' => 60000,
        ]);

        // Clear any cached data
        Cache::forget("protection_analysis_{$user->id}");

        // Set up mock expectations with minimal data
        $mockNeeds = ['total_need' => 500000];
        $mockCoverage = ['total_coverage' => 200000];
        $mockGaps = ['total_gap' => 300000, 'gaps_by_category' => []];
        $mockScoreInsights = ['score' => 40];
        $mockRecommendations = [];
        $mockScenario = [];
        $mockCompleteness = ['overall_score' => 70];

        $this->gapAnalyzer->shouldReceive('calculateProtectionNeeds')->andReturn($mockNeeds);
        $this->gapAnalyzer->shouldReceive('calculateTotalCoverage')->andReturn($mockCoverage);
        $this->gapAnalyzer->shouldReceive('calculateCoverageGap')->andReturn($mockGaps);
        $this->adequacyScorer->shouldReceive('calculateAdequacyScore')->andReturn(40);
        $this->adequacyScorer->shouldReceive('generateScoreInsights')->andReturn($mockScoreInsights);
        $this->recommendationEngine->shouldReceive('generateRecommendations')->andReturn($mockRecommendations);
        $this->scenarioBuilder->shouldReceive('modelDeathScenario')->andReturn($mockScenario);
        $this->scenarioBuilder->shouldReceive('modelCriticalIllnessScenario')->andReturn($mockScenario);
        $this->scenarioBuilder->shouldReceive('modelDisabilityScenario')->andReturn($mockScenario);
        $this->completenessChecker->shouldReceive('checkCompleteness')->andReturn($mockCompleteness);

        $result = $this->agent->analyze($user->id);

        expect($result['data'])->toHaveKeys([
            'profile',
            'needs',
            'coverage',
            'gaps',
            'adequacy_score',
            'recommendations',
            'scenarios',
            'goal_commitments',
            'policies',
            'profile_completeness',
        ]);
    });
});

describe('generateRecommendations', function () {
    it('returns error when analysis data is incomplete', function () {
        $analysisData = [
            'success' => true,
            'data' => [], // Missing recommendations key
        ];

        $result = $this->agent->generateRecommendations($analysisData);

        expect($result)->toBeArray()
            ->and($result['success'])->toBeFalse()
            ->and($result['message'])->toContain('Analysis data is incomplete');
    });

    it('returns recommendations from analysis data', function () {
        $mockRecommendations = [
            [
                'priority' => 1,
                'category' => 'Life Insurance',
                'action' => 'Increase coverage',
                'rationale' => 'Gap exists',
                'impact' => 'High',
                'estimated_cost' => 45,
            ],
            [
                'priority' => 2,
                'category' => 'Income Protection',
                'action' => 'Add income protection policy',
                'rationale' => 'No current coverage',
                'impact' => 'Medium',
                'estimated_cost' => 30,
            ],
        ];

        $analysisData = [
            'success' => true,
            'data' => [
                'recommendations' => $mockRecommendations,
            ],
        ];

        $result = $this->agent->generateRecommendations($analysisData);

        expect($result)->toBeArray()
            ->and($result['success'])->toBeTrue()
            ->and($result['message'])->toContain('Recommendations generated successfully')
            ->and($result['data']['recommendations'])->toBe($mockRecommendations);
    });
});

describe('buildScenarios', function () {
    it('returns error when user has no protection profile', function () {
        $user = User::factory()->create();

        $result = $this->agent->buildScenarios($user->id, []);

        expect($result)->toBeArray()
            ->and($result['success'])->toBeFalse()
            ->and($result['message'])->toContain('Protection profile not found');
    });

    it('builds default scenarios (death, critical_illness, disability)', function () {
        $user = User::factory()->create();
        $profile = ProtectionProfile::factory()->create([
            'user_id' => $user->id,
        ]);

        $mockCoverage = [
            'life_coverage' => 200000,
            'critical_illness_coverage' => 100000,
            'income_protection_coverage' => 24000,
            'disability_coverage' => 12000,
            'sickness_illness_coverage' => 0,
            'total_coverage' => 300000,
            'total_income_coverage' => 36000,
        ];

        $mockDeathScenario = [
            'scenario_type' => 'Death',
            'payout' => 200000,
            'adequacy' => 'Good',
        ];
        $mockCriticalIllnessScenario = [
            'scenario_type' => 'Critical Illness',
            'payout' => 100000,
            'adequacy' => 'Fair',
        ];
        $mockDisabilityScenario = [
            'scenario_type' => 'Disability',
            'annual_benefit' => 24000,
            'adequacy' => 'Good',
        ];

        $this->gapAnalyzer->shouldReceive('calculateTotalCoverage')
            ->once()
            ->andReturn($mockCoverage);

        $this->scenarioBuilder->shouldReceive('modelDeathScenario')
            ->once()
            ->with(Mockery::type(ProtectionProfile::class), $mockCoverage)
            ->andReturn($mockDeathScenario);

        $this->scenarioBuilder->shouldReceive('modelCriticalIllnessScenario')
            ->once()
            ->with(Mockery::type(ProtectionProfile::class), $mockCoverage)
            ->andReturn($mockCriticalIllnessScenario);

        $this->scenarioBuilder->shouldReceive('modelDisabilityScenario')
            ->once()
            ->with(Mockery::type(ProtectionProfile::class), $mockCoverage)
            ->andReturn($mockDisabilityScenario);

        $result = $this->agent->buildScenarios($user->id, []);

        expect($result)->toBeArray()
            ->and($result['success'])->toBeTrue()
            ->and($result['message'])->toContain('Scenarios built successfully')
            ->and($result['data']['scenarios'])->toHaveKeys(['death', 'critical_illness', 'disability']);
    });

    it('builds specific scenarios when scenario_types parameter provided', function () {
        $user = User::factory()->create();
        $profile = ProtectionProfile::factory()->create([
            'user_id' => $user->id,
        ]);

        $mockCoverage = [
            'life_coverage' => 200000,
            'critical_illness_coverage' => 100000,
            'total_coverage' => 300000,
        ];

        $mockDeathScenario = [
            'scenario_type' => 'Death',
            'payout' => 200000,
        ];

        $this->gapAnalyzer->shouldReceive('calculateTotalCoverage')
            ->once()
            ->andReturn($mockCoverage);

        $this->scenarioBuilder->shouldReceive('modelDeathScenario')
            ->once()
            ->with(Mockery::type(ProtectionProfile::class), $mockCoverage)
            ->andReturn($mockDeathScenario);

        // Only request death scenario
        $result = $this->agent->buildScenarios($user->id, ['scenario_types' => ['death']]);

        expect($result)->toBeArray()
            ->and($result['success'])->toBeTrue()
            ->and($result['data']['scenarios'])->toHaveKey('death')
            ->and($result['data']['scenarios']['death'])->toBe($mockDeathScenario);
    });
});

describe('invalidateCache', function () {
    it('clears cache for user', function () {
        $userId = 123;
        $cacheKey = "protection_analysis_{$userId}";

        // Clear any existing cache
        Cache::flush();

        // Set a value in cache using key-based caching
        Cache::put($cacheKey, ['test' => 'data'], 86400);

        // Verify it exists
        expect(Cache::has($cacheKey))->toBeTrue();

        // Create a fresh agent instance for this test (without mocks interfering)
        $personaliserMock = Mockery::mock(RecommendationPersonaliser::class);
        $personaliserMock->shouldReceive('personaliseRecommendations')->andReturnUsing(fn ($recs, $user) => $recs);
        $readinessMock = Mockery::mock(ProtectionDataReadinessService::class);
        $readinessMock->shouldReceive('assess')->andReturn([
            'can_proceed' => true,
            'blocking' => [],
            'warnings' => [],
            'info' => [],
        ]);
        $realAgent = new ProtectionAgent(
            Mockery::mock(CoverageGapAnalyzer::class),
            Mockery::mock(AdequacyScorer::class),
            Mockery::mock(RecommendationEngine::class),
            Mockery::mock(ScenarioBuilder::class),
            Mockery::mock(ProfileCompletenessChecker::class),
            $personaliserMock,
            $readinessMock
        );

        // Invalidate the cache
        $realAgent->invalidateCache($userId);

        // Verify it's cleared
        expect(Cache::has($cacheKey))->toBeFalse();
    });
});
