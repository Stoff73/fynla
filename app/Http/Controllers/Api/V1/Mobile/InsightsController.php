<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Mobile;

use App\Http\Controllers\Controller;
use App\Http\Traits\SanitizedErrorResponse;
use App\Services\Mobile\DailyInsightService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InsightsController extends Controller
{
    use SanitizedErrorResponse;

    public function __construct(
        private readonly DailyInsightService $dailyInsight,
    ) {}

    /**
     * Get daily Fyn insight for the user.
     *
     * GET /api/v1/mobile/insights/daily
     *
     * W-0478 — the composition used to live in this controller, where it was the
     * SECOND implementation of the daily insight and the one no client called. It now
     * lives in `DailyInsightService`, which the dashboard payload's `fyn_insight`
     * reads too, so both surfaces say the same thing about the same household.
     */
    public function daily(Request $request): JsonResponse
    {
        try {
            return response()->json([
                'success' => true,
                'data' => $this->dailyInsight->daily($request->user()->id),
            ]);
        } catch (\Exception $e) {
            return $this->errorResponse($e, 'Fetching daily insight');
        }
    }
}
