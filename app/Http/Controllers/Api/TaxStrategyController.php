<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\DataTransferObjects\TaxStrategyOverridesDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\TaxStrategyCalculateRequest;
use App\Services\Coordination\ComposedTaxPlanService;
use App\Services\Tax\TaxStrategyService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * SaveTax campaign — Tax Strategy dashboard endpoints.
 *
 * - GET  /api/tax-strategy           → initial dashboard payload (allowance grid + suggestions)
 * - POST /api/tax-strategy/calculate → in-memory recalculation with slider overrides
 */
final class TaxStrategyController extends Controller
{
    public function __construct(
        private readonly TaxStrategyService $service,
        private readonly ComposedTaxPlanService $composedTaxPlan,
    ) {}

    public function show(Request $request): JsonResponse
    {
        $user = $request->user();
        $payload = $this->service->getDashboardPayload($user);
        $payload['composed_plan'] = $this->composedTaxPlan->forUser($user);

        return response()->json(['data' => $payload]);
    }

    public function calculate(TaxStrategyCalculateRequest $request): JsonResponse
    {
        $overrides = TaxStrategyOverridesDTO::fromArray($request->validated());
        $payload = $this->service->recalculate($request->user(), $overrides);

        return response()->json(['data' => $payload]);
    }
}
