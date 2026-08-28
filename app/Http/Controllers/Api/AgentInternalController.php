<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Agents\CoordinatingAgent;
use App\Agents\EstateAgent;
use App\Agents\GoalsAgent;
use App\Agents\InvestmentAgent;
use App\Agents\ProtectionAgent;
use App\Agents\RetirementAgent;
use App\Agents\SavingsAgent;
use App\Agents\TaxOptimisationAgent;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\PrerequisiteGateService;
use App\Services\TaxConfigService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Internal API endpoints consumed by the Python Agent SDK sidecar.
 *
 * These routes are protected by AgentTokenAuth middleware (shared secret),
 * not by Sanctum. They provide the agent with access to module analysis,
 * tax configuration, scenario builders, and prerequisite gates.
 */
class AgentInternalController extends Controller
{
    public function __construct(
        private readonly CoordinatingAgent $coordinatingAgent,
        private readonly ProtectionAgent $protectionAgent,
        private readonly SavingsAgent $savingsAgent,
        private readonly InvestmentAgent $investmentAgent,
        private readonly RetirementAgent $retirementAgent,
        private readonly EstateAgent $estateAgent,
        private readonly GoalsAgent $goalsAgent,
        private readonly TaxOptimisationAgent $taxOptimisationAgent,
        private readonly TaxConfigService $taxConfigService,
        private readonly PrerequisiteGateService $prerequisiteGateService,
    ) {}

    /**
     * Fetch analysis for a specific financial module.
     *
     * GET /api/internal/agent/analysis/{module}
     */
    public function moduleAnalysis(Request $request, string $module): JsonResponse
    {
        $userId = (int) $request->query('user_id', 0);

        if ($userId <= 0) {
            return response()->json(['success' => false, 'message' => 'user_id query parameter is required'], 400);
        }

        $user = User::find($userId);
        if (! $user) {
            return response()->json(['success' => false, 'message' => 'User not found'], 404);
        }

        $agent = $this->resolveAgent($module);
        if (! $agent) {
            return response()->json(['success' => false, 'message' => "Unknown module: {$module}"], 400);
        }

        try {
            $analysis = $agent->analyze($userId);

            return response()->json([
                'success' => true,
                'data' => $analysis,
            ]);
        } catch (\Exception $e) {
            Log::error('[AgentInternal] Module analysis failed', [
                'module' => $module,
                'user_id' => $userId,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Analysis failed',
            ], 500);
        }
    }

    /**
     * Fetch UK tax configuration data for a topic.
     *
     * GET /api/internal/agent/tax/{topic}
     */
    public function taxInformation(string $topic): JsonResponse
    {
        $data = match ($topic) {
            'income_tax' => $this->taxConfigService->getIncomeTax(),
            'national_insurance' => $this->taxConfigService->getNationalInsurance(),
            'inheritance_tax' => $this->taxConfigService->getInheritanceTax(),
            'capital_gains_tax' => $this->taxConfigService->getCapitalGainsTax(),
            'dividend_tax' => $this->taxConfigService->getDividendTax(),
            'stamp_duty' => $this->taxConfigService->getStampDuty(),
            'isa' => $this->taxConfigService->getISAAllowances(),
            'pension' => $this->taxConfigService->getPensionAllowances(),
            'gifting' => $this->taxConfigService->getGiftingExemptions(),
            'assumptions' => $this->taxConfigService->getAssumptions(),
            'all' => $this->taxConfigService->getAll(),
            default => null,
        };

        if ($data === null) {
            return response()->json(['success' => false, 'message' => "Unknown tax topic: {$topic}"], 400);
        }

        return response()->json([
            'success' => true,
            'data' => $data,
            'tax_year' => $this->taxConfigService->getTaxYear(),
        ]);
    }

    /**
     * Run a what-if scenario against a module.
     *
     * POST /api/internal/agent/scenario
     */
    public function scenario(Request $request): JsonResponse
    {
        $module = $request->input('module', '');
        $parameters = $request->input('parameters', []);
        $userId = (int) $request->input('user_id', 0);

        if ($userId <= 0) {
            return response()->json(['success' => false, 'message' => 'user_id is required'], 400);
        }

        $agent = $this->resolveAgent($module);
        if (! $agent) {
            return response()->json(['success' => false, 'message' => "Unknown module: {$module}"], 400);
        }

        try {
            $result = $agent->buildScenarios($userId, $parameters);

            return response()->json([
                'success' => true,
                'data' => $result,
            ]);
        } catch (\Exception $e) {
            Log::error('[AgentInternal] Scenario failed', [
                'module' => $module,
                'user_id' => $userId,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Scenario execution failed',
            ], 500);
        }
    }

    /**
     * Check prerequisites before tool execution.
     *
     * POST /api/internal/agent/prerequisite-check
     */
    public function prerequisiteCheck(Request $request): JsonResponse
    {
        $toolName = $request->input('tool_name', '');
        $toolInput = $request->input('tool_input', []);
        $userId = (int) ($toolInput['user_id'] ?? $request->input('user_id', 0));

        if ($userId <= 0) {
            return response()->json([
                'can_proceed' => true,
                'missing' => [],
                'guidance' => '',
                'required_actions' => [],
            ]);
        }

        $user = User::find($userId);
        if (! $user) {
            return response()->json([
                'can_proceed' => false,
                'missing' => ['valid user'],
                'guidance' => 'User not found.',
                'required_actions' => [],
            ]);
        }

        $gate = $this->prerequisiteGateService->canExecuteTool($toolName, $toolInput, $user);

        return response()->json($gate);
    }

    /**
     * Fetch full orchestrated analysis context for a user.
     *
     * GET /api/internal/agent/user-context/{userId}
     */
    public function userContext(int $userId): JsonResponse
    {
        $user = User::find($userId);
        if (! $user) {
            return response()->json(['success' => false, 'message' => 'User not found'], 404);
        }

        try {
            $analysis = $this->coordinatingAgent->orchestrateAnalysis($userId);

            return response()->json([
                'success' => true,
                'data' => $analysis,
            ]);
        } catch (\Exception $e) {
            Log::error('[AgentInternal] User context failed', [
                'user_id' => $userId,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to build user context',
            ], 500);
        }
    }

    /**
     * Fetch current recommendations from the coordinating agent.
     *
     * GET /api/internal/agent/recommendations
     */
    public function recommendations(Request $request): JsonResponse
    {
        $userId = (int) $request->query('user_id', 0);

        if ($userId <= 0) {
            return response()->json(['success' => false, 'message' => 'user_id query parameter is required'], 400);
        }

        try {
            $analysis = $this->coordinatingAgent->orchestrateAnalysis($userId);
            $recommendations = $this->coordinatingAgent->generateRecommendations(
                array_merge($analysis, ['user_id' => $userId])
            );

            return response()->json([
                'success' => true,
                'data' => $recommendations,
            ]);
        } catch (\Exception $e) {
            Log::error('[AgentInternal] Recommendations failed', [
                'user_id' => $userId,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to generate recommendations',
            ], 500);
        }
    }

    /**
     * Resolve a module name to its agent instance.
     */
    private function resolveAgent(string $module): ?object
    {
        return match ($module) {
            'protection' => $this->protectionAgent,
            'savings' => $this->savingsAgent,
            'investment' => $this->investmentAgent,
            'retirement' => $this->retirementAgent,
            'estate' => $this->estateAgent,
            'goals' => $this->goalsAgent,
            'tax_optimisation', 'tax' => $this->taxOptimisationAgent,
            default => null,
        };
    }
}
