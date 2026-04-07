<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Investment;

use App\Http\Controllers\Controller;
use App\Http\Traits\SanitizedErrorResponse;
use App\Models\Investment\InvestmentScenario;
use App\Services\Investment\ScenarioService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InvestmentScenarioController extends Controller
{
    use SanitizedErrorResponse;

    public function __construct(
        private readonly ScenarioService $scenarioService
    ) {}

    /**
     * Get scenario templates
     */
    public function templates(): JsonResponse
    {
        $templates = $this->scenarioService->getTemplates();

        return response()->json([
            'success' => true,
            'data' => $templates,
        ]);
    }

    /**
     * Get all scenarios for authenticated user
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $status = $request->query('status');
        $type = $request->query('type');
        $savedOnly = $request->query('saved_only') === 'true';

        $result = $this->scenarioService->getUserScenarios(
            $user->id,
            $status,
            $type,
            $savedOnly
        );

        return response()->json([
            'success' => true,
            'data' => $result,
        ]);
    }

    /**
     * Get single scenario
     */
    public function show(Request $request, int $id): JsonResponse
    {
        $user = $request->user();

        $scenario = InvestmentScenario::where('user_id', $user->id)
            ->where('id', $id)
            ->first();

        if (! $scenario) {
            return response()->json([
                'success' => false,
                'message' => 'Scenario not found',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $scenario,
        ]);
    }

    /**
     * Create new scenario
     */
    public function store(Request $request): JsonResponse
    {
        $user = $request->user();

        $data = $request->validate([
            'scenario_name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'scenario_type' => 'required|string|in:custom,template,comparison',
            'template_name' => 'nullable|string',
            'parameters' => 'required|array',
        ]);

        try {
            $scenario = $this->scenarioService->createScenario(
                $user->id,
                $data['scenario_name'],
                $data['description'] ?? null,
                $data['scenario_type'],
                $data['template_name'] ?? null,
                $data['parameters']
            );

            return response()->json([
                'success' => true,
                'data' => $scenario,
                'message' => 'Scenario created successfully',
            ], 201);
        } catch (\Exception $e) {
            return $this->errorResponse($e, 'Creating scenario');
        }
    }

    /**
     * Update scenario
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $user = $request->user();

        $scenario = InvestmentScenario::where('user_id', $user->id)
            ->where('id', $id)
            ->first();

        if (! $scenario) {
            return response()->json([
                'success' => false,
                'message' => 'Scenario not found',
            ], 404);
        }

        $validated = $request->validate([
            'scenario_name' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'parameters' => 'sometimes|array',
        ]);

        $scenario->update($validated);

        return response()->json([
            'success' => true,
            'data' => $scenario,
            'message' => 'Scenario updated successfully',
        ]);
    }

    /**
     * Run scenario simulation
     */
    public function run(Request $request, int $id): JsonResponse
    {
        $user = $request->user();

        $scenario = InvestmentScenario::where('user_id', $user->id)
            ->where('id', $id)
            ->first();

        if (! $scenario) {
            return response()->json([
                'success' => false,
                'message' => 'Scenario not found',
            ], 404);
        }

        try {
            $jobId = $this->scenarioService->runScenario($scenario);

            return response()->json([
                'success' => true,
                'data' => [
                    'scenario_id' => $scenario->id,
                    'job_id' => $jobId,
                    'status' => 'running',
                ],
                'message' => 'Scenario simulation started',
            ]);
        } catch (\Exception $e) {
            return $this->errorResponse($e, 'Running scenario');
        }
    }

    /**
     * Get scenario simulation results
     */
    public function results(Request $request, int $id): JsonResponse
    {
        $user = $request->user();

        $scenario = InvestmentScenario::where('user_id', $user->id)
            ->where('id', $id)
            ->first();

        if (! $scenario) {
            return response()->json([
                'success' => false,
                'message' => 'Scenario not found',
            ], 404);
        }

        if ($scenario->status === 'running') {
            return response()->json([
                'success' => true,
                'data' => [
                    'status' => 'running',
                    'job_id' => $scenario->monte_carlo_job_id,
                ],
            ]);
        }

        if ($scenario->status !== 'completed') {
            return response()->json([
                'success' => false,
                'message' => 'Scenario simulation not completed',
                'data' => [
                    'status' => $scenario->status,
                ],
            ], 400);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'status' => 'completed',
                'results' => $scenario->results,
                'completed_at' => $scenario->completed_at,
            ],
        ]);
    }

    /**
     * Compare multiple scenarios
     */
    public function compare(Request $request): JsonResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'scenario_ids' => 'required|array|min:2',
            'scenario_ids.*' => 'integer|exists:investment_scenarios,id',
        ]);

        try {
            $comparison = $this->scenarioService->compareScenarios(
                $user->id,
                $validated['scenario_ids']
            );

            return response()->json([
                'success' => true,
                'data' => $comparison,
            ]);
        } catch (\Exception $e) {
            return $this->errorResponse($e, 'Comparing scenarios', 400);
        }
    }

    /**
     * Save/bookmark scenario
     */
    public function save(Request $request, int $id): JsonResponse
    {
        $user = $request->user();

        $scenario = InvestmentScenario::where('user_id', $user->id)
            ->where('id', $id)
            ->first();

        if (! $scenario) {
            return response()->json([
                'success' => false,
                'message' => 'Scenario not found',
            ], 404);
        }

        $this->scenarioService->saveScenario($scenario);

        return response()->json([
            'success' => true,
            'data' => $scenario,
            'message' => 'Scenario saved successfully',
        ]);
    }

    /**
     * Unsave/unbookmark scenario
     */
    public function unsave(Request $request, int $id): JsonResponse
    {
        $user = $request->user();

        $scenario = InvestmentScenario::where('user_id', $user->id)
            ->where('id', $id)
            ->first();

        if (! $scenario) {
            return response()->json([
                'success' => false,
                'message' => 'Scenario not found',
            ], 404);
        }

        $this->scenarioService->unsaveScenario($scenario);

        return response()->json([
            'success' => true,
            'data' => $scenario,
            'message' => 'Scenario unsaved successfully',
        ]);
    }

    /**
     * Delete scenario
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        $user = $request->user();

        $scenario = InvestmentScenario::where('user_id', $user->id)
            ->where('id', $id)
            ->first();

        if (! $scenario) {
            return response()->json([
                'success' => false,
                'message' => 'Scenario not found',
            ], 404);
        }

        $this->scenarioService->deleteScenario($scenario);

        return response()->json([
            'success' => true,
            'message' => 'Scenario deleted successfully',
        ]);
    }
}
