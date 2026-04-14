<?php

declare(strict_types=1);

namespace App\Services\Lifecycle;

use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class LifecycleSnapshotService
{
    private const MODULE_TABLES = [
        'properties',
        'dc_pensions',
        'savings_accounts',
        'investment_accounts',
        'life_insurance_policies',
        'goals',
    ];

    public function isEmpty(User $user): bool
    {
        foreach (self::MODULE_TABLES as $table) {
            if (DB::table($table)->where('user_id', $user->id)->exists()) {
                return false;
            }
        }

        return true;
    }

    /**
     * Return the subset of the given user IDs that have data in any module table.
     *
     * Uses a UNION across the 6 module tables — faster than N individual exists() checks
     * when the candidate set is large (eg. batch eligibility filtering in the engine).
     *
     * @param  array<int>  $userIds
     * @return Collection<int, int>
     */
    public function findUserIdsWithData(array $userIds): Collection
    {
        if (empty($userIds)) {
            return collect();
        }

        $query = DB::table('properties')->whereIn('user_id', $userIds)->select('user_id');

        foreach (['dc_pensions', 'savings_accounts', 'investment_accounts', 'life_insurance_policies', 'goals'] as $table) {
            $query->union(DB::table($table)->whereIn('user_id', $userIds)->select('user_id'));
        }

        return $query->pluck('user_id')->unique()->values();
    }
}
