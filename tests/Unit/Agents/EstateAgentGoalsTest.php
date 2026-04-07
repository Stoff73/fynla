<?php

declare(strict_types=1);

use App\Agents\EstateAgent;
use App\Models\Goal;
use App\Models\User;
use App\Services\Coordination\RecommendationPersonaliser;
use App\Services\Estate\ComprehensiveEstatePlanService;
use App\Services\Estate\EstateAssetAggregatorService;
use App\Services\Estate\EstateDataReadinessService;
use App\Services\Estate\GiftingStrategyOptimizer;
use App\Services\Estate\IHTCalculationService;
use App\Services\Estate\LifeCoverCalculator;
use App\Services\Estate\PersonalizedTrustStrategyService;
use App\Services\Estate\WillAnalysisService;
use App\Services\TaxConfigService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

beforeEach(function () {
    $this->user = User::factory()->create([
        'date_of_birth' => now()->subYears(45),
        'marital_status' => 'single',
    ]);

    // Mock all EstateAgent dependencies
    $this->ihtCalculator = Mockery::mock(IHTCalculationService::class);
    $this->assetAggregator = Mockery::mock(EstateAssetAggregatorService::class);
    $this->estatePlanService = Mockery::mock(ComprehensiveEstatePlanService::class);
    $this->giftingOptimizer = Mockery::mock(GiftingStrategyOptimizer::class);
    $this->trustStrategyService = Mockery::mock(PersonalizedTrustStrategyService::class);
    $this->willAnalysisService = Mockery::mock(WillAnalysisService::class);
    $this->taxConfig = Mockery::mock(TaxConfigService::class);
    $this->personaliser = Mockery::mock(RecommendationPersonaliser::class);
    $this->readinessService = Mockery::mock(EstateDataReadinessService::class);
    $this->lifeCoverCalculator = Mockery::mock(LifeCoverCalculator::class);

    // Readiness gate must pass
    $this->readinessService->shouldReceive('assess')->andReturn([
        'can_proceed' => true,
        'blocking' => [],
        'warnings' => [],
        'info' => [],
    ])->byDefault();

    // Asset aggregator returns empty collection & zero liabilities
    $this->assetAggregator->shouldReceive('gatherUserAssets')->andReturn(new Collection)->byDefault();
    $this->assetAggregator->shouldReceive('calculateUserLiabilities')->andReturn(0)->byDefault();

    // IHT calculator returns minimal result
    $this->ihtCalculator->shouldReceive('calculate')->andReturn([
        'iht_liability' => 0,
        'effective_rate' => 0,
        'nrb_available' => 325000,
        'rnrb_available' => 0,
        'pension_amendment' => ['amendment_warning' => false],
    ])->byDefault();

    // Gifting optimizer returns empty
    $this->giftingOptimizer->shouldReceive('calculateOptimalGiftingStrategy')->andReturn([])->byDefault();

    // Tax config returns IHT defaults
    $this->taxConfig->shouldReceive('getInheritanceTax')->andReturn([
        'nil_rate_band' => 325000,
        'rate' => 40,
    ])->byDefault();

    // Will analysis returns empty
    $this->willAnalysisService->shouldReceive('detectTrustTriggeringWishes')->andReturn([])->byDefault();
    $this->willAnalysisService->shouldReceive('analyzeCharitableBequests')->andReturn([])->byDefault();

    $this->agent = new EstateAgent(
        $this->ihtCalculator,
        $this->assetAggregator,
        $this->estatePlanService,
        $this->giftingOptimizer,
        $this->trustStrategyService,
        $this->willAnalysisService,
        $this->taxConfig,
        $this->personaliser,
        $this->readinessService,
        $this->lifeCoverCalculator
    );

    Cache::flush();
});

afterEach(function () {
    Mockery::close();
});

describe('goal liquidity risk in estate analysis', function () {
    it('includes goal liquidity with correct total outstanding', function () {
        // Create active goals with outstanding amounts
        Goal::create([
            'user_id' => $this->user->id,
            'goal_name' => 'House Deposit',
            'goal_type' => 'home_deposit',
            'target_amount' => 50000.00,
            'current_amount' => 20000.00,
            'target_date' => now()->addYears(3),
            'start_date' => now()->subMonths(6),
            'assigned_module' => 'savings',
            'priority' => 'high',
            'status' => 'active',
        ]);

        Goal::create([
            'user_id' => $this->user->id,
            'goal_name' => 'Emergency Fund',
            'goal_type' => 'emergency_fund',
            'target_amount' => 15000.00,
            'current_amount' => 5000.00,
            'target_date' => now()->addYear(),
            'start_date' => now()->subMonths(3),
            'assigned_module' => 'savings',
            'priority' => 'high',
            'status' => 'active',
        ]);

        $result = $this->agent->analyze($this->user->id);

        expect($result['success'])->toBeTrue()
            ->and($result['data'])->toHaveKey('goal_liquidity')
            ->and($result['data']['goal_liquidity']['total_outstanding'])->toBe(40000.00)
            ->and($result['data']['goal_liquidity']['goals'])->toHaveCount(2)
            ->and($result['data']['goal_liquidity']['goals'][0]['name'])->toBe('House Deposit')
            ->and($result['data']['goal_liquidity']['goals'][0]['outstanding'])->toBe(30000.00)
            ->and($result['data']['goal_liquidity']['goals'][1]['name'])->toBe('Emergency Fund')
            ->and($result['data']['goal_liquidity']['goals'][1]['outstanding'])->toBe(10000.00);
    });

    it('excludes completed goals from liquidity calculation', function () {
        Goal::create([
            'user_id' => $this->user->id,
            'goal_name' => 'Active Goal',
            'goal_type' => 'emergency_fund',
            'target_amount' => 10000.00,
            'current_amount' => 3000.00,
            'target_date' => now()->addYear(),
            'start_date' => now()->subMonths(3),
            'assigned_module' => 'savings',
            'priority' => 'medium',
            'status' => 'active',
        ]);

        Goal::create([
            'user_id' => $this->user->id,
            'goal_name' => 'Done Goal',
            'goal_type' => 'holiday',
            'target_amount' => 5000.00,
            'current_amount' => 5000.00,
            'target_date' => now()->subMonth(),
            'start_date' => now()->subYear(),
            'assigned_module' => 'savings',
            'priority' => 'low',
            'status' => 'completed',
        ]);

        $result = $this->agent->analyze($this->user->id);

        expect($result['data']['goal_liquidity']['total_outstanding'])->toBe(7000.00)
            ->and($result['data']['goal_liquidity']['goals'])->toHaveCount(1)
            ->and($result['data']['goal_liquidity']['goals'][0]['name'])->toBe('Active Goal');
    });

    it('returns zero outstanding when user has no active goals', function () {
        $result = $this->agent->analyze($this->user->id);

        expect($result['data'])->toHaveKey('goal_liquidity')
            ->and($result['data']['goal_liquidity']['total_outstanding'])->toBe(0.0)
            ->and($result['data']['goal_liquidity']['goals'])->toBeEmpty();
    });

    it('excludes fully funded goals from the goals list', function () {
        Goal::create([
            'user_id' => $this->user->id,
            'goal_name' => 'Fully Funded',
            'goal_type' => 'holiday',
            'target_amount' => 5000.00,
            'current_amount' => 6000.00,
            'target_date' => now()->addMonths(6),
            'start_date' => now()->subMonths(3),
            'assigned_module' => 'savings',
            'priority' => 'low',
            'status' => 'active',
        ]);

        Goal::create([
            'user_id' => $this->user->id,
            'goal_name' => 'Partially Funded',
            'goal_type' => 'emergency_fund',
            'target_amount' => 10000.00,
            'current_amount' => 2000.00,
            'target_date' => now()->addYear(),
            'start_date' => now()->subMonths(6),
            'assigned_module' => 'savings',
            'priority' => 'high',
            'status' => 'active',
        ]);

        $result = $this->agent->analyze($this->user->id);

        // Fully funded goal (current >= target) should not appear in goals list
        expect($result['data']['goal_liquidity']['total_outstanding'])->toBe(8000.00)
            ->and($result['data']['goal_liquidity']['goals'])->toHaveCount(1)
            ->and($result['data']['goal_liquidity']['goals'][0]['name'])->toBe('Partially Funded');
    });
});
