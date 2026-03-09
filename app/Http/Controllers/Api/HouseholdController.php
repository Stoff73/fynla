<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Traits\SanitizedErrorResponse;
use App\Services\Coordination\HouseholdPlanningService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HouseholdController extends Controller
{
    use SanitizedErrorResponse;

    public function __construct(
        private readonly HouseholdPlanningService $householdService
    ) {}

    /**
     * Get household net worth breakdown.
     *
     * Returns combined net worth for user and spouse (if data sharing enabled).
     * Returns individual net worth only if no spouse is linked.
     */
    public function getNetWorth(Request $request): JsonResponse
    {
        try {
            $user = $request->user();

            if (! $user) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not authenticated',
                ], 401);
            }

            $netWorth = $this->householdService->calculateHouseholdNetWorth($user);

            return response()->json([
                'success' => true,
                'message' => 'Household net worth calculated successfully',
                'data' => $netWorth,
            ]);
        } catch (\Exception $e) {
            return $this->errorResponse($e, 'Calculating household net worth');
        }
    }

    /**
     * Get spousal optimisation recommendations.
     *
     * Returns tax optimisation recommendations for married/partnered users
     * with data sharing enabled. Returns empty array for single users.
     */
    public function getOptimisations(Request $request): JsonResponse
    {
        try {
            $user = $request->user();

            if (! $user) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not authenticated',
                ], 401);
            }

            $optimisations = $this->householdService->generateSpousalOptimisations($user);

            return response()->json([
                'success' => true,
                'message' => count($optimisations) > 0
                    ? 'Spousal optimisations generated successfully'
                    : 'No spousal optimisations available',
                'data' => $optimisations,
            ]);
        } catch (\Exception $e) {
            return $this->errorResponse($e, 'Generating spousal optimisations');
        }
    }

    /**
     * Get death-of-spouse scenario analysis.
     *
     * Models the financial impact if either spouse passes away.
     * Accepts ?spouse=primary|partner to specify which spouse.
     * Returns individual estate analysis for single users.
     */
    public function getDeathScenario(Request $request): JsonResponse
    {
        try {
            $user = $request->user();

            if (! $user) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not authenticated',
                ], 401);
            }

            $whichSpouse = $request->query('spouse', 'primary');

            if (! in_array($whichSpouse, ['primary', 'partner'], true)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid spouse parameter. Use "primary" or "partner".',
                ], 400);
            }

            $scenario = $this->householdService->modelDeathOfSpouseScenario($user, $whichSpouse);

            return response()->json([
                'success' => true,
                'message' => 'Death scenario modelled successfully',
                'data' => $scenario,
            ]);
        } catch (\Exception $e) {
            return $this->errorResponse($e, 'Modelling death scenario');
        }
    }
}
