<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Mobile;

use App\Http\Controllers\Controller;
use App\Http\Traits\SanitizedErrorResponse;
use App\Models\Goal;
use App\Models\User;
use App\Services\Coordination\ComposedTaxPlanService;
use App\Services\Mobile\MilestoneDetectionService;
use App\Services\Mobile\MobileDashboardAggregator;
use App\Services\Mobile\MobileLevelService;
use App\Services\Mobile\NextActionsService;
use App\Services\Mobile\PlanningProgressService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class MobileDashboardController extends Controller
{
    use SanitizedErrorResponse;

    public function __construct(
        private readonly MobileDashboardAggregator $aggregator,
        private readonly MobileLevelService $levelService,
        private readonly NextActionsService $nextActions,
        private readonly PlanningProgressService $planningProgress,
        private readonly MilestoneDetectionService $milestones,
    ) {}

    /**
     * Get aggregated mobile dashboard data.
     *
     * Returns module summaries, net worth, Fyn insight (cached 24h), plus the
     * user's gamification level, planning-progress percentile, and a unified
     * next-actions list (recommendations and KYC-unlock prompts, max 4) for the
     * redesigned dashboard.
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $userId = $request->user()->id;

            $data = $this->aggregator->getAggregatedDashboard($userId);

            // Per-area focus cards for the carousel: a "Top actions" card (the
            // unified <=4) plus one card per module (real recs when the KYC gate
            // is open, a locked unlock card when gated). One aggregation.
            $focusAreas = $this->nextActions->focusAreas($userId);
            $data['focus_areas'] = $focusAreas;

            // The Top card's actions ARE the unified <=4 list that feeds the wheel
            // "X of Y actions" heading (spec decision B). Derive it from the cards
            // so we don't aggregate a second time.
            $actions = $focusAreas[0]['actions'] ?? [];
            $data['next_actions'] = $actions;

            // Level ring + "X of Y actions" derived from that list.
            $data['level'] = $this->levelService->levelFor($userId, $actions);

            // Planning-progress percentile (cohort = viewer's preview class).
            $data['percentile'] = $this->planningProgress->percentileFor($request->user());

            // Milestone detection runs here (not in the 24h-cached aggregator)
            // and only on this mobile-only endpoint, so a web read can't consume
            // a milestone before the mobile user sees its share prompt.
            $data['new_milestones'] = $this->detectMilestones($request->user(), $data);

            // WP-5c-iii — one persistent hero nudge: the nearest upcoming
            // milestone with its step (computed after detection so the earned
            // state is fresh). Never breaks the read.
            try {
                $upcoming = $this->milestones->upcoming(
                    $request->user(),
                    (float) ($data['net_worth']['total'] ?? 0),
                    (float) ($data['net_worth']['breakdown']['assets']['pensions'] ?? 0),
                );
                $data['next_milestone'] = $upcoming[0] ?? null;
            } catch (\Throwable $e) {
                $data['next_milestone'] = null;
            }

            return response()->json([
                'success' => true,
                'data' => $data,
            ]);
        } catch (\Exception $e) {
            return $this->errorResponse($e, 'Fetching mobile dashboard data');
        }
    }

    /**
     * Detect net-worth + goal milestones newly crossed by this user. Each fires
     * once (persisted in user_milestones). Never throws — a milestone failure
     * must not break the dashboard.
     *
     * @param  array<string,mixed>  $data  The aggregated dashboard payload
     * @return array<int,array<string,mixed>>
     */
    private function detectMilestones(User $user, array $data): array
    {
        try {
            $netWorthTotal = (float) ($data['net_worth']['total'] ?? 0);
            $milestones = $this->milestones->detectNetWorth($user, $netWorthTotal);

            $goals = Goal::forUserOrJoint($user->id)->get()->map(fn (Goal $g) => [
                'id' => $g->id,
                'name' => $g->name ?? $g->goal_name ?? 'your goal',
                'progress_percentage' => (float) $g->progress_percentage,
            ])->all();

            // WP-5c — module profiles are complete where the focus-area card
            // is unlocked (gate open + advice flowing); derived from the
            // payload, zero extra queries.
            $completeModules = [];
            foreach (($data['focus_areas'] ?? []) as $area) {
                if (($area['key'] ?? '') !== 'top' && ($area['locked'] ?? true) === false) {
                    $completeModules[] = (string) $area['key'];
                }
            }

            $modules = $data['modules'] ?? [];
            $savings = $modules['savings'] ?? [];
            $retirement = $modules['retirement'] ?? [];
            $protection = $modules['protection'] ?? [];
            $assets = $data['net_worth']['breakdown']['assets'] ?? [];

            return array_merge(
                $milestones,
                $this->milestones->detectGoals($user, $goals),
                // WP-5 — journey milestones (profile complete, first action;
                // WP-5c adds anniversaries + household).
                $this->milestones->detectJourney($user),
                // WP-5c — figures already computed in the aggregated payload.
                $this->milestones->detectPensionPot($user, (float) ($assets['pensions'] ?? 0)),
                $this->milestones->detectEmergencyFund($user, (float) ($savings['emergency_fund_months'] ?? 0)),
                $this->milestones->detectRetirementOnTrack(
                    $user,
                    (float) ($retirement['projected_income'] ?? 0),
                    (float) ($retirement['target_income'] ?? 0),
                ),
                ($protection['status'] ?? '') === 'active'
                    ? $this->milestones->detectProtectionAdequate(
                        $user,
                        (int) ($protection['policy_count'] ?? 0),
                        (int) ($protection['critical_gaps'] ?? 1),
                    )
                    : [],
                $this->milestones->detectModuleProfiles($user, $completeModules),
                // WP-5c — cheap indexed lookups.
                $this->milestones->detectMortgagesPaid($user),
                $this->milestones->detectEstateBasics($user),
                $this->milestones->detectIsaFirst($user),
                // WP-5c-iii — the tax-savings milestone no longer waits for a
                // /tax-strategy visit: the focus-areas build already composed
                // the plan for strategy unlocks (when the tax gate is open),
                // and the scoped memo hands it over for free. Declines (null)
                // when the plan was never composed this request.
                $this->detectTaxSavingsFromMemo($user),
            );
        } catch (\Throwable $e) {
            Log::warning('Milestone detection failed', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
                'at' => $e->getFile().':'.$e->getLine(),
            ]);

            return [];
        }
    }

    /**
     * WP-5c-iii — first-quantified-saving detection from the request-scoped
     * composed-plan memo. Never composes the plan itself.
     *
     * @return array<int,array<string,mixed>>
     */
    private function detectTaxSavingsFromMemo(User $user): array
    {
        $plan = app(ComposedTaxPlanService::class)->forUserIfComputed($user);
        if ($plan === null) {
            return [];
        }

        return $this->milestones->detectTaxSavingsIdentified(
            $user,
            (float) ($plan['combined_annual_saving'] ?? 0),
        );
    }
}
