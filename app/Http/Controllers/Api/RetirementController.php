<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Agents\RetirementAgent;
use App\Http\Controllers\Controller;
use App\Http\Requests\Retirement\RetirementAnalysisRequest;
use App\Http\Requests\Retirement\ScenarioRequest;
use App\Http\Requests\Retirement\StoreDBPensionRequest;
use App\Http\Requests\Retirement\StoreDCPensionRequest;
use App\Http\Requests\Retirement\UpdateStatePensionRequest;
use App\Models\DBPension;
use App\Models\DCPension;
use App\Models\RetirementProfile;
use App\Models\StatePension;
use App\Services\Retirement\AnnualAllowanceChecker;
use App\Services\Retirement\RetirementProjectionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/**
 * Retirement Controller
 *
 * Handles API endpoints for the Retirement module including pension CRUD operations,
 * analysis, recommendations, and scenario planning.
 */
class RetirementController extends Controller
{
    public function __construct(
        private RetirementAgent $agent,
        private AnnualAllowanceChecker $allowanceChecker,
        private RetirementProjectionService $projectionService
    ) {}

    /**
     * Get all retirement data for the authenticated user.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $data = [
            'profile' => RetirementProfile::where('user_id', $user->id)->first(),
            'dc_pensions' => DCPension::where('user_id', $user->id)->get(),
            'db_pensions' => DBPension::where('user_id', $user->id)->get(),
            'state_pension' => StatePension::where('user_id', $user->id)->first(),
        ];

        return response()->json([
            'success' => true,
            'message' => 'Retirement data retrieved successfully',
            'data' => $data,
        ]);
    }

    /**
     * Get retirement projections with Monte Carlo simulation.
     */
    public function getProjections(Request $request): JsonResponse
    {
        $user = $request->user();

        $projections = $this->projectionService->getProjections($user->id);

        return response()->json([
            'success' => true,
            'message' => 'Retirement projections generated successfully',
            'data' => $projections,
        ]);
    }

    /**
     * Analyze user's retirement position.
     */
    public function analyze(RetirementAnalysisRequest $request): JsonResponse
    {
        $user = $request->user();
        $analysis = $this->agent->analyze($user->id);

        // If analysis failed, return as is
        if (! $analysis['success']) {
            return response()->json($analysis);
        }

        // Flatten the structure to match frontend expectations
        $data = $analysis['data'];
        $incomeProjection = $data['income_projection'] ?? [];

        $flattenedData = [
            'readiness_score' => $data['summary']['readiness_score'] ?? 0,
            'readiness_category' => $data['summary']['readiness_category'] ?? 'unknown',
            'readiness_color' => $data['summary']['readiness_color'] ?? 'gray',
            'projected_income' => $data['summary']['projected_retirement_income'] ?? 0,
            'target_income' => $data['summary']['target_retirement_income'] ?? 0,
            'income_gap' => $data['summary']['income_gap'] ?? 0,
            'years_to_retirement' => $data['summary']['years_to_retirement'] ?? 0,
            'total_pension_wealth' => $data['summary']['total_dc_value'] ?? 0,
            'recommendations' => $data['recommendations'] ?? [],
            'income_projection' => $incomeProjection,
            'breakdown' => $data['breakdown'] ?? null,
            'annual_allowance' => $data['annual_allowance'] ?? null,
            // Add projections for integration tests
            'dc_projection' => $incomeProjection['dc_projection'] ?? null,
            'db_projection' => $incomeProjection['db_projection'] ?? null,
            'state_pension_projection' => $incomeProjection['state_pension_projection'] ?? null,
        ];

        return response()->json([
            'success' => true,
            'message' => $analysis['message'] ?? 'Retirement analysis completed',
            'data' => $flattenedData,
        ]);
    }

    /**
     * Get retirement recommendations.
     */
    public function recommendations(Request $request): JsonResponse
    {
        $user = $request->user();

        // First get the analysis
        $analysis = $this->agent->analyze($user->id);

        if (! $analysis['success']) {
            return response()->json($analysis);
        }

        // Generate recommendations based on analysis
        $recommendations = $this->agent->generateRecommendations($analysis['data']);

        return response()->json([
            'success' => true,
            'message' => 'Recommendations generated successfully',
            'data' => $recommendations,
        ]);
    }

    /**
     * Build retirement scenarios.
     */
    public function scenarios(ScenarioRequest $request): JsonResponse
    {
        $user = $request->user();
        $parameters = $request->validated();

        $result = $this->agent->buildScenarios($user->id, $parameters);

        // If failed, return as is
        if (! $result['success']) {
            return response()->json($result);
        }

        // Transform to match test expectations
        $scenarios = $result['data']['scenarios'] ?? [];
        $baseline = $scenarios['current'] ?? null;

        // Get the first non-current scenario as the "scenario"
        $scenario = null;
        foreach ($scenarios as $key => $value) {
            if ($key !== 'current') {
                $scenario = $value;
                break;
            }
        }

        // Calculate difference if both baseline and scenario exist
        $difference = null;
        if ($baseline && $scenario) {
            $difference = [
                'income_difference' => ($scenario['projected_income'] ?? 0) - ($baseline['projected_income'] ?? 0),
                'score_difference' => ($scenario['readiness_score'] ?? 0) - ($baseline['readiness_score'] ?? 0),
                'gap_difference' => ($baseline['income_gap'] ?? 0) - ($scenario['income_gap'] ?? 0),
            ];
        }

        return response()->json([
            'success' => true,
            'message' => $result['message'] ?? 'Scenarios generated successfully',
            'data' => [
                'baseline' => $baseline,
                'scenario' => $scenario,
                'difference' => $difference,
                'comparison' => $result['data']['comparison'] ?? null,
            ],
        ]);
    }

    /**
     * Check annual allowance for a given tax year.
     */
    public function checkAnnualAllowance(Request $request, string $taxYear): JsonResponse
    {
        $user = $request->user();
        $allowance = $this->allowanceChecker->checkAnnualAllowance($user->id, $taxYear);

        // Map is_tapered to tapered for consistency with tests
        $allowance['tapered'] = $allowance['is_tapered'] ?? false;
        $allowance['carry_forward'] = $allowance['carry_forward_available'] ?? 0;

        return response()->json([
            'success' => true,
            'message' => 'Annual allowance check completed',
            'data' => $allowance,
        ]);
    }

    /**
     * Store a new DC pension.
     */
    public function storeDCPension(StoreDCPensionRequest $request): JsonResponse
    {
        $user = $request->user();
        $data = $request->validated();
        $data['user_id'] = $user->id;

        $pension = DCPension::create($data);

        // Invalidate cache
        $this->invalidateRetirementCache($user->id);

        return response()->json([
            'success' => true,
            'message' => 'DC pension added successfully',
            'data' => $pension,
        ], 201);
    }

    /**
     * Update a DC pension.
     */
    public function updateDCPension(StoreDCPensionRequest $request, int $id): JsonResponse
    {
        $user = $request->user();
        $pension = DCPension::findOrFail($id);

        // Check authorization
        if ($pension->user_id !== $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized access to this pension',
            ], 403);
        }

        $pension->update($request->validated());

        // Invalidate cache
        $this->invalidateRetirementCache($user->id);

        return response()->json([
            'success' => true,
            'message' => 'DC pension updated successfully',
            'data' => $pension,
        ]);
    }

    /**
     * Delete a DC pension.
     */
    public function destroyDCPension(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        $pension = DCPension::findOrFail($id);

        // Check authorization
        if ($pension->user_id !== $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized access to this pension',
            ], 403);
        }

        $pension->delete();

        // Invalidate cache
        $this->invalidateRetirementCache($user->id);

        return response()->json([
            'success' => true,
            'message' => 'DC pension deleted successfully',
        ]);
    }

    /**
     * Store a new DB pension.
     */
    public function storeDBPension(StoreDBPensionRequest $request): JsonResponse
    {
        $user = $request->user();
        $data = $request->validated();
        $data['user_id'] = $user->id;

        $pension = DBPension::create($data);

        // Invalidate cache
        $this->invalidateRetirementCache($user->id);

        return response()->json([
            'success' => true,
            'message' => 'DB pension added successfully',
            'data' => $pension,
        ], 201);
    }

    /**
     * Update a DB pension.
     */
    public function updateDBPension(StoreDBPensionRequest $request, int $id): JsonResponse
    {
        $user = $request->user();
        $pension = DBPension::findOrFail($id);

        // Check authorization
        if ($pension->user_id !== $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized access to this pension',
            ], 403);
        }

        $pension->update($request->validated());

        // Invalidate cache
        $this->invalidateRetirementCache($user->id);

        return response()->json([
            'success' => true,
            'message' => 'DB pension updated successfully',
            'data' => $pension,
        ]);
    }

    /**
     * Delete a DB pension.
     */
    public function destroyDBPension(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        $pension = DBPension::findOrFail($id);

        // Check authorization
        if ($pension->user_id !== $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized access to this pension',
            ], 403);
        }

        $pension->delete();

        // Invalidate cache
        $this->invalidateRetirementCache($user->id);

        return response()->json([
            'success' => true,
            'message' => 'DB pension deleted successfully',
        ]);
    }

    /**
     * Update State Pension information.
     */
    public function updateStatePension(UpdateStatePensionRequest $request): JsonResponse
    {
        $user = $request->user();
        $data = $request->validated();

        $statePension = StatePension::updateOrCreate(
            ['user_id' => $user->id],
            $data
        );

        // Invalidate cache
        $this->invalidateRetirementCache($user->id);

        return response()->json([
            'success' => true,
            'message' => 'State Pension information updated successfully',
            'data' => $statePension,
        ]);
    }

    /**
     * Analyze DC Pension Portfolio (advanced portfolio optimization)
     *
     * Provides same analytics as Investment module:
     * - Risk metrics (Alpha, Beta, Sharpe Ratio, Volatility, Max Drawdown, VaR)
     * - Asset allocation & diversification
     * - Fee analysis & optimization
     * - Monte Carlo simulations
     * - Efficient Frontier analysis
     */
    public function analyzeDCPensionPortfolio(Request $request, ?int $dcPensionId = null): JsonResponse
    {
        $user = $request->user();

        // If a specific pension ID is provided, verify ownership
        if ($dcPensionId) {
            $pension = DCPension::findOrFail($dcPensionId);
            if ($pension->user_id !== $user->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access to this pension',
                ], 403);
            }
        }

        $analysis = $this->agent->analyzeDCPensionPortfolio($user->id, $dcPensionId);

        return response()->json([
            'success' => true,
            'message' => 'DC pension portfolio analysis completed',
            'data' => $analysis,
        ]);
    }

    /**
     * Invalidate retirement-related cache for a user.
     */
    private function invalidateRetirementCache(int $userId): void
    {
        Cache::forget("retirement_analysis_{$userId}");
        Cache::forget("dc_pensions_portfolio_{$userId}");

        // Also clear individual pension portfolio caches
        $dcPensions = DCPension::where('user_id', $userId)->get();
        foreach ($dcPensions as $pension) {
            Cache::forget("dc_pension_{$pension->id}_portfolio");
        }
    }
}
