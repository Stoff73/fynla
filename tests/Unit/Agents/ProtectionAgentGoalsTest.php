<?php

declare(strict_types=1);

use App\Agents\ProtectionAgent;
use App\Models\Goal;
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

    // Set up default mock expectations for full analysis flow
    $this->gapAnalyzer->shouldReceive('calculateProtectionNeeds')->andReturn(['total_need' => 500000])->byDefault();
    $this->gapAnalyzer->shouldReceive('calculateTotalCoverage')->andReturn(['total_coverage' => 200000, 'critical_illness_coverage' => 50000])->byDefault();
    $this->gapAnalyzer->shouldReceive('calculateCoverageGap')->andReturn(['total_gap' => 300000, 'gaps_by_category' => []])->byDefault();
    $this->adequacyScorer->shouldReceive('calculateAdequacyScore')->andReturn(40)->byDefault();
    $this->adequacyScorer->shouldReceive('generateScoreInsights')->andReturn(['score' => 40])->byDefault();
    $this->recommendationEngine->shouldReceive('generateRecommendations')->andReturn([])->byDefault();
    $this->scenarioBuilder->shouldReceive('modelDeathScenario')->andReturn([])->byDefault();
    $this->scenarioBuilder->shouldReceive('modelCriticalIllnessScenario')->andReturn([])->byDefault();
    $this->scenarioBuilder->shouldReceive('modelDisabilityScenario')->andReturn([])->byDefault();
    $this->completenessChecker->shouldReceive('checkCompleteness')->andReturn(['overall_score' => 70])->byDefault();
});

afterEach(function () {
    Mockery::close();
});

describe('goal commitments in coverage analysis', function () {
    it('includes active goal commitments in analysis output with correct total outstanding', function () {
        $user = User::factory()->create([
            'date_of_birth' => now()->subYears(35),
        ]);
        ProtectionProfile::factory()->create([
            'user_id' => $user->id,
            'annual_income' => 50000,
        ]);

        // Create active goals with known amounts
        Goal::factory()->create([
            'user_id' => $user->id,
            'goal_name' => 'Emergency Fund',
            'status' => 'active',
            'target_amount' => 10000.00,
            'current_amount' => 3000.00,
        ]);
        Goal::factory()->create([
            'user_id' => $user->id,
            'goal_name' => 'Home Deposit',
            'status' => 'active',
            'target_amount' => 50000.00,
            'current_amount' => 15000.00,
        ]);

        // Create a completed goal (should not be included)
        Goal::factory()->completed()->create([
            'user_id' => $user->id,
            'goal_name' => 'Holiday Fund',
            'target_amount' => 5000.00,
            'current_amount' => 5000.00,
        ]);

        Cache::forget("protection_analysis_{$user->id}");

        $result = $this->agent->analyze($user->id);

        expect($result['success'])->toBeTrue()
            ->and($result['data'])->toHaveKey('goal_commitments')
            ->and($result['data']['goal_commitments']['count'])->toBe(2)
            ->and($result['data']['goal_commitments']['total_outstanding'])->toBe(42000.00)
            ->and($result['data']['goal_commitments']['goals'])->toHaveCount(2)
            ->and($result['data']['goal_commitments']['coverage_note'])->toContain('2 active goals')
            ->and($result['data']['goal_commitments']['coverage_note'])->toContain('£42,000');
    });

    it('returns empty goal commitments when user has no active goals', function () {
        $user = User::factory()->create([
            'date_of_birth' => now()->subYears(40),
        ]);
        ProtectionProfile::factory()->create([
            'user_id' => $user->id,
            'annual_income' => 60000,
        ]);

        Cache::forget("protection_analysis_{$user->id}");

        $result = $this->agent->analyze($user->id);

        expect($result['success'])->toBeTrue()
            ->and($result['data']['goal_commitments']['count'])->toBe(0)
            ->and($result['data']['goal_commitments']['total_outstanding'])->toBe(0.00)
            ->and($result['data']['goal_commitments']['goals'])->toBeEmpty()
            ->and($result['data']['goal_commitments']['coverage_note'])->toBeNull();
    });

    it('uses singular goal text when only one active goal exists', function () {
        $user = User::factory()->create([
            'date_of_birth' => now()->subYears(30),
        ]);
        ProtectionProfile::factory()->create([
            'user_id' => $user->id,
            'annual_income' => 45000,
        ]);

        Goal::factory()->create([
            'user_id' => $user->id,
            'goal_name' => 'Emergency Fund',
            'status' => 'active',
            'target_amount' => 15000.00,
            'current_amount' => 5000.00,
        ]);

        Cache::forget("protection_analysis_{$user->id}");

        $result = $this->agent->analyze($user->id);

        expect($result['data']['goal_commitments']['count'])->toBe(1)
            ->and($result['data']['goal_commitments']['coverage_note'])->toContain('1 active goal')
            ->and($result['data']['goal_commitments']['coverage_note'])->not->toContain('goals');
    });

    it('excludes goals with zero outstanding from the goals list', function () {
        $user = User::factory()->create([
            'date_of_birth' => now()->subYears(35),
        ]);
        ProtectionProfile::factory()->create([
            'user_id' => $user->id,
            'annual_income' => 50000,
        ]);

        // Active goal that is fully funded (outstanding = 0)
        Goal::factory()->create([
            'user_id' => $user->id,
            'goal_name' => 'Fully Funded Goal',
            'status' => 'active',
            'target_amount' => 10000.00,
            'current_amount' => 10000.00,
        ]);

        // Active goal with outstanding balance
        Goal::factory()->create([
            'user_id' => $user->id,
            'goal_name' => 'Partially Funded Goal',
            'status' => 'active',
            'target_amount' => 20000.00,
            'current_amount' => 5000.00,
        ]);

        Cache::forget("protection_analysis_{$user->id}");

        $result = $this->agent->analyze($user->id);

        // Count includes all active goals, but goals list excludes zero outstanding
        expect($result['data']['goal_commitments']['count'])->toBe(2)
            ->and($result['data']['goal_commitments']['goals'])->toHaveCount(1)
            ->and($result['data']['goal_commitments']['goals'][0]['name'])->toBe('Partially Funded Goal')
            ->and($result['data']['goal_commitments']['goals'][0]['outstanding'])->toBe(15000.00);
    });
});
