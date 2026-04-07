<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Investment;

use App\Http\Controllers\Controller;
use App\Http\Traits\SanitizedErrorResponse;
use App\Models\Investment\InvestmentAccount;
use App\Services\Investment\ModelPortfolio\AssetAllocationOptimizer;
use App\Services\Investment\ModelPortfolio\FundSelector;
use App\Services\Investment\ModelPortfolio\ModelPortfolioBuilder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Model Portfolio Controller
 * Manages API endpoints for model portfolio recommendations
 */
class ModelPortfolioController extends Controller
{
    use SanitizedErrorResponse;

    public function __construct(
        private ModelPortfolioBuilder $builder,
        private AssetAllocationOptimizer $optimizer,
        private FundSelector $fundSelector
    ) {}

    /**
     * Get model portfolio by risk level
     *
     * GET /api/investment/model-portfolio/{riskLevel}
     *
     * @param  string|int  $riskLevel  Numeric (1-5) or string (conservative, moderate, etc.)
     */
    public function getModelPortfolio(Request $request, string $riskLevel): JsonResponse
    {
        try {
            // Map string risk levels to numeric values
            $riskLevelMap = [
                'conservative' => 1,
                'moderately_conservative' => 2,
                'moderate' => 3,
                'moderately_aggressive' => 4,
                'aggressive' => 5,
            ];

            // Convert to integer if it's a numeric string or map from name
            if (is_numeric($riskLevel)) {
                $riskLevelInt = (int) $riskLevel;
            } else {
                $riskLevelInt = $riskLevelMap[strtolower($riskLevel)] ?? null;
            }

            if ($riskLevelInt === null || $riskLevelInt < 1 || $riskLevelInt > 5) {
                return response()->json([
                    'success' => false,
                    'message' => 'Risk level must be between 1 and 5, or a valid name (conservative, moderate, aggressive)',
                ], 422);
            }

            $portfolio = $this->builder->getModelPortfolio($riskLevelInt);

            return response()->json([
                'success' => true,
                'data' => $portfolio,
            ]);
        } catch (\Exception $e) {
            return $this->errorResponse($e, 'Fetching model portfolio', 500, ['risk_level' => $riskLevel]);
        }
    }

    /**
     * Get all model portfolios
     *
     * GET /api/investment/model-portfolio/all
     */
    public function getAllPortfolios(Request $request): JsonResponse
    {
        try {
            $portfolios = $this->builder->getAllModelPortfolios();

            return response()->json([
                'success' => true,
                'data' => $portfolios,
            ]);
        } catch (\Exception $e) {
            return $this->errorResponse($e, 'Fetching all portfolios');
        }
    }

    /**
     * Compare current allocation with model
     *
     * POST /api/investment/model-portfolio/compare
     */
    public function compareWithModel(Request $request): JsonResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'risk_level' => 'required|integer|min:1|max:5',
        ]);

        try {
            // Get user's current holdings
            $accounts = InvestmentAccount::where('user_id', $user->id)->with('holdings')->get();
            $currentHoldings = [];

            foreach ($accounts as $account) {
                foreach ($account->holdings as $holding) {
                    $currentHoldings[] = [
                        'asset_type' => $holding->asset_type,
                        'current_value' => $holding->current_value,
                    ];
                }
            }

            if (empty($currentHoldings)) {
                return response()->json([
                    'success' => false,
                    'message' => 'No holdings found to compare',
                ], 404);
            }

            $comparison = $this->builder->compareWithModel($currentHoldings, $validated['risk_level']);

            return response()->json([
                'success' => true,
                'data' => $comparison,
            ]);
        } catch (\Exception $e) {
            return $this->errorResponse($e, 'Comparing portfolio with model', 500, ['user_id' => $user->id]);
        }
    }

    /**
     * Optimize allocation by age
     *
     * GET /api/investment/model-portfolio/optimize-by-age
     */
    public function optimizeByAge(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'age' => 'required|integer|min:18|max:100',
            'rule' => 'nullable|in:100_minus_age,110_minus_age,120_minus_age',
        ]);

        try {
            $result = $this->optimizer->optimizeByAge(
                $validated['age'],
                $validated['rule'] ?? '110_minus_age'
            );

            return response()->json([
                'success' => true,
                'data' => $result,
            ]);
        } catch (\Exception $e) {
            return $this->errorResponse($e, 'Optimising by age');
        }
    }

    /**
     * Optimize allocation by time horizon
     *
     * POST /api/investment/model-portfolio/optimize-by-horizon
     */
    public function optimizeByTimeHorizon(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'years' => 'required|integer|min:1|max:50',
            'target_value' => 'required|numeric|min:0',
            'current_value' => 'required|numeric|min:0',
        ]);

        try {
            $result = $this->optimizer->optimizeByTimeHorizon(
                $validated['years'],
                $validated['target_value'],
                $validated['current_value']
            );

            return response()->json([
                'success' => true,
                'data' => $result,
            ]);
        } catch (\Exception $e) {
            return $this->errorResponse($e, 'Optimising by time horizon');
        }
    }

    /**
     * Get glide path allocation
     *
     * GET /api/investment/model-portfolio/glide-path
     */
    public function getGlidePath(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'years_to_retirement' => 'required|integer|min:0|max:50',
        ]);

        try {
            $result = $this->optimizer->getGlidePathAllocation($validated['years_to_retirement']);

            return response()->json([
                'success' => true,
                'data' => $result,
            ]);
        } catch (\Exception $e) {
            return $this->errorResponse($e, 'Calculating glide path');
        }
    }

    /**
     * Get fund recommendations
     *
     * POST /api/investment/model-portfolio/funds
     */
    public function getFundRecommendations(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'equities' => 'required|numeric|min:0|max:100',
            'bonds' => 'required|numeric|min:0|max:100',
            'cash' => 'required|numeric|min:0|max:100',
            'alternatives' => 'required|numeric|min:0|max:100',
        ]);

        $total = $validated['equities'] + $validated['bonds'] + $validated['cash'] + $validated['alternatives'];

        if (abs($total - 100) > 0.1) {
            return response()->json([
                'success' => false,
                'message' => 'Asset allocation must sum to 100%',
            ], 422);
        }

        try {
            $result = $this->fundSelector->getFundRecommendations($validated);

            return response()->json([
                'success' => true,
                'data' => $result,
            ]);
        } catch (\Exception $e) {
            return $this->errorResponse($e, 'Fetching fund recommendations');
        }
    }
}
