<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Risk\RiskPreferenceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

/**
 * Risk Preference Controller
 *
 * Manages API endpoints for user risk preferences
 */
class RiskPreferenceController extends Controller
{
    public function __construct(
        private RiskPreferenceService $riskPreferenceService
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
            Log::error('Failed to retrieve risk levels', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve risk levels',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get user's risk profile
     *
     * GET /api/risk/profile
     */
    public function getProfile(Request $request): JsonResponse
    {
        $user = $request->user();

        try {
            $profile = $this->riskPreferenceService->getRiskProfile($user->id);

            if (! $profile) {
                return response()->json([
                    'success' => true,
                    'data' => null,
                    'message' => 'No risk profile set. Please set your risk preference.',
                ]);
            }

            return response()->json([
                'success' => true,
                'data' => $profile,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to retrieve risk profile', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve risk profile',
                'error' => $e->getMessage(),
            ], 500);
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

        $validator = Validator::make($request->all(), [
            'risk_level' => 'required|string|in:low,lower_medium,medium,upper_medium,high',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $validated = $validator->validated();

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
            Log::error('Failed to set risk profile', [
                'user_id' => $user->id,
                'risk_level' => $validated['risk_level'],
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to set risk profile',
                'error' => $e->getMessage(),
            ], 500);
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
            Log::error('Failed to retrieve allowed risk levels', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve allowed risk levels',
                'error' => $e->getMessage(),
            ], 500);
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

        $validator = Validator::make($request->all(), [
            'risk_level' => 'required|string|in:low,lower_medium,medium,upper_medium,high',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $validated = $validator->validated();

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
            Log::error('Failed to validate product risk level', [
                'user_id' => $user->id,
                'risk_level' => $validated['risk_level'],
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to validate risk level',
                'error' => $e->getMessage(),
            ], 500);
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
            return response()->json([
                'success' => false,
                'message' => 'Invalid risk level',
                'error' => $e->getMessage(),
            ], 422);
        } catch (\Exception $e) {
            Log::error('Failed to retrieve risk config', [
                'level' => $level,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve risk configuration',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
