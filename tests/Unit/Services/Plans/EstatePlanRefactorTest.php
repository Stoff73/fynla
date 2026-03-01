<?php

declare(strict_types=1);

use App\Agents\EstateAgent;
use App\Models\User;
use App\Services\Estate\IHTCalculationService;
use App\Services\Plans\DisposableIncomeAccessor;
use App\Services\Plans\EstatePlanService;
use App\Services\Plans\PlanConfigService;
use App\Services\TaxConfigService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->estateAgent = Mockery::mock(EstateAgent::class);
    $this->ihtCalculator = Mockery::mock(IHTCalculationService::class);
    $this->taxConfig = Mockery::mock(TaxConfigService::class);
    $this->planConfig = Mockery::mock(PlanConfigService::class);
    $this->disposableIncome = Mockery::mock(DisposableIncomeAccessor::class);

    $this->planConfig->shouldReceive('getEstateAgeGate')->andReturn(35);
    $this->planConfig->shouldReceive('getCharitableGivingThreshold')->andReturn(10);

    $this->taxConfig->shouldReceive('getInheritanceTax')->andReturn([
        'nil_rate_band' => 325000,
        'residence_nil_rate_band' => 175000,
        'standard_rate' => 0.40,
        'reduced_rate_charity' => 0.36,
    ]);
    $this->taxConfig->shouldReceive('getGiftingExemptions')->andReturn([
        'annual_exemption' => 3000,
    ]);

    $this->service = new EstatePlanService(
        $this->estateAgent,
        $this->ihtCalculator,
        $this->taxConfig,
        $this->planConfig,
        $this->disposableIncome
    );
});

afterEach(function () {
    Mockery::close();
});

describe('Estate Plan Redundancy Elimination', function () {
    it('calls estateAgent->analyze() only once during generatePlan [4A.T1]', function () {
        $user = User::factory()->create([
            'date_of_birth' => now()->subYears(50),
        ]);

        $analysisData = buildMockAnalysis(100000);

        // analyze() should be called exactly ONCE (not 4-5 times)
        $this->estateAgent->shouldReceive('analyze')
            ->once()
            ->with($user->id)
            ->andReturn($analysisData);

        $this->estateAgent->shouldReceive('generateRecommendations')
            ->once()
            ->with($analysisData)
            ->andReturn([
                'success' => true,
                'data' => ['recommendations' => [
                    ['category' => 'annual_gifting', 'priority' => 'medium', 'step' => 4, 'title' => 'Annual Gifting', 'description' => 'Test', 'actions' => [], 'potential_saving' => 5000],
                ]],
            ]);

        $this->disposableIncome->shouldReceive('getMonthlyForUser')->andReturn(2000.0);

        $plan = $this->service->generatePlan($user->id);

        expect($plan)->toHaveKey('executive_summary')
            ->and($plan)->toHaveKey('current_situation')
            ->and($plan)->toHaveKey('actions')
            ->and($plan)->not->toHaveKey('not_applicable');
    });
});

describe('Joint Estate View', function () {
    it('returns joint view for married user with spouse [4A.T2]', function () {
        $spouse = User::factory()->create(['first_name' => 'Sarah']);
        $user = User::factory()->create([
            'date_of_birth' => now()->subYears(50),
            'spouse_id' => $spouse->id,
        ]);

        $analysisData = buildMockAnalysis(100000, hasSpouse: true, spouseGross: 300000);

        $this->estateAgent->shouldReceive('analyze')->once()->andReturn($analysisData);
        $this->estateAgent->shouldReceive('generateRecommendations')->once()->andReturn([
            'success' => true,
            'data' => ['recommendations' => []],
        ]);
        $this->disposableIncome->shouldReceive('getMonthlyForUser')->andReturn(2000.0);

        $plan = $this->service->generatePlan($user->id);

        expect($plan['joint_estate_view'])->not->toBeNull()
            ->and($plan['joint_estate_view']['is_joint_view'])->toBeTrue()
            ->and($plan['joint_estate_view']['primary']['name'])->toBe($user->first_name)
            ->and($plan['joint_estate_view']['spouse']['name'])->toBe('Sarah')
            ->and($plan['joint_estate_view']['spouse']['gross_estate'])->toBe(300000.0);
    });

    it('returns null joint view for single user [4A.T3]', function () {
        $user = User::factory()->create([
            'date_of_birth' => now()->subYears(50),
            'spouse_id' => null,
        ]);

        $analysisData = buildMockAnalysis(100000, hasSpouse: false);

        $this->estateAgent->shouldReceive('analyze')->once()->andReturn($analysisData);
        $this->estateAgent->shouldReceive('generateRecommendations')->once()->andReturn([
            'success' => true,
            'data' => ['recommendations' => []],
        ]);
        $this->disposableIncome->shouldReceive('getMonthlyForUser')->andReturn(2000.0);

        $plan = $this->service->generatePlan($user->id);

        expect($plan['joint_estate_view'])->toBeNull();
    });
});

describe('Funding Source Tracking', function () {
    it('includes funding source for charitable recommendation [4A.T4]', function () {
        $user = User::factory()->create([
            'date_of_birth' => now()->subYears(50),
        ]);

        $analysisData = buildMockAnalysis(100000);

        $this->estateAgent->shouldReceive('analyze')->once()->andReturn($analysisData);
        $this->estateAgent->shouldReceive('generateRecommendations')->once()->andReturn([
            'success' => true,
            'data' => ['recommendations' => [
                [
                    'category' => 'charitable_bequest',
                    'priority' => 'high',
                    'step' => 1,
                    'title' => 'Charitable Bequest Opportunity',
                    'description' => 'Test',
                    'actions' => [],
                    'potential_saving' => 8000,
                ],
            ]],
        ]);
        $this->disposableIncome->shouldReceive('getMonthlyForUser')->andReturn(2000.0);

        $plan = $this->service->generatePlan($user->id);
        $charitableAction = collect($plan['actions'])->firstWhere('category', 'charitable_bequest');

        expect($charitableAction)->not->toBeNull()
            ->and($charitableAction['funding_source'])->toBeArray()
            ->and($charitableAction['funding_source'])->toHaveKeys(['recommended_from', 'liquid_assets_available', 'amount_needed', 'note']);
    });

    it('includes funding source for gifting recommendation [4A.T5]', function () {
        $user = User::factory()->create([
            'date_of_birth' => now()->subYears(50),
        ]);

        $analysisData = buildMockAnalysis(100000);

        $this->estateAgent->shouldReceive('analyze')->once()->andReturn($analysisData);
        $this->estateAgent->shouldReceive('generateRecommendations')->once()->andReturn([
            'success' => true,
            'data' => ['recommendations' => [
                [
                    'category' => 'annual_gifting',
                    'priority' => 'medium',
                    'step' => 4,
                    'title' => 'Annual Gifting Strategy',
                    'description' => 'Test',
                    'actions' => [],
                    'potential_saving' => 5000,
                ],
            ]],
        ]);
        $this->disposableIncome->shouldReceive('getMonthlyForUser')->andReturn(2000.0);

        $plan = $this->service->generatePlan($user->id);
        $giftingAction = collect($plan['actions'])->firstWhere('category', 'annual_gifting');

        expect($giftingAction)->not->toBeNull()
            ->and($giftingAction['funding_source'])->toBeArray()
            ->and($giftingAction['funding_source']['recommended_from'])->toBe('liquid_assets');
    });
});

describe('Life Cover Affordability', function () {
    it('marks affordable life cover with no warning [4A.T6]', function () {
        $user = User::factory()->create([
            'date_of_birth' => now()->subYears(45),
        ]);

        $analysisData = buildMockAnalysis(100000);

        $this->estateAgent->shouldReceive('analyze')->once()->andReturn($analysisData);
        $this->estateAgent->shouldReceive('generateRecommendations')->once()->andReturn([
            'success' => true,
            'data' => ['recommendations' => [
                [
                    'category' => 'new_life_cover',
                    'priority' => 'medium',
                    'step' => 5,
                    'title' => 'Whole of Life Cover',
                    'description' => 'Test',
                    'actions' => [],
                    'estimated_premium' => 1200,
                    'cover_amount' => 60000,
                ],
            ]],
        ]);
        // Monthly premium would be £100, 5% of £2000 disposable = affordable
        $this->disposableIncome->shouldReceive('getMonthlyForUser')->andReturn(2000.0);

        $plan = $this->service->generatePlan($user->id);
        $lifeCoverAction = collect($plan['actions'])->firstWhere('category', 'new_life_cover');

        expect($lifeCoverAction['affordability']['is_affordable'])->toBeTrue()
            ->and($lifeCoverAction['affordability_warning'])->toBeNull();
    });

    it('flags unaffordable life cover with warning [4A.T7]', function () {
        $user = User::factory()->create([
            'date_of_birth' => now()->subYears(45),
        ]);

        $analysisData = buildMockAnalysis(100000);

        $this->estateAgent->shouldReceive('analyze')->once()->andReturn($analysisData);
        $this->estateAgent->shouldReceive('generateRecommendations')->once()->andReturn([
            'success' => true,
            'data' => ['recommendations' => [
                [
                    'category' => 'new_life_cover',
                    'priority' => 'medium',
                    'step' => 5,
                    'title' => 'Whole of Life Cover',
                    'description' => 'Test',
                    'actions' => [],
                    'estimated_premium' => 12000,
                    'cover_amount' => 600000,
                ],
            ]],
        ]);
        // Monthly premium would be £1000, 50% of £2000 disposable = unaffordable
        $this->disposableIncome->shouldReceive('getMonthlyForUser')->andReturn(2000.0);

        $plan = $this->service->generatePlan($user->id);
        $lifeCoverAction = collect($plan['actions'])->firstWhere('category', 'new_life_cover');

        expect($lifeCoverAction['affordability']['is_affordable'])->toBeFalse()
            ->and($lifeCoverAction)->toHaveKey('affordability_warning');
    });
});

describe('Health Score Removal', function () {
    it('generated plan contains no health_score in current situation [4B.T1]', function () {
        $user = User::factory()->create([
            'date_of_birth' => now()->subYears(50),
        ]);

        $analysisData = buildMockAnalysis(100000);

        $this->estateAgent->shouldReceive('analyze')->once()->andReturn($analysisData);
        $this->estateAgent->shouldReceive('generateRecommendations')->once()->andReturn([
            'success' => true,
            'data' => ['recommendations' => []],
        ]);
        $this->disposableIncome->shouldReceive('getMonthlyForUser')->andReturn(2000.0);

        $plan = $this->service->generatePlan($user->id);

        expect($plan['current_situation']['iht_calculation'])->not->toHaveKey('health_score');
    });
});

describe('Gate Checks', function () {
    it('returns not_applicable for user under age gate [age gate]', function () {
        $user = User::factory()->create([
            'date_of_birth' => now()->subYears(30),
        ]);

        $plan = $this->service->generatePlan($user->id);

        expect($plan)->toHaveKey('not_applicable')
            ->and($plan['not_applicable'])->toBeTrue();
    });

    it('returns error plan when analysis fails [failure path]', function () {
        $user = User::factory()->create([
            'date_of_birth' => now()->subYears(50),
        ]);

        $this->estateAgent->shouldReceive('analyze')->once()->andReturn([
            'success' => false,
            'message' => 'Calculation error',
            'data' => [],
        ]);

        $plan = $this->service->generatePlan($user->id);

        expect($plan)->toHaveKey('error')
            ->and($plan['error'])->toBe('Calculation error')
            ->and($plan['what_if'])->toBeNull();
    });

    it('returns not_applicable when IHT liability is zero [IHT gate]', function () {
        $user = User::factory()->create([
            'date_of_birth' => now()->subYears(50),
        ]);

        $analysisData = buildMockAnalysis(0);

        $this->estateAgent->shouldReceive('analyze')->once()->andReturn($analysisData);

        $plan = $this->service->generatePlan($user->id);

        expect($plan)->toHaveKey('not_applicable')
            ->and($plan['not_applicable'])->toBeTrue();
    });
});

describe('Detailed Action Guidance', function () {
    it('includes step-by-step guidance for each recommendation', function () {
        $user = User::factory()->create([
            'date_of_birth' => now()->subYears(50),
        ]);

        $analysisData = buildMockAnalysis(100000);

        $this->estateAgent->shouldReceive('analyze')->once()->andReturn($analysisData);
        $this->estateAgent->shouldReceive('generateRecommendations')->once()->andReturn([
            'success' => true,
            'data' => ['recommendations' => [
                ['category' => 'charitable_bequest', 'priority' => 'high', 'step' => 1, 'title' => 'Test', 'description' => 'Test', 'actions' => [], 'potential_saving' => 5000],
                ['category' => 'annual_gifting', 'priority' => 'medium', 'step' => 4, 'title' => 'Test', 'description' => 'Test', 'actions' => [], 'potential_saving' => 3000],
            ]],
        ]);
        $this->disposableIncome->shouldReceive('getMonthlyForUser')->andReturn(2000.0);

        $plan = $this->service->generatePlan($user->id);

        foreach ($plan['actions'] as $action) {
            expect($action['guidance'])->toBeArray()
                ->and($action['guidance'])->toHaveKeys(['steps', 'timeframe', 'professional_advice'])
                ->and($action['guidance']['steps'])->toBeArray()
                ->and(count($action['guidance']['steps']))->toBeGreaterThan(0);
        }
    });
});

/**
 * Build a mock analysis response structure.
 */
function buildMockAnalysis(float $ihtLiability, bool $hasSpouse = false, float $spouseGross = 0): array
{
    return [
        'success' => true,
        'data' => [
            'summary' => [
                'gross_estate' => 800000,
                'net_estate' => 700000,
                'total_liabilities' => 100000,
                'iht_liability' => $ihtLiability,
                'effective_tax_rate' => 12.5,
            ],
            'asset_breakdown' => [
                'liquid' => 200000,
                'semi_liquid' => 300000,
                'illiquid' => 300000,
            ],
            'iht_calculation' => [
                'nrb_available' => $hasSpouse ? 650000 : 325000,
                'rnrb_available' => $hasSpouse ? 350000 : 175000,
                'spouse_net_estate' => $hasSpouse ? $spouseGross : 0,
                'user_gross_assets' => 800000,
                'user_total_liabilities' => 100000,
                'spouse_gross_assets' => $spouseGross,
                'spouse_total_liabilities' => 0,
            ],
            'life_cover' => [
                'total_cover_in_trust' => 50000,
                'total_cover_not_in_trust' => 20000,
                'user_cover_in_trust' => 50000,
                'spouse_cover_in_trust' => 0,
                'policy_count' => 1,
                'policies_not_in_trust_count' => 1,
            ],
            'charitable_analysis' => [
                'status' => 'below',
                'current_percentage' => 2.5,
                'shortfall' => 15000,
                'potential_saving' => 8000,
            ],
            'profile' => [
                'current_age' => 50,
                'marital_status' => $hasSpouse ? 'married' : 'single',
                'has_dependents' => true,
                'has_spouse' => $hasSpouse,
                'has_iht_profile' => true,
            ],
            'trust_recommendations' => [],
            'gifting_opportunities' => [],
            'trust_wish_triggers' => [],
        ],
    ];
}
