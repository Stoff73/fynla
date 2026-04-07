<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Estate;

use App\Http\Controllers\Controller;
use App\Http\Traits\SanitizedErrorResponse;
use App\Services\Estate\LifePolicyStrategyService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LifePolicyController extends Controller
{
    use SanitizedErrorResponse;

    public function __construct(
        private readonly IHTController $ihtController,
        private readonly LifePolicyStrategyService $lifePolicyStrategy
    ) {}

    /**
     * Get life policy strategy (Whole of Life vs. Self-Insurance)
     *
     * Reuses existing IHT planning data to calculate optimal insurance strategy
     */
    public function getLifePolicyStrategy(Request $request): JsonResponse
    {
        $user = $request->user();

        try {
            // Validate user has required data
            if (! $user->date_of_birth || ! $user->gender) {
                return response()->json([
                    'success' => false,
                    'message' => 'Date of birth and gender are required to calculate life expectancy and premiums',
                    'requires_profile_update' => true,
                ], 422);
            }

            // ========== REUSE EXISTING IHT PLANNING DATA ==========
            // Get IHT planning data (second death for married, standard for single)
            $isMarried = $user->marital_status === 'married' && $user->spouse_id !== null;

            if ($isMarried) {
                // For married users, use second death IHT calculation
                $ihtPlanningResponse = $this->ihtController->calculateIHT($request);
                $ihtPlanningData = $ihtPlanningResponse->getData(true);

                if (! $ihtPlanningData['success']) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Failed to retrieve IHT planning data',
                    ], 500);
                }

                // Extract IHT summary data (new unified structure)
                $ihtSummary = $ihtPlanningData['iht_summary'] ?? null;
                if (! $ihtSummary) {
                    return response()->json([
                        'success' => false,
                        'message' => 'IHT summary not available',
                    ], 500);
                }

                // Use CURRENT IHT liability (not projected - life insurance covers today's liability)
                $ihtLiability = $ihtSummary['current']['iht_liability'];
                $yearsUntilDeath = (int) $ihtSummary['projected']['years_to_death'];
                $currentAge = \Carbon\Carbon::parse($user->date_of_birth)->age;

                // Get spouse data for joint policy calculation
                $spouse = $user->spouse_id ? \App\Models\User::find($user->spouse_id) : null;
                $spouseAge = $spouse && $spouse->date_of_birth ? \Carbon\Carbon::parse($spouse->date_of_birth)->age : null;
                $spouseGender = $spouse ? $spouse->gender : null;

            } else {
                // For single users, use standard IHT calculation with projection
                $ihtResponse = $this->ihtController->calculateIHT($request);
                $ihtData = $ihtResponse->getData(true);

                if (! $ihtData['success']) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Failed to retrieve IHT calculation',
                    ], 500);
                }

                // Extract IHT summary data (new unified structure)
                $ihtSummary = $ihtData['iht_summary'] ?? null;
                if (! $ihtSummary) {
                    return response()->json([
                        'success' => false,
                        'message' => 'IHT summary not available',
                    ], 500);
                }

                // Use CURRENT IHT liability (not projected - life insurance covers today's liability)
                $ihtLiability = $ihtSummary['current']['iht_liability'];
                $yearsUntilDeath = (int) $ihtSummary['projected']['years_to_death'];
                $currentAge = \Carbon\Carbon::parse($user->date_of_birth)->age;

                $spouseAge = null;
                $spouseGender = null;
            }

            // If no IHT liability, return message
            if ($ihtLiability <= 0) {
                return response()->json([
                    'success' => true,
                    'no_iht_liability' => true,
                    'message' => 'You have no projected IHT liability. Life insurance for IHT planning is not required.',
                    'data' => null,
                ]);
            }

            // Calculate life policy strategy using the service
            $strategy = $this->lifePolicyStrategy->calculateStrategy(
                coverAmount: $ihtLiability,
                yearsUntilDeath: $yearsUntilDeath,
                currentAge: $currentAge,
                gender: $user->gender,
                spouseAge: $spouseAge,
                spouseGender: $spouseGender
            );

            return response()->json([
                'success' => true,
                'data' => $strategy,
            ]);

        } catch (\Exception $e) {
            return $this->errorResponse($e, 'Calculating life policy strategy');
        }
    }
}
