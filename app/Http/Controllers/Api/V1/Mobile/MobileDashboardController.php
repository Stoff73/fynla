<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Mobile;

use App\Http\Controllers\Controller;
use App\Http\Traits\SanitizedErrorResponse;
use App\Models\Goal;
use App\Models\User;
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

            return array_merge(
                $milestones,
                $this->milestones->detectGoals($user, $goals),
                // WP-5 — journey milestones (profile complete, first action).
                $this->milestones->detectJourney($user),
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
}
