<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreRetirementActionDefinitionRequest;
use App\Http\Traits\SanitizedErrorResponse;
use App\Models\RetirementActionDefinition;
use Illuminate\Http\JsonResponse;

class RetirementActionDefinitionController extends Controller
{
    use SanitizedErrorResponse;

    /**
     * List all action definitions ordered by sort_order.
     */
    public function index(): JsonResponse
    {
        try {
            $definitions = RetirementActionDefinition::orderBy('sort_order')
                ->orderBy('id')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $definitions,
            ]);
        } catch (\Exception $e) {
            return $this->safeErrorResponse('Failed to fetch retirement action definitions', $e);
        }
    }

    /**
     * Show a single action definition.
     */
    public function show(int $id): JsonResponse
    {
        $definition = RetirementActionDefinition::find($id);

        if (! $definition) {
            return response()->json([
                'success' => false,
                'message' => 'Retirement action definition not found',
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
    public function store(StoreRetirementActionDefinitionRequest $request): JsonResponse
    {
        try {
            $definition = RetirementActionDefinition::create($request->validated());

            return response()->json([
                'success' => true,
                'message' => 'Retirement action definition created successfully',
                'data' => $definition,
            ], 201);
        } catch (\Exception $e) {
            return $this->safeErrorResponse('Failed to create retirement action definition', $e);
        }
    }

    /**
     * Update an existing action definition.
     */
    public function update(StoreRetirementActionDefinitionRequest $request, int $id): JsonResponse
    {
        $definition = RetirementActionDefinition::find($id);

        if (! $definition) {
            return response()->json([
                'success' => false,
                'message' => 'Retirement action definition not found',
            ], 404);
        }

        try {
            $definition->update($request->validated());

            return response()->json([
                'success' => true,
                'message' => 'Retirement action definition updated successfully',
                'data' => $definition->fresh(),
            ]);
        } catch (\Exception $e) {
            return $this->safeErrorResponse('Failed to update retirement action definition', $e);
        }
    }

    /**
     * Delete an action definition.
     */
    public function destroy(int $id): JsonResponse
    {
        $definition = RetirementActionDefinition::find($id);

        if (! $definition) {
            return response()->json([
                'success' => false,
                'message' => 'Retirement action definition not found',
            ], 404);
        }

        try {
            $definition->delete();

            return response()->json([
                'success' => true,
                'message' => 'Retirement action definition deleted successfully',
            ]);
        } catch (\Exception $e) {
            return $this->safeErrorResponse('Failed to delete retirement action definition', $e);
        }
    }

    /**
     * Toggle the enabled state of an action definition.
     */
    public function toggleEnabled(int $id): JsonResponse
    {
        $definition = RetirementActionDefinition::find($id);

        if (! $definition) {
            return response()->json([
                'success' => false,
                'message' => 'Retirement action definition not found',
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
            return $this->safeErrorResponse('Failed to toggle retirement action definition', $e);
        }
    }
}
