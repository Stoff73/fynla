<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreProtectionActionDefinitionRequest;
use App\Http\Traits\SanitizedErrorResponse;
use App\Models\ProtectionActionDefinition;
use Illuminate\Http\JsonResponse;

class ProtectionActionDefinitionController extends Controller
{
    use SanitizedErrorResponse;

    /**
     * List all action definitions ordered by sort_order.
     */
    public function index(): JsonResponse
    {
        try {
            $definitions = ProtectionActionDefinition::orderBy('sort_order')
                ->orderBy('id')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $definitions,
            ]);
        } catch (\Exception $e) {
            return $this->safeErrorResponse('Failed to fetch protection action definitions', $e);
        }
    }

    /**
     * Show a single action definition.
     */
    public function show(int $id): JsonResponse
    {
        $definition = ProtectionActionDefinition::find($id);

        if (! $definition) {
            return response()->json([
                'success' => false,
                'message' => 'Protection action definition not found',
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
    public function store(StoreProtectionActionDefinitionRequest $request): JsonResponse
    {
        try {
            $definition = ProtectionActionDefinition::create($request->validated());

            return response()->json([
                'success' => true,
                'message' => 'Protection action definition created successfully',
                'data' => $definition,
            ], 201);
        } catch (\Exception $e) {
            return $this->safeErrorResponse('Failed to create protection action definition', $e);
        }
    }

    /**
     * Update an existing action definition.
     */
    public function update(StoreProtectionActionDefinitionRequest $request, int $id): JsonResponse
    {
        $definition = ProtectionActionDefinition::find($id);

        if (! $definition) {
            return response()->json([
                'success' => false,
                'message' => 'Protection action definition not found',
            ], 404);
        }

        try {
            $definition->update($request->validated());

            return response()->json([
                'success' => true,
                'message' => 'Protection action definition updated successfully',
                'data' => $definition->fresh(),
            ]);
        } catch (\Exception $e) {
            return $this->safeErrorResponse('Failed to update protection action definition', $e);
        }
    }

    /**
     * Delete an action definition.
     */
    public function destroy(int $id): JsonResponse
    {
        $definition = ProtectionActionDefinition::find($id);

        if (! $definition) {
            return response()->json([
                'success' => false,
                'message' => 'Protection action definition not found',
            ], 404);
        }

        try {
            $definition->delete();

            return response()->json([
                'success' => true,
                'message' => 'Protection action definition deleted successfully',
            ]);
        } catch (\Exception $e) {
            return $this->safeErrorResponse('Failed to delete protection action definition', $e);
        }
    }

    /**
     * Toggle the enabled state of an action definition.
     */
    public function toggleEnabled(int $id): JsonResponse
    {
        $definition = ProtectionActionDefinition::find($id);

        if (! $definition) {
            return response()->json([
                'success' => false,
                'message' => 'Protection action definition not found',
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
            return $this->safeErrorResponse('Failed to toggle protection action definition', $e);
        }
    }
}
