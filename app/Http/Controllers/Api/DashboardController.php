<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Traits\SanitizedErrorResponse;
use App\Services\Cache\CacheInvalidationService;
use App\Services\Dashboard\DashboardAggregator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class DashboardController extends Controller
{
    use SanitizedErrorResponse;

    public function __construct(
        private readonly DashboardAggregator $aggregator,
        private readonly CacheInvalidationService $cacheInvalidation
    ) {}

    /**
     * Get aggregated dashboard overview data from all modules
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $userId = $request->user()->id;
            $cacheKey = "dashboard_{$userId}";

            $data = Cache::remember($cacheKey, 86400, function () use ($userId) {
                return $this->aggregator->aggregateOverviewData($userId);
            });

            return response()->json([
                'success' => true,
                'data' => $data,
            ]);
        } catch (\Exception $e) {
            return $this->errorResponse($e, 'Fetching dashboard data');
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

            $data = Cache::remember($cacheKey, 86400, function () use ($userId) {
                return $this->aggregator->aggregateAlerts($userId);
            });

            return response()->json([
                'success' => true,
                'data' => $data,
            ]);
        } catch (\Exception $e) {
            return $this->errorResponse($e, 'Fetching alerts');
        }
    }

    /**
     * Dismiss an alert
     */
    public function dismissAlert(Request $request, int $id): JsonResponse
    {
        try {
            $userId = $request->user()->id;

            Cache::forget("alerts_{$userId}");

            return response()->json([
                'success' => true,
                'message' => 'Alert dismissed successfully',
            ]);
        } catch (\Exception $e) {
            return $this->errorResponse($e, 'Dismissing alert');
        }
    }

    /**
     * Invalidate dashboard cache (called after any module data update)
     */
    public function invalidateCache(Request $request): JsonResponse
    {
        try {
            $userId = $request->user()->id;

            $this->cacheInvalidation->invalidateForUser($userId);

            return response()->json([
                'success' => true,
                'message' => 'Dashboard cache invalidated successfully',
            ]);
        } catch (\Exception $e) {
            return $this->errorResponse($e, 'Invalidating dashboard cache');
        }
    }
}
