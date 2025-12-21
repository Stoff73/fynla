<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Agents\InvestmentAgent;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InvestmentProjectionController extends Controller
{
    public function __construct(
        private InvestmentAgent $investmentAgent
    ) {}

    /**
     * Get portfolio projections with Monte Carlo simulation.
     * POST /api/investment/projections
     */
    public function getProjections(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'projection_periods' => 'nullable|array',
            'projection_periods.*' => 'integer|min:1|max:50',
            'selected_period' => 'nullable|integer|min:1|max:50',
            'contribution_overrides' => 'nullable|array',
            'contribution_overrides.*' => 'numeric|min:0',
        ]);

        $user = $request->user();

        $projections = $this->investmentAgent->getPortfolioProjections(
            $user->id,
            $validated['projection_periods'] ?? [5, 10, 20, 30],
            $validated['contribution_overrides'] ?? null,
            $validated['selected_period'] ?? null
        );

        return response()->json([
            'success' => true,
            'data' => $projections,
        ]);
    }
}
