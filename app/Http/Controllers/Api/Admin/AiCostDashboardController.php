<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\AiCostAttribution;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Admin cost-attribution dashboard data (CoALA Phase 5 item 8 — FR-M15).
 *
 * Aggregates the per-action cost ledger so the board / FCA view answers
 * "we spent X on advice, of which Y was reasoning" — spend broken down by
 * action_type, session_mode, procedural_version, and day.
 */
class AiCostDashboardController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $days = (int) $request->integer('days', 30);
        $days = max(1, min($days, 365));
        $since = Carbon::now()->subDays($days)->startOfDay();

        $base = fn () => AiCostAttribution::query()->where('created_at', '>=', $since);

        return response()->json([
            'success' => true,
            'data' => [
                'range_days' => $days,
                'total_gbp' => round((float) $base()->sum('gbp_cost'), 6),
                'total_calls' => $base()->count(),
                'unpriced_calls' => $base()->where('gbp_cost_priced', false)->count(),
                'by_action_type' => $base()
                    ->selectRaw("COALESCE(action_type, 'unknown') as action_type, COUNT(*) as calls, SUM(gbp_cost) as gbp_cost")
                    ->groupBy('action_type')
                    ->orderByDesc('gbp_cost')
                    ->get(),
                'by_session_mode' => $base()
                    ->selectRaw("COALESCE(session_mode, 'unknown') as session_mode, COUNT(*) as calls, SUM(gbp_cost) as gbp_cost")
                    ->groupBy('session_mode')
                    ->orderByDesc('gbp_cost')
                    ->get(),
                'by_stage' => $base()
                    ->selectRaw('stage, COUNT(*) as calls, SUM(gbp_cost) as gbp_cost')
                    ->groupBy('stage')
                    ->orderByDesc('gbp_cost')
                    ->get(),
                'by_day' => $base()
                    ->selectRaw('DATE(created_at) as day, COUNT(*) as calls, SUM(gbp_cost) as gbp_cost')
                    ->groupBy('day')
                    ->orderBy('day')
                    ->get(),
            ],
        ]);
    }
}
