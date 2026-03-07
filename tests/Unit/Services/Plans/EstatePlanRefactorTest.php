<?php

declare(strict_types=1);

use App\Agents\EstateAgent;
use App\Models\User;
use App\Services\Coordination\RecommendationPersonaliser;
use App\Services\Estate\EstateAssetAggregatorService;
use App\Services\Estate\IHTCalculationService;
use App\Services\Estate\IHTFormattingService;
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
    $this->assetAggregator = Mockery::mock(EstateAssetAggregatorService::class);
    $this->formattingService = Mockery::mock(IHTFormattingService::class);

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

    $this->assetAggregator->shouldReceive('gatherUserAssets')->andReturn(collect());
    $this->disposableIncome->shouldReceive('getForUser')->andReturn([
        'annual' => 24000.0,
        'monthly' => 2000.0,
        'net_income' => 50000.0,
        'annual_expenditure' => 26000.0,
    ]);

    // Mock IHTFormattingService
    $this->formattingService->shouldReceive('formatAssetsBreakdown')->andReturn([
        'user' => [
            'name' => 'Test User',
            'assets' => ['investment' => [], 'property' => [], 'cash' => [], 'business' => [], 'chattel' => []],
            'total' => 800000,
            'projected_total' => 900000,
        ],
        'spouse' => null,
    ]);
    $this->formattingService->shouldReceive('formatLiabilitiesBreakdown')->andReturn([
        'user' => [
            'name' => 'Test User',
            'liabilities' => ['mortgages' => [], 'other_liabilities' => []],
            'mortgages_total' => 80000,
            'liabilities_total' => 20000,
            'total' => 100000,
            'projected_total' => 0,
        ],
        'spouse' => null,
    ]);

    $this->personaliser = Mockery::mock(RecommendationPersonaliser::class);
    $this->personaliser->shouldReceive('personaliseRecommendations')->andReturnUsing(fn ($recs, $user) => $recs);

    $this->service = new EstatePlanService(
        $this->estateAgent,
        $this->ihtCalculator,
        $this->taxConfig,
        $this->planConfig,
        $this->disposableIncome,
        $this->assetAggregator,
        $this->formattingService,
        $this->personaliser
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

describe('Joint Estate View Removed', function () {
    it('does not include joint_estate_view in plan output [4A.T2]', function () {
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

        expect($plan)->not->toHaveKey('joint_estate_view');
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

        expect($plan['current_situation']['iht_summary']['current'])->not->toHaveKey('health_score');
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

describe('Personal Information', function () {
    it('returns correct shape for married user [5.1.T1]', function () {
        $spouse = User::factory()->create(['first_name' => 'Sarah', 'surname' => 'Mitchell']);
        $user = User::factory()->create([
            'date_of_birth' => now()->subYears(50),
            'marital_status' => 'married',
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

        expect($plan)->toHaveKey('personal_information')
            ->and($plan['personal_information'])->toHaveKeys([
                'full_name', 'date_of_birth', 'age', 'marital_status',
                'spouse_name', 'children', 'gross_income', 'net_income',
                'annual_expenditure', 'disposable_income', 'monthly_disposable',
                'estimated_age_at_death', 'years_to_death', 'marital_status_iht', 'has_will',
            ])
            ->and($plan['personal_information']['marital_status'])->toBe('married')
            ->and($plan['personal_information']['marital_status_iht'])->toBe('married')
            ->and($plan['personal_information']['spouse_name'])->toContain('Sarah');
    });

    it('returns correct shape for widowed user [5.1.T2]', function () {
        $user = User::factory()->create([
            'date_of_birth' => now()->subYears(70),
            'marital_status' => 'widowed',
            'spouse_id' => null,
        ]);

        $analysisData = buildMockAnalysis(100000);
        $analysisData['data']['profile']['marital_status'] = 'widowed';
        $analysisData['data']['iht_calculation']['transferable_nrb'] = 325000;

        $this->estateAgent->shouldReceive('analyze')->once()->andReturn($analysisData);
        $this->estateAgent->shouldReceive('generateRecommendations')->once()->andReturn([
            'success' => true,
            'data' => ['recommendations' => []],
        ]);
        $this->disposableIncome->shouldReceive('getMonthlyForUser')->andReturn(2000.0);

        $plan = $this->service->generatePlan($user->id);

        expect($plan)->toHaveKey('personal_information')
            ->and($plan['personal_information']['marital_status'])->toBe('widowed')
            ->and($plan['personal_information']['marital_status_iht'])->toBe('widowed')
            ->and($plan['personal_information']['spouse_name'])->toBeNull();
    });
});

describe('Structured Executive Summary', function () {
    it('returns structured format with greeting key [5.1.T3]', function () {
        $user = User::factory()->create([
            'date_of_birth' => now()->subYears(50),
        ]);

        $analysisData = buildMockAnalysis(100000);

        $this->estateAgent->shouldReceive('analyze')->once()->andReturn($analysisData);
        $this->estateAgent->shouldReceive('generateRecommendations')->once()->andReturn([
            'success' => true,
            'data' => ['recommendations' => [
                ['category' => 'annual_gifting', 'priority' => 'medium', 'step' => 4, 'title' => 'Annual Gifting', 'description' => 'Test', 'actions' => [], 'potential_saving' => 5000],
            ]],
        ]);
        $this->disposableIncome->shouldReceive('getMonthlyForUser')->andReturn(2000.0);

        $plan = $this->service->generatePlan($user->id);

        expect($plan['executive_summary'])->toHaveKeys([
            'greeting', 'opening', 'introduction',
            'actions_summary', 'total_actions', 'closing',
        ])
            ->and($plan['executive_summary'])->not->toHaveKey('iht_summary')
            ->and($plan['executive_summary']['greeting'])->toContain('Dear');
    });
});

describe('Current Situation Expansion', function () {
    it('includes full IHT table data with calculation and breakdowns [5.1.T4]', function () {
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

        expect($plan['current_situation'])->toHaveKeys([
            'calculation', 'assets_breakdown', 'liabilities_breakdown', 'iht_summary',
            'iht_rate_type', 'iht_rate_message', 'nrb_message', 'rnrb_message',
        ])
            ->and($plan['current_situation']['iht_summary']['current'])->toHaveKeys([
                'net_estate', 'gross_assets', 'liabilities', 'nrb_available',
                'taxable_estate', 'iht_liability', 'effective_rate',
            ])
            ->and($plan['current_situation']['iht_summary']['projected'])->toHaveKeys([
                'net_estate', 'gross_assets', 'liabilities', 'taxable_estate', 'iht_liability',
            ]);
    });

    it('includes linked_accounts and supplementary cards [5.1.T5]', function () {
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

        expect($plan['current_situation'])->toHaveKey('linked_accounts')
            ->and($plan['current_situation']['linked_accounts'])->toBeArray()
            ->and($plan['current_situation'])->toHaveKey('asset_breakdown')
            ->and($plan['current_situation'])->toHaveKey('life_cover')
            ->and($plan['current_situation'])->toHaveKey('charitable_giving');
    });
});

describe('Gifting Detail Attachment', function () {
    it('merges gift_schedule for pet_gifting [5.1.T6]', function () {
        $user = User::factory()->create([
            'date_of_birth' => now()->subYears(50),
        ]);

        $analysisData = buildMockAnalysis(100000);
        $analysisData['data']['gifting_opportunities'] = [
            'strategies' => [
                [
                    'strategy_name' => 'Potentially Exempt Transfer Strategy',
                    'gift_schedule' => [
                        ['year' => 0, 'amount' => 50000, 'iht_reduction' => 20000, 'exempt_after_year' => 7],
                        ['year' => 7, 'amount' => 50000, 'iht_reduction' => 20000, 'exempt_after_year' => 14],
                    ],
                    'number_of_cycles' => 2,
                    'amount_per_cycle' => 50000,
                ],
            ],
        ];

        $this->estateAgent->shouldReceive('analyze')->once()->andReturn($analysisData);
        $this->estateAgent->shouldReceive('generateRecommendations')->once()->andReturn([
            'success' => true,
            'data' => ['recommendations' => [
                ['category' => 'pet_gifting', 'priority' => 'high', 'step' => 2, 'title' => 'PET Gifting', 'description' => 'Test', 'actions' => [], 'potential_saving' => 40000],
            ]],
        ]);
        $this->disposableIncome->shouldReceive('getMonthlyForUser')->andReturn(2000.0);

        $plan = $this->service->generatePlan($user->id);
        $petAction = collect($plan['actions'])->firstWhere('category', 'pet_gifting');

        expect($petAction)->not->toBeNull()
            ->and($petAction)->toHaveKey('gift_schedule')
            ->and($petAction['gift_schedule'])->toBeArray()
            ->and(count($petAction['gift_schedule']))->toBe(2)
            ->and($petAction['seven_year_cycles'])->toBe(2)
            ->and($petAction['amount_per_cycle'])->toBe(50000.0);
    });

    it('merges annual_gifting_detail for annual_gifting [5.1.T7]', function () {
        $user = User::factory()->create([
            'date_of_birth' => now()->subYears(50),
        ]);

        $analysisData = buildMockAnalysis(100000);
        $analysisData['data']['gifting_opportunities'] = [
            'strategies' => [
                [
                    'strategy_name' => 'Annual Exemption Gifting',
                    'annual_amount' => 3000,
                    'years' => 10,
                    'total_gifted' => 30000,
                    'iht_saved' => 12000,
                ],
            ],
        ];

        $this->estateAgent->shouldReceive('analyze')->once()->andReturn($analysisData);
        $this->estateAgent->shouldReceive('generateRecommendations')->once()->andReturn([
            'success' => true,
            'data' => ['recommendations' => [
                ['category' => 'annual_gifting', 'priority' => 'medium', 'step' => 4, 'title' => 'Annual Gifting', 'description' => 'Test', 'actions' => [], 'potential_saving' => 12000],
            ]],
        ]);
        $this->disposableIncome->shouldReceive('getMonthlyForUser')->andReturn(2000.0);

        $plan = $this->service->generatePlan($user->id);
        $annualAction = collect($plan['actions'])->firstWhere('category', 'annual_gifting');

        expect($annualAction)->not->toBeNull()
            ->and($annualAction)->toHaveKey('annual_gifting_detail')
            ->and($annualAction['annual_gifting_detail'])->toHaveKeys(['annual_amount', 'years', 'total_gifted', 'iht_saved'])
            ->and($annualAction['annual_gifting_detail']['annual_amount'])->toBe(3000.0)
            ->and($annualAction['annual_gifting_detail']['total_gifted'])->toBe(30000.0);
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
    $nrbAvailable = $hasSpouse ? 650000 : 325000;
    $rnrbAvailable = $hasSpouse ? 350000 : 175000;
    $totalAllowances = $nrbAvailable + $rnrbAvailable;
    $netEstate = 700000;
    $taxableEstate = max(0, $netEstate - $totalAllowances);

    return [
        'success' => true,
        'data' => [
            'summary' => [
                'gross_estate' => 800000,
                'net_estate' => $netEstate,
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
                'nrb_available' => $nrbAvailable,
                'nrb_individual' => $hasSpouse ? 325000 : 325000,
                'nrb_transferred' => $hasSpouse ? 325000 : 0,
                'nrb_message' => 'Test NRB message',
                'rnrb_available' => $rnrbAvailable,
                'rnrb_individual' => $hasSpouse ? 175000 : 175000,
                'rnrb_transferred' => $hasSpouse ? 175000 : 0,
                'rnrb_status' => 'full',
                'rnrb_message' => 'Test RNRB message',
                'total_allowances' => $totalAllowances,
                'taxable_estate' => $taxableEstate,
                'iht_liability' => $ihtLiability,
                'effective_rate' => 12.5,
                'total_gross_assets' => 800000,
                'total_net_estate' => $netEstate,
                'total_liabilities' => 100000,
                'projected_gross_assets' => 900000,
                'projected_liabilities' => 0,
                'projected_net_estate' => 900000,
                'projected_taxable_estate' => max(0, 900000 - $totalAllowances),
                'projected_iht_liability' => max(0, 900000 - $totalAllowances) * 0.40,
                'projected_cash' => 250000,
                'projected_investments' => 350000,
                'projected_properties' => 300000,
                'years_to_death' => 35,
                'estimated_age_at_death' => 85,
                'retirement_age' => 68,
                'is_married' => $hasSpouse,
                'is_widowed' => false,
                'data_sharing_enabled' => false,
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
            ],
            'trust_recommendations' => [],
            'gifting_opportunities' => [],
            'trust_wish_triggers' => [],
        ],
    ];
}
