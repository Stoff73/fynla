<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Traits\SanitizedErrorResponse;
use App\Services\Onboarding\OnboardingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OnboardingController extends Controller
{
    use SanitizedErrorResponse;

    public function __construct(
        private readonly OnboardingService $onboardingService
    ) {}

    /**
     * Get onboarding status for the authenticated user
     */
    public function getOnboardingStatus(Request $request): JsonResponse
    {
        try {
            $status = $this->onboardingService->getOnboardingStatus($request->user()->id);

            return response()->json([
                'success' => true,
                'data' => $status,
            ]);
        } catch (\Exception $e) {
            return $this->errorResponse($e, 'Onboarding status retrieval');
        }
    }

    /**
     * Set the focus area for onboarding
     */
    public function setFocusArea(Request $request): JsonResponse
    {
        $request->validate([
            'focus_area' => 'required|in:estate',  // Only estate has a fully implemented legacy flow
        ]);

        try {
            $user = $this->onboardingService->setFocusArea(
                $request->user()->id,
                $request->input('focus_area')
            );

            return response()->json([
                'success' => true,
                'data' => [
                    'focus_area' => $user->life_stage,
                    'current_step' => $user->onboarding_current_step,
                    'started_at' => $user->onboarding_started_at?->toISOString(),
                ],
                'message' => 'Focus area set successfully',
            ]);
        } catch (\Exception $e) {
            return $this->errorResponse($e, 'Focus area setting');
        }
    }

    /**
     * Save progress for a step
     */
    public function saveStepProgress(Request $request): JsonResponse
    {
        $request->validate([
            'step_name' => 'required|string',
            'data' => 'required|array',
        ]);

        try {
            \Log::info('Saving step progress', [
                'step_name' => $request->input('step_name'),
                'user_id' => $request->user()->id,
            ]);

            $progress = $this->onboardingService->saveStepProgress(
                $request->user()->id,
                $request->input('step_name'),
                $request->input('data')
            );

            // Get updated progress percentage
            $status = $this->onboardingService->getOnboardingStatus($request->user()->id);

            return response()->json([
                'success' => true,
                'data' => [
                    'progress' => $progress,
                    'progress_percentage' => $status['progress_percentage'],
                    'next_step' => $this->onboardingService->getNextStep(
                        $request->user()->id,
                        $request->input('step_name')
                    ),
                ],
                'message' => 'Step progress saved successfully',
            ]);
        } catch (\Exception $e) {
            return $this->errorResponse($e, 'Step progress save');
        }
    }

    /**
     * Skip a step
     */
    public function skipStep(Request $request): JsonResponse
    {
        $request->validate([
            'step_name' => 'required|string',
        ]);

        try {
            $progress = $this->onboardingService->skipStep(
                $request->user()->id,
                $request->input('step_name')
            );

            // Get updated progress percentage
            $status = $this->onboardingService->getOnboardingStatus($request->user()->id);

            return response()->json([
                'success' => true,
                'data' => [
                    'progress' => $progress,
                    'progress_percentage' => $status['progress_percentage'],
                    'next_step' => $this->onboardingService->getNextStep(
                        $request->user()->id,
                        $request->input('step_name')
                    ),
                ],
                'message' => 'Step skipped successfully',
            ]);
        } catch (\Exception $e) {
            return $this->errorResponse($e, 'Step skip');
        }
    }

    /**
     * Skip all remaining steps and go to dashboard
     */
    public function skipToDashboard(Request $request): JsonResponse
    {
        try {
            $user = $this->onboardingService->skipToDashboard($request->user()->id);

            return response()->json([
                'success' => true,
                'data' => [
                    'onboarding_completed' => $user->onboarding_completed,
                    'skipped_steps' => $user->onboarding_skipped_steps,
                    'completed_at' => $user->onboarding_completed_at?->toISOString(),
                ],
                'message' => 'Skipped to dashboard successfully',
            ]);
        } catch (\Exception $e) {
            return $this->errorResponse($e, 'Skip to dashboard');
        }
    }

    /**
     * Complete the quick onboarding process (3-step progressive flow)
     */
    public function completeQuickOnboarding(Request $request): JsonResponse
    {
        try {
            $user = $this->onboardingService->completeQuickOnboarding($request->user()->id);

            return response()->json([
                'success' => true,
                'data' => [
                    'onboarding_completed' => $user->onboarding_completed,
                    'onboarding_mode' => $user->onboarding_mode,
                    'asset_flags' => $user->onboarding_asset_flags,
                    'completed_at' => $user->onboarding_completed_at?->toISOString(),
                ],
                'message' => 'Quick onboarding completed successfully',
            ]);
        } catch (\Exception $e) {
            return $this->errorResponse($e, 'Quick onboarding completion');
        }
    }

    /**
     * Complete the onboarding process
     */
    public function completeOnboarding(Request $request): JsonResponse
    {
        try {
            $user = $this->onboardingService->completeOnboarding($request->user()->id);

            return response()->json([
                'success' => true,
                'data' => [
                    'onboarding_completed' => $user->onboarding_completed,
                    'completed_at' => $user->onboarding_completed_at?->toISOString(),
                ],
                'message' => 'Onboarding completed successfully',
            ]);
        } catch (\Exception $e) {
            return $this->errorResponse($e, 'Onboarding completion');
        }
    }

    /**
     * Restart the onboarding process
     */
    public function restartOnboarding(Request $request): JsonResponse
    {
        try {
            $user = $this->onboardingService->restartOnboarding($request->user()->id);

            return response()->json([
                'success' => true,
                'data' => [
                    'onboarding_completed' => $user->onboarding_completed,
                    'focus_area' => $user->life_stage,
                ],
                'message' => 'Onboarding restarted successfully',
            ]);
        } catch (\Exception $e) {
            return $this->errorResponse($e, 'Onboarding restart');
        }
    }

    /**
     * Get data for a specific step
     */
    public function getStepData(Request $request, string $step): JsonResponse
    {
        try {
            $userId = $request->user()->id;

            $stepData = $this->onboardingService->getStepData($userId, $step);

            return response()->json([
                'success' => true,
                'data' => $stepData,
            ]);
        } catch (\Exception $e) {
            return $this->errorResponse($e, 'Step data retrieval');
        }
    }

    /**
     * Get all steps for the user's focus area
     */
    public function getSteps(Request $request): JsonResponse
    {
        try {
            $user = $request->user();

            if (! $user->life_stage) {
                return response()->json([
                    'success' => false,
                    'message' => 'Life stage not set',
                ], 400);
            }

            $steps = $this->onboardingService->getOnboardingSteps(
                $user->life_stage,
                $user->id
            );

            return response()->json([
                'success' => true,
                'data' => [
                    'steps' => array_values($steps),
                    'total' => count($steps),
                ],
            ]);
        } catch (\Exception $e) {
            return $this->errorResponse($e, 'Steps retrieval');
        }
    }

    /**
     * Get skip reason for a step
     */
    public function getSkipReason(Request $request, string $step): JsonResponse
    {
        try {
            $user = $request->user();

            if (! $user->life_stage) {
                return response()->json([
                    'success' => false,
                    'message' => 'Life stage not set',
                ], 400);
            }

            $reason = $this->onboardingService->getSkipReasonText(
                $user->life_stage,
                $step
            );

            return response()->json([
                'success' => true,
                'data' => [
                    'skip_reason' => $reason,
                ],
            ]);
        } catch (\Exception $e) {
            return $this->errorResponse($e, 'Skip reason retrieval');
        }
    }
}
