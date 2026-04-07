<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Investment;

use App\Http\Controllers\Controller;
use App\Http\Traits\SanitizedErrorResponse;
use App\Services\Investment\PortfolioStrategyService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PortfolioStrategyController extends Controller
{
    use SanitizedErrorResponse;

    public function __construct(
        private readonly PortfolioStrategyService $strategyService
    ) {}

    /**
     * Get portfolio-level strategy recommendations
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $userId = $request->user()->id;
            $result = $this->strategyService->getPortfolioStrategy($userId);

            if (! $result['success']) {
                return response()->json(['success' => false, 'data' => $result], 404);
            }

            return response()->json(['success' => true, 'data' => $result]);
        } catch (\Exception $e) {
            return $this->errorResponse($e, 'Portfolio strategy retrieval');
        }
    }

    /**
     * Get strategy recommendations for specific account
     */
    public function forAccount(Request $request, int $accountId): JsonResponse
    {
        try {
            $userId = $request->user()->id;
            $result = $this->strategyService->getAccountStrategy($userId, $accountId);

            if (! $result['success']) {
                return response()->json(['success' => false, 'data' => $result], 404);
            }

            return response()->json(['success' => true, 'data' => $result]);
        } catch (\Exception $e) {
            return $this->errorResponse($e, 'Account strategy retrieval');
        }
    }
}
