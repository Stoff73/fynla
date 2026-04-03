<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Traits\SanitizedErrorResponse;
use App\Services\Investment\Analytics\CorrelationMatrixCalculator;
use App\Services\Investment\Analytics\CovarianceMatrixCalculator;
use App\Services\Investment\Analytics\EfficientFrontierCalculator;
use App\Services\Investment\Analytics\HoldingsDataExtractor;
use App\Services\Investment\Analytics\MarkowitzOptimizer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Portfolio Optimization API Controller
 * Provides endpoints for Modern Portfolio Theory analysis and optimization
 */
class PortfolioOptimizationController extends Controller
{
    use SanitizedErrorResponse;

    public function __construct(
        private EfficientFrontierCalculator $frontierCalculator,
        private MarkowitzOptimizer $optimizer,
        private HoldingsDataExtractor $holdingsExtractor,
        private CovarianceMatrixCalculator $covCalculator,
        private CorrelationMatrixCalculator $corrCalculator
    ) {}

    /**
     * Calculate efficient frontier for user's portfolio
     */
    public function calculateEfficientFrontier(Request $request): JsonResponse
    {
        $user = $request->user();
        $userId = $user->id;

        // Validate optional parameters
        $validated = $request->validate([
            'risk_free_rate' => 'nullable|numeric|min:0|max:0.15',
            'num_points' => 'nullable|integer|min:10|max:100',
        ]);

        $riskFreeRate = $validated['risk_free_rate'] ?? 0.045; // UK Gilts ~4.5%
        $numPoints = $validated['num_points'] ?? 50;

        // Cache key for this calculation
        $cacheKey = "efficient_frontier_{$userId}_{$riskFreeRate}_{$numPoints}";

        try {
            $result = Cache::remember($cacheKey, 86400, function () use ($userId, $riskFreeRate, $numPoints) {
                return $this->frontierCalculator->calculate(
                    $userId,
                    $riskFreeRate,
                    $numPoints
                );
            });

            if (! $result['success']) {
                return response()->json([
                    'success' => false,
                    'message' => $result['message'],
                ], 400);
            }

            return response()->json([
                'success' => true,
                'data' => $result,
            ]);
        } catch (\Exception $e) {
            return $this->errorResponse($e, 'Efficient frontier calculation');
        }
    }

    /**
     * Optimize portfolio for minimum variance
     */
    public function optimizeMinimumVariance(Request $request): JsonResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'min_weight' => 'nullable|numeric|min:0|max:1',
            'max_weight' => 'nullable|numeric|min:0|max:1',
            'account_ids' => 'nullable|array',
            'account_ids.*' => 'integer|exists:investment_accounts,id',
        ]);

        try {
            // Extract holdings data
            $holdingsData = $this->holdingsExtractor->extractForUser(
                $user->id,
                $validated['account_ids'] ?? null
            );

            if (! $holdingsData['success']) {
                return response()->json([
                    'success' => false,
                    'message' => $holdingsData['message'],
                ], 400);
            }

            $data = $holdingsData['data'];
            $expectedReturns = $data['expected_returns'];

            // Calculate covariance matrix from holdings
            $accounts = \App\Models\Investment\InvestmentAccount::where('user_id', $user->id)
                ->with('holdings')
                ->get();
            $holdings = $accounts->flatMap->holdings;
            $covData = $this->covCalculator->calculate($holdings);
            $covarianceMatrix = $covData['matrix'];

            $constraints = [
                'min_weight' => $validated['min_weight'] ?? 0.0,
                'max_weight' => $validated['max_weight'] ?? 1.0,
            ];

            $result = $this->optimizer->minimumVariance(
                $expectedReturns,
                $covarianceMatrix,
                $constraints
            );

            // Add labels and metadata to result
            $result['labels'] = $data['labels'];
            $result['holdings_metadata'] = $data['metadata'];

            return response()->json([
                'success' => true,
                'data' => $result,
            ]);
        } catch (\Exception $e) {
            return $this->errorResponse($e, 'Minimum variance optimisation');
        }
    }

    /**
     * Optimize portfolio for maximum Sharpe ratio
     */
    public function optimizeMaximumSharpe(Request $request): JsonResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'risk_free_rate' => 'nullable|numeric|min:0|max:0.15',
            'min_weight' => 'nullable|numeric|min:0|max:1',
            'max_weight' => 'nullable|numeric|min:0|max:1',
            'account_ids' => 'nullable|array',
            'account_ids.*' => 'integer|exists:investment_accounts,id',
        ]);

        try {
            // Extract holdings data
            $holdingsData = $this->holdingsExtractor->extractForUser(
                $user->id,
                $validated['account_ids'] ?? null
            );

            if (! $holdingsData['success']) {
                return response()->json([
                    'success' => false,
                    'message' => $holdingsData['message'],
                ], 400);
            }

            $data = $holdingsData['data'];
            $expectedReturns = $data['expected_returns'];

            // Calculate covariance matrix from holdings
            $accounts = \App\Models\Investment\InvestmentAccount::where('user_id', $user->id)
                ->with('holdings')
                ->get();
            $holdings = $accounts->flatMap->holdings;
            $covData = $this->covCalculator->calculate($holdings);
            $covarianceMatrix = $covData['matrix'];

            $riskFreeRate = $validated['risk_free_rate'] ?? 0.045;
            $constraints = [
                'min_weight' => $validated['min_weight'] ?? 0.0,
                'max_weight' => $validated['max_weight'] ?? 1.0,
            ];

            $result = $this->optimizer->maximumSharpe(
                $expectedReturns,
                $covarianceMatrix,
                $riskFreeRate,
                $constraints
            );

            // Add labels and metadata to result
            $result['labels'] = $data['labels'];
            $result['holdings_metadata'] = $data['metadata'];

            return response()->json([
                'success' => true,
                'data' => $result,
            ]);
        } catch (\Exception $e) {
            return $this->errorResponse($e, 'Maximum Sharpe optimisation');
        }
    }

    /**
     * Optimize portfolio for target return
     */
    public function optimizeTargetReturn(Request $request): JsonResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'target_return' => 'required|numeric|min:0|max:1',
            'min_weight' => 'nullable|numeric|min:0|max:1',
            'max_weight' => 'nullable|numeric|min:0|max:1',
            'account_ids' => 'nullable|array',
            'account_ids.*' => 'integer|exists:investment_accounts,id',
        ]);

        try {
            // Extract holdings data
            $holdingsData = $this->holdingsExtractor->extractForUser(
                $user->id,
                $validated['account_ids'] ?? null
            );

            if (! $holdingsData['success']) {
                return response()->json([
                    'success' => false,
                    'message' => $holdingsData['message'],
                ], 400);
            }

            $data = $holdingsData['data'];
            $expectedReturns = $data['expected_returns'];

            // Calculate covariance matrix from holdings
            $accounts = \App\Models\Investment\InvestmentAccount::where('user_id', $user->id)
                ->with('holdings')
                ->get();
            $holdings = $accounts->flatMap->holdings;
            $covData = $this->covCalculator->calculate($holdings);
            $covarianceMatrix = $covData['matrix'];

            $targetReturn = $validated['target_return'];
            $constraints = [
                'min_weight' => $validated['min_weight'] ?? 0.0,
                'max_weight' => $validated['max_weight'] ?? 1.0,
            ];

            $result = $this->optimizer->targetReturn(
                $expectedReturns,
                $covarianceMatrix,
                $targetReturn,
                $constraints
            );

            // Add labels and metadata to result
            $result['labels'] = $data['labels'];
            $result['holdings_metadata'] = $data['metadata'];

            return response()->json([
                'success' => true,
                'data' => $result,
            ]);
        } catch (\Exception $e) {
            return $this->errorResponse($e, 'Target return optimisation');
        }
    }

    /**
     * Calculate risk parity portfolio
     */
    public function optimizeRiskParity(Request $request): JsonResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'account_ids' => 'nullable|array',
            'account_ids.*' => 'integer|exists:investment_accounts,id',
        ]);

        try {
            // Extract holdings data
            $holdingsData = $this->holdingsExtractor->extractForUser(
                $user->id,
                $validated['account_ids'] ?? null
            );

            if (! $holdingsData['success']) {
                return response()->json([
                    'success' => false,
                    'message' => $holdingsData['message'],
                ], 400);
            }

            $data = $holdingsData['data'];
            $expectedReturns = $data['expected_returns'];

            // Calculate covariance matrix and volatilities from holdings
            $accounts = \App\Models\Investment\InvestmentAccount::where('user_id', $user->id)
                ->with('holdings')
                ->get();
            $holdings = $accounts->flatMap->holdings;
            $covData = $this->covCalculator->calculate($holdings);
            $covarianceMatrix = $covData['matrix'];
            $volatilities = $covData['volatilities'];

            $result = $this->optimizer->riskParity(
                $volatilities,
                $expectedReturns,
                $covarianceMatrix
            );

            // Add labels and metadata to result
            $result['labels'] = $data['labels'];
            $result['holdings_metadata'] = $data['metadata'];

            return response()->json([
                'success' => true,
                'data' => $result,
            ]);
        } catch (\Exception $e) {
            return $this->errorResponse($e, 'Risk parity optimisation');
        }
    }

    /**
     * Get current portfolio position on efficient frontier
     */
    public function getCurrentPosition(Request $request): JsonResponse
    {
        $user = $request->user();

        try {
            // Get efficient frontier data (cached)
            $frontierData = $this->frontierCalculator->calculate($user->id);

            if (! $frontierData['success']) {
                return response()->json([
                    'success' => false,
                    'message' => $frontierData['message'],
                ], 400);
            }

            // Extract just current portfolio position
            $currentPosition = $frontierData['current_portfolio'];
            $improvements = $frontierData['improvement_opportunities'];

            return response()->json([
                'success' => true,
                'data' => [
                    'current_portfolio' => $currentPosition,
                    'improvement_opportunities' => $improvements,
                    'on_efficient_frontier' => $improvements['sharpe_improvement'] < 0.05,
                ],
            ]);
        } catch (\Exception $e) {
            return $this->errorResponse($e, 'Current position calculation');
        }
    }

    /**
     * Get correlation matrix for user's holdings
     */
    public function getCorrelationMatrix(Request $request): JsonResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'account_ids' => 'nullable|array',
            'account_ids.*' => 'integer|exists:investment_accounts,id',
        ]);

        // Cache key for correlation matrix
        $accountIdsStr = isset($validated['account_ids'])
            ? implode('_', $validated['account_ids'])
            : 'all';
        $cacheKey = "correlation_matrix_{$user->id}_{$accountIdsStr}";

        try {
            $result = Cache::remember($cacheKey, 86400, function () use ($user, $validated) {
                // Get user's investment accounts
                $query = \App\Models\Investment\InvestmentAccount::where('user_id', $user->id)
                    ->with('holdings');

                if (isset($validated['account_ids'])) {
                    $query->whereIn('id', $validated['account_ids']);
                }

                $accounts = $query->get();

                if ($accounts->isEmpty()) {
                    return [
                        'success' => false,
                        'message' => 'No investment accounts found',
                    ];
                }

                $holdings = $accounts->flatMap->holdings;

                if ($holdings->isEmpty()) {
                    return [
                        'success' => false,
                        'message' => 'No holdings found in investment accounts',
                    ];
                }

                if ($holdings->count() < 2) {
                    return [
                        'success' => false,
                        'message' => 'At least 2 holdings required for correlation analysis',
                    ];
                }

                // Calculate correlation matrix
                $correlationData = $this->corrCalculator->calculate($holdings);

                return [
                    'success' => true,
                    'data' => $correlationData,
                ];
            });

            if (! $result['success']) {
                return response()->json([
                    'success' => false,
                    'message' => $result['message'],
                ], 400);
            }

            return response()->json([
                'success' => true,
                'data' => $result['data'],
            ]);
        } catch (\Exception $e) {
            return $this->errorResponse($e, 'Correlation matrix calculation');
        }
    }

    /**
     * Clear cached efficient frontier calculations
     */
    public function clearCache(Request $request): JsonResponse
    {
        $user = $request->user();
        self::clearUserOptimizationCache($user->id);

        return response()->json([
            'success' => true,
            'message' => 'Cache cleared successfully',
        ]);
    }

    /**
     * Clear all optimization caches for a user
     * Can be called statically from other controllers when holdings change
     *
     * @param  int  $userId  User ID
     */
    public static function clearUserOptimizationCache(int $userId): void
    {
        // Clear efficient frontier caches
        $riskFreeRates = [0.03, 0.035, 0.04, 0.045, 0.05];
        $numPointsOptions = [25, 50, 100];

        foreach ($riskFreeRates as $rate) {
            foreach ($numPointsOptions as $points) {
                Cache::forget("efficient_frontier_{$userId}_{$rate}_{$points}");
            }
        }

        // Clear correlation matrix caches (all account combinations)
        // Pattern: correlation_matrix_{userId}_{accountIds|"all"}
        Cache::forget("correlation_matrix_{$userId}_all");

        // Note: We can't easily clear all possible account ID combinations
        // But "all" accounts is the most common case
        // Individual account caches will expire naturally after 1 hour

        Log::info('Cleared optimization caches for user', ['user_id' => $userId]);
    }
}
