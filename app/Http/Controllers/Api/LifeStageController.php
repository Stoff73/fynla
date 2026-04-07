<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Traits\SanitizedErrorResponse;
use App\Services\LifeStage\LifeStageService;
use App\Services\PrerequisiteGateService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LifeStageController extends Controller
{
    use SanitizedErrorResponse;

    public function __construct(
        private readonly LifeStageService $lifeStageService,
        private readonly PrerequisiteGateService $prerequisiteGate
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
            $stepCompleteness = $this->lifeStageService->getStepCompleteness($user);

            return response()->json([
                'success' => true,
                'data' => array_merge($progress, [
                    'data_completed_steps' => $dataCompleted,
                    'step_completeness' => $stepCompleteness,
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
            'life_stage' => 'required|string|in:'.implode(',', LifeStageService::VALID_STAGES),
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

    /**
     * Get per-module information completeness.
     *
     * Returns two tiers per module:
     * - has_data (display level): user has entered any data for this module
     * - can_advise (advice level): Agent has enough data for regulated advice
     * - missing: specific fields still needed for advice level
     * - guidance: user-friendly explanation of what's needed
     *
     * This is the single source of truth for progress indicators, dashboard
     * card visibility, and Agent/AI chat advice readiness.
     */
    public function completeness(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            $modules = $this->buildModuleCompleteness($user);

            return response()->json([
                'success' => true,
                'data' => [
                    'modules' => $modules,
                    'life_stage' => $user->life_stage,
                ],
            ]);
        } catch (\Throwable $e) {
            return $this->errorResponse($e, 'Get module completeness');
        }
    }

    /**
     * Build per-module completeness combining display-level and advice-level checks.
     *
     * Display level: does any data exist for this module? (enough to show a card)
     * Advice level: do all BLOCKING prerequisites pass? (enough for Agent analysis)
     */
    private function buildModuleCompleteness(\App\Models\User $user): array
    {
        // Full assessments from DataReadiness services (field-level detail)
        $assessments = $this->prerequisiteGate->assessAll($user);

        // Module gates (includes goals + tax which have no DataReadiness service)
        $gates = [
            'protection' => $this->prerequisiteGate->canAnalyseProtection($user),
            'savings' => $this->prerequisiteGate->canAnalyseSavings($user),
            'retirement' => $this->prerequisiteGate->canAnalyseRetirement($user),
            'investment' => $this->prerequisiteGate->canAnalyseInvestment($user),
            'estate' => $this->prerequisiteGate->canAnalyseEstate($user),
            'goals' => $this->prerequisiteGate->canAnalyseGoals($user),
            'tax_optimisation' => $this->prerequisiteGate->canAnalyseTax($user),
        ];

        // Display-level checks: does the user have ANY data for this module?
        $displayChecks = [
            'protection' => $user->lifeInsurancePolicies()->exists()
                || $user->criticalIllnessPolicies()->exists()
                || $user->incomeProtectionPolicies()->exists(),
            'savings' => $user->savingsAccounts()->exists(),
            'retirement' => $user->dcPensions()->exists()
                || $user->dbPensions()->exists()
                || $user->statePension()->exists(),
            'investment' => $user->investmentAccounts()->exists(),
            'estate' => $user->properties()->exists()
                || $user->investmentAccounts()->exists()
                || $user->savingsAccounts()->exists()
                || $user->liabilities()->exists(),
            'goals' => $user->goals()->exists(),
            'tax_optimisation' => $gates['tax_optimisation']['can_proceed'],
        ];

        $modules = [];
        foreach ($gates as $module => $gate) {
            $assessment = $assessments[$module] ?? null;
            $modules[$module] = [
                'has_data' => $displayChecks[$module] ?? false,
                'can_advise' => $gate['can_proceed'],
                'missing' => $gate['missing'],
                'guidance' => $gate['guidance'],
                'required_actions' => $gate['required_actions'],
                // Field-level detail from DataReadiness services
                'completeness_percent' => $assessment['completeness_percent'] ?? null,
                'blocking' => $assessment['blocking'] ?? [],
                'warnings' => $assessment['warnings'] ?? [],
                'total_checks' => $assessment['total_checks'] ?? null,
                'passed_checks' => $assessment['passed_checks'] ?? null,
            ];
        }

        return $modules;
    }
}
