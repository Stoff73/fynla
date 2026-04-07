<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Settings;

use App\Http\Controllers\Controller;
use App\Http\Traits\SanitizedErrorResponse;
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
    use SanitizedErrorResponse;

    public function __construct(
        private readonly AssumptionsService $assumptionsService
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
            return $this->errorResponse($e, 'Fetching assumptions');
        }
    }

    /**
     * Update assumptions for a specific type (pensions, investments, or estate_planning).
     */
    public function update(Request $request, string $type): JsonResponse
    {
        $validTypes = ['pensions', 'investments', 'estate_planning'];

        if (! in_array($type, $validTypes, true)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid assumption type. Must be "pensions", "investments", or "estate_planning".',
            ], 422);
        }

        // Use different validation rules for estate_planning
        if ($type === 'estate_planning') {
            $validated = $request->validate([
                'inflation_rate' => 'nullable|numeric|min:0|max:20',
                'property_growth_rate' => 'nullable|numeric|min:-10|max:20',
                'investment_growth_method' => 'nullable|in:monte_carlo,custom',
                'custom_investment_rate' => 'nullable|numeric|min:-10|max:30',
                'reset' => 'nullable|boolean',
            ]);
        } else {
            $validated = $request->validate([
                'inflation_rate' => 'nullable|numeric|min:0|max:20',
                'return_rate' => 'nullable|numeric|min:-10|max:30',
                'compound_periods' => 'nullable|integer|min:1|max:365',
                'reset' => 'nullable|boolean',
            ]);
        }

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

            // Build update data based on type
            if ($type === 'estate_planning') {
                $updateData = [
                    'inflation_rate' => $validated['inflation_rate'] ?? null,
                    'property_growth_rate' => $validated['property_growth_rate'] ?? null,
                    'investment_growth_method' => $validated['investment_growth_method'] ?? null,
                    'custom_investment_rate' => $validated['custom_investment_rate'] ?? null,
                ];
            } else {
                $updateData = [
                    'inflation_rate' => $validated['inflation_rate'] ?? null,
                    'return_rate' => $validated['return_rate'] ?? null,
                    'compound_periods' => $validated['compound_periods'] ?? null,
                ];
            }

            // Update with provided values
            $assumptions = $this->assumptionsService->updateAssumptions($userId, $type, $updateData);

            return response()->json([
                'success' => true,
                'message' => 'Assumptions updated successfully',
                'data' => $assumptions,
            ]);
        } catch (\InvalidArgumentException $e) {
            return $this->errorResponse($e, 'Assumptions validation', 422);
        } catch (\Exception $e) {
            return $this->errorResponse($e, 'Updating assumptions');
        }
    }
}
