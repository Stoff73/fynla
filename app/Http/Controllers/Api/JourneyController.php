<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Onboarding\StoreJourneySelectionsRequest;
use App\Http\Traits\SanitizedErrorResponse;
use App\Services\Onboarding\DashboardPromptService;
use App\Services\Onboarding\JourneyFieldResolver;
use App\Services\Onboarding\JourneyStateService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class JourneyController extends Controller
{
    use SanitizedErrorResponse;

    public function __construct(
        private readonly JourneyStateService $journeyStateService,
        private readonly JourneyFieldResolver $journeyFieldResolver,
        private readonly DashboardPromptService $dashboardPromptService
    ) {}

    public function getSelections(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'success' => true,
            'data' => [
                'selections' => $this->journeyStateService->getSelectedJourneys($user),
                'states' => $this->journeyStateService->getJourneyStates($user),
            ],
        ]);
    }

    public function saveSelections(StoreJourneySelectionsRequest $request): JsonResponse
    {
        try {
            $user = $request->user();
            $journeys = $request->validated()['journeys'];

            $this->journeyStateService->setSelectedJourneys($user, $journeys);

            return response()->json([
                'success' => true,
                'message' => 'Focus areas saved',
                'data' => [
                    'selections' => $this->journeyStateService->getSelectedJourneys($user),
                    'states' => $this->journeyStateService->getJourneyStates($user),
                ],
            ], 201);
        } catch (\InvalidArgumentException $e) {
            return $this->validationErrorResponse($e->getMessage());
        } catch (\Throwable $e) {
            return $this->errorResponse($e, 'Save journey selections');
        }
    }

    public function getSteps(Request $request, string $journey): JsonResponse
    {
        try {
            $user = $request->user();
            $selectedJourneys = $this->journeyStateService->getSelectedJourneys($user);

            if (count($selectedJourneys) > 1 && in_array($journey, $selectedJourneys, true)) {
                $steps = $this->journeyFieldResolver->getStepsForJourneys($selectedJourneys);
            } else {
                $steps = $this->journeyFieldResolver->getStepsForJourney($journey);
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'steps' => $steps,
                ],
            ]);
        } catch (\InvalidArgumentException $e) {
            return $this->validationErrorResponse($e->getMessage());
        } catch (\Throwable $e) {
            return $this->errorResponse($e, 'Get journey steps');
        }
    }

    public function startJourney(Request $request, string $journey): JsonResponse
    {
        try {
            $this->journeyStateService->startJourney($request->user(), $journey);

            return response()->json([
                'success' => true,
                'message' => 'Journey started',
            ]);
        } catch (\InvalidArgumentException $e) {
            return $this->validationErrorResponse($e->getMessage());
        } catch (\Throwable $e) {
            return $this->errorResponse($e, 'Start journey');
        }
    }

    public function completeJourney(Request $request, string $journey): JsonResponse
    {
        try {
            $this->journeyStateService->completeJourney($request->user(), $journey);

            return response()->json([
                'success' => true,
                'message' => 'Journey completed',
            ]);
        } catch (\InvalidArgumentException $e) {
            return $this->validationErrorResponse($e->getMessage());
        } catch (\Throwable $e) {
            return $this->errorResponse($e, 'Complete journey');
        }
    }

    public function preview(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'journeys' => 'required|array|min:1',
                'journeys.*' => 'required|string',
            ]);

            $journeys = $request->input('journeys');
            $previewData = $this->journeyFieldResolver->getPreviewForJourneys($journeys);

            return response()->json([
                'success' => true,
                'data' => $previewData,
            ]);
        } catch (\InvalidArgumentException $e) {
            return $this->validationErrorResponse($e->getMessage());
        } catch (\Throwable $e) {
            return $this->errorResponse($e, 'Journey preview');
        }
    }

    public function getDashboardPrompts(Request $request): JsonResponse
    {
        try {
            $prompts = $this->dashboardPromptService->getDashboardPrompts($request->user());

            return response()->json([
                'success' => true,
                'data' => [
                    'prompts' => $prompts,
                ],
            ]);
        } catch (\Throwable $e) {
            return $this->errorResponse($e, 'Get dashboard prompts');
        }
    }

    public function dismissPrompt(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'prompt_id' => 'required|string',
            ]);

            $this->dashboardPromptService->dismissPrompt(
                $request->user(),
                $request->input('prompt_id')
            );

            return response()->json([
                'success' => true,
                'message' => 'Prompt dismissed',
            ]);
        } catch (\Throwable $e) {
            return $this->errorResponse($e, 'Dismiss prompt');
        }
    }
}
