<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreWhatIfScenarioRequest;
use App\Http\Resources\WhatIfScenarioResource;
use App\Http\Traits\SanitizedErrorResponse;
use App\Models\WhatIfScenario;
use App\Services\WhatIf\WhatIfScenarioService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * What-If Scenario Controller
 *
 * Handles CRUD operations for what-if scenarios, allowing users
 * to model hypothetical changes and compare projected outcomes
 * against their current financial position.
 */
class WhatIfScenarioController extends Controller
{
    use SanitizedErrorResponse;

    public function __construct(
        private readonly WhatIfScenarioService $scenarioService
    ) {}

    /**
     * List all scenarios for the authenticated user.
     *
     * GET /api/what-if-scenarios
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $scenarios = WhatIfScenario::where('user_id', $user->id)
            ->orderBy('updated_at', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => [
                'scenarios' => WhatIfScenarioResource::collection($scenarios),
                'count' => $scenarios->count(),
            ],
        ]);
    }

    /**
     * Get the count of scenarios for the authenticated user.
     *
     * GET /api/what-if-scenarios/count
     */
    public function count(Request $request): JsonResponse
    {
        $user = $request->user();

        $count = WhatIfScenario::where('user_id', $user->id)->count();

        return response()->json([
            'success' => true,
            'data' => [
                'count' => $count,
            ],
        ]);
    }

    /**
     * Show a single scenario with live comparison data.
     *
     * GET /api/what-if-scenarios/{id}
     */
    public function show(Request $request, int $id): JsonResponse
    {
        $user = $request->user();

        $scenario = WhatIfScenario::where('id', $id)
            ->where('user_id', $user->id)
            ->first();

        if (! $scenario) {
            return $this->notFoundResponse('Scenario');
        }

        try {
            $comparison = $this->scenarioService->calculateComparison($scenario);

            return response()->json([
                'success' => true,
                'data' => [
                    'scenario' => new WhatIfScenarioResource($scenario),
                    'comparison' => $comparison,
                ],
            ]);
        } catch (\Throwable $e) {
            return $this->errorResponse($e, 'Scenario comparison', 500, ['scenario_id' => $id]);
        }
    }

    /**
     * Create a new what-if scenario.
     *
     * POST /api/what-if-scenarios
     */
    public function store(StoreWhatIfScenarioRequest $request): JsonResponse
    {
        $user = $request->user();
        $validated = $request->validated();

        try {
            $scenario = $this->scenarioService->createScenario($user, $validated);

            return response()->json([
                'success' => true,
                'message' => 'Scenario created successfully.',
                'data' => new WhatIfScenarioResource($scenario),
            ], 201);
        } catch (\Throwable $e) {
            return $this->errorResponse($e, 'Create scenario', 500, ['user_id' => $user->id]);
        }
    }

    /**
     * Update a scenario (rename only).
     *
     * PUT /api/what-if-scenarios/{id}
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $user = $request->user();

        $scenario = WhatIfScenario::where('id', $id)
            ->where('user_id', $user->id)
            ->first();

        if (! $scenario) {
            return $this->notFoundResponse('Scenario');
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
        ]);

        try {
            $scenario->update($validated);

            return response()->json([
                'success' => true,
                'message' => 'Scenario updated successfully.',
                'data' => new WhatIfScenarioResource($scenario),
            ]);
        } catch (\Throwable $e) {
            return $this->errorResponse($e, 'Update scenario', 500, ['scenario_id' => $id]);
        }
    }

    /**
     * Soft delete a scenario.
     *
     * DELETE /api/what-if-scenarios/{id}
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        $user = $request->user();

        $scenario = WhatIfScenario::where('id', $id)
            ->where('user_id', $user->id)
            ->first();

        if (! $scenario) {
            return $this->notFoundResponse('Scenario');
        }

        try {
            $scenario->delete();

            return response()->json([
                'success' => true,
                'message' => 'Scenario deleted successfully.',
            ]);
        } catch (\Throwable $e) {
            return $this->errorResponse($e, 'Delete scenario', 500, ['scenario_id' => $id]);
        }
    }
}
