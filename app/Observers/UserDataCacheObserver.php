<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\Investment\Holding;
use App\Models\User;
use App\Services\Cache\CacheInvalidationService;
use Illuminate\Database\Eloquent\Model;

/**
 * A financial record changed, so every figure derived from it is stale.
 *
 * The ONE home for that statement (Rule 20). Three observers used to answer it
 * and none of them answered it completely:
 *
 * - `NetWorthCacheObserver` forgot a single date-keyed net worth blob.
 * - `GoalCacheObserver` forgot the goal projections and called
 *   `CacheInvalidationService` — but only for goals.
 * - `RecommendationCacheObserver` instantiated three to six agents per write to
 *   call `invalidateUserCache` on each. The mobile dashboard was only ever
 *   cleared as a **side effect** of that: it always poked `CoordinatingAgent`,
 *   whose override happens to call `CacheInvalidationService`. Three hops of
 *   coincidence stood between a data change and `mobile_dashboard_{id}`, and the
 *   agent's own `investment_analysis_{id}` key was never in any of them.
 *
 * So a 24-hour blob outlived the data it described (W-0239). This observer
 * replaces all three: one call, to the one service, for everyone affected.
 *
 * **Who is affected is three questions, not one.**
 * 1. The recording owner — `user_id`.
 * 2. The co-owner — `joint_owner_id`. A joint asset is a SINGLE record (Rule 6),
 *    so the counterparty's figures move when it changes and their cache must go
 *    with it.
 * 3. **Each of their spouses.** Household figures reach across accounts without
 *    any column on the record saying so: `LifeCoverReach` finds a joint-life
 *    policy through `users.spouse_id` because `life_insurance_policies` has no
 *    `joint_owner_id` at all (W-0186), and `SharedExpenditure` divides a
 *    household's spending the same way (W-0190). Following only the two owner
 *    columns leaves exactly those figures stale on the other login.
 *
 * `User` is deliberately NOT observed here. Its rows are written on every login,
 * token refresh and verification code, and the profile fields that do feed
 * figures already invalidate explicitly in `UserProfileController`. Observing it
 * would clear sixty keys per sign-in to catch a change that is already covered.
 */
class UserDataCacheObserver
{
    public function __construct(
        private readonly CacheInvalidationService $cacheInvalidation
    ) {}

    public function created(Model $model): void
    {
        $this->invalidate($model);
    }

    public function updated(Model $model): void
    {
        $this->invalidate($model);
    }

    public function deleted(Model $model): void
    {
        $this->invalidate($model);
    }

    public function restored(Model $model): void
    {
        $this->invalidate($model);
    }

    private function invalidate(Model $model): void
    {
        foreach ($this->affectedUserIds($model) as $userId) {
            $this->cacheInvalidation->invalidateForUser($userId);
        }
    }

    /**
     * Everyone whose derived figures this record feeds.
     *
     * @return array<int, int>
     */
    private function affectedUserIds(Model $model): array
    {
        // A holding carries no user_id of its own — it hangs off an investment
        // account or a defined contribution pension, and the ownership lives there.
        $record = $model instanceof Holding ? $model->holdable : $model;

        if (! $record instanceof Model) {
            return [];
        }

        $owners = array_filter([
            $record->user_id ?? null,
            $record->joint_owner_id ?? null,
        ]);

        if ($owners === []) {
            return [];
        }

        $spouseIds = User::query()
            ->whereIn('id', $owners)
            ->pluck('spouse_id')
            ->filter()
            ->all();

        return array_values(array_unique(array_map(
            static fn ($id): int => (int) $id,
            array_merge($owners, $spouseIds)
        )));
    }
}
