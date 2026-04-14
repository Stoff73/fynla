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
}
