<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\UserGamification;
use App\Services\Gamification\ActivityFeedService;
use App\Services\Gamification\LevelService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GamificationController extends Controller
{
    public function __construct(private readonly LevelService $levels) {}

    public function status(Request $request): JsonResponse
    {
        $user = $request->user();
        $g = UserGamification::firstOrCreate(['user_id' => $user->id]);

        $progress = $this->levels->progress((int) $g->total_points);
        $nextActions = $this->levels->nextActions($user);

        // The ladder starts at 1, so a user who has never celebrated anything
        // is owed everything above 1. NEVER coalesce to the current level — a
        // new user who reaches level 2 before acking would then have
        // from == to and never be celebrated at all.
        $from = (int) ($g->celebrated_level ?? 1);
        $to = (int) $progress['level'];

        return response()->json([
            'level' => $progress['level'],
            'level_name' => $progress['level_name'],
            'level_label' => $progress['level_label'],
            'progress_percent' => $progress['progress_percent'],
            'next_level_name' => $progress['next_level_name'],
            'next_actions' => $nextActions,
            'celebrate_from' => min($from, $to),
            'celebrate_to' => $to,
        ]);
    }

    public function ackCelebration(Request $request): JsonResponse
    {
        $user = $request->user();
        $g = UserGamification::firstOrCreate(['user_id' => $user->id]);

        // Clamp against the SAME level status() offers — derived from points,
        // not the stored column. They can drift (csjones user 399: 825 points
        // = level 7, column still said 6), and when they do, an ack clamped to
        // the stale column can never catch up to the range status() keeps
        // offering, so the climb replays on every dashboard view. Found in the
        // browser, 2026-09-17.
        $real = $this->levels->levelForPoints((int) $g->total_points);
        $asked = $request->integer('level') ?: $real;

        // Monotonic and clamped: a double ack, an out-of-order ack, or an ack
        // for a level the user has not reached can never move this backwards
        // or past the truth.
        $g->celebrated_level = min($real, max((int) ($g->celebrated_level ?? 1), $asked));
        $g->save();

        return response()->json([
            'acknowledged' => true,
            'celebrated_level' => (int) $g->celebrated_level,
        ]);
    }

    /**
     * WP-3 — the activity history: everything the user has done (onboarding
     * answers, records added, actions completed, milestones, streaks),
     * newest first, translated from the point_awards ledger. Events and
     * dates only — never point values (Rule #12).
     */
    public function activity(Request $request, ActivityFeedService $feed): JsonResponse
    {
        // WP-5c-ii — cursor pagination: ?before=<ledger id> loads the next
        // page; next_cursor is null once the ledger is exhausted.
        $before = $request->filled('before') ? (int) $request->query('before') : null;
        $page = $feed->feed($request->user(), 50, $before);

        return response()->json([
            'data' => $page['events'],
            'next_cursor' => $page['next_cursor'],
        ]);
    }
}
