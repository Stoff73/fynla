<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Mobile;

use App\Http\Controllers\Controller;
use App\Http\Traits\SanitizedErrorResponse;
use App\Models\Goal;
use App\Models\PointAward;
use App\Models\RecommendationTracking;
use App\Models\User;
use App\Models\UserGamification;
use App\Models\UserMilestone;
use App\Services\Gamification\LevelService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MobileAchievementsController extends Controller
{
    use SanitizedErrorResponse;

    public function __construct(
        private readonly LevelService $levels,
    ) {}

    public function index(Request $request): JsonResponse
    {
        try {
            $user = $request->user();

            // WP-4 — the old `next` list (the dashboard's top-4 repeated) is
            // gone: actions live on the dashboard; this page is what the user
            // has DONE and earned. Dropping it also saves a full
            // recommendations aggregation per page load.
            return response()->json([
                'success' => true,
                'data' => [
                    'achievements' => $this->achievements($user),
                    // WP-2 — completed actions were saved (recommendation_tracking)
                    // but shown nowhere; surface them so done work has a home.
                    'completed' => $this->completedActions($user),
                    'milestones' => $this->milestones($user),
                ],
            ]);
        } catch (\Throwable $e) {
            return $this->errorResponse($e, 'Fetching achievements');
        }
    }

    /**
     * WP-2 — the user's completed actions (recommendation_tracking rows),
     * newest first, in the same lean shape the Next list uses so the /m
     * template renders both with one card style.
     *
     * @return array<int,array<string,mixed>>
     */
    private function completedActions(User $user): array
    {
        return RecommendationTracking::where('user_id', $user->id)
            ->completed()
            ->orderByDesc('completed_at')
            ->limit(50)
            ->get()
            ->map(static fn (RecommendationTracking $row): array => [
                'id' => (string) $row->recommendation_id,
                'title' => (string) $row->recommendation_text,
                'module' => (string) ($row->module ?? 'general'),
                'completed_at' => $row->completed_at?->toIso8601String(),
            ])
            ->all();
    }

    /**
     * Earned badges derived from the point_awards ledger + current level.
     *
     * @return array<int,array<string,mixed>>
     */
    private function achievements(User $user): array
    {
        $g = UserGamification::where('user_id', $user->id)->first();
        $level = $g?->level ?? 1;
        $awards = PointAward::where('user_id', $user->id)->get();

        $out = [];

        $out[] = [
            'key' => 'level',
            'title' => 'Reached '.$this->levels->levelName($level),
            'description' => 'Your current planning level.',
            'earned' => $level > 1,
            'earned_at' => null,
        ];

        $dataBadges = [
            'protection_policy' => 'protection',
            'savings_account' => 'savings',
            'investment_account' => 'investment',
            'pension' => 'retirement',
            'estate' => 'estate',
            'goal' => 'goals',
        ];
        foreach ($dataBadges as $category => $label) {
            $award = $awards->first(fn ($a) => $a->dedup_key === "data:{$category}:first");
            $out[] = [
                'key' => 'data_'.$category,
                'title' => 'Added '.$label.' details',
                'description' => 'You started building your '.$label.' picture.',
                'earned' => $award !== null,
                'earned_at' => $award?->created_at?->toIso8601String(),
            ];
        }

        // WP-4 — a badge is a fixed goal, not a live counter. "Actioned 0
        // recommendations" read as broken; the badge is now "First action
        // completed", stamped with the first recommendation award's date.
        $firstRecAward = $awards
            ->where('source_type', 'recommendation')
            ->sortBy('id')
            ->first();
        $out[] = [
            'key' => 'recs_actioned',
            'title' => 'First action completed',
            'description' => 'You completed your first recommended action.',
            'earned' => $firstRecAward !== null,
            'earned_at' => $firstRecAward?->created_at?->toIso8601String(),
        ];

        // WP-4 — earned off the PERSISTED streak award, not the live counter:
        // login_streak_days resets when a run breaks, which un-earned the
        // badge, and "1-day check-in streak — Not yet earned" read as broken.
        $streakAward = $awards
            ->filter(fn ($a) => str_starts_with($a->dedup_key, 'streak:'))
            ->sortBy('id')
            ->first();
        $out[] = [
            'key' => 'streak',
            'title' => '3-day check-in streak',
            'description' => 'Check in three days in a row.',
            'earned' => $streakAward !== null,
            'earned_at' => $streakAward?->created_at?->toIso8601String(),
        ];

        return $out;
    }

    /**
     * Financial milestones the user has crossed, from user_milestones. Labels
     * are derived to mirror MilestoneDetectionService (no label column exists).
     *
     * @return array<int,array<string,mixed>>
     */
    private function milestones(User $user): array
    {
        return UserMilestone::where('user_id', $user->id)
            ->orderByDesc('achieved_at')
            ->get()
            ->map(fn (UserMilestone $m) => [
                'key' => $m->milestone_type.':'.($m->reference_id ?? 0).':'.(int) $m->threshold,
                'title' => $this->milestoneTitle($m),
                'achieved' => true,
                'achieved_at' => $m->achieved_at?->toIso8601String(),
            ])
            ->all();
    }

    private function milestoneTitle(UserMilestone $m): string
    {
        $threshold = (float) $m->threshold;

        if ($m->milestone_type === 'net_worth') {
            return 'Your net worth has passed £'.number_format($threshold).'.';
        }

        if ($m->milestone_type === 'goal') {
            $goal = Goal::find($m->reference_id);
            $goalName = $goal?->goal_name ?? 'your goal';

            return $threshold >= 100
                ? sprintf("You've reached your goal: %s.", $goalName)
                : sprintf("You're %d%% of the way to %s.", (int) $threshold, $goalName);
        }

        return 'Milestone reached';
    }
}
