<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreActionDefinitionRequest;
use App\Http\Traits\SanitizedErrorResponse;
use App\Models\EstateActionDefinition;
use App\Models\InvestmentActionDefinition;
use App\Models\ProtectionActionDefinition;
use App\Models\RetirementActionDefinition;
use App\Models\SavingsActionDefinition;
use App\Models\TaxActionDefinition;
use Illuminate\Http\JsonResponse;

class ActionDefinitionController extends Controller
{
    use SanitizedErrorResponse;

    private const ALLOWED_MODULES = [
        'protection' => ProtectionActionDefinition::class,
        'savings' => SavingsActionDefinition::class,
        'investment' => InvestmentActionDefinition::class,
        'retirement' => RetirementActionDefinition::class,
        'estate' => EstateActionDefinition::class,
        'tax' => TaxActionDefinition::class,
    ];

    /**
     * Resolve the model class for a given module, or abort 422.
     */
    private function resolveModel(string $module): string
    {
        if (! isset(self::ALLOWED_MODULES[$module])) {
            abort(422, 'Invalid module: '.$module);
        }

        return self::ALLOWED_MODULES[$module];
    }

    /**
     * List all action definitions for a module.
     */
    public function index(string $module): JsonResponse
    {
        $modelClass = $this->resolveModel($module);

        try {
            $definitions = $modelClass::orderBy('sort_order')
                ->orderBy('id')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $definitions,
            ]);
        } catch (\Exception $e) {
            return $this->safeErrorResponse('Failed to fetch action definitions', $e);
        }
    }

    /**
     * Show a single action definition.
     */
    public function show(string $module, int $id): JsonResponse
    {
        $modelClass = $this->resolveModel($module);
        $definition = $modelClass::find($id);

        if (! $definition) {
            return response()->json([
                'success' => false,
                'message' => 'Action definition not found',
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
    public function store(StoreActionDefinitionRequest $request, string $module): JsonResponse
    {
        $modelClass = $this->resolveModel($module);

        try {
            $definition = $modelClass::create($request->validated());

            return response()->json([
                'success' => true,
                'message' => 'Action definition created successfully',
                'data' => $definition,
            ], 201);
        } catch (\Exception $e) {
            return $this->safeErrorResponse('Failed to create action definition', $e);
        }
    }

    /**
     * Update an existing action definition.
     */
    public function update(StoreActionDefinitionRequest $request, string $module, int $id): JsonResponse
    {
        $modelClass = $this->resolveModel($module);
        $definition = $modelClass::find($id);

        if (! $definition) {
            return response()->json([
                'success' => false,
                'message' => 'Action definition not found',
            ], 404);
        }

        try {
            $definition->update($request->validated());

            return response()->json([
                'success' => true,
                'message' => 'Action definition updated successfully',
                'data' => $definition->fresh(),
            ]);
        } catch (\Exception $e) {
            return $this->safeErrorResponse('Failed to update action definition', $e);
        }
    }

    /**
     * Delete an action definition.
     */
    public function destroy(string $module, int $id): JsonResponse
    {
        $modelClass = $this->resolveModel($module);
        $definition = $modelClass::find($id);

        if (! $definition) {
            return response()->json([
                'success' => false,
                'message' => 'Action definition not found',
            ], 404);
        }

        try {
            $definition->delete();

            return response()->json([
                'success' => true,
                'message' => 'Action definition deleted successfully',
            ]);
        } catch (\Exception $e) {
            return $this->safeErrorResponse('Failed to delete action definition', $e);
        }
    }

    /**
     * Toggle the enabled state of an action definition.
     */
    public function toggleEnabled(string $module, int $id): JsonResponse
    {
        $modelClass = $this->resolveModel($module);
        $definition = $modelClass::find($id);

        if (! $definition) {
            return response()->json([
                'success' => false,
                'message' => 'Action definition not found',
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
            return $this->safeErrorResponse('Failed to toggle action definition', $e);
        }
    }

    /**
     * Get decision matrix data for a module (tree rendering).
     */
    public function decisionMatrix(string $module): JsonResponse
    {
        $modelClass = $this->resolveModel($module);

        try {
            $definitions = $modelClass::orderBy('sort_order')->orderBy('id')->get();

            // Group by category
            $categories = $definitions->groupBy('category')->map(function ($defs, $name) {
                return [
                    'name' => $name,
                    'definitions' => $defs->map(function ($def) {
                        return array_merge($def->toArray(), [
                            'tree_nodes' => $this->buildTreeNodes($def),
                        ]);
                    })->values(),
                ];
            })->values();

            // Stats
            $stats = [
                'total' => $definitions->count(),
                'enabled' => $definitions->where('is_enabled', true)->count(),
                'disabled' => $definitions->where('is_enabled', false)->count(),
                'critical_high' => $definitions->whereIn('priority', ['critical', 'high'])->count(),
                'medium' => $definitions->where('priority', 'medium')->count(),
            ];

            return response()->json([
                'success' => true,
                'data' => [
                    'module' => $module,
                    'stats' => $stats,
                    'categories' => $categories,
                ],
            ]);
        } catch (\Exception $e) {
            return $this->safeErrorResponse('Failed to fetch decision matrix', $e);
        }
    }

    /**
     * Converts trigger_config into the 4-column tree node structure.
     */
    private function buildTreeNodes(object $definition): array
    {
        $config = $definition->trigger_config ?? [];

        return [
            'data' => [
                'type' => 'data',
                'label' => $config['condition'] ?? 'Unknown',
                'description' => 'Reads user data for '.($config['condition'] ?? 'condition'),
            ],
            'trigger' => [
                'type' => 'trigger',
                'label' => $config['condition'] ?? 'Unknown',
                'description' => isset($config['threshold'])
                    ? "Threshold: {$config['threshold']}"
                    : 'Boolean condition',
            ],
            'logic' => [
                'type' => 'logic',
                'label' => $definition->what_if_impact_type ?? 'default',
                'description' => $definition->scope ?? 'portfolio',
            ],
            'outcome' => [
                'type' => 'outcome',
                'label' => $definition->title_template,
                'description' => $definition->category,
            ],
        ];
    }
}
