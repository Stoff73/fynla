<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreInvestmentActionDefinitionRequest;
use App\Http\Traits\SanitizedErrorResponse;
use App\Models\InvestmentActionDefinition;
use Illuminate\Http\JsonResponse;

class InvestmentActionDefinitionController extends Controller
{
    use SanitizedErrorResponse;

    /**
     * List all action definitions ordered by sort_order.
     */
    public function index(): JsonResponse
    {
        try {
            $definitions = InvestmentActionDefinition::orderBy('sort_order')
                ->orderBy('id')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $definitions,
            ]);
        } catch (\Exception $e) {
            return $this->safeErrorResponse('Failed to fetch investment action definitions', $e);
        }
    }

    /**
     * Show a single action definition.
     */
    public function show(int $id): JsonResponse
    {
        $definition = InvestmentActionDefinition::find($id);

        if (! $definition) {
            return response()->json([
                'success' => false,
                'message' => 'Investment action definition not found',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $definition,
        ]);
    }

    /**
     * Create a new action definition.
     */
    public function store(StoreInvestmentActionDefinitionRequest $request): JsonResponse
    {
        try {
            $definition = InvestmentActionDefinition::create($request->validated());

            return response()->json([
                'success' => true,
                'message' => 'Investment action definition created successfully',
                'data' => $definition,
            ], 201);
        } catch (\Exception $e) {
            return $this->safeErrorResponse('Failed to create investment action definition', $e);
        }
    }

    /**
     * Update an existing action definition.
     */
    public function update(StoreInvestmentActionDefinitionRequest $request, int $id): JsonResponse
    {
        $definition = InvestmentActionDefinition::find($id);

        if (! $definition) {
            return response()->json([
                'success' => false,
                'message' => 'Investment action definition not found',
            ], 404);
        }

        try {
            $definition->update($request->validated());

            return response()->json([
                'success' => true,
                'message' => 'Investment action definition updated successfully',
                'data' => $definition->fresh(),
            ]);
        } catch (\Exception $e) {
            return $this->safeErrorResponse('Failed to update investment action definition', $e);
        }
    }

    /**
     * Delete an action definition.
     */
    public function destroy(int $id): JsonResponse
    {
        $definition = InvestmentActionDefinition::find($id);

        if (! $definition) {
            return response()->json([
                'success' => false,
                'message' => 'Investment action definition not found',
            ], 404);
        }

        try {
            $definition->delete();

            return response()->json([
                'success' => true,
                'message' => 'Investment action definition deleted successfully',
            ]);
        } catch (\Exception $e) {
            return $this->safeErrorResponse('Failed to delete investment action definition', $e);
        }
    }

    /**
     * Toggle the enabled state of an action definition.
     */
    public function toggleEnabled(int $id): JsonResponse
    {
        $definition = InvestmentActionDefinition::find($id);

        if (! $definition) {
            return response()->json([
                'success' => false,
                'message' => 'Investment action definition not found',
            ], 404);
        }

        try {
            $definition->update(['is_enabled' => ! $definition->is_enabled]);

            return response()->json([
                'success' => true,
                'message' => $definition->is_enabled ? 'Action enabled' : 'Action disabled',
                'data' => $definition->fresh(),
            ]);
        } catch (\Exception $e) {
            return $this->safeErrorResponse('Failed to toggle investment action definition', $e);
        }
    }
}
