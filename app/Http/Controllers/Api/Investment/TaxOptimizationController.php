<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Investment;

use App\Http\Controllers\Controller;
use App\Http\Traits\SanitizedErrorResponse;
use App\Services\Investment\Tax\BedAndISACalculator;
use App\Services\Investment\Tax\CGTHarvestingCalculator;
use App\Services\Investment\Tax\ISAAllowanceOptimizer;
use App\Services\Investment\Tax\TaxOptimizationAnalyzer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/**
 * Tax Optimization Controller
 * Comprehensive UK tax optimization strategies for investment portfolios
 */
class TaxOptimizationController extends Controller
{
    use SanitizedErrorResponse;

    public function __construct(
        private readonly TaxOptimizationAnalyzer $taxOptimizer,
        private readonly ISAAllowanceOptimizer $isaOptimizer,
        private readonly CGTHarvestingCalculator $cgtHarvester,
        private readonly BedAndISACalculator $bedAndISACalculator
    ) {}

    /**
     * Get comprehensive tax optimization analysis
     *
     * GET /api/investment/tax-optimization/analyze
     */
    public function analyzeTaxPosition(Request $request): JsonResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'tax_year' => 'nullable|string|regex:/^\d{4}\/\d{2}$/',
        ]);

        try {
            $cacheKey = "tax_optimization_analysis_{$user->id}_".
                ($validated['tax_year'] ?? 'current');

            $result = Cache::remember($cacheKey, 86400, function () use ($user, $validated) {
                return $this->taxOptimizer->analyzeCompleteTaxPosition(
                    $user->id,
                    $validated
                );
            });

            if (! $result['success']) {
                return response()->json($result, 404);
            }

            return response()->json([
                'success' => true,
                'data' => $result,
            ]);
        } catch (\Exception $e) {
            return $this->errorResponse($e, 'Tax position analysis');
        }
    }

    /**
     * Get ISA allowance optimization recommendations
     *
     * GET /api/investment/tax-optimization/isa-strategy
     */
    public function getISAStrategy(Request $request): JsonResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'available_funds' => 'nullable|numeric|min:0',
            'monthly_contribution' => 'nullable|numeric|min:0',
            'expected_return' => 'nullable|numeric|min:0|max:1',
            'dividend_yield' => 'nullable|numeric|min:0|max:1',
            'tax_rate' => 'nullable|numeric|min:0|max:1',
        ]);

        try {
            $result = $this->isaOptimizer->calculateOptimalStrategy($user->id, $validated);

            if (! $result['success']) {
                return response()->json($result, 404);
            }

            return response()->json([
                'success' => true,
                'data' => $result,
            ]);
        } catch (\Exception $e) {
            return $this->errorResponse($e, 'ISA strategy calculation');
        }
    }

    /**
     * Get CGT tax-loss harvesting opportunities
     *
     * GET /api/investment/tax-optimization/cgt-harvesting
     */
    public function getCGTHarvestingOpportunities(Request $request): JsonResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'cgt_allowance' => 'nullable|numeric|min:0',
            'expected_gains' => 'nullable|numeric|min:0',
            'tax_rate' => 'nullable|numeric|min:0|max:1',
            'loss_carryforward' => 'nullable|numeric|min:0',
        ]);

        try {
            $result = $this->cgtHarvester->calculateHarvestingOpportunities($user->id, $validated);

            return response()->json([
                'success' => true,
                'data' => $result,
            ]);
        } catch (\Exception $e) {
            return $this->errorResponse($e, 'CGT harvesting calculation');
        }
    }

    /**
     * Get Bed and ISA transfer opportunities
     *
     * GET /api/investment/tax-optimization/bed-and-isa
     */
    public function getBedAndISAOpportunities(Request $request): JsonResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'cgt_allowance' => 'nullable|numeric|min:0',
            'isa_allowance_remaining' => 'nullable|numeric|min:0',
            'tax_rate' => 'nullable|numeric|min:0|max:1',
        ]);

        try {
            $result = $this->bedAndISACalculator->calculateOpportunities($user->id, $validated);

            return response()->json([
                'success' => true,
                'data' => $result,
            ]);
        } catch (\Exception $e) {
            return $this->errorResponse($e, 'Bed and ISA calculation');
        }
    }

    /**
     * Get tax efficiency score
     *
     * GET /api/investment/tax-optimization/efficiency-score
     */
    public function getTaxEfficiencyScore(Request $request): JsonResponse
    {
        $user = $request->user();

        try {
            $cacheKey = "tax_efficiency_score_{$user->id}";

            $result = Cache::remember($cacheKey, 86400, function () use ($user) {
                $analysis = $this->taxOptimizer->analyzeCompleteTaxPosition($user->id);

                if (! $analysis['success']) {
                    return $analysis;
                }

                return [
                    'success' => true,
                    'efficiency_score' => $analysis['efficiency_score'],
                    'current_position' => $analysis['current_position'],
                    'summary' => $analysis['summary'],
                ];
            });

            if (! $result['success']) {
                return response()->json($result, 404);
            }

            return response()->json([
                'success' => true,
                'data' => $result,
            ]);
        } catch (\Exception $e) {
            return $this->errorResponse($e, 'Tax efficiency score calculation');
        }
    }

    /**
     * Get comprehensive tax optimization recommendations
     *
     * GET /api/investment/tax-optimization/recommendations
     */
    public function getRecommendations(Request $request): JsonResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'priority' => 'nullable|in:high,medium,low',
            'type' => 'nullable|in:isa,cgt,bed_and_isa,dividend',
        ]);

        try {
            $cacheKey = "tax_recommendations_{$user->id}_".
                ($validated['priority'] ?? 'all').'_'.
                ($validated['type'] ?? 'all');

            $result = Cache::remember($cacheKey, 86400, function () use ($user, $validated) {
                $analysis = $this->taxOptimizer->analyzeCompleteTaxPosition($user->id);

                if (! $analysis['success']) {
                    return $analysis;
                }

                $recommendations = $analysis['recommendations'];

                // Filter by priority
                if (isset($validated['priority'])) {
                    $recommendations = array_filter(
                        $recommendations,
                        fn ($rec) => $rec['priority'] === $validated['priority']
                    );
                }

                // Filter by type
                if (isset($validated['type'])) {
                    $recommendations = array_filter(
                        $recommendations,
                        fn ($rec) => str_contains($rec['type'], $validated['type'])
                    );
                }

                return [
                    'success' => true,
                    'recommendations' => array_values($recommendations),
                    'count' => count($recommendations),
                    'potential_savings' => $analysis['potential_savings'],
                ];
            });

            if (! $result['success']) {
                return response()->json($result, 404);
            }

            return response()->json([
                'success' => true,
                'data' => $result,
            ]);
        } catch (\Exception $e) {
            return $this->errorResponse($e, 'Tax recommendations retrieval');
        }
    }

    /**
     * Calculate potential tax savings from proposed actions
     *
     * POST /api/investment/tax-optimization/calculate-savings
     */
    public function calculatePotentialSavings(Request $request): JsonResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'isa_contribution' => 'nullable|numeric|min:0',
            'harvest_losses' => 'nullable|numeric|min:0',
            'bed_and_isa_transfer' => 'nullable|numeric|min:0',
            'expected_return' => 'nullable|numeric|min:0|max:1',
            'tax_rate' => 'nullable|numeric|min:0|max:1',
        ]);

        try {
            $expectedReturn = $validated['expected_return'] ?? 0.06;
            $taxRate = $validated['tax_rate'] ?? 0.20;

            $savings = [];
            $totalAnnualSaving = 0;

            // ISA contribution savings
            if (isset($validated['isa_contribution']) && $validated['isa_contribution'] > 0) {
                $annualGrowth = $validated['isa_contribution'] * $expectedReturn;
                $annualDividend = $validated['isa_contribution'] * 0.02;
                $cgtSaving = $annualGrowth * $taxRate;
                $dividendSaving = $annualDividend * 0.0875;
                $isaSaving = $cgtSaving + $dividendSaving;

                $savings['isa_contribution'] = [
                    'amount' => $validated['isa_contribution'],
                    'annual_saving' => round($isaSaving, 2),
                    'five_year_saving' => round($isaSaving * 5 * 1.15, 2),
                    'ten_year_saving' => round($isaSaving * 10 * 1.30, 2),
                ];

                $totalAnnualSaving += $isaSaving;
            }

            // Loss harvesting savings
            if (isset($validated['harvest_losses']) && $validated['harvest_losses'] > 0) {
                $harvestSaving = $validated['harvest_losses'] * $taxRate;

                $savings['harvest_losses'] = [
                    'amount' => $validated['harvest_losses'],
                    'immediate_saving' => round($harvestSaving, 2),
                ];

                $totalAnnualSaving += $harvestSaving;
            }

            // Bed and ISA savings
            if (isset($validated['bed_and_isa_transfer']) && $validated['bed_and_isa_transfer'] > 0) {
                $annualGrowth = $validated['bed_and_isa_transfer'] * $expectedReturn;
                $annualDividend = $validated['bed_and_isa_transfer'] * 0.02;
                $cgtSaving = $annualGrowth * $taxRate;
                $dividendSaving = $annualDividend * 0.0875;
                $bedAndISASaving = $cgtSaving + $dividendSaving;

                $savings['bed_and_isa'] = [
                    'amount' => $validated['bed_and_isa_transfer'],
                    'annual_saving' => round($bedAndISASaving, 2),
                    'five_year_saving' => round($bedAndISASaving * 5 * 1.15, 2),
                ];

                $totalAnnualSaving += $bedAndISASaving;
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'savings' => $savings,
                    'total_annual_saving' => round($totalAnnualSaving, 2),
                    'assumptions' => [
                        'expected_return' => $expectedReturn * 100,
                        'tax_rate' => $taxRate * 100,
                        'dividend_yield' => 2.0,
                    ],
                ],
            ]);
        } catch (\Exception $e) {
            return $this->errorResponse($e, 'Potential savings calculation');
        }
    }

    /**
     * Clear tax optimization caches
     *
     * DELETE /api/investment/tax-optimization/clear-cache
     */
    public function clearCache(Request $request): JsonResponse
    {
        $user = $request->user();

        try {
            $cacheKeys = [
                "tax_optimization_analysis_{$user->id}_current",
                "tax_efficiency_score_{$user->id}",
                "tax_recommendations_{$user->id}_all_all",
                "tax_recommendations_{$user->id}_high_all",
                "tax_recommendations_{$user->id}_medium_all",
                "tax_recommendations_{$user->id}_low_all",
            ];

            foreach ($cacheKeys as $key) {
                Cache::forget($key);
            }

            return response()->json([
                'success' => true,
                'message' => 'Tax optimization caches cleared',
            ]);
        } catch (\Exception $e) {
            return $this->errorResponse($e, 'Tax cache clearing');
        }
    }

    /**
     * Clear user's tax optimization cache (static method for use by other controllers)
     *
     * @param  int  $userId  User ID
     */
    public static function clearUserTaxCache(int $userId): void
    {
        $cacheKeys = [
            "tax_optimization_analysis_{$userId}_current",
            "tax_efficiency_score_{$userId}",
            "tax_recommendations_{$userId}_all_all",
            "tax_recommendations_{$userId}_high_all",
            "tax_recommendations_{$userId}_medium_all",
            "tax_recommendations_{$userId}_low_all",
        ];

        foreach ($cacheKeys as $key) {
            Cache::forget($key);
        }
    }
}
