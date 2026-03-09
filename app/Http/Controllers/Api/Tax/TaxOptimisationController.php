<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Tax;

use App\Agents\TaxOptimisationAgent;
use App\Http\Controllers\Controller;
use App\Http\Traits\SanitizedErrorResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Tax Optimisation Controller
 *
 * Provides endpoints for cross-module tax optimisation analysis and strategies.
 */
class TaxOptimisationController extends Controller
{
    use SanitizedErrorResponse;

    public function __construct(
        private readonly TaxOptimisationAgent $taxAgent
    ) {}

    /**
     * Get comprehensive tax optimisation analysis.
     *
     * GET /api/tax/optimisation-analysis
     */
    public function getAnalysis(Request $request): JsonResponse
    {
        $user = $request->user();

        try {
            $result = $this->taxAgent->analyze($user->id);

            if (! ($result['success'] ?? false)) {
                return response()->json($result, 404);
            }

            return response()->json([
                'success' => true,
                'data' => $result['data'] ?? $result,
            ]);
        } catch (\Exception $e) {
            return $this->errorResponse($e, 'Tax optimisation analysis');
        }
    }

    /**
     * Get prioritised tax-saving strategies.
     *
     * GET /api/tax/strategies
     */
    public function getStrategies(Request $request): JsonResponse
    {
        $user = $request->user();

        try {
            $result = $this->taxAgent->analyze($user->id);

            if (! ($result['success'] ?? false)) {
                return response()->json($result, 404);
            }

            $data = $result['data'] ?? $result;

            return response()->json([
                'success' => true,
                'data' => [
                    'strategies' => $data['strategies'] ?? [],
                    'total_estimated_saving' => $data['total_estimated_saving'] ?? 0,
                    'strategy_count' => $data['strategy_count'] ?? 0,
                ],
            ]);
        } catch (\Exception $e) {
            return $this->errorResponse($e, 'Tax strategies retrieval');
        }
    }
}
