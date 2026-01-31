<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Settings;

use App\Http\Controllers\Controller;
use App\Services\Settings\AssumptionsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Assumptions Controller
 *
 * Handles API requests for managing planning assumptions.
 */
class AssumptionsController extends Controller
{
    public function __construct(
        private AssumptionsService $assumptionsService
    ) {}

    /**
     * Get all assumptions for the authenticated user.
     */
    public function index(): JsonResponse
    {
        $userId = Auth::id();

        try {
            $assumptions = $this->assumptionsService->getAssumptions($userId);

            return response()->json([
                'success' => true,
                'data' => $assumptions,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch assumptions',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Update assumptions for a specific type (pensions or investments).
     */
    public function update(Request $request, string $type): JsonResponse
    {
        if (! in_array($type, ['pensions', 'investments'], true)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid assumption type. Must be "pensions" or "investments".',
            ], 422);
        }

        $validated = $request->validate([
            'inflation_rate' => 'nullable|numeric|min:0|max:20',
            'return_rate' => 'nullable|numeric|min:-10|max:30',
            'compound_periods' => 'nullable|integer|min:1|max:365',
            'reset' => 'nullable|boolean',
        ]);

        $userId = Auth::id();

        try {
            // Handle reset request
            if (! empty($validated['reset'])) {
                $assumptions = $this->assumptionsService->resetAssumptions($userId, $type);

                return response()->json([
                    'success' => true,
                    'message' => 'Assumptions reset to defaults',
                    'data' => $assumptions,
                ]);
            }

            // Update with provided values
            $assumptions = $this->assumptionsService->updateAssumptions($userId, $type, [
                'inflation_rate' => $validated['inflation_rate'] ?? null,
                'return_rate' => $validated['return_rate'] ?? null,
                'compound_periods' => $validated['compound_periods'] ?? null,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Assumptions updated successfully',
                'data' => $assumptions,
            ]);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update assumptions',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
