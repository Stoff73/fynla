<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Mobile;

use App\Http\Controllers\Controller;
use App\Http\Traits\SanitizedErrorResponse;
use App\Services\Mobile\MobileDashboardAggregator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MobileDashboardController extends Controller
{
    use SanitizedErrorResponse;

    public function __construct(
        private readonly MobileDashboardAggregator $aggregator
    ) {}

    /**
     * Get aggregated mobile dashboard data.
     *
     * Returns all module summaries, net worth, alerts, and Fyn insight
     * in a single optimised response for the mobile app.
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $userId = $request->user()->id;
            $data = $this->aggregator->getAggregatedDashboard($userId);

            return response()->json([
                'success' => true,
                'data' => $data,
            ]);
        } catch (\Exception $e) {
            return $this->errorResponse($e, 'Fetching mobile dashboard data');
        }
    }
}
