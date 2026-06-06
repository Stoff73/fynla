<?php

declare(strict_types=1);

namespace App\Services\Gamification;

use App\Models\PointAward;
use App\Models\User;
use App\Models\UserGamification;
use App\Traits\StructuredLogging;
use Illuminate\Support\Facades\DB;
use Throwable;

class PointsService
{
    use StructuredLogging;

    public function __construct(
        private readonly LevelService $levels,
        private readonly LevelUpCollector $collector,
    ) {}

    /**
     * Award points idempotently. Returns an AwardResult; never throws — a
     * gamification failure must not break the triggering write.
     */
    public function award(User $user, string $sourceType, string $dedupKey, int $points, array $meta = []): AwardResult
    {
        if ($user->is_preview_user) {
            return AwardResult::noop();
        }
        if ($points <= 0) {
            return AwardResult::noop();
        }

        try {
            return DB::transaction(function () use ($user, $sourceType, $dedupKey, $points, $meta) {
                $award = PointAward::firstOrCreate(
                    ['user_id' => $user->id, 'dedup_key' => $dedupKey],
                    ['source_type' => $sourceType, 'points' => $points, 'meta' => $meta],
                );

                if (! $award->wasRecentlyCreated) {
                    $g = UserGamification::where('user_id', $user->id)->first();
                    $level = $g?->level ?? 1;

                    return new AwardResult(false, 0, false, $level, $this->levels->levelName($level));
                }

                $g = UserGamification::firstOrCreate(['user_id' => $user->id]);
                $oldLevel = $g->level;
                $g->total_points += $points;
                $newLevel = $this->levels->levelForPoints($g->total_points);
                $leveledUp = $newLevel > $oldLevel;

                $g->level = $newLevel;
                if ($leveledUp) {
                    $g->pending_celebration_level = $newLevel;
                }
                $g->save();

                if ($leveledUp) {
                    $this->collector->record($newLevel, $this->levels->levelName($newLevel));
                }

                return new AwardResult(true, $points, $leveledUp, $newLevel, $this->levels->levelName($newLevel));
            });
        } catch (Throwable $e) {
            $this->logError('Gamification award failed', [
                'user_id' => $user->id,
                'dedup_key' => $dedupKey,
                'error' => $e->getMessage(),
            ]);

            return AwardResult::noop();
        }
    }
}
