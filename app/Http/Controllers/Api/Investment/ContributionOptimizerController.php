<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Investment;

use App\Http\Controllers\Controller;
use App\Models\Investment\InvestmentAccount;
use App\Services\Investment\ContributionOptimizer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Contribution Optimizer Controller
 * API endpoints for contribution planning and optimization
 */
class ContributionOptimizerController extends Controller
{
    public function __construct(
        private ContributionOptimizer $contributionOptimizer
    ) {}

    /**
     * Optimize contribution strategy
     *
     * POST /api/investment/contribution/optimize
     */
    public function optimize(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'monthly_investable_income' => 'required|numeric|min:0',
            'lump_sum_amount' => 'nullable|numeric|min:0',
            'time_horizon_years' => 'required|integer|min:1|max:50',
            'risk_tolerance' => 'required|in:conservative,moderately_conservative,balanced,moderately_aggressive,aggressive',
            'income_tax_band' => 'required|in:basic,higher,additional',
        ]);

        $result = $this->contributionOptimizer->optimizeContributions(
            $request->user()->id,
            $validated
        );

        return response()->json($result);
    }

    /**
     * Get affordability analysis
     *
     * POST /api/investment/contribution/affordability
     */
    public function affordability(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'monthly_income' => 'required|numeric|min:0',
            'monthly_expenses' => 'required|numeric|min:0',
            'existing_commitments' => 'nullable|numeric|min:0',
        ]);

        $monthlyIncome = $validated['monthly_income'];
        $monthlyExpenses = $validated['monthly_expenses'];
        $existingCommitments = $validated['existing_commitments'] ?? 0;

        $surplus = $monthlyIncome - $monthlyExpenses - $existingCommitments;
        $investableIncome = max(0, $surplus * 0.8); // Conservative 80% allocation

        return response()->json([
            'success' => true,
            'monthly_income' => $monthlyIncome,
            'monthly_expenses' => $monthlyExpenses,
            'existing_commitments' => $existingCommitments,
            'monthly_surplus' => round($surplus, 2),
            'recommended_investment' => round($investableIncome, 2),
            'safety_buffer' => round($surplus * 0.2, 2),
            'is_affordable' => $surplus > 0,
        ]);
    }

    /**
     * Compare lump sum vs DCA strategies
     *
     * POST /api/investment/contribution/lump-sum-vs-dca
     */
    public function lumpSumVsDCA(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'lump_sum_amount' => 'required|numeric|min:100',
            'time_horizon_years' => 'required|integer|min:1|max:50',
            'risk_tolerance' => 'required|in:conservative,moderately_conservative,balanced,moderately_aggressive,aggressive',
        ]);

        // Get current portfolio value
        $user = $request->user();
        $currentValue = InvestmentAccount::where('user_id', $user->id)
            ->sum('current_value');

        $result = $this->contributionOptimizer->optimizeContributions(
            $user->id,
            [
                'monthly_investable_income' => 0,
                'lump_sum_amount' => $validated['lump_sum_amount'],
                'time_horizon_years' => $validated['time_horizon_years'],
                'risk_tolerance' => $validated['risk_tolerance'],
                'income_tax_band' => 'basic', // Default for this analysis
            ]
        );

        return response()->json([
            'success' => true,
            'analysis' => $result['lump_sum_analysis'] ?? null,
        ]);
    }
}
