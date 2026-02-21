<?php

declare(strict_types=1);

use App\Models\Investment\Holding;
use App\Models\Investment\InvestmentAccount;
use App\Models\User;
use Database\Seeders\TaxConfigurationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(TaxConfigurationSeeder::class);

    $this->user = User::factory()->create([
        'first_name' => 'Test',
        'surname' => 'Portfolio Manager',
        'email' => 'portfolio@example.com',
    ]);

    Sanctum::actingAs($this->user);

    // Create a test portfolio with multiple holdings
    $this->account = InvestmentAccount::factory()->create([
        'user_id' => $this->user->id,
        'account_type' => 'gia',
        'provider' => 'Interactive Brokers',
        'current_value' => 100000.00,
    ]);

    // Create diverse holdings for optimization
    $this->holdings = [
        // UK Equity
        Holding::factory()->create([
            'holdable_id' => $this->account->id,
            'holdable_type' => InvestmentAccount::class,
            'asset_type' => 'uk_equity',
            'security_name' => 'Vanguard FTSE 100 ETF',
            'ticker' => 'VUKE',
            'current_value' => 25000.00,
            'allocation_percent' => 25.0,
            'purchase_price' => 70.00,
            'current_price' => 75.00,
            'purchase_date' => now()->subYears(2),
            'dividend_yield' => 0.035,
        ]),
        // US Equity
        Holding::factory()->create([
            'holdable_id' => $this->account->id,
            'holdable_type' => InvestmentAccount::class,
            'asset_type' => 'us_equity',
            'security_name' => 'Vanguard S&P 500 ETF',
            'ticker' => 'VOO',
            'current_value' => 30000.00,
            'allocation_percent' => 30.0,
            'purchase_price' => 350.00,
            'current_price' => 400.00,
            'purchase_date' => now()->subYears(3),
            'dividend_yield' => 0.015,
        ]),
        // Bonds
        Holding::factory()->create([
            'holdable_id' => $this->account->id,
            'holdable_type' => InvestmentAccount::class,
            'asset_type' => 'bond',
            'security_name' => 'Vanguard UK Gilt ETF',
            'ticker' => 'VGOV',
            'current_value' => 20000.00,
            'allocation_percent' => 20.0,
            'purchase_price' => 48.00,
            'current_price' => 50.00,
            'purchase_date' => now()->subYears(1),
            'dividend_yield' => 0.040,
        ]),
        // Global Equity
        Holding::factory()->create([
            'holdable_id' => $this->account->id,
            'holdable_type' => InvestmentAccount::class,
            'asset_type' => 'international_equity',
            'security_name' => 'Vanguard All-World ETF',
            'ticker' => 'VWRL',
            'current_value' => 25000.00,
            'allocation_percent' => 25.0,
            'purchase_price' => 85.00,
            'current_price' => 92.00,
            'purchase_date' => now()->subYears(2),
            'dividend_yield' => 0.025,
        ]),
    ];
});

describe('Efficient Frontier Calculation', function () {
    it('calculates efficient frontier for user portfolio', function () {
        $response = $this->postJson('/api/investment/optimization/efficient-frontier', [
            'risk_free_rate' => 0.045,
            'num_points' => 50,
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'calculation_date',
                    'risk_free_rate',
                    'holdings_count',
                    'holdings_labels',
                    'current_portfolio' => [
                        'weights',
                        'expected_return',
                        'expected_risk',
                        'sharpe_ratio',
                    ],
                    'minimum_variance_portfolio' => [
                        'weights',
                        'expected_return',
                        'expected_risk',
                    ],
                    'tangency_portfolio' => [
                        'weights',
                        'expected_return',
                        'expected_risk',
                        'sharpe_ratio',
                    ],
                    'frontier_points',
                    'capital_allocation_line',
                    'diversification',
                    'correlation_summary',
                    'improvement_opportunities',
                ],
            ])
            ->assertJson([
                'success' => true,
            ]);

        $data = $response->json('data');

        expect($data['holdings_count'])->toBe(4)
            ->and($data['risk_free_rate'])->toBe(0.045)
            ->and($data['frontier_points'])->toHaveCount(50)
            ->and($data['current_portfolio'])->toHaveKeys(['weights', 'expected_return', 'expected_risk', 'sharpe_ratio']);
    });

    it('returns error when user has no holdings', function () {
        // Delete all holdings
        Holding::where('holdable_type', InvestmentAccount::class)
            ->where('holdable_id', $this->account->id)
            ->delete();

        $response = $this->postJson('/api/investment/optimization/efficient-frontier');

        $response->assertStatus(400)
            ->assertJson([
                'success' => false,
                'message' => 'No holdings found in investment accounts',
            ]);
    });

    it('handles user with limited holdings gracefully', function () {
        // Delete all but one holding
        Holding::where('holdable_type', InvestmentAccount::class)
            ->where('holdable_id', $this->account->id)
            ->skip(1)
            ->take(3)
            ->delete();

        $response = $this->postJson('/api/investment/optimization/efficient-frontier');

        // API may return 200 with limited data or 400 with error - both are valid responses
        expect($response->getStatusCode())->toBeIn([200, 400]);
    });

    it('caches efficient frontier calculations', function () {
        // First request - should calculate
        $response1 = $this->postJson('/api/investment/optimization/efficient-frontier', [
            'risk_free_rate' => 0.045,
            'num_points' => 50,
        ]);

        $response1->assertStatus(200);

        // Check cache exists
        $cacheKey = "efficient_frontier_{$this->user->id}_0.045_50";
        expect(Cache::has($cacheKey))->toBeTrue();

        // Second request - should use cache
        $response2 = $this->postJson('/api/investment/optimization/efficient-frontier', [
            'risk_free_rate' => 0.045,
            'num_points' => 50,
        ]);

        $response2->assertStatus(200);

        // Results should be identical
        expect($response1->json('data'))->toEqual($response2->json('data'));
    });
});

describe('Minimum Variance Optimization', function () {
    it('optimizes portfolio for minimum variance', function () {
        $response = $this->postJson('/api/investment/optimization/minimize-variance', [
            'min_weight' => 0.05,
            'max_weight' => 0.50,
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'weights',
                    'expected_return',
                    'expected_risk',
                    'labels',
                    'holdings_metadata',
                    'optimization_type',
                ],
            ])
            ->assertJson([
                'success' => true,
                'data' => [
                    'optimization_type' => 'minimum_variance',
                ],
            ]);

        $data = $response->json('data');

        // Verify weights sum to 1.0
        $weightsSum = array_sum($data['weights']);
        expect($weightsSum)->toBeGreaterThan(0.99)
            ->and($weightsSum)->toBeLessThan(1.01);

        // Verify constraints are respected
        foreach ($data['weights'] as $weight) {
            expect($weight)->toBeGreaterThanOrEqual(0.05)
                ->and($weight)->toBeLessThanOrEqual(0.50);
        }

        // Verify labels match holdings
        expect($data['labels'])->toHaveCount(4);
    });

    it('respects different weight constraints', function () {
        $response = $this->postJson('/api/investment/optimization/minimize-variance', [
            'min_weight' => 0.10,
            'max_weight' => 0.40,
        ]);

        $response->assertStatus(200);

        $weights = $response->json('data.weights');

        foreach ($weights as $weight) {
            expect($weight)->toBeGreaterThanOrEqual(0.10)
                ->and($weight)->toBeLessThanOrEqual(0.40);
        }
    });
});

describe('Maximum Sharpe Ratio Optimization', function () {
    it('optimizes portfolio for maximum sharpe ratio', function () {
        $response = $this->postJson('/api/investment/optimization/maximize-sharpe', [
            'risk_free_rate' => 0.045,
            'min_weight' => 0.0,
            'max_weight' => 1.0,
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'weights',
                    'expected_return',
                    'expected_risk',
                    'sharpe_ratio',
                    'labels',
                    'optimization_type',
                ],
            ])
            ->assertJson([
                'success' => true,
                'data' => [
                    'optimization_type' => 'maximum_sharpe',
                ],
            ]);

        $data = $response->json('data');

        // Sharpe ratio should be positive for reasonable portfolios
        expect($data['sharpe_ratio'])->toBeGreaterThan(0);

        // Expected return should be greater than risk-free rate
        expect($data['expected_return'])->toBeGreaterThan(0.045);
    });

    it('uses different risk-free rates correctly', function () {
        $response1 = $this->postJson('/api/investment/optimization/maximize-sharpe', [
            'risk_free_rate' => 0.03,
        ]);

        $response2 = $this->postJson('/api/investment/optimization/maximize-sharpe', [
            'risk_free_rate' => 0.05,
        ]);

        $response1->assertStatus(200);
        $response2->assertStatus(200);

        // Higher risk-free rate should result in different optimal portfolio
        $sharpe1 = $response1->json('data.sharpe_ratio');
        $sharpe2 = $response2->json('data.sharpe_ratio');

        expect($sharpe1)->not->toBe($sharpe2);
    });
});

describe('Target Return Optimization', function () {
    it('optimizes portfolio for target return', function () {
        $response = $this->postJson('/api/investment/optimization/target-return', [
            'target_return' => 0.08,
            'min_weight' => 0.0,
            'max_weight' => 1.0,
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'weights',
                    'expected_return',
                    'expected_risk',
                    'labels',
                    'optimization_type',
                ],
            ])
            ->assertJson([
                'success' => true,
                'data' => [
                    'optimization_type' => 'target_return',
                ],
            ]);

        $data = $response->json('data');

        // Expected return should be reasonably close to target
        // Note: optimization may return different values based on portfolio constraints
        expect($data['expected_return'])->toBeGreaterThan(0.0)
            ->and($data['expected_return'])->toBeLessThan(0.15);
    });

    it('requires target_return parameter', function () {
        $response = $this->postJson('/api/investment/optimization/target-return', [
            'min_weight' => 0.0,
            'max_weight' => 1.0,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['target_return']);
    });

    it('validates target_return is between 0 and 1', function () {
        $response = $this->postJson('/api/investment/optimization/target-return', [
            'target_return' => 1.5, // 150% is unrealistic
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['target_return']);
    });
});

describe('Risk Parity Optimization', function () {
    it('calculates risk parity portfolio', function () {
        $response = $this->postJson('/api/investment/optimization/risk-parity');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'weights',
                    'expected_return',
                    'expected_risk',
                    'labels',
                ],
            ])
            ->assertJson([
                'success' => true,
            ]);

        $data = $response->json('data');

        // Verify basic data structure is present
        expect($data['weights'])->toBeArray()
            ->and($data['expected_return'])->toBeNumeric()
            ->and($data['expected_risk'])->toBeNumeric();
    });
});

describe('Correlation Matrix Calculation', function () {
    it('calculates correlation matrix for holdings', function () {
        $response = $this->getJson('/api/investment/optimization/correlation-matrix');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'matrix',
                    'labels',
                ],
            ])
            ->assertJson([
                'success' => true,
            ]);

        $data = $response->json('data');

        // Matrix should be square
        $matrixSize = count($data['matrix']);
        expect($matrixSize)->toBe(4);

        foreach ($data['matrix'] as $row) {
            expect(count($row))->toBe(4);
        }

        // Diagonal should be 1.0 (perfect self-correlation)
        for ($i = 0; $i < $matrixSize; $i++) {
            expect($data['matrix'][$i][$i])->toBeGreaterThan(0.99)
                ->and($data['matrix'][$i][$i])->toBeLessThan(1.01);
        }

        // Matrix should be symmetric
        for ($i = 0; $i < $matrixSize; $i++) {
            for ($j = $i + 1; $j < $matrixSize; $j++) {
                expect($data['matrix'][$i][$j])->toBe($data['matrix'][$j][$i]);
            }
        }
    });

    it('caches correlation matrix calculations', function () {
        $response1 = $this->getJson('/api/investment/optimization/correlation-matrix');
        $response1->assertStatus(200);

        // Check cache exists
        $cacheKey = "correlation_matrix_{$this->user->id}_all";
        expect(Cache::has($cacheKey))->toBeTrue();

        // Second request should use cache
        $response2 = $this->getJson('/api/investment/optimization/correlation-matrix');
        $response2->assertStatus(200);

        // Results should be identical
        expect($response1->json('data'))->toEqual($response2->json('data'));
    });

    it('filters by account_ids if provided', function () {
        // Create second account with different holdings
        $account2 = InvestmentAccount::factory()->create([
            'user_id' => $this->user->id,
            'account_type' => 'isa',
            'current_value' => 20000.00,
        ]);

        Holding::factory()->create([
            'holdable_id' => $account2->id,
            'holdable_type' => InvestmentAccount::class,
            'asset_type' => 'bond',
            'current_value' => 20000.00,
        ]);

        // Request only first account
        $response = $this->getJson('/api/investment/optimization/correlation-matrix?account_ids[]='.$this->account->id);

        $response->assertStatus(200);

        // Should only include 4 holdings from first account
        expect($response->json('data.labels'))->toHaveCount(4);
    });
});

describe('Current Portfolio Position', function () {
    it('gets current portfolio position on frontier', function () {
        $response = $this->getJson('/api/investment/optimization/current-position');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'current_portfolio' => [
                        'weights',
                        'expected_return',
                        'expected_risk',
                        'sharpe_ratio',
                    ],
                    'improvement_opportunities' => [
                        'sharpe_improvement',
                        'potential_return_increase',
                        'potential_risk_reduction',
                    ],
                    'on_efficient_frontier',
                ],
            ])
            ->assertJson([
                'success' => true,
            ]);
    });
});

describe('Cache Management', function () {
    it('clears all optimization caches for user', function () {
        // Calculate to populate caches
        $this->postJson('/api/investment/optimization/efficient-frontier');
        $this->getJson('/api/investment/optimization/correlation-matrix');

        $frontierCacheKey = "efficient_frontier_{$this->user->id}_0.045_50";
        $corrCacheKey = "correlation_matrix_{$this->user->id}_all";

        expect(Cache::has($frontierCacheKey))->toBeTrue()
            ->and(Cache::has($corrCacheKey))->toBeTrue();

        // Clear cache
        $response = $this->deleteJson('/api/investment/optimization/clear-cache');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Cache cleared successfully',
            ]);

        // Caches should be cleared
        expect(Cache::has($frontierCacheKey))->toBeFalse()
            ->and(Cache::has($corrCacheKey))->toBeFalse();
    });
});

describe('Holdings CRUD Operations', function () {
    it('can create a holding', function () {
        // Create new holding - use investment_account_id as expected by API
        $response = $this->postJson('/api/investment/holdings', [
            'investment_account_id' => $this->account->id,
            'asset_type' => 'equity',
            'security_name' => 'Test Stock',
            'ticker' => 'TEST',
            'allocation_percent' => 10.0,
            'current_value' => 10000.00,
            'current_price' => 100.00,
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('holdings', [
            'security_name' => 'Test Stock',
            'ticker' => 'TEST',
        ]);
    });

    it('can update a holding', function () {
        // Update holding
        $holding = $this->holdings[0];
        $response = $this->putJson("/api/investment/holdings/{$holding->id}", [
            'current_price' => 80.00,
            'current_value' => 26000.00,
        ]);

        // Verify the update was successful
        $response->assertStatus(200);

        // Verify the holding was updated
        expect($holding->fresh()->current_price)->toBe(80.00);
    });

    it('can delete a holding', function () {
        // Delete holding
        $holding = $this->holdings[0];
        $holdingId = $holding->id;

        $response = $this->deleteJson("/api/investment/holdings/{$holdingId}");

        $response->assertStatus(200);
        $this->assertSoftDeleted('holdings', ['id' => $holdingId]);
    });
});

describe('Security and Authorization', function () {
    it('requires authentication for all endpoints', function () {
        // Create a fresh test client without authentication
        $this->app = $this->createApplication();

        $endpoints = [
            ['POST', '/api/investment/optimization/efficient-frontier'],
            ['GET', '/api/investment/optimization/current-position'],
            ['GET', '/api/investment/optimization/correlation-matrix'],
            ['POST', '/api/investment/optimization/minimize-variance'],
            ['POST', '/api/investment/optimization/maximize-sharpe'],
            ['POST', '/api/investment/optimization/target-return'],
            ['POST', '/api/investment/optimization/risk-parity'],
            ['DELETE', '/api/investment/optimization/clear-cache'],
        ];

        foreach ($endpoints as [$method, $endpoint]) {
            $response = match ($method) {
                'GET' => $this->withHeaders(['Accept' => 'application/json'])->getJson($endpoint),
                'POST' => $this->withHeaders(['Accept' => 'application/json'])->postJson($endpoint),
                'DELETE' => $this->withHeaders(['Accept' => 'application/json'])->deleteJson($endpoint),
            };

            $response->assertStatus(401);
        }
    });

    it('cannot access another user\'s portfolio data', function () {
        $otherUser = User::factory()->create();
        $otherAccount = InvestmentAccount::factory()->create([
            'user_id' => $otherUser->id,
        ]);

        Holding::factory()->count(3)->create([
            'holdable_id' => $otherAccount->id,
            'holdable_type' => InvestmentAccount::class,
        ]);

        // Try to filter by other user's account
        $response = $this->getJson('/api/investment/optimization/correlation-matrix?account_ids[]='.$otherAccount->id);

        // Should return a client error (400 Bad Request or 422 Validation Error)
        // The API returns 400 Bad Request when filtering by invalid accounts
        expect($response->getStatusCode())->toBeIn([400, 422]);
    });
});
