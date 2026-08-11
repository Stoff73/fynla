<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Mobile;

use App\Http\Controllers\Controller;
use App\Http\Traits\SanitizedErrorResponse;
use App\Models\Goal;
use App\Models\RecommendationTracking;
use App\Models\User;
use App\Models\UserMilestone;
use App\Services\Mobile\AchievementPresentationService;
use App\Services\Mobile\MilestoneDetectionService;
use App\Services\NetWorth\NetWorthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class MobileAchievementsController extends Controller
{
    use SanitizedErrorResponse;

    /** Canonical v2 earned-milestone page size. */
    private const CANONICAL_MILESTONES_PER_PAGE = 50;

    public function __construct(
        private readonly AchievementPresentationService $presentation,
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
                    // WP-5c-ii — first page only; /completed serves the rest.
                    'completed' => $this->completedActions($user),
                    'completed_total' => $this->completedTotal($user),
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
        $pensionPot = null;
        try {
            $nw = app(NetWorthService::class)->getCachedNetWorth($user);
            $netWorth = (float) ($nw['net_worth'] ?? 0);
            // WP-5c-ii — the same cached payload carries the pension total,
            // so the pension-pot distance is free too.
            $pensionPot = (float) ($nw['breakdown']['pensions'] ?? 0);
        } catch (\Throwable $e) {
            // best-effort — upcoming still renders without the £-away figures
        }

        return app(MilestoneDetectionService::class)->upcoming($user, $netWorth, $pensionPot);
    }

    /** WP-5c-ii — completed actions page size (load-more on /m). */
    private const COMPLETED_PER_PAGE = 25;

    /**
     * WP-5c-ii — a load-more page of completed actions. The index payload
     * carries the first page + the total; this endpoint serves the rest.
     * GET /api/v1/mobile/achievements/completed?page=N
     */
    public function completed(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            $page = max(1, (int) $request->query('page', '1'));

            return response()->json([
                'success' => true,
                'data' => [
                    'completed' => $this->completedActions($user, $page),
                    'completed_total' => $this->completedTotal($user),
                    'page' => $page,
                    'per_page' => self::COMPLETED_PER_PAGE,
                ],
            ]);
        } catch (\Throwable $e) {
            return $this->errorResponse($e, 'Fetching completed actions');
        }
    }

    /**
     * Versioned canonical achievements contract for native clients.
     *
     * Legacy `/mobile/achievements` intentionally continues to return its
     * complete `milestones` collection. Native clients must consume this
     * bounded v2 first page and the continuation endpoint below.
     */
    public function canonical(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            $this->detectJourneySafely($user);
            $milestones = $this->canonicalMilestones($user);

            return response()->json([
                'success' => true,
                'data' => [
                    'achievements' => $this->achievements($user),
                    'completed' => $this->completedActions($user),
                    'completed_total' => $this->completedTotal($user),
                    'milestones' => $milestones['items'],
                    'milestones_total' => $milestones['total'],
                    'per_page' => self::CANONICAL_MILESTONES_PER_PAGE,
                    'next_cursor' => $milestones['next_cursor'],
                    'upcoming' => $this->upcomingMilestones($user),
                ],
            ]);
        } catch (\Throwable $e) {
            return $this->errorResponse($e, 'Fetching canonical achievements');
        }
    }

    /**
     * Canonical earned-milestone continuation endpoint.
     * GET /api/v1/mobile/achievements/v2/milestones?cursor=TOKEN
     */
    public function canonicalMilestonePage(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            $milestones = $this->canonicalMilestones($user, $request->query('cursor'));

            return response()->json([
                'success' => true,
                'data' => [
                    'milestones' => $milestones['items'],
                    'milestones_total' => $milestones['total'],
                    'per_page' => self::CANONICAL_MILESTONES_PER_PAGE,
                    'next_cursor' => $milestones['next_cursor'],
                ],
            ]);
        } catch (\Throwable $e) {
            return $this->errorResponse($e, 'Fetching canonical milestones');
        }
    }

    /**
     * WP-2 — the user's completed actions (recommendation_tracking rows),
     * newest first, in the same lean shape the Next list uses so the /m
     * template renders both with one card style. WP-5c-ii: paginated
     * (25/page) instead of a hard 50-cap — done work is never truncated.
     *
     * @return array<int,array<string,mixed>>
     */
    private function completedActions(User $user, int $page = 1): array
    {
        return RecommendationTracking::where('user_id', $user->id)
            ->completed()
            ->orderByDesc('completed_at')
            ->forPage($page, self::COMPLETED_PER_PAGE)
            ->get()
            ->map(static fn (RecommendationTracking $row): array => [
                'id' => (string) $row->recommendation_id,
                'title' => (string) $row->recommendation_text,
                'module' => (string) ($row->module ?? 'general'),
                'completed_at' => $row->completed_at?->toIso8601String(),
            ])
            ->all();
    }

    private function completedTotal(User $user): int
    {
        return RecommendationTracking::where('user_id', $user->id)->completed()->count();
    }

    /**
     * Earned badges derived from the point_awards ledger + current level.
     *
     * @return array<int,array<string,mixed>>
     */
    private function achievements(User $user): array
    {
        return $this->presentation->badges($user);
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
        $this->detectJourneySafely($user);

        return $this->presentMilestones(UserMilestone::where('user_id', $user->id)
            ->orderByDesc('achieved_at')
            ->orderByDesc('id')
            ->get(), $user);
    }

    private function detectJourneySafely(User $user): void
    {
        try {
            app(MilestoneDetectionService::class)->detectJourney($user);
        } catch (\Throwable $e) {
            // Never let detection break the page.
        }
    }

    /** @return array{items:array<int,array<string,mixed>>,total:int,next_cursor:?string} */
    private function canonicalMilestones(User $user, ?string $cursor = null): array
    {
        $total = UserMilestone::where('user_id', $user->id)->count();
        $query = UserMilestone::where('user_id', $user->id)
            ->orderByDesc('achieved_at')
            ->orderByDesc('id');
        if ($cursor !== null && $cursor !== '') {
            $decoded = $this->decodeMilestoneCursor($cursor);
            if ($decoded !== null) {
                $query->where(function ($rows) use ($decoded): void {
                    $rows->where('achieved_at', '<', $decoded['achieved_at'])
                        ->orWhere(function ($sameTime) use ($decoded): void {
                            $sameTime->where('achieved_at', $decoded['achieved_at'])->where('id', '<', $decoded['id']);
                        });
                });
            }
        }
        $rows = $query->limit(self::CANONICAL_MILESTONES_PER_PAGE + 1)->get();
        $hasMore = $rows->count() > self::CANONICAL_MILESTONES_PER_PAGE;
        $items = $rows->take(self::CANONICAL_MILESTONES_PER_PAGE);
        $last = $items->last();

        return [
            'items' => $this->presentMilestones($items, $user),
            'total' => $total,
            'next_cursor' => $hasMore && $last !== null ? $this->encodeMilestoneCursor($last) : null,
        ];
    }

    private function encodeMilestoneCursor(UserMilestone $milestone): string
    {
        return rtrim(strtr(base64_encode(json_encode([
            'achieved_at' => $milestone->achieved_at?->toIso8601String(),
            'id' => $milestone->id,
        ], JSON_THROW_ON_ERROR)), '+/', '-_'), '=');
    }

    /** @return array{achieved_at:string,id:int}|null */
    private function decodeMilestoneCursor(string $cursor): ?array
    {
        try {
            $padded = strtr($cursor, '-_', '+/').str_repeat('=', (4 - strlen($cursor) % 4) % 4);
            $decoded = json_decode(base64_decode($padded, true) ?: '', true, 512, JSON_THROW_ON_ERROR);

            return is_array($decoded) && is_string($decoded['achieved_at'] ?? null) && is_int($decoded['id'] ?? null)
                ? ['achieved_at' => $decoded['achieved_at'], 'id' => $decoded['id']]
                : null;
        } catch (\JsonException) {
            return null;
        }
    }

    /** @param Collection<int,UserMilestone> $milestones @return array<int,array<string,mixed>> */
    private function presentMilestones(Collection $milestones, User $user): array
    {
        $goalTitles = Goal::forUserOrJoint($user->id)
            ->whereIn('id', $milestones->where('milestone_type', 'goal')->pluck('reference_id')->filter()->unique())
            ->pluck('goal_name', 'id');

        return $milestones
            ->map(fn (UserMilestone $m) => $this->presentation->milestone($m, $this->milestoneTitle($m, $goalTitles)))
            ->all();
    }

    /** @param Collection<int,string> $goalTitles */
    private function milestoneTitle(UserMilestone $m, Collection $goalTitles): string
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
            $goalName = $goalTitles->get($m->reference_id, 'your goal');

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
