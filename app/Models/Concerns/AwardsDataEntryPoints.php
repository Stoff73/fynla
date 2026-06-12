<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use App\Models\User;
use App\Services\Gamification\PointsService;

/**
 * Awards data-entry gamification points when a record is created.
 * The consuming model must define `gamificationCategory(): string`.
 */
trait AwardsDataEntryPoints
{
    public static function bootAwardsDataEntryPoints(): void
    {
        static::created(function ($model): void {
            if (empty($model->user_id)) {
                return;
            }
            $user = $model->user ?? User::find($model->user_id);
            if (! $user) {
                return;
            }
            app(PointsService::class)->awardDataEntry($user, $model->gamificationCategory(), (int) $model->getKey());
        });
    }
}
