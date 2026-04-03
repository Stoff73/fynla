<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Mobile;

use App\Agents\EstateAgent;
use App\Agents\GoalsAgent;
use App\Agents\InvestmentAgent;
use App\Agents\ProtectionAgent;
use App\Agents\RetirementAgent;
use App\Agents\SavingsAgent;
use App\Agents\TaxOptimisationAgent;
use App\Http\Controllers\Controller;
use App\Http\Traits\SanitizedErrorResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class ModuleSummaryController extends Controller
{
    use SanitizedErrorResponse;

    /**
     * Valid module names mapped to their agent method.
     */
    private const VALID_MODULES = [
        'protection',
        'savings',
        'investment',
        'retirement',
        'estate',
        'goals',
        'tax',
    ];

    public function __construct(
        private readonly ProtectionAgent $protectionAgent,
        private readonly SavingsAgent $savingsAgent,
        private readonly InvestmentAgent $investmentAgent,
        private readonly RetirementAgent $retirementAgent,
        private readonly EstateAgent $estateAgent,
        private readonly GoalsAgent $goalsAgent,
        private readonly TaxOptimisationAgent $taxOptimisationAgent,
    ) {}

    /**
     * Get summary for a specific module.
     *
     * GET /api/v1/mobile/modules/{module}
     */
    public function show(Request $request, string $module): JsonResponse
    {
        if (! in_array($module, self::VALID_MODULES, true)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid module specified.',
            ], 404);
        }

        try {
            $userId = $request->user()->id;
            $cacheKey = "mobile_module_{$module}_{$userId}";

            $data = Cache::remember($cacheKey, 86400, function () use ($module, $userId) {
                return [
                    'summary' => $this->getModuleSummary($module, $userId),
                    'cached_at' => now()->toIso8601String(),
                ];
            });

            // Strip any score-related keys from the summary (rule #13)
            $data['summary'] = $this->removeScores($data['summary']);

            return response()->json([
                'success' => true,
                'data' => [
                    'module' => $module,
                    'summary' => $data['summary'],
                    'cached_at' => $data['cached_at'],
                ],
            ]);
        } catch (\Exception $e) {
            return $this->errorResponse($e, 'Fetching module summary', 500, [
                'module' => $module,
            ]);
        }
    }

    /**
     * Route the request to the correct agent's analyze() method.
     */
    private function getModuleSummary(string $module, int $userId): array
    {
        return match ($module) {
            'protection' => $this->protectionAgent->analyze($userId),
            'savings' => $this->savingsAgent->analyze($userId),
            'investment' => $this->investmentAgent->analyze($userId),
            'retirement' => $this->retirementAgent->analyze($userId),
            'estate' => $this->estateAgent->analyze($userId),
            'goals' => $this->goalsAgent->analyze($userId),
            'tax' => $this->taxOptimisationAgent->analyze($userId),
        };
    }

    /**
     * Recursively remove score-related keys from the summary data.
     *
     * Rule #13: No scores in user-facing UI. Strip adequacy_score,
     * diversification_score, health_score, portfolio_health_score, etc.
     */
    private function removeScores(array $data): array
    {
        $scoreKeys = [
            'adequacy_score',
            'diversification_score',
            'health_score',
            'portfolio_health_score',
            'score',
            'overall_score',
            'composite_score',
            'risk_score',
        ];

        foreach ($data as $key => $value) {
            if (in_array($key, $scoreKeys, true)) {
                unset($data[$key]);

                continue;
            }

            if (is_array($value)) {
                $data[$key] = $this->removeScores($value);
            }
        }

        return $data;
    }
}
