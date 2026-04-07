<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Investment;

use App\Http\Controllers\Controller;
use App\Http\Traits\SanitizedErrorResponse;
use App\Services\Investment\Performance\AlphaBetaCalculator;
use App\Services\Investment\Performance\BenchmarkComparator;
use App\Services\Investment\Performance\PerformanceAttributionAnalyzer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/**
 * Performance Attribution Controller
 * Provides performance attribution, benchmark comparison, and risk metrics
 */
class PerformanceAttributionController extends Controller
{
    use SanitizedErrorResponse;

    public function __construct(
        private readonly PerformanceAttributionAnalyzer $attributionAnalyzer,
        private readonly BenchmarkComparator $benchmarkComparator,
        private readonly AlphaBetaCalculator $alphaBetaCalculator
    ) {}

    /**
     * Get comprehensive performance attribution analysis
     *
     * GET /api/investment/performance/analyze
     */
    public function analyzePerformance(Request $request): JsonResponse
    {
        $user = $request->user();

        $request->validate([
            'period' => 'nullable|in:1y,3y,5y,10y,max',
        ]);

        $period = $request->input('period', '1y');

        try {
            $cacheKey = "performance_attribution_{$user->id}_{$period}";

            $result = Cache::remember($cacheKey, 86400, function () use ($user, $period) {
                return $this->attributionAnalyzer->analyzePerformance($user->id, $period);
            });

            if (! $result['success']) {
                return response()->json($result, 404);
            }

            return response()->json([
                'success' => true,
                'data' => $result,
            ]);
        } catch (\Exception $e) {
            return $this->errorResponse($e, 'Performance attribution analysis');
        }
    }

    /**
     * Compare portfolio with benchmark
     *
     * GET /api/investment/performance/benchmark
     */
    public function compareWithBenchmark(Request $request): JsonResponse
    {
        $user = $request->user();

        $request->validate([
            'benchmark' => 'nullable|in:ftse_all_share,ftse_100,sp500,msci_world,uk_gilts,60_40_portfolio',
            'period' => 'nullable|in:1y,3y,5y,10y',
        ]);

        $benchmark = $request->input('benchmark', 'ftse_all_share');
        $period = $request->input('period', '1y');

        try {
            $result = $this->benchmarkComparator->compareWithBenchmark($user->id, $benchmark, $period);

            return response()->json([
                'success' => true,
                'data' => $result,
            ]);
        } catch (\Exception $e) {
            return $this->errorResponse($e, 'Benchmark comparison');
        }
    }

    /**
     * Compare with multiple benchmarks
     *
     * GET /api/investment/performance/multi-benchmark
     */
    public function compareWithMultipleBenchmarks(Request $request): JsonResponse
    {
        $user = $request->user();

        $request->validate([
            'period' => 'nullable|in:1y,3y,5y,10y',
        ]);

        $period = $request->input('period', '1y');

        try {
            $result = $this->benchmarkComparator->compareWithMultipleBenchmarks($user->id, $period);

            return response()->json([
                'success' => true,
                'data' => $result,
            ]);
        } catch (\Exception $e) {
            return $this->errorResponse($e, 'Multi-benchmark comparison');
        }
    }

    /**
     * Get risk metrics
     *
     * GET /api/investment/performance/risk-metrics
     */
    public function getRiskMetrics(Request $request): JsonResponse
    {
        $user = $request->user();

        try {
            $cacheKey = "risk_metrics_{$user->id}";

            $result = Cache::remember($cacheKey, 86400, function () {
                // Get portfolio returns (would be real data in production)
                $portfolioReturns = $this->generateSampleReturns(0.007, 0.03, 36);
                $benchmarkReturns = $this->generateSampleReturns(0.0065, 0.035, 36);

                $alphaBeta = $this->alphaBetaCalculator->calculateAlphaBeta(
                    $portfolioReturns,
                    $benchmarkReturns
                );

                $sharpe = $this->alphaBetaCalculator->calculateSharpeRatio($portfolioReturns);
                $treynor = $this->alphaBetaCalculator->calculateTreynorRatio(
                    $portfolioReturns,
                    $benchmarkReturns
                );

                return [
                    'success' => true,
                    'alpha_beta' => $alphaBeta,
                    'sharpe_ratio' => $sharpe,
                    'treynor_ratio' => $treynor,
                ];
            });

            return response()->json([
                'success' => true,
                'data' => $result,
            ]);
        } catch (\Exception $e) {
            return $this->errorResponse($e, 'Risk metrics calculation');
        }
    }

    /**
     * Clear performance caches
     *
     * DELETE /api/investment/performance/clear-cache
     */
    public function clearCache(Request $request): JsonResponse
    {
        $user = $request->user();

        try {
            $periods = ['1y', '3y', '5y', '10y', 'max'];
            $cacheKeys = ["risk_metrics_{$user->id}"];

            foreach ($periods as $period) {
                $cacheKeys[] = "performance_attribution_{$user->id}_{$period}";
            }

            foreach ($cacheKeys as $key) {
                Cache::forget($key);
            }

            return response()->json([
                'success' => true,
                'message' => 'Performance caches cleared',
            ]);
        } catch (\Exception $e) {
            return $this->errorResponse($e, 'Performance cache clearing');
        }
    }

    /**
     * Generate sample returns for testing
     *
     * @param  float  $mean  Mean return
     * @param  float  $stddev  Standard deviation
     * @param  int  $count  Number of periods
     * @return array Returns
     */
    private function generateSampleReturns(float $mean, float $stddev, int $count): array
    {
        $returns = [];
        for ($i = 0; $i < $count; $i++) {
            $u1 = mt_rand() / mt_getrandmax();
            $u2 = mt_rand() / mt_getrandmax();
            $z = sqrt(-2 * log($u1)) * cos(2 * pi() * $u2);
            $returns[] = $mean + ($z * $stddev);
        }

        return $returns;
    }

    /**
     * Clear user's performance cache (static method for use by other controllers)
     *
     * @param  int  $userId  User ID
     */
    public static function clearUserPerformanceCache(int $userId): void
    {
        $periods = ['1y', '3y', '5y', '10y', 'max'];
        $cacheKeys = ["risk_metrics_{$userId}"];

        foreach ($periods as $period) {
            $cacheKeys[] = "performance_attribution_{$userId}_{$period}";
        }

        foreach ($cacheKeys as $key) {
            Cache::forget($key);
        }
    }
}
