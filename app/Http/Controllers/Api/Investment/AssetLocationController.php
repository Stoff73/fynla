<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Investment;

use App\Constants\TaxDefaults;
use App\Http\Controllers\Controller;
use App\Http\Traits\SanitizedErrorResponse;
use App\Services\Investment\AssetLocation\AccountTypeRecommender;
use App\Services\Investment\AssetLocation\AssetLocationOptimizer;
use App\Services\Investment\AssetLocation\TaxDragCalculator;
use App\Services\TaxConfigService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/**
 * Asset Location Controller
 * Manages API endpoints for asset location optimization across account types
 */
class AssetLocationController extends Controller
{
    use SanitizedErrorResponse;

    public function __construct(
        private readonly AssetLocationOptimizer $optimizer,
        private readonly TaxDragCalculator $taxDragCalculator,
        private readonly AccountTypeRecommender $recommender,
        private readonly TaxConfigService $taxConfig
    ) {}

    /**
     * Get comprehensive asset location analysis
     *
     * GET /api/investment/asset-location/analyze
     */
    public function analyzeAssetLocation(Request $request): JsonResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'isa_allowance_used' => 'nullable|numeric|min:0|max:'.(string) TaxDefaults::ISA_ALLOWANCE,
            'cgt_allowance_used' => 'nullable|numeric|min:0',
            'dividend_allowance_used' => 'nullable|numeric|min:0',
            'expected_return' => 'nullable|numeric|min:0|max:0.5',
            'prefer_pension' => 'nullable|boolean',
        ]);

        try {
            $cacheKey = "asset_location_analysis_{$user->id}";

            $result = Cache::remember($cacheKey, 86400, function () use ($user, $validated) {
                return $this->optimizer->analyzeAssetLocation($user->id, $validated);
            });

            if (! $result['success']) {
                return response()->json($result, 404);
            }

            return response()->json([
                'success' => true,
                'data' => $result,
            ]);
        } catch (\Exception $e) {
            return $this->errorResponse($e, 'Asset location analysis');
        }
    }

    /**
     * Get placement recommendations for all holdings
     *
     * GET /api/investment/asset-location/recommendations
     */
    public function getRecommendations(Request $request): JsonResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'priority' => 'nullable|in:high,medium,low',
            'min_saving' => 'nullable|numeric|min:0',
        ]);

        try {
            $userTaxProfile = $this->buildDefaultTaxProfile($user);
            $result = $this->recommender->generateRecommendations($user->id, $userTaxProfile);

            // Filter by priority if specified
            if (isset($validated['priority'])) {
                $result['recommendations'] = array_filter(
                    $result['recommendations'],
                    fn ($rec) => $rec['priority'] === $validated['priority']
                );
                $result['recommendations'] = array_values($result['recommendations']);
            }

            // Filter by minimum saving if specified
            if (isset($validated['min_saving'])) {
                $result['recommendations'] = array_filter(
                    $result['recommendations'],
                    fn ($rec) => $rec['potential_annual_saving'] >= $validated['min_saving']
                );
                $result['recommendations'] = array_values($result['recommendations']);
            }

            return response()->json([
                'success' => true,
                'data' => $result,
            ]);
        } catch (\Exception $e) {
            return $this->errorResponse($e, 'Asset location recommendations');
        }
    }

    /**
     * Calculate portfolio tax drag
     *
     * GET /api/investment/asset-location/tax-drag
     */
    public function calculateTaxDrag(Request $request): JsonResponse
    {
        $user = $request->user();

        try {
            $userTaxProfile = $this->buildDefaultTaxProfile($user);
            $result = $this->taxDragCalculator->calculatePortfolioTaxDrag($user->id, $userTaxProfile);

            return response()->json([
                'success' => true,
                'data' => $result,
            ]);
        } catch (\Exception $e) {
            return $this->errorResponse($e, 'Tax drag calculation');
        }
    }

    /**
     * Get optimization score
     *
     * GET /api/investment/asset-location/optimization-score
     */
    public function getOptimizationScore(Request $request): JsonResponse
    {
        $user = $request->user();

        try {
            $cacheKey = "asset_location_score_{$user->id}";

            $result = Cache::remember($cacheKey, 86400, function () use ($user) {
                $analysis = $this->optimizer->analyzeAssetLocation($user->id);

                if (! $analysis['success']) {
                    return $analysis;
                }

                return [
                    'success' => true,
                    'optimization_score' => $analysis['optimization_score'],
                    'summary' => $analysis['summary'],
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
            return $this->errorResponse($e, 'Optimisation score calculation');
        }
    }

    /**
     * Compare account types for a specific holding
     *
     * POST /api/investment/asset-location/compare-accounts
     */
    public function compareAccountTypes(Request $request): JsonResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'holding_id' => 'required|integer|exists:holdings,id',
        ]);

        try {
            // SECURITY: Fetch with ownership check to prevent information disclosure
            $holding = \App\Models\Investment\Holding::whereHas('investmentAccount', function ($query) use ($user) {
                $query->where('user_id', $user->id);
            })->where('id', $validated['holding_id'])->firstOrFail();

            $userTaxProfile = $this->buildDefaultTaxProfile($user);
            $comparison = $this->taxDragCalculator->compareAccountTypes($holding, $userTaxProfile);

            return response()->json([
                'success' => true,
                'data' => $comparison,
            ]);
        } catch (\Exception $e) {
            return $this->errorResponse($e, 'Account type comparison');
        }
    }

    /**
     * Clear asset location caches
     *
     * DELETE /api/investment/asset-location/clear-cache
     */
    public function clearCache(Request $request): JsonResponse
    {
        $user = $request->user();

        try {
            $cacheKeys = [
                "asset_location_analysis_{$user->id}",
                "asset_location_score_{$user->id}",
            ];

            foreach ($cacheKeys as $key) {
                Cache::forget($key);
            }

            return response()->json([
                'success' => true,
                'message' => 'Asset location caches cleared',
            ]);
        } catch (\Exception $e) {
            return $this->errorResponse($e, 'Asset location cache clearing');
        }
    }

    /**
     * Build default tax profile for user
     *
     * @param  \App\Models\User  $user  User
     * @return array Tax profile
     */
    private function buildDefaultTaxProfile($user): array
    {
        $annualIncome = $user->gross_annual_income ?? 50000;
        $age = $user->date_of_birth
            ? \Carbon\Carbon::parse($user->date_of_birth)->age
            : 45;

        $incomeTax = $this->taxConfig->getIncomeTax();
        $higherRateThreshold = (float) ($incomeTax['bands'][0]['upper_limit'] ?? 50270);

        $isaAllowance = $this->taxConfig->getISAAllowances()['annual_allowance'] ?? 20000;
        $retirementAge = (int) $this->taxConfig->get('pension.state_pension.current_spa', 66);
        $expectedReturn = (float) $this->taxConfig->get('assumptions.investment_growth.balanced_portfolio', 0.04);
        $basicRate = (float) $this->taxConfig->get('income_tax.bands.0.rate', 0.20);

        return [
            'annual_income' => $annualIncome,
            'income_tax_rate' => $this->calculateIncomeTaxRate($annualIncome),
            'cgt_rate' => $annualIncome <= $higherRateThreshold ? 0.10 : 0.20,
            'isa_allowance_remaining' => $isaAllowance,
            'cgt_allowance_used' => 0,
            'dividend_allowance_used' => 0,
            'psa_used' => 0,
            'expected_return' => $expectedReturn,
            'years_to_retirement' => max(0, $retirementAge - $age),
            'expected_withdrawal_tax_rate' => $basicRate,
            'prefer_pension' => false,
        ];
    }

    /**
     * Calculate income tax rate
     *
     * @param  float  $income  Annual income
     * @return float Tax rate
     */
    private function calculateIncomeTaxRate(float $income): float
    {
        $incomeTax = $this->taxConfig->getIncomeTax();
        $personalAllowance = (float) ($incomeTax['personal_allowance'] ?? 12570);
        $higherRateThreshold = (float) ($incomeTax['bands'][0]['upper_limit'] ?? 50270);
        $additionalRateThreshold = (float) ($incomeTax['bands'][1]['upper_limit'] ?? 125140);

        if ($income <= $personalAllowance) {
            return 0.0;
        } elseif ($income <= $higherRateThreshold) {
            return 0.20;
        } elseif ($income <= $additionalRateThreshold) {
            return 0.40;
        } else {
            return 0.45;
        }
    }

    /**
     * Clear user's asset location cache (static method for use by other controllers)
     *
     * @param  int  $userId  User ID
     */
    public static function clearUserAssetLocationCache(int $userId): void
    {
        $cacheKeys = [
            "asset_location_analysis_{$userId}",
            "asset_location_score_{$userId}",
        ];

        foreach ($cacheKeys as $key) {
            Cache::forget($key);
        }
    }
}
