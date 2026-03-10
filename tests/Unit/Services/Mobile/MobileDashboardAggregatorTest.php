<?php

declare(strict_types=1);

use App\Agents\EstateAgent;
use App\Agents\GoalsAgent;
use App\Agents\InvestmentAgent;
use App\Agents\ProtectionAgent;
use App\Agents\RetirementAgent;
use App\Agents\SavingsAgent;
use App\Models\Mortgage;
use App\Models\Property;
use App\Models\SavingsAccount;
use App\Models\User;
use App\Services\Dashboard\DashboardAggregator;
use App\Services\Mobile\MobileDashboardAggregator;
use Illuminate\Support\Facades\Cache;

beforeEach(function () {
    $this->protectionAgent = Mockery::mock(ProtectionAgent::class);
    $this->savingsAgent = Mockery::mock(SavingsAgent::class);
    $this->investmentAgent = Mockery::mock(InvestmentAgent::class);
    $this->retirementAgent = Mockery::mock(RetirementAgent::class);
    $this->estateAgent = Mockery::mock(EstateAgent::class);
    $this->goalsAgent = Mockery::mock(GoalsAgent::class);
    $this->dashboardAggregator = Mockery::mock(DashboardAggregator::class);

    $this->service = new MobileDashboardAggregator(
        $this->protectionAgent,
        $this->savingsAgent,
        $this->investmentAgent,
        $this->retirementAgent,
        $this->estateAgent,
        $this->goalsAgent,
        $this->dashboardAggregator
    );

    // Clear cache before each test
    Cache::flush();
});

afterEach(function () {
    Mockery::close();
});

describe('getAggregatedDashboard', function () {
    it('returns the correct top-level response shape', function () {
        $user = User::factory()->create();
        setupAllAgentMocks($this, $user->id);

        $result = $this->service->getAggregatedDashboard($user->id);

        expect($result)->toHaveKeys(['modules', 'net_worth', 'alerts', 'fyn_insight', 'cached_at'])
            ->and($result['modules'])->toHaveKeys(['protection', 'savings', 'investment', 'retirement', 'estate', 'goals'])
            ->and($result['net_worth'])->toHaveKeys(['total', 'breakdown'])
            ->and($result['alerts'])->toBeArray()
            ->and($result['fyn_insight'])->toBeString()
            ->and($result['cached_at'])->toBeString();
    });

    it('includes all 6 module summaries', function () {
        $user = User::factory()->create();
        setupAllAgentMocks($this, $user->id);

        $result = $this->service->getAggregatedDashboard($user->id);

        expect(array_keys($result['modules']))->toBe([
            'protection', 'savings', 'investment', 'retirement', 'estate', 'goals',
        ]);
    });

    it('extracts protection summary correctly', function () {
        $user = User::factory()->create();
        setupAllAgentMocks($this, $user->id);

        $result = $this->service->getAggregatedDashboard($user->id);
        $protection = $result['modules']['protection'];

        expect($protection['status'])->toBe('active')
            ->and($protection)->toHaveKeys(['total_coverage', 'critical_gaps', 'has_income_protection']);
    });

    it('extracts savings summary correctly', function () {
        $user = User::factory()->create();
        setupAllAgentMocks($this, $user->id);

        $result = $this->service->getAggregatedDashboard($user->id);
        $savings = $result['modules']['savings'];

        expect($savings['status'])->toBe('active')
            ->and($savings)->toHaveKeys(['total_savings', 'total_accounts', 'emergency_fund_months', 'emergency_fund_status']);
    });

    it('extracts investment summary correctly', function () {
        $user = User::factory()->create();
        setupAllAgentMocks($this, $user->id);

        $result = $this->service->getAggregatedDashboard($user->id);
        $investment = $result['modules']['investment'];

        expect($investment['status'])->toBe('active')
            ->and($investment)->toHaveKeys(['portfolio_value', 'accounts_count', 'holdings_count']);
    });

    it('extracts retirement summary correctly', function () {
        $user = User::factory()->create();
        setupAllAgentMocks($this, $user->id);

        $result = $this->service->getAggregatedDashboard($user->id);
        $retirement = $result['modules']['retirement'];

        expect($retirement['status'])->toBe('active')
            ->and($retirement)->toHaveKeys(['years_to_retirement', 'projected_income', 'target_income', 'income_gap', 'total_pensions']);
    });

    it('extracts estate summary correctly', function () {
        $user = User::factory()->create();
        setupAllAgentMocks($this, $user->id);

        $result = $this->service->getAggregatedDashboard($user->id);
        $estate = $result['modules']['estate'];

        expect($estate['status'])->toBe('active')
            ->and($estate)->toHaveKeys(['net_estate', 'iht_liability', 'effective_tax_rate']);
    });

    it('extracts goals summary correctly', function () {
        $user = User::factory()->create();
        setupAllAgentMocks($this, $user->id);

        $result = $this->service->getAggregatedDashboard($user->id);
        $goals = $result['modules']['goals'];

        expect($goals['status'])->toBe('active')
            ->and($goals)->toHaveKeys(['total_goals', 'completed_goals', 'total_target', 'total_saved']);
    });

    it('does not include any numerical scores in the response', function () {
        $user = User::factory()->create();
        setupAllAgentMocks($this, $user->id);

        $result = $this->service->getAggregatedDashboard($user->id);

        $json = json_encode($result);

        // No score keys should appear anywhere in the response
        expect($json)->not->toContain('"score"')
            ->and($json)->not->toContain('"adequacy_score"')
            ->and($json)->not->toContain('"diversification_score"')
            ->and($json)->not->toContain('"composite_score"');
    });
});

describe('caching', function () {
    it('caches the result for subsequent calls', function () {
        $user = User::factory()->create();
        setupAllAgentMocks($this, $user->id, expectOnce: true);

        // First call: agents are invoked
        $result1 = $this->service->getAggregatedDashboard($user->id);

        // Second call: should use cache, agents should NOT be called again
        $result2 = $this->service->getAggregatedDashboard($user->id);

        expect($result1)->toBe($result2);
    });

    it('uses a 5-minute cache key per user', function () {
        $user = User::factory()->create();
        setupAllAgentMocks($this, $user->id);

        $this->service->getAggregatedDashboard($user->id);

        expect(Cache::has("mobile_dashboard_{$user->id}"))->toBeTrue();
    });

    it('clears cache when clearCache is called', function () {
        $user = User::factory()->create();
        setupAllAgentMocks($this, $user->id);

        $this->service->getAggregatedDashboard($user->id);
        expect(Cache::has("mobile_dashboard_{$user->id}"))->toBeTrue();

        $this->service->clearCache($user->id);
        expect(Cache::has("mobile_dashboard_{$user->id}"))->toBeFalse();
    });
});

describe('partial failure handling', function () {
    it('still returns data when one module fails', function () {
        $user = User::factory()->create();

        // Protection throws an exception
        $this->protectionAgent->shouldReceive('analyze')
            ->with($user->id)
            ->andThrow(new \RuntimeException('Protection service unavailable'));

        // All other agents work normally
        $this->savingsAgent->shouldReceive('analyze')->with($user->id)->andReturn(fakeSavingsAnalysis());
        $this->investmentAgent->shouldReceive('analyze')->with($user->id)->andReturn(fakeInvestmentAnalysis());
        $this->retirementAgent->shouldReceive('analyze')->with($user->id)->andReturn(fakeRetirementAnalysis());
        $this->estateAgent->shouldReceive('analyze')->with($user->id)->andReturn(fakeEstateAnalysis());
        $this->goalsAgent->shouldReceive('analyze')->with($user->id)->andReturn(fakeGoalsAnalysis());
        $this->dashboardAggregator->shouldReceive('aggregateAlerts')->with($user->id)->andReturn([]);

        $result = $this->service->getAggregatedDashboard($user->id);

        // Protection should be marked unavailable
        expect($result['modules']['protection']['status'])->toBe('unavailable')
            ->and($result['modules']['protection'])->toHaveKey('message');

        // Other modules should still work
        expect($result['modules']['savings']['status'])->toBe('active')
            ->and($result['modules']['investment']['status'])->toBe('active')
            ->and($result['modules']['retirement']['status'])->toBe('active')
            ->and($result['modules']['estate']['status'])->toBe('active')
            ->and($result['modules']['goals']['status'])->toBe('active');
    });

    it('still returns data when multiple modules fail', function () {
        $user = User::factory()->create();

        $this->protectionAgent->shouldReceive('analyze')->andThrow(new \RuntimeException('fail'));
        $this->savingsAgent->shouldReceive('analyze')->andThrow(new \RuntimeException('fail'));
        $this->investmentAgent->shouldReceive('analyze')->andThrow(new \RuntimeException('fail'));
        $this->retirementAgent->shouldReceive('analyze')->with($user->id)->andReturn(fakeRetirementAnalysis());
        $this->estateAgent->shouldReceive('analyze')->with($user->id)->andReturn(fakeEstateAnalysis());
        $this->goalsAgent->shouldReceive('analyze')->with($user->id)->andReturn(fakeGoalsAnalysis());
        $this->dashboardAggregator->shouldReceive('aggregateAlerts')->andReturn([]);

        $result = $this->service->getAggregatedDashboard($user->id);

        expect($result['modules']['protection']['status'])->toBe('unavailable')
            ->and($result['modules']['savings']['status'])->toBe('unavailable')
            ->and($result['modules']['investment']['status'])->toBe('unavailable')
            ->and($result['modules']['retirement']['status'])->toBe('active')
            ->and($result['modules']['estate']['status'])->toBe('active')
            ->and($result['modules']['goals']['status'])->toBe('active');
    });

    it('returns empty alerts when alert aggregation fails', function () {
        $user = User::factory()->create();
        setupAllAgentMocksNoAlerts($this, $user->id);

        $this->dashboardAggregator->shouldReceive('aggregateAlerts')
            ->with($user->id)
            ->andThrow(new \RuntimeException('Alert service down'));

        $result = $this->service->getAggregatedDashboard($user->id);

        expect($result['alerts'])->toBe([]);
    });
});

describe('net worth calculation', function () {
    it('calculates net worth from user assets and liabilities', function () {
        $user = User::factory()->create();

        // Create a property owned individually
        $property = Property::factory()->create([
            'user_id' => $user->id,
            'current_value' => 300000,
            'ownership_type' => 'individual',
            'ownership_percentage' => 100,
        ]);

        // Create a savings account
        SavingsAccount::factory()->create([
            'user_id' => $user->id,
            'current_balance' => 25000,
            'ownership_type' => 'individual',
            'ownership_percentage' => 100,
        ]);

        // Create a mortgage linked to the property
        Mortgage::factory()->create([
            'user_id' => $user->id,
            'property_id' => $property->id,
            'outstanding_balance' => 200000,
            'ownership_type' => 'individual',
            'ownership_percentage' => 100,
        ]);

        setupAllAgentMocks($this, $user->id);

        $result = $this->service->getAggregatedDashboard($user->id);

        expect($result['net_worth']['total'])->toBeGreaterThan(0.0)
            ->and($result['net_worth']['breakdown'])->toHaveKeys(['assets', 'liabilities', 'total_assets', 'total_liabilities'])
            ->and($result['net_worth']['breakdown']['assets'])->toHaveKeys([
                'property', 'savings', 'investments', 'pensions', 'business', 'chattels', 'cash',
            ])
            ->and($result['net_worth']['breakdown']['liabilities'])->toHaveKeys([
                'mortgages', 'other_liabilities',
            ]);
    });

    it('handles joint assets correctly with ownership percentages', function () {
        $primaryUser = User::factory()->create();
        $jointUser = User::factory()->create();

        // Create a jointly-owned property (60/40 split)
        Property::factory()->create([
            'user_id' => $primaryUser->id,
            'joint_owner_id' => $jointUser->id,
            'current_value' => 500000,
            'ownership_type' => 'joint',
            'ownership_percentage' => 60,
        ]);

        setupAllAgentMocks($this, $primaryUser->id);

        $result = $this->service->getAggregatedDashboard($primaryUser->id);

        // Primary user should get 60% of £500k = £300k
        expect($result['net_worth']['breakdown']['assets']['property'])->toBe(300000.0);
    });

    it('includes joint owner share when queried as joint owner', function () {
        $primaryUser = User::factory()->create();
        $jointUser = User::factory()->create();

        // Create a jointly-owned property (60/40 split)
        Property::factory()->create([
            'user_id' => $primaryUser->id,
            'joint_owner_id' => $jointUser->id,
            'current_value' => 500000,
            'ownership_type' => 'joint',
            'ownership_percentage' => 60,
        ]);

        setupAllAgentMocks($this, $jointUser->id);

        $result = $this->service->getAggregatedDashboard($jointUser->id);

        // Joint user should get 40% of £500k = £200k
        expect($result['net_worth']['breakdown']['assets']['property'])->toBe(200000.0);
    });

    it('returns zero net worth for user with no assets', function () {
        $user = User::factory()->create();
        setupAllAgentMocks($this, $user->id);

        $result = $this->service->getAggregatedDashboard($user->id);

        expect($result['net_worth']['total'])->toBe(0.0);
    });
});

describe('fyn insight generation', function () {
    it('returns a string insight', function () {
        $user = User::factory()->create();
        setupAllAgentMocks($this, $user->id);

        $result = $this->service->getAggregatedDashboard($user->id);

        expect($result['fyn_insight'])->toBeString()
            ->and(strlen($result['fyn_insight']))->toBeGreaterThan(10);
    });

    it('generates protection-related insight when gaps exist', function () {
        $user = User::factory()->create();

        // Protection with gaps
        $this->protectionAgent->shouldReceive('analyze')->with($user->id)->andReturn([
            'success' => true,
            'message' => 'OK',
            'data' => [
                'coverage' => ['total_life_cover' => 100000, 'income_protection_coverage' => 0],
                'gaps' => [
                    'life' => ['gap' => 50000],
                    'income_protection' => ['gap' => 20000],
                ],
            ],
            'timestamp' => now()->toIso8601String(),
        ]);
        $this->savingsAgent->shouldReceive('analyze')->with($user->id)->andReturn(fakeSavingsAnalysis());
        $this->investmentAgent->shouldReceive('analyze')->with($user->id)->andReturn(fakeInvestmentAnalysis());
        $this->retirementAgent->shouldReceive('analyze')->with($user->id)->andReturn(fakeRetirementAnalysis());
        $this->estateAgent->shouldReceive('analyze')->with($user->id)->andReturn(fakeEstateAnalysis());
        $this->goalsAgent->shouldReceive('analyze')->with($user->id)->andReturn(fakeGoalsAnalysis());
        $this->dashboardAggregator->shouldReceive('aggregateAlerts')->andReturn([]);

        $result = $this->service->getAggregatedDashboard($user->id);

        expect($result['fyn_insight'])->toContain('protection gap');
    });
});

// Helper functions

function setupAllAgentMocks($test, int $userId, bool $expectOnce = false): void
{
    $protectionMock = $test->protectionAgent->shouldReceive('analyze')->with($userId);
    $savingsMock = $test->savingsAgent->shouldReceive('analyze')->with($userId);
    $investmentMock = $test->investmentAgent->shouldReceive('analyze')->with($userId);
    $retirementMock = $test->retirementAgent->shouldReceive('analyze')->with($userId);
    $estateMock = $test->estateAgent->shouldReceive('analyze')->with($userId);
    $goalsMock = $test->goalsAgent->shouldReceive('analyze')->with($userId);
    $alertsMock = $test->dashboardAggregator->shouldReceive('aggregateAlerts')->with($userId);

    if ($expectOnce) {
        $protectionMock->once();
        $savingsMock->once();
        $investmentMock->once();
        $retirementMock->once();
        $estateMock->once();
        $goalsMock->once();
        $alertsMock->once();
    }

    $protectionMock->andReturn(fakeProtectionAnalysis());
    $savingsMock->andReturn(fakeSavingsAnalysis());
    $investmentMock->andReturn(fakeInvestmentAnalysis());
    $retirementMock->andReturn(fakeRetirementAnalysis());
    $estateMock->andReturn(fakeEstateAnalysis());
    $goalsMock->andReturn(fakeGoalsAnalysis());
    $alertsMock->andReturn(fakeAlerts());
}

function setupAllAgentMocksNoAlerts($test, int $userId): void
{
    $test->protectionAgent->shouldReceive('analyze')->with($userId)->andReturn(fakeProtectionAnalysis());
    $test->savingsAgent->shouldReceive('analyze')->with($userId)->andReturn(fakeSavingsAnalysis());
    $test->investmentAgent->shouldReceive('analyze')->with($userId)->andReturn(fakeInvestmentAnalysis());
    $test->retirementAgent->shouldReceive('analyze')->with($userId)->andReturn(fakeRetirementAnalysis());
    $test->estateAgent->shouldReceive('analyze')->with($userId)->andReturn(fakeEstateAnalysis());
    $test->goalsAgent->shouldReceive('analyze')->with($userId)->andReturn(fakeGoalsAnalysis());
}

function fakeProtectionAnalysis(): array
{
    return [
        'success' => true,
        'message' => 'Protection analysis completed successfully.',
        'data' => [
            'coverage' => [
                'total_life_cover' => 500000,
                'income_protection_coverage' => 30000,
            ],
            'gaps' => [
                'life' => ['gap' => 0],
            ],
        ],
        'timestamp' => now()->toIso8601String(),
    ];
}

function fakeSavingsAnalysis(): array
{
    return [
        'summary' => [
            'total_savings' => 25000.00,
            'total_accounts' => 3,
            'total_goals' => 2,
            'monthly_expenditure' => 2000.00,
        ],
        'emergency_fund' => [
            'runway_months' => 6.0,
            'adequacy' => ['adequacy_score' => 100, 'shortfall' => 0],
            'category' => 'Excellent',
        ],
        'isa_allowance' => ['remaining' => 12000],
        'liquidity' => ['summary' => ['risk_level' => 'Low']],
        'rate_comparisons' => [],
        'goals' => ['progress' => [], 'prioritized' => []],
    ];
}

function fakeInvestmentAnalysis(): array
{
    return [
        'portfolio_summary' => [
            'total_value' => 150000.00,
            'accounts_count' => 2,
            'holdings_count' => 12,
        ],
        'returns' => [],
        'asset_allocation' => [],
        'risk_metrics' => [],
        'fee_analysis' => [],
        'tax_wrappers' => [],
    ];
}

function fakeRetirementAnalysis(): array
{
    return [
        'success' => true,
        'message' => 'Analysis complete',
        'data' => [
            'summary' => [
                'years_to_retirement' => 15,
                'target_retirement_age' => 65,
                'projected_retirement_income' => 35000,
                'target_retirement_income' => 40000,
                'income_gap' => 5000,
                'total_pensions_count' => 3,
            ],
        ],
        'timestamp' => now()->toIso8601String(),
    ];
}

function fakeEstateAnalysis(): array
{
    return [
        'success' => true,
        'message' => 'Estate analysis completed successfully.',
        'data' => [
            'summary' => [
                'gross_estate' => 800000,
                'net_estate' => 675000,
                'total_liabilities' => 125000,
                'iht_liability' => 0,
                'effective_tax_rate' => 0,
            ],
        ],
        'timestamp' => now()->toIso8601String(),
    ];
}

function fakeGoalsAnalysis(): array
{
    return [
        'has_goals' => true,
        'summary' => [
            'total_target' => 50000,
            'total_saved' => 20000,
        ],
        'goals_count' => 3,
        'completed_count' => 1,
        'by_module' => [],
        'top_goals' => [],
        'affordability' => [],
        'streaks' => ['best_current_streak' => 0, 'longest_ever_streak' => 0],
    ];
}

function fakeAlerts(): array
{
    return [
        [
            'id' => 1,
            'module' => 'Savings',
            'severity' => 'critical',
            'title' => 'Emergency Fund Below Target',
            'message' => 'Your emergency fund covers only 4 months.',
        ],
    ];
}
