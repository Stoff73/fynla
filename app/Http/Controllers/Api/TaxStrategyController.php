<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\DataTransferObjects\TaxStrategyOverridesDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\TaxStrategyCalculateRequest;
use App\Models\RecommendationTracking;
use App\Models\User;
use App\Services\Coordination\ComposedTaxPlanService;
use App\Services\Mobile\MilestoneDetectionService;
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
        $payload = $this->attachCompletionState($user, $payload);

        // WP-5 — the first quantified annual saving is a milestone; detected
        // here because the composed plan is already in hand (recomputing it
        // in a generic detection pass would double the cost). Never breaks
        // the read. WP-5c adds tax-year allowance usage (from the grid in the
        // payload) and the cumulative actioned saving (from completed items).
        try {
            $milestones = app(MilestoneDetectionService::class);
            $milestones->detectTaxSavingsIdentified(
                $user,
                (float) ($payload['composed_plan']['combined_annual_saving'] ?? 0),
            );
            $milestones->detectTaxYearAllowances(
                $user,
                (string) ($payload['tax_year'] ?? ''),
                (array) ($payload['user_allowances'] ?? []),
            );

            $actioned = 0.0;
            foreach ((array) ($payload['composed_plan']['items'] ?? []) as $item) {
                if (is_array($item) && ($item['completed'] ?? false)) {
                    $actioned += (float) ($item['estimated_annual_tax_saved'] ?? 0);
                }
            }
            $milestones->detectTaxActioned($user, $actioned);
        } catch (\Throwable $e) {
            // best-effort
        }

        return response()->json(['data' => $payload]);
    }

    /**
     * WP-2 (one actions model) — stamp every strategy item with the SAME
     * stable id the recommendations aggregator uses ('tax_' + strategy type)
     * plus its completion state from recommendation_tracking. The tax pages
     * previously rendered these items with no id and no completion state, so
     * an action marked done on the dashboard looked untouched here and the
     * page itself offered no way to complete one.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function attachCompletionState(User $user, array $payload): array
    {
        $stamp = function (array $items) use ($user): array {
            $ids = array_map(
                static fn (array $item): string => 'tax_'.(string) ($item['type'] ?? ''),
                $items
            );

            $completedAt = RecommendationTracking::where('user_id', $user->id)
                ->whereIn('recommendation_id', $ids)
                ->where('status', 'completed')
                ->pluck('completed_at', 'recommendation_id');

            return array_map(static function (array $item) use ($completedAt): array {
                $id = 'tax_'.(string) ($item['type'] ?? '');
                $item['recommendation_id'] = $id;
                $item['completed'] = $completedAt->has($id);
                $item['completed_at'] = $completedAt->get($id)?->toIso8601String();

                return $item;
            }, $items);
        };

        if (is_array($payload['recommendations'] ?? null)) {
            $payload['recommendations'] = $stamp($payload['recommendations']);
        }
        if (is_array($payload['composed_plan']['items'] ?? null)) {
            $payload['composed_plan']['items'] = $stamp($payload['composed_plan']['items']);
        }

        return $payload;
    }

    public function calculate(TaxStrategyCalculateRequest $request): JsonResponse
    {
        $overrides = TaxStrategyOverridesDTO::fromArray($request->validated());
        $payload = $this->service->recalculate($request->user(), $overrides);

        return response()->json(['data' => $payload]);
    }
}
