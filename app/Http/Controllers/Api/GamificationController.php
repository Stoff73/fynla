<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\UserGamification;
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

        $pending = null;
        if ($g->pending_celebration_level !== null) {
            $pending = [
                'level' => (int) $g->pending_celebration_level,
                'level_name' => $this->levels->levelName((int) $g->pending_celebration_level),
                'next_actions' => $nextActions,
            ];
        }

        return response()->json([
            'level' => $progress['level'],
            'level_name' => $progress['level_name'],
            'level_label' => $progress['level_label'],
            'progress_percent' => $progress['progress_percent'],
            'next_level_name' => $progress['next_level_name'],
            'next_actions' => $nextActions,
            'pending_celebration' => $pending,
        ]);
    }

    public function ackCelebration(Request $request): JsonResponse
    {
        $user = $request->user();
        UserGamification::where('user_id', $user->id)->update(['pending_celebration_level' => null]);

        return response()->json(['acknowledged' => true]);
    }
}
