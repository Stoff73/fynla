<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Traits\SanitizedErrorResponse;
use App\Services\Risk\RiskPreferenceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Risk Preference Controller
 *
 * Manages API endpoints for user risk preferences
 */
class RiskPreferenceController extends Controller
{
    use SanitizedErrorResponse;

    public function __construct(
        private readonly RiskPreferenceService $riskPreferenceService
    ) {}

    /**
     * Get all available risk levels
     *
     * GET /api/risk/levels
     */
    public function getLevels(): JsonResponse
    {
        try {
            $levels = $this->riskPreferenceService->getAvailableRiskLevels();

            return response()->json([
                'success' => true,
                'data' => $levels,
            ]);
        } catch (\Exception $e) {
            return $this->errorResponse($e, 'Risk levels retrieval');
        }
    }

    /**
     * Get user's risk profile
     *
     * GET /api/risk/profile
     *
     * If no profile exists, automatically calculates one from financial factors.
     */
    public function getProfile(Request $request): JsonResponse
    {
        $user = $request->user();

        try {
            $profile = $this->riskPreferenceService->getRiskProfile($user->id);

            // Auto-calculate if no profile exists
            if (! $profile) {
                $profile = $this->riskPreferenceService->calculateAndSetRiskLevel($user->id);

                return response()->json([
                    'success' => true,
                    'data' => $profile,
                    'auto_calculated' => true,
                    'message' => 'Risk profile automatically calculated based on your financial data.',
                ]);
            }

            return response()->json([
                'success' => true,
                'data' => $profile,
                'auto_calculated' => false,
            ]);
        } catch (\Exception $e) {
            return $this->errorResponse($e, 'Risk profile retrieval');
        }
    }

    /**
     * Set user's main risk preference
     *
     * POST /api/risk/profile
     */
    public function setProfile(Request $request): JsonResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'risk_level' => 'required|string|in:low,lower_medium,medium,upper_medium,high',
        ]);

        try {
            $riskProfile = $this->riskPreferenceService->setMainRiskLevel(
                $user->id,
                $validated['risk_level']
            );

            $profile = $this->riskPreferenceService->getRiskProfile($user->id);

            return response()->json([
                'success' => true,
                'message' => 'Risk profile updated successfully',
                'data' => $profile,
            ]);
        } catch (\Exception $e) {
            return $this->errorResponse($e, 'Risk profile update');
        }
    }

    /**
     * Recalculate risk profile based on financial factors
     *
     * POST /api/risk/recalculate
     */
    public function recalculate(Request $request): JsonResponse
    {
        $user = $request->user();

        try {
            $result = $this->riskPreferenceService->calculateAndSetRiskLevel($user->id);

            return response()->json([
                'success' => true,
                'message' => 'Risk profile recalculated successfully',
                'data' => $result,
            ]);
        } catch (\Exception $e) {
            return $this->errorResponse($e, 'Risk profile recalculation');
        }
    }

    /**
     * Get allowed risk levels for product override
     *
     * GET /api/risk/allowed-levels
     */
    public function getAllowedLevels(Request $request): JsonResponse
    {
        $user = $request->user();

        try {
            $allowedLevels = $this->riskPreferenceService->getAllowedProductRiskLevelsWithConfig($user->id);
            $mainLevel = $this->riskPreferenceService->getMainRiskLevel($user->id);

            return response()->json([
                'success' => true,
                'data' => [
                    'main_level' => $mainLevel,
                    'allowed_levels' => $allowedLevels,
                ],
            ]);
        } catch (\Exception $e) {
            return $this->errorResponse($e, 'Allowed risk levels retrieval');
        }
    }

    /**
     * Validate a product risk level
     *
     * POST /api/risk/validate-product-level
     */
    public function validateProductLevel(Request $request): JsonResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'risk_level' => 'required|string|in:low,lower_medium,medium,upper_medium,high',
        ]);

        try {
            $isValid = $this->riskPreferenceService->validateProductRiskLevel(
                $user->id,
                $validated['risk_level']
            );

            $mainLevel = $this->riskPreferenceService->getMainRiskLevel($user->id);

            return response()->json([
                'success' => true,
                'data' => [
                    'is_valid' => $isValid,
                    'main_level' => $mainLevel,
                    'requested_level' => $validated['risk_level'],
                    'message' => $isValid
                        ? 'Risk level is valid for your profile'
                        : 'Risk level is outside your allowed range (±1 from your main profile)',
                ],
            ]);
        } catch (\Exception $e) {
            return $this->errorResponse($e, 'Product risk level validation');
        }
    }

    /**
     * Get risk level configuration by key
     *
     * GET /api/risk/config/{level}
     */
    public function getRiskConfig(string $level): JsonResponse
    {
        try {
            $config = $this->riskPreferenceService->getRiskLevelConfig($level);

            return response()->json([
                'success' => true,
                'data' => $config,
            ]);
        } catch (\InvalidArgumentException $e) {
            return $this->errorResponse($e, 'Risk level validation', 422);
        } catch (\Exception $e) {
            return $this->errorResponse($e, 'Risk configuration retrieval');
        }
    }
}
