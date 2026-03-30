<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Investment;

use App\Http\Controllers\Controller;
use App\Services\Investment\PortfolioStrategyService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PortfolioStrategyController extends Controller
{
    public function __construct(
        private readonly PortfolioStrategyService $strategyService
    ) {}

    /**
     * Get portfolio-level strategy recommendations
     */
    public function index(Request $request): JsonResponse
    {
        $userId = $request->user()->id;
        $result = $this->strategyService->getPortfolioStrategy($userId);

        if (! $result['success']) {
            return response()->json($result, 404);
        }

        return response()->json($result);
    }

    /**
     * Get strategy recommendations for specific account
     */
    public function forAccount(Request $request, int $accountId): JsonResponse
    {
        $userId = $request->user()->id;
        $result = $this->strategyService->getAccountStrategy($userId, $accountId);

        if (! $result['success']) {
            return response()->json($result, 404);
        }

        return response()->json($result);
    }
}
