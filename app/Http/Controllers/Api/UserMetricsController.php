<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Admin\UserMetricsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserMetricsController extends Controller
{
    public function __construct(
        private readonly UserMetricsService $metricsService
    ) {}

    public function snapshot(): JsonResponse
    {
        return response()->json($this->metricsService->getSnapshot());
    }

    public function trials(): JsonResponse
    {
        return response()->json($this->metricsService->getTrialBreakdown());
    }

    public function plans(): JsonResponse
    {
        return response()->json($this->metricsService->getPlanBreakdown());
    }

    public function activity(Request $request): JsonResponse
    {
        $period = $request->input('period', 'day');
        $range = (int) $request->input('range', 7);

        $validPeriods = ['day', 'week', 'month', 'quarter', 'year'];
        if (! in_array($period, $validPeriods, true)) {
            return response()->json(['error' => 'Invalid period'], 422);
        }

        $range = max(1, min($range, 365));

        return response()->json($this->metricsService->getActivity($period, $range));
    }

    public function engagement(): JsonResponse
    {
        return response()->json($this->metricsService->getEngagementStats());
    }
}
