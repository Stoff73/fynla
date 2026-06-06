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

    /**
     * Award data-entry points: a one-time first-in-category bonus, then a small
     * per-record award capped per category. Idempotent per record id.
     */
    public function awardDataEntry(User $user, string $category, int $recordId): void
    {
        $cfg = config('gamification.points');

        // First-in-category (once ever). The triggering record id is stored in
        // meta so it is never also paid as an extra below.
        $first = $this->award($user, 'data', "data:{$category}:first", (int) $cfg['data_first_in_category'], [
            'category' => $category,
            'record_id' => $recordId,
        ]);

        // If this IS the first-in-category award we just made, don't also pay an extra for it.
        if ($first->awarded) {
            return;
        }

        // Extra records, capped per category.
        if ($user->is_preview_user) {
            return;
        }

        // The record that earned the first-in-category bonus must never also be
        // paid as an extra (it is the same physical record).
        $firstAward = PointAward::where('user_id', $user->id)
            ->where('dedup_key', "data:{$category}:first")
            ->first();
        if ($firstAward && (int) ($firstAward->meta['record_id'] ?? 0) === $recordId) {
            return;
        }

        $cap = (int) $cfg['data_extra_cap_per_category'];
        $extrasSoFar = PointAward::where('user_id', $user->id)
            ->where('source_type', 'data')
            ->where('dedup_key', 'like', "data:{$category}:rec:%")
            ->count();
        if ($extrasSoFar >= $cap) {
            return;
        }

        $this->award($user, 'data', "data:{$category}:rec:{$recordId}", (int) $cfg['data_extra_record'], [
            'category' => $category,
            'record_id' => $recordId,
        ]);
    }
}
