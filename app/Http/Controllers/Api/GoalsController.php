<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Agents\GoalsAgent;
use App\Http\Controllers\Controller;
use App\Http\Requests\Goals\StoreGoalRequest;
use App\Http\Requests\Goals\UpdateGoalRequest;
use App\Http\Resources\GoalContributionResource;
use App\Http\Resources\GoalResource;
use App\Http\Traits\SanitizedErrorResponse;
use App\Models\Goal;
use App\Services\Goals\FinancialForecastService;
use App\Services\Goals\GoalAffordabilityService;
use App\Services\Goals\GoalAssignmentService;
use App\Services\Goals\GoalProgressService;
use App\Services\Goals\GoalRiskService;
use App\Services\Goals\GoalsProjectionService;
use App\Services\Goals\LifeEventService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Goals Controller
 *
 * Handles CRUD operations and analysis for financial goals.
 */
class GoalsController extends Controller
{
    use SanitizedErrorResponse;

    public function __construct(
        private readonly GoalsAgent $goalsAgent,
        private readonly GoalAssignmentService $assignmentService,
        private readonly GoalAffordabilityService $affordabilityService,
        private readonly GoalProgressService $progressService,
        private readonly GoalRiskService $riskService,
        private readonly GoalsProjectionService $projectionService,
        private readonly LifeEventService $lifeEventService,
        private readonly FinancialForecastService $forecastService
    ) {}

    /**
     * Get all goals for authenticated user.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $request->validate([
            'module' => 'nullable|string|in:savings,investment,retirement,protection,estate',
            'status' => 'nullable|string|in:active,completed,paused,cancelled',
            'priority' => 'nullable|string|in:critical,high,medium,low',
        ]);

        $query = Goal::forUserOrJoint($user->id);

        // Filter by module
        if ($request->has('module')) {
            $query->where('assigned_module', $request->input('module'));
        }

        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->input('status'));
        }

        // Filter by priority
        if ($request->has('priority')) {
            $query->where('priority', $request->input('priority'));
        }

        $goals = $query->orderBy('priority')
            ->orderBy('target_date')
            ->limit(100)
            ->get();

        return response()->json([
            'success' => true,
            'data' => [
                'goals' => GoalResource::collection($goals),
                'count' => $goals->count(),
            ],
        ]);
    }

    /**
     * Get comprehensive goals analysis.
     */
    public function analysis(Request $request): JsonResponse
    {
        $user = $request->user();

        try {
            $analysis = $this->goalsAgent->analyze($user->id);
            $recommendations = $this->goalsAgent->generateRecommendations($analysis);

            return response()->json([
                'success' => true,
                'data' => array_merge($analysis, ['recommendations' => $recommendations]),
            ]);
        } catch (\Exception $e) {
            return $this->errorResponse($e, 'Goals analysis', 500, ['user_id' => $user->id]);
        }
    }

    /**
     * Get dashboard overview for goals card.
     */
    public function dashboardOverview(Request $request): JsonResponse
    {
        $user = $request->user();

        try {
            $overview = $this->goalsAgent->getDashboardOverview($user->id);

            return response()->json([
                'success' => true,
                'data' => $overview,
            ]);
        } catch (\Exception $e) {
            return $this->errorResponse($e, 'Goals dashboard overview', 500, ['user_id' => $user->id]);
        }
    }

    /**
     * Create a new goal.
     */
    public function store(StoreGoalRequest $request): JsonResponse
    {
        $user = $request->user();
        $data = $request->validated();

        try {
            // Auto-assign module if not overridden
            if (empty($data['assigned_module']) || ! ($data['module_override'] ?? false)) {
                $data['assigned_module'] = $this->assignmentService->determineModule($data);
            }

            $data['user_id'] = $user->id;
            $data['start_date'] = $data['start_date'] ?? now()->toDateString();

            // Calculate property costs if property goal
            if (in_array($data['goal_type'], ['property_purchase', 'home_deposit']) && ! empty($data['estimated_property_price'])) {
                $propertyCosts = $this->assignmentService->calculatePropertyCosts($data);
                $data['stamp_duty_estimate'] = $propertyCosts['stamp_duty'];
                $data['additional_costs_estimate'] = $propertyCosts['legal_fees'] + $propertyCosts['survey_costs'] + $propertyCosts['moving_costs'];

                // Update target amount to include all costs if not manually set
                if (empty($data['target_amount'])) {
                    $data['target_amount'] = $propertyCosts['total_upfront'];
                }
            }

            $goal = Goal::create($data);

            // Clear cache
            $this->goalsAgent->clearCache($user->id);
            $this->projectionService->clearCache($user->id);

            // Refresh to ensure casts are applied for serialization
            $goal = $goal->fresh();

            return response()->json([
                'success' => true,
                'message' => 'Goal created successfully.',
                'data' => new GoalResource($goal),
            ], 201);
        } catch (\Throwable $e) {
            return $this->errorResponse($e, 'Create goal', 500, ['user_id' => $user->id]);
        }
    }

    /**
     * Get a specific goal.
     */
    public function show(Request $request, int $id): JsonResponse
    {
        $user = $request->user();

        $goal = Goal::where('id', $id)
            ->where(function ($query) use ($user) {
                $query->where('user_id', $user->id)
                    ->orWhere('joint_owner_id', $user->id);
            })
            ->first();

        if (! $goal) {
            return response()->json([
                'success' => false,
                'message' => 'Goal not found.',
            ], 404);
        }

        // Get progress details
        $progress = $this->progressService->calculateProgress($goal);
        $milestones = $this->progressService->checkMilestones($goal);
        $streak = $this->progressService->getStreakDisplay($goal);

        // Get affordability analysis
        $affordability = $this->affordabilityService->analyzeAffordability($goal, $user);

        // Get projections for investment goals
        $projections = null;
        if ($goal->assigned_module === 'investment') {
            $riskProfile = $this->riskService->getUserRiskProfile($user);
            $projections = $this->riskService->getProjections($goal, $riskProfile);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'goal' => new GoalResource($goal),
                'progress' => $progress,
                'milestones' => $milestones,
                'streak' => $streak,
                'affordability' => $affordability,
                'projections' => $projections,
            ],
        ]);
    }

    /**
     * Update a goal.
     */
    public function update(UpdateGoalRequest $request, int $id): JsonResponse
    {
        $user = $request->user();

        $goal = Goal::where('id', $id)
            ->where('user_id', $user->id)
            ->first();

        if (! $goal) {
            return response()->json([
                'success' => false,
                'message' => 'Goal not found or you do not have permission to update it.',
            ], 404);
        }

        try {
            $data = $request->validated();

            // Handle status change to completed
            if (($data['status'] ?? null) === 'completed' && $goal->status !== 'completed') {
                $goal = $this->progressService->completeGoal($goal, $data['completion_notes'] ?? null);
                unset($data['status'], $data['completion_notes']);
            }

            // Re-calculate module if goal type or time horizon changed
            if ((isset($data['goal_type']) || isset($data['target_date'])) && ! ($data['module_override'] ?? $goal->module_override)) {
                $checkData = array_merge($goal->toArray(), $data);
                $data['assigned_module'] = $this->assignmentService->determineModule($checkData);
            }

            // Single-record pattern: Handle ownership percentage when changing to/from joint
            $ownershipType = $data['ownership_type'] ?? $goal->ownership_type;
            $jointOwnerId = $data['joint_owner_id'] ?? $goal->joint_owner_id;

            if ($ownershipType === 'joint' && $jointOwnerId) {
                // Switching to joint or already joint - default to 50% if not specified
                if (! isset($data['ownership_percentage'])) {
                    $data['ownership_percentage'] = 50.00;
                }
            } elseif ($ownershipType === 'individual') {
                // Switching to individual - reset to 100%
                $data['ownership_percentage'] = 100.00;
                $data['joint_owner_id'] = null;
            }

            $goal->update($data);

            // Clear cache
            $this->goalsAgent->clearCache($user->id);
            $this->projectionService->clearCache($user->id);

            return response()->json([
                'success' => true,
                'message' => 'Goal updated successfully.',
                'data' => new GoalResource($goal->fresh()),
            ]);
        } catch (\Exception $e) {
            return $this->errorResponse($e, 'Update goal', 500, ['goal_id' => $id]);
        }
    }

    /**
     * Delete a goal.
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        $user = $request->user();

        $goal = Goal::where('id', $id)
            ->where('user_id', $user->id)
            ->first();

        if (! $goal) {
            return response()->json([
                'success' => false,
                'message' => 'Goal not found or you do not have permission to delete it.',
            ], 404);
        }

        try {
            $goal->delete();

            // Clear cache
            $this->goalsAgent->clearCache($user->id);
            $this->projectionService->clearCache($user->id);

            return response()->json([
                'success' => true,
                'message' => 'Goal deleted successfully.',
            ]);
        } catch (\Exception $e) {
            return $this->errorResponse($e, 'Delete goal', 500, ['goal_id' => $id]);
        }
    }

    /**
     * Record a contribution to a goal.
     */
    public function recordContribution(Request $request, int $id): JsonResponse
    {
        $user = $request->user();

        $request->validate([
            'amount' => 'required|numeric|min:0.01',
            'contribution_type' => 'nullable|string|in:manual,automatic,lump_sum,interest,adjustment',
            'notes' => 'nullable|string|max:255',
        ]);

        $goal = Goal::where('id', $id)
            ->where(function ($query) use ($user) {
                $query->where('user_id', $user->id)
                    ->orWhere('joint_owner_id', $user->id);
            })
            ->first();

        if (! $goal) {
            return response()->json([
                'success' => false,
                'message' => 'Goal not found.',
            ], 404);
        }

        try {
            $contribution = $this->progressService->recordContribution(
                $goal,
                (float) $request->input('amount'),
                $request->input('contribution_type', 'manual'),
                $request->input('notes')
            );

            // Check if goal is now complete — use progressService to record milestone
            $freshGoal = $goal->fresh();
            if ($freshGoal->progress_percentage >= 100 && $freshGoal->status === 'active') {
                $this->progressService->completeGoal($freshGoal);
            }

            // Clear cache
            $this->goalsAgent->clearCache($user->id);
            $this->projectionService->clearCache($user->id);

            return response()->json([
                'success' => true,
                'message' => 'Contribution recorded successfully.',
                'data' => [
                    'contribution' => new GoalContributionResource($contribution),
                    'goal' => new GoalResource($goal->fresh()),
                    'milestones' => $this->progressService->checkMilestones($goal->fresh()),
                ],
            ]);
        } catch (\Exception $e) {
            return $this->errorResponse($e, 'Record contribution', 500, ['goal_id' => $id]);
        }
    }

    /**
     * Get projections for a goal.
     */
    public function getProjections(Request $request, int $id): JsonResponse
    {
        $user = $request->user();

        $goal = Goal::where('id', $id)
            ->where(function ($query) use ($user) {
                $query->where('user_id', $user->id)
                    ->orWhere('joint_owner_id', $user->id);
            })
            ->first();

        if (! $goal) {
            return response()->json([
                'success' => false,
                'message' => 'Goal not found.',
            ], 404);
        }

        try {
            $riskProfile = $this->riskService->getUserRiskProfile($user);
            $projections = $this->riskService->getProjections($goal, $riskProfile);

            return response()->json([
                'success' => true,
                'data' => $projections,
            ]);
        } catch (\Exception $e) {
            return $this->errorResponse($e, 'Get projections', 500, ['goal_id' => $id]);
        }
    }

    /**
     * Calculate property purchase costs.
     */
    public function calculatePropertyCosts(Request $request): JsonResponse
    {
        $request->validate([
            'estimated_property_price' => 'required|numeric|min:1',
            'deposit_percentage' => 'nullable|numeric|min:0|max:100',
            'is_first_time_buyer' => 'nullable|boolean',
        ]);

        try {
            $costs = $this->assignmentService->calculatePropertyCosts($request->validated());

            return response()->json([
                'success' => true,
                'data' => $costs,
            ]);
        } catch (\Exception $e) {
            return $this->errorResponse($e, 'Calculate property costs', 500);
        }
    }

    /**
     * Get scenarios for a goal.
     */
    public function getScenarios(Request $request, int $id): JsonResponse
    {
        $user = $request->user();

        $goal = Goal::where('id', $id)
            ->where(function ($query) use ($user) {
                $query->where('user_id', $user->id)
                    ->orWhere('joint_owner_id', $user->id);
            })
            ->first();

        if (! $goal) {
            return response()->json([
                'success' => false,
                'message' => 'Goal not found.',
            ], 404);
        }

        try {
            $scenarios = $this->goalsAgent->buildScenarios($user->id, ['goal_id' => $id]);

            return response()->json([
                'success' => true,
                'data' => $scenarios,
            ]);
        } catch (\Exception $e) {
            return $this->errorResponse($e, 'Get scenarios', 500, ['goal_id' => $id]);
        }
    }

    /**
     * Get available goal types.
     */
    public function getGoalTypes(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $this->assignmentService->getGoalTypes(),
        ]);
    }

    /**
     * Get available risk levels.
     */
    public function getRiskLevels(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $this->riskService->getAvailableRiskLevels(),
        ]);
    }

    /**
     * Get contribution history for a goal.
     */
    public function getContributionHistory(Request $request, int $id): JsonResponse
    {
        $user = $request->user();

        $goal = Goal::where('id', $id)
            ->where(function ($query) use ($user) {
                $query->where('user_id', $user->id)
                    ->orWhere('joint_owner_id', $user->id);
            })
            ->first();

        if (! $goal) {
            return response()->json([
                'success' => false,
                'message' => 'Goal not found.',
            ], 404);
        }

        $limit = (int) $request->input('limit', 12);
        $history = $this->progressService->getContributionHistory($goal, $limit);
        $monthlySummary = $this->progressService->getMonthlySummary($goal, 12);

        return response()->json([
            'success' => true,
            'data' => [
                'contributions' => $history,
                'monthly_summary' => $monthlySummary,
            ],
        ]);
    }

    /**
     * Get net worth projection with goals and life events.
     */
    public function getProjection(Request $request): JsonResponse
    {
        $user = $request->user();
        $household = $request->boolean('household', false);

        try {
            $projection = $this->projectionService->generateProjection($user->id, $household);

            return response()->json([
                'success' => true,
                'data' => $projection,
            ]);
        } catch (\Exception $e) {
            return $this->errorResponse($e, 'Get goals projection', 500, ['user_id' => $user->id]);
        }
    }

    /**
     * Get household summary combining user and spouse data.
     */
    public function getHouseholdSummary(Request $request): JsonResponse
    {
        $user = $request->user();

        // Check spouse permission
        if (! $user->hasAcceptedSpousePermission()) {
            return response()->json([
                'success' => false,
                'message' => 'Spouse permission required to view household data.',
            ], 403);
        }

        try {
            // Get combined goals
            $goals = Goal::where(function ($query) use ($user) {
                $query->where('user_id', $user->id)
                    ->orWhere('joint_owner_id', $user->id);
            })
                ->orWhere(function ($query) use ($user) {
                    $query->where('user_id', $user->spouse_user_id)
                        ->where('show_in_household_view', true);
                })
                ->where('status', 'active')
                ->orderBy('target_date')
                ->get();

            // Get combined life events
            $lifeEvents = $this->lifeEventService->getEvents($user->id, true);

            // Get projection
            $projection = $this->projectionService->generateProjection($user->id, true);

            return response()->json([
                'success' => true,
                'data' => [
                    'goals' => [
                        'items' => GoalResource::collection($goals),
                        'count' => $goals->count(),
                        'total_target' => $goals->sum('target_amount'),
                        'total_current' => $goals->sum('current_amount'),
                    ],
                    'life_events' => [
                        'items' => $lifeEvents,
                        'count' => $lifeEvents->count(),
                        'total_income' => $lifeEvents->where('impact_type', 'income')->sum('amount'),
                        'total_expense' => $lifeEvents->where('impact_type', 'expense')->sum('amount'),
                    ],
                    'projection' => $projection,
                ],
            ]);
        } catch (\Exception $e) {
            return $this->errorResponse($e, 'Get household summary', 500, ['user_id' => $user->id]);
        }
    }

    /**
     * Get dependencies for a goal.
     */
    public function getDependencies(Request $request, int $id): JsonResponse
    {
        $user = $request->user();

        $goal = Goal::where('id', $id)
            ->where('user_id', $user->id)
            ->with(['dependsOn:id,goal_name,goal_type,status,target_amount,current_amount,target_date',
                'dependedOnBy:id,goal_name,goal_type,status,target_amount,current_amount,target_date'])
            ->first();

        if (! $goal) {
            return response()->json([
                'success' => false,
                'message' => 'Goal not found.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'depends_on' => $goal->dependsOn->map(fn ($dep) => [
                    'id' => $dep->id,
                    'goal_name' => $dep->goal_name,
                    'goal_type' => $dep->goal_type,
                    'status' => $dep->status,
                    'progress_percentage' => $dep->progress_percentage,
                    'dependency_type' => $dep->pivot->dependency_type,
                    'notes' => $dep->pivot->notes,
                ]),
                'depended_on_by' => $goal->dependedOnBy->map(fn ($dep) => [
                    'id' => $dep->id,
                    'goal_name' => $dep->goal_name,
                    'goal_type' => $dep->goal_type,
                    'status' => $dep->status,
                    'progress_percentage' => $dep->progress_percentage,
                    'dependency_type' => $dep->pivot->dependency_type,
                    'notes' => $dep->pivot->notes,
                ]),
                'is_blocked' => $goal->isBlocked(),
            ],
        ]);
    }

    /**
     * Add a dependency to a goal.
     */
    public function addDependency(Request $request, int $id): JsonResponse
    {
        $user = $request->user();

        $request->validate([
            'depends_on_goal_id' => 'required|integer|exists:goals,id',
            'dependency_type' => 'required|string|in:blocks,funds,prerequisite',
            'notes' => 'nullable|string|max:500',
        ]);

        $goal = Goal::where('id', $id)->where('user_id', $user->id)->first();
        $dependsOnGoal = Goal::where('id', $request->input('depends_on_goal_id'))
            ->where('user_id', $user->id)
            ->first();

        if (! $goal || ! $dependsOnGoal) {
            return response()->json([
                'success' => false,
                'message' => 'Goal not found.',
            ], 404);
        }

        // Prevent self-dependency
        if ($goal->id === $dependsOnGoal->id) {
            return response()->json([
                'success' => false,
                'message' => 'A goal cannot depend on itself.',
            ], 422);
        }

        // Check for circular dependency
        if ($this->wouldCreateCircularDependency($goal->id, $dependsOnGoal->id)) {
            return response()->json([
                'success' => false,
                'message' => 'This dependency would create a circular chain.',
            ], 422);
        }

        try {
            $goal->dependsOn()->syncWithoutDetaching([
                $dependsOnGoal->id => [
                    'dependency_type' => $request->input('dependency_type'),
                    'notes' => $request->input('notes'),
                ],
            ]);

            $this->goalsAgent->clearCache($user->id);

            return response()->json([
                'success' => true,
                'message' => 'Dependency added successfully.',
            ]);
        } catch (\Exception $e) {
            return $this->errorResponse($e, 'Add dependency', 500, ['goal_id' => $id]);
        }
    }

    /**
     * Remove a dependency from a goal.
     */
    public function removeDependency(Request $request, int $id, int $dependsOnId): JsonResponse
    {
        $user = $request->user();

        $goal = Goal::where('id', $id)->where('user_id', $user->id)->first();

        if (! $goal) {
            return response()->json([
                'success' => false,
                'message' => 'Goal not found.',
            ], 404);
        }

        $goal->dependsOn()->detach($dependsOnId);
        $this->goalsAgent->clearCache($user->id);

        return response()->json([
            'success' => true,
            'message' => 'Dependency removed successfully.',
        ]);
    }

    /**
     * Check if adding a dependency would create a circular chain.
     */
    private function wouldCreateCircularDependency(int $goalId, int $dependsOnId): bool
    {
        $visited = [];
        $queue = [$dependsOnId];

        while (! empty($queue)) {
            $current = array_shift($queue);

            if ($current === $goalId) {
                return true;
            }

            if (in_array($current, $visited)) {
                continue;
            }
            $visited[] = $current;

            $currentGoal = Goal::find($current);
            if ($currentGoal) {
                $deps = $currentGoal->dependsOn()->pluck('goals.id')->toArray();
                $queue = array_merge($queue, $deps);
            }
        }

        return false;
    }

    /**
     * Get financial forecast with life events overlay.
     */
    public function getFinancialForecast(Request $request): JsonResponse
    {
        $user = $request->user();
        $view = $request->input('view', 'monthly');
        $months = (int) $request->input('months', 12);
        $years = (int) $request->input('years', 5);

        try {
            if ($view === 'annual') {
                $forecast = $this->forecastService->getAnnualForecast($user->id, $years);
            } else {
                $forecast = $this->forecastService->getMonthlyForecast($user->id, $months);
            }

            return response()->json([
                'success' => true,
                'data' => $forecast,
            ]);
        } catch (\Exception $e) {
            return $this->errorResponse($e, 'Get financial forecast', 500, ['user_id' => $user->id]);
        }
    }
}
