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
use App\Http\Traits\SanitizedErrorResponse;
use App\Models\DBPension;
use App\Models\DCPension;
use App\Models\Investment\RiskProfile;
use App\Models\RetirementProfile;
use App\Models\StatePension;
use App\Services\Cache\CacheInvalidationService;
use App\Services\Goals\GoalStrategyService;
use App\Services\Goals\LifeEventIntegrationService;
use App\Services\Investment\DiversificationAnalyzer;
use App\Services\Retirement\AnnualAllowanceChecker;
use App\Services\Retirement\RequiredCapitalCalculator;
use App\Services\Retirement\RetirementIncomeService;
use App\Services\Retirement\RetirementProjectionService;
use App\Services\Retirement\RetirementStrategyService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Retirement Controller
 *
 * Handles API endpoints for the Retirement module including pension CRUD operations,
 * analysis, recommendations, and scenario planning.
 */
class RetirementController extends Controller
{
    use SanitizedErrorResponse;

    public function __construct(
        private readonly RetirementAgent $agent,
        private readonly AnnualAllowanceChecker $allowanceChecker,
        private readonly RetirementProjectionService $projectionService,
        private readonly RetirementStrategyService $strategyService,
        private readonly RetirementIncomeService $retirementIncomeService,
        private readonly DiversificationAnalyzer $diversificationAnalyzer,
        private readonly RequiredCapitalCalculator $requiredCapitalCalculator,
        private readonly LifeEventIntegrationService $lifeEventIntegration,
        private readonly GoalStrategyService $goalStrategy,
        private readonly CacheInvalidationService $cacheInvalidation
    ) {}

    /**
     * Get all retirement data for the authenticated user.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        // Get life events and goal strategies
        try {
            $lifeEvents = $this->lifeEventIntegration->getEventsForModule($user->id, 'retirement');
            $lifeEventImpact = $this->lifeEventIntegration->getModuleImpactSummary($user->id, 'retirement');
            $goalStrategies = $this->goalStrategy->getStrategiesForModule($user->id, 'retirement');
            $goalsSummary = $this->goalStrategy->getModuleGoalsSummary($user->id, 'retirement');
        } catch (\Throwable $e) {
            report($e);
            $lifeEvents = [];
            $lifeEventImpact = null;
            $goalStrategies = [];
            $goalsSummary = null;
        }

        $profile = RetirementProfile::where('user_id', $user->id)->first();

        // Fall back to the user's target_retirement_age when the profile doesn't have one
        if ($user->target_retirement_age) {
            if ($profile && ! $profile->target_retirement_age) {
                $profile->target_retirement_age = $user->target_retirement_age;
            } elseif (! $profile) {
                $profile = (object) [
                    'target_retirement_age' => $user->target_retirement_age,
                    'current_age' => $user->date_of_birth?->age,
                ];
            }
        }

        $data = [
            'profile' => $profile,
            'dc_pensions' => DCPension::where('user_id', $user->id)->with('holdings')->get(),
            'db_pensions' => DBPension::where('user_id', $user->id)->get(),
            'state_pension' => StatePension::where('user_id', $user->id)->first(),
            'life_events' => $lifeEvents,
            'life_event_impact' => $lifeEventImpact,
            'goal_strategies' => $goalStrategies,
            'goals_summary' => $goalsSummary,
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
     * Get Monte Carlo projections for a specific DC pension.
     */
    public function getDCPensionProjection(Request $request, int $id): JsonResponse
    {
        $user = $request->user();

        try {
            $projections = $this->projectionService->projectIndividualDCPension($id, $user->id);

            return response()->json([
                'success' => true,
                'message' => 'DC pension projections generated successfully',
                'data' => $projections,
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Pension not found',
            ], 404);
        }
    }

    /**
     * Get required capital calculations with present value breakdown.
     *
     * Returns required income, required capital at retirement (FV),
     * required capital in today's money (PV), and year-by-year projections.
     */
    public function getRequiredCapital(Request $request): JsonResponse
    {
        $user = $request->user();

        $data = $this->requiredCapitalCalculator->calculate($user->id);

        return response()->json([
            'success' => true,
            'message' => 'Required capital calculations generated successfully',
            'data' => $data,
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

        // Auto-assign main risk level if user has a risk profile
        $riskProfile = RiskProfile::where('user_id', $user->id)->first();
        if ($riskProfile && $riskProfile->risk_level) {
            $data['risk_preference'] = $riskProfile->risk_level;
        }

        // Extract holdings before creating pension (not a model field)
        $holdings = $data['holdings'] ?? [];
        unset($data['holdings']);

        $pension = null;

        DB::transaction(function () use ($data, $holdings, &$pension) {
            $pension = DCPension::create($data);

            if (! empty($holdings)) {
                $hasCashHolding = false;

                foreach ($holdings as $holdingData) {
                    $currentValue = ($pension->current_fund_value * $holdingData['allocation_percent']) / 100;

                    if (($holdingData['asset_type'] ?? '') === 'cash') {
                        $hasCashHolding = true;
                    }

                    $pension->holdings()->create([
                        'holdable_type' => DCPension::class,
                        'holdable_id' => $pension->id,
                        'security_name' => $holdingData['security_name'],
                        'asset_type' => $holdingData['asset_type'] ?? 'fund',
                        'allocation_percent' => $holdingData['allocation_percent'],
                        'current_value' => $currentValue,
                        'ocf_percent' => $holdingData['ocf_percent'] ?? 0,
                        'cost_basis' => $holdingData['cost_basis'] ?? null,
                    ]);
                }

                // Auto-create cash holding for remainder
                $totalAllocated = collect($holdings)->sum('allocation_percent');
                if ($totalAllocated < 100 && ! $hasCashHolding) {
                    $remainderPercent = 100 - $totalAllocated;
                    $pension->holdings()->create([
                        'holdable_type' => DCPension::class,
                        'holdable_id' => $pension->id,
                        'security_name' => 'Cash',
                        'asset_type' => 'cash',
                        'allocation_percent' => $remainderPercent,
                        'current_value' => ($pension->current_fund_value * $remainderPercent) / 100,
                    ]);
                }
            }
        });

        // Invalidate cache
        $this->invalidateRetirementCache($user->id);

        // Load holdings for response
        $pension->load('holdings');

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
        $pension = DCPension::where('user_id', $user->id)->findOrFail($id);

        $data = $request->validated();

        // Extract holdings before updating pension (not a model field)
        $holdings = $data['holdings'] ?? null;
        unset($data['holdings']);

        DB::transaction(function () use ($pension, $data, $holdings) {
            $pension->update($data);

            // Sync holdings if provided
            if ($holdings !== null) {
                // Delete existing holdings
                $pension->holdings()->delete();

                $hasCashHolding = false;

                foreach ($holdings as $holdingData) {
                    $currentValue = ($pension->current_fund_value * $holdingData['allocation_percent']) / 100;

                    if (($holdingData['asset_type'] ?? '') === 'cash') {
                        $hasCashHolding = true;
                    }

                    $pension->holdings()->create([
                        'holdable_type' => DCPension::class,
                        'holdable_id' => $pension->id,
                        'security_name' => $holdingData['security_name'],
                        'asset_type' => $holdingData['asset_type'] ?? 'fund',
                        'allocation_percent' => $holdingData['allocation_percent'],
                        'current_value' => $currentValue,
                        'ocf_percent' => $holdingData['ocf_percent'] ?? 0,
                        'cost_basis' => $holdingData['cost_basis'] ?? null,
                    ]);
                }

                // Auto-create cash holding for remainder
                $totalAllocated = collect($holdings)->sum('allocation_percent');
                if ($totalAllocated < 100 && ! $hasCashHolding) {
                    $remainderPercent = 100 - $totalAllocated;
                    $pension->holdings()->create([
                        'holdable_type' => DCPension::class,
                        'holdable_id' => $pension->id,
                        'security_name' => 'Cash',
                        'asset_type' => 'cash',
                        'allocation_percent' => $remainderPercent,
                        'current_value' => ($pension->current_fund_value * $remainderPercent) / 100,
                    ]);
                }
            }
        });

        // Invalidate cache
        $this->invalidateRetirementCache($user->id);

        // Load holdings for response
        $pension->load('holdings');

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
        $pension = DCPension::where('user_id', $user->id)->findOrFail($id);

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
        $pension = DBPension::where('user_id', $user->id)->findOrFail($id);

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
        $pension = DBPension::where('user_id', $user->id)->findOrFail($id);

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
            $pension = DCPension::where('user_id', $user->id)->findOrFail($dcPensionId);
        }

        $analysis = $this->agent->analyzeDCPensionPortfolio($user->id, $dcPensionId);

        return response()->json([
            'success' => true,
            'message' => 'DC pension portfolio analysis completed',
            'data' => $analysis,
        ]);
    }

    /**
     * Get retirement strategies for the authenticated user.
     */
    public function getStrategies(Request $request): JsonResponse
    {
        $user = $request->user();
        $strategies = $this->strategyService->getStrategies($user->id);

        return response()->json([
            'success' => true,
            'message' => 'Retirement strategies retrieved successfully',
            'data' => $strategies,
        ]);
    }

    /**
     * Calculate the impact of a strategy change.
     *
     * Accepts optional cumulative context from prior strategies to enable
     * chained/stacked strategy impact calculations.
     */
    public function calculateStrategyImpact(Request $request): JsonResponse
    {
        $request->validate([
            'strategy_type' => 'required|in:employer_match,increase_contribution,retirement_age,income_target',
            'new_value' => 'required|numeric',
            'prior_additional_monthly' => 'nullable|numeric',
            'prior_additional_income' => 'nullable|numeric',
            'prior_probability' => 'nullable|numeric',
        ]);

        $user = $request->user();
        $impact = $this->strategyService->calculateStrategyImpact(
            $user->id,
            $request->input('strategy_type'),
            (float) $request->input('new_value'),
            (float) ($request->input('prior_additional_monthly') ?? 0),
            (float) ($request->input('prior_additional_income') ?? 0),
            $request->input('prior_probability') !== null ? (float) $request->input('prior_probability') : null
        );

        return response()->json([
            'success' => true,
            'message' => 'Strategy impact calculated successfully',
            'data' => $impact,
        ]);
    }

    /**
     * Get retirement income configuration with default tax-optimized allocations.
     */
    public function getRetirementIncome(Request $request): JsonResponse
    {
        $user = $request->user();
        $includeSpouse = $request->boolean('include_spouse', false);

        $data = $this->retirementIncomeService->getRetirementIncomeConfig($user->id, $includeSpouse);

        return response()->json([
            'success' => true,
            'message' => 'Retirement income configuration retrieved successfully',
            'data' => $data,
        ]);
    }

    /**
     * Calculate retirement income based on user-specified allocations.
     */
    public function calculateRetirementIncome(Request $request): JsonResponse
    {
        $request->validate([
            'income_allocations' => 'required|array',
            'income_allocations.*.source_type' => 'required|string',
            'income_allocations.*.source_id' => 'required', // Can be integer or string (e.g., 'pension_pot')
            'income_allocations.*.annual_amount' => 'required|numeric|min:0',
            'income_allocations.*.tax_treatment' => 'nullable|string',
            'income_allocations.*.name' => 'nullable|string',
            'include_spouse' => 'boolean',
            'custom_target_income' => 'nullable|numeric|min:0',
        ]);

        $user = $request->user();
        $allocations = $request->input('income_allocations');
        $customTargetIncome = $request->input('custom_target_income');
        $includeSpouse = $request->boolean('include_spouse', false);

        $data = $this->retirementIncomeService->calculateIncomeScenario(
            $user->id,
            $allocations,
            $customTargetIncome,
            $includeSpouse
        );

        return response()->json([
            'success' => true,
            'message' => 'Retirement income calculated successfully',
            'data' => $data,
        ]);
    }

    /**
     * Get available accounts for retirement income.
     *
     * Returns all accounts eligible for retirement income, including the combined
     * Pension Pot with Monte Carlo 80% projected value, ISAs, bonds, GIAs and savings.
     */
    public function getIncomeAccounts(Request $request): JsonResponse
    {
        $user = $request->user();
        $includeSpouse = $request->boolean('include_spouse', false);

        // Get the projected pension pot value (80% Monte Carlo confidence)
        $potProjection = $this->projectionService->projectPensionPot($user);
        $projectedPensionPot = (float) ($potProjection['percentile_20_at_retirement'] ?? 0);

        // Calculate years to retirement for projecting asset values
        $profile = RetirementProfile::where('user_id', $user->id)->first();
        $currentAge = $user->date_of_birth ? $user->date_of_birth->age : null;
        $retirementAge = $profile?->target_retirement_age ?? 68;
        $yearsToRetirement = max(0, $retirementAge - ($currentAge ?? 45));

        // Get available accounts with projected pension pot and years to retirement
        $accounts = $this->retirementIncomeService->getAvailableAccounts(
            $user->id,
            $includeSpouse,
            $projectedPensionPot,
            $yearsToRetirement
        );

        return response()->json([
            'success' => true,
            'message' => 'Income accounts retrieved successfully',
            'data' => [
                'accounts' => $accounts,
                'projected_pension_pot' => round($projectedPensionPot, 2),
                'years_to_retirement' => $yearsToRetirement,
            ],
        ]);
    }

    /**
     * Get diversification analysis for a DC pension.
     *
     * GET /api/retirement/pensions/dc/{id}/diversification
     */
    public function getDCPensionDiversification(Request $request, int $id): JsonResponse
    {
        $user = $request->user();

        $pension = DCPension::with('holdings')
            ->where('user_id', $user->id)
            ->find($id);

        if (! $pension) {
            return response()->json([
                'success' => false,
                'message' => 'DC pension not found',
            ], 404);
        }

        $holdings = $pension->holdings;

        // Handle empty holdings
        if ($holdings->isEmpty()) {
            return response()->json([
                'success' => true,
                'data' => [
                    'message' => 'No holdings recorded for this pension',
                    'has_holdings' => false,
                    'pension_id' => $id,
                    'pension_name' => $pension->scheme_name ?? 'DC Pension',
                ],
            ]);
        }

        // Get user's risk level (default to 3/medium if not set)
        $riskProfile = RiskProfile::where('user_id', $user->id)->first();
        $userRiskLevel = $riskProfile ? $this->diversificationAnalyzer->normalizeRiskLevel($riskProfile->risk_level ?? $riskProfile->risk_tolerance) : 3;

        // Get pension-level risk override if set
        $pensionRiskLevel = null;
        if ($pension->has_custom_risk && $pension->risk_preference) {
            $pensionRiskLevel = $this->diversificationAnalyzer->normalizeRiskLevel($pension->risk_preference);
        }

        // Run full analysis
        $analysis = $this->diversificationAnalyzer->analyze($holdings, $userRiskLevel, $pensionRiskLevel);

        return response()->json([
            'success' => true,
            'data' => array_merge($analysis, [
                'has_holdings' => true,
                'pension_id' => $id,
                'pension_name' => $pension->scheme_name ?? 'DC Pension',
                'pension_type' => $pension->scheme_type,
            ]),
        ]);
    }

    /**
     * Invalidate retirement-related cache for a user.
     */
    private function invalidateRetirementCache(int $userId): void
    {
        $this->cacheInvalidation->invalidateForUser($userId);

        // Clear individual pension portfolio caches (resource-specific keys)
        $dcPensions = DCPension::where('user_id', $userId)->get();
        foreach ($dcPensions as $pension) {
            Cache::forget("dc_pension_{$pension->id}_portfolio");
        }
    }
}
