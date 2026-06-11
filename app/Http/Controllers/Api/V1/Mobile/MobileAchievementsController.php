<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Mobile;

use App\Http\Controllers\Controller;
use App\Http\Traits\SanitizedErrorResponse;
use App\Models\Goal;
use App\Models\PointAward;
use App\Models\User;
use App\Models\UserGamification;
use App\Models\UserMilestone;
use App\Services\Gamification\LevelService;
use App\Services\Mobile\NextActionsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MobileAchievementsController extends Controller
{
    use SanitizedErrorResponse;

    public function __construct(
        private readonly NextActionsService $nextActions,
        private readonly LevelService $levels,
    ) {}

    public function index(Request $request): JsonResponse
    {
        try {
            $user = $request->user();

            return response()->json([
                'success' => true,
                'data' => [
                    'achievements' => $this->achievements($user),
                    'next' => $this->nextActions->build($user->id),
                    'milestones' => $this->milestones($user),
                ],
            ]);
        } catch (\Throwable $e) {
            return $this->errorResponse($e, 'Fetching achievements');
        }
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

        $recCount = $awards->where('source_type', 'recommendation')->count();
        $out[] = [
            'key' => 'recs_actioned',
            'title' => 'Actioned '.$recCount.' recommendation'.($recCount === 1 ? '' : 's'),
            'description' => 'Keep acting on recommendations to progress.',
            'earned' => $recCount > 0,
            'earned_at' => null,
        ];

        $streak = $g?->login_streak_days ?? 0;
        $out[] = [
            'key' => 'streak',
            'title' => $streak.'-day check-in streak',
            'description' => 'Log in daily to keep your streak.',
            'earned' => $streak >= 3,
            'earned_at' => null,
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
