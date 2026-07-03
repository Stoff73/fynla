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
use App\Services\Mobile\MilestoneDetectionService;
use App\Services\NetWorth\NetWorthService;
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
                    // WP-5b — the milestones the user can achieve next, with
                    // the concrete step for each.
                    'upcoming' => $this->upcomingMilestones($user),
                ],
            ]);
        } catch (\Throwable $e) {
            return $this->errorResponse($e, 'Fetching achievements');
        }
    }

    /**
     * WP-5b — upcoming milestones with the step needed for each. Net worth
     * comes from the day-cached calculation so the "£X away" figure is cheap
     * on repeat reads; a calculation failure degrades to the list without
     * distances rather than breaking the page.
     *
     * @return array<int,array{title: string, steps: string}>
     */
    private function upcomingMilestones(User $user): array
    {
        $netWorth = null;
        try {
            $nw = app(NetWorthService::class)->getCachedNetWorth($user);
            $netWorth = (float) ($nw['net_worth'] ?? 0);
        } catch (\Throwable $e) {
            // best-effort — upcoming still renders without the £-away figure
        }

        return app(MilestoneDetectionService::class)->upcoming($user, $netWorth);
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
        // WP-5 — self-healing: detection used to run only on the /m dashboard
        // read, so a user who never opened it saw an empty milestones page.
        // The journey flavours are cheap; net-worth/goal detection still runs
        // on the dashboard read where the aggregates are already computed.
        try {
            app(MilestoneDetectionService::class)->detectJourney($user);
        } catch (\Throwable $e) {
            // Never let detection break the page.
        }

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

        if ($m->milestone_type === 'campaign') {
            return 'You completed your tax profile.';
        }

        if ($m->milestone_type === 'action') {
            return 'You completed your first action.';
        }

        if ($m->milestone_type === 'tax_savings') {
            return 'We found £'.number_format($threshold).' a year you could save in tax.';
        }

        if ($m->milestone_type === 'goal') {
            $goal = Goal::find($m->reference_id);
            $goalName = $goal?->goal_name ?? 'your goal';

            return $threshold >= 100
                ? sprintf("You've reached your goal: %s.", $goalName)
                : sprintf("You're %d%% of the way to %s.", (int) $threshold, $goalName);
        }

        // WP-5c — the expanded catalogue.
        if ($m->milestone_type === 'pension_pot') {
            return 'Your pension savings have passed £'.number_format($threshold).'.';
        }

        if ($m->milestone_type === 'emergency_fund') {
            return $threshold <= 1
                ? 'Your emergency fund covers a month of your spending.'
                : 'Your emergency fund covers '.(int) $threshold.' months of your spending.';
        }

        if ($m->milestone_type === 'retirement_on_track') {
            return "You're on track for the retirement you've planned.";
        }

        if ($m->milestone_type === 'protection_adequate') {
            return 'Your protection now covers what your family would need.';
        }

        if ($m->milestone_type === 'mortgage_paid') {
            return match ((int) $threshold) {
                25 => "You've paid off a quarter of your mortgage.",
                50 => "You've paid off half your mortgage.",
                75 => "You've paid off three-quarters of your mortgage.",
                default => "You've paid off your mortgage.",
            };
        }

        if ($m->milestone_type === 'will_in_place') {
            return 'Your will is in place.';
        }

        if ($m->milestone_type === 'lpa_in_place') {
            return 'Your Lasting Power of Attorney is in place.';
        }

        if ($m->milestone_type === 'estate_plan_started') {
            return "You've started planning your estate.";
        }

        if ($m->milestone_type === 'isa_first') {
            return "You've opened your first ISA.";
        }

        if ($m->milestone_type === 'isa_used' || $m->milestone_type === 'pension_aa_used') {
            $name = $m->milestone_type === 'isa_used' ? 'your ISA allowance' : 'your pension Annual Allowance';
            $year = (int) $m->reference_id;
            $yearLabel = $year.'/'.substr((string) ($year + 1), -2);

            return ($threshold >= 100 ? "You've used all of " : "You've used half of ").$name.' for '.$yearLabel.'.';
        }

        if ($m->milestone_type === 'module_profile') {
            $module = array_search((int) $m->reference_id, MilestoneDetectionService::MODULE_IDS, true);

            return 'Your '.($module !== false ? $module : 'module').' profile is complete.';
        }

        if ($m->milestone_type === 'anniversary') {
            return $threshold <= 1
                ? 'A year of planning with Fynla.'
                : (int) $threshold.' years of planning with Fynla.';
        }

        if ($m->milestone_type === 'household') {
            return "You've linked your household — planning together.";
        }

        if ($m->milestone_type === 'tax_actioned') {
            return $threshold <= 1
                ? "You've actioned your first tax saving."
                : "Actions you've completed are saving you £".number_format($threshold).' a year in tax.';
        }

        if (str_starts_with($m->milestone_type, 'strategy:')) {
            $family = substr($m->milestone_type, strlen('strategy:'));

            return MilestoneDetectionService::STRATEGY_FAMILY_LABELS[$family] ?? 'You completed a first tax strategy action.';
        }

        return 'Milestone reached';
    }
}
