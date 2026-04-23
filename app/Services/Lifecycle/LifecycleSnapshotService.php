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

    /**
     * Build a personalisation context array for the lifecycle email templates.
     *
     * Returns the user's first name, a completion percentage (modules started
     * out of the total 6), the modules that have data, and a list of modules
     * remaining. Used by the engaged trialer campaign to personalise the
     * welcome email copy.
     *
     * @return array{
     *     first_name: string|null,
     *     completion_pct: int,
     *     modules_with_data: array<int, array{name: string, count: int, label: string}>,
     *     modules_remaining: array<int, string>,
     *     days_since_signup: int,
     * }
     */
    public function buildContext(User $user): array
    {
        $modules = [
            ['name' => 'Properties', 'table' => 'properties', 'label' => 'properties'],
            ['name' => 'Pensions', 'table' => 'dc_pensions', 'label' => 'pension'],
            ['name' => 'Savings', 'table' => 'savings_accounts', 'label' => 'savings accounts'],
            ['name' => 'Investments', 'table' => 'investment_accounts', 'label' => 'investment accounts'],
            ['name' => 'Protection', 'table' => 'life_insurance_policies', 'label' => 'protection policies'],
            ['name' => 'Goals', 'table' => 'goals', 'label' => 'goals'],
        ];

        $modulesWithData = [];
        $modulesRemaining = [];

        foreach ($modules as $module) {
            $count = DB::table($module['table'])->where('user_id', $user->id)->count();
            if ($count > 0) {
                $modulesWithData[] = [
                    'name' => $module['name'],
                    'count' => $count,
                    'label' => $module['label'],
                ];
            } else {
                $modulesRemaining[] = $module['name'];
            }
        }

        $totalModules = count($modules);
        $modulesStarted = count($modulesWithData);
        $completionPct = $totalModules > 0 ? (int) round(($modulesStarted / $totalModules) * 100) : 0;

        return [
            'first_name' => $user->first_name,
            'completion_pct' => $completionPct,
            'modules_with_data' => $modulesWithData,
            'modules_remaining' => $modulesRemaining,
            'days_since_signup' => (int) $user->created_at?->diffInDays(now()),
        ];
    }
}
