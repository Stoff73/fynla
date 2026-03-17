<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Traits\SanitizedErrorResponse;
use App\Services\LifeStage\LifeStageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LifeStageController extends Controller
{
    use SanitizedErrorResponse;

    public function __construct(
        private readonly LifeStageService $lifeStageService
    ) {}

    /**
     * Get the user's life stage progress (current stage + completed steps).
     */
    public function progress(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            $progress = $this->lifeStageService->getProgress($user);
            $dataCompleted = $this->lifeStageService->getDataCompleteness($user);

            return response()->json([
                'success' => true,
                'data' => array_merge($progress, [
                    'data_completed_steps' => $dataCompleted,
                ]),
            ]);
        } catch (\Throwable $e) {
            return $this->errorResponse($e, 'Get life stage progress');
        }
    }

    /**
     * Set the user's life stage.
     */
    public function setStage(Request $request): JsonResponse
    {
        $request->validate([
            'life_stage' => 'required|string|in:' . implode(',', LifeStageService::VALID_STAGES),
        ]);

        try {
            $this->lifeStageService->setStage($request->user(), $request->life_stage);

            return response()->json([
                'success' => true,
                'life_stage' => $request->life_stage,
            ]);
        } catch (\InvalidArgumentException $e) {
            return $this->validationErrorResponse($e->getMessage());
        } catch (\Throwable $e) {
            return $this->errorResponse($e, 'Set life stage');
        }
    }

    /**
     * Mark an onboarding step as completed for the current life stage.
     */
    public function completeStep(Request $request): JsonResponse
    {
        $request->validate([
            'step' => 'required|string',
        ]);

        try {
            $this->lifeStageService->completeStep($request->user(), $request->step);

            return response()->json([
                'success' => true,
            ]);
        } catch (\Throwable $e) {
            return $this->errorResponse($e, 'Complete life stage step');
        }
    }
}
