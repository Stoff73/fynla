<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Traits\SanitizedErrorResponse;
use App\Services\Dashboard\DashboardAggregator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class DashboardController extends Controller
{
    use SanitizedErrorResponse;

    public function __construct(
        private DashboardAggregator $aggregator
    ) {}

    /**
     * Get aggregated dashboard overview data from all modules
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $userId = $request->user()->id;
            $cacheKey = "dashboard_{$userId}";

            // Try to get from cache (5 minute TTL)
            $data = Cache::remember($cacheKey, 300, function () use ($userId) {
                return $this->aggregator->aggregateOverviewData($userId);
            });

            return response()->json([
                'success' => true,
                'data' => $data,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch dashboard data',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get financial health score calculated from all module scores
     */
    public function financialHealthScore(Request $request): JsonResponse
    {
        try {
            $userId = $request->user()->id;
            $cacheKey = "financial_health_score_{$userId}";

            // Try to get from cache (1 hour TTL)
            $data = Cache::remember($cacheKey, 3600, function () use ($userId) {
                return $this->aggregator->calculateFinancialHealthScore($userId);
            });

            return response()->json([
                'success' => true,
                'data' => $data,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to calculate financial health score',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get prioritized alerts from all modules
     */
    public function alerts(Request $request): JsonResponse
    {
        try {
            $userId = $request->user()->id;
            $cacheKey = "alerts_{$userId}";

            // Try to get from cache (15 minute TTL)
            $data = Cache::remember($cacheKey, 900, function () use ($userId) {
                return $this->aggregator->aggregateAlerts($userId);
            });

            return response()->json([
                'success' => true,
                'data' => $data,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch alerts',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Dismiss an alert
     */
    public function dismissAlert(Request $request, int $id): JsonResponse
    {
        try {
            $userId = $request->user()->id;

            // In a real implementation, this would update a database record
            // For now, we'll just invalidate the cache
            Cache::forget("alerts_{$userId}");

            return response()->json([
                'success' => true,
                'message' => 'Alert dismissed successfully',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to dismiss alert',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Invalidate dashboard cache (called after any module data update)
     */
    public function invalidateCache(Request $request): JsonResponse
    {
        try {
            $userId = $request->user()->id;

            Cache::forget("dashboard_{$userId}");
            Cache::forget("financial_health_score_{$userId}");
            Cache::forget("alerts_{$userId}");

            return response()->json([
                'success' => true,
                'message' => 'Dashboard cache invalidated successfully',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to invalidate cache',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
