<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Data migration: copies existing linked_savings_account_id values from
 * the goals table into the new goal_savings_account pivot table.
 *
 * The linked_savings_account_id column is NOT removed here — it is kept
 * for backwards compatibility until all consumers are fully migrated.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('goal_savings_account')) {
            return;
        }

        if (! Schema::hasColumn('goals', 'linked_savings_account_id')) {
            return;
        }

        // Select all goals that have a linked savings account
        $goals = DB::table('goals')
            ->whereNotNull('linked_savings_account_id')
            ->select('id', 'linked_savings_account_id')
            ->get();

        foreach ($goals as $goal) {
            // Only insert if the pivot row doesn't already exist
            $exists = DB::table('goal_savings_account')
                ->where('goal_id', $goal->id)
                ->where('savings_account_id', $goal->linked_savings_account_id)
                ->exists();

            if (! $exists) {
                DB::table('goal_savings_account')->insert([
                    'goal_id' => $goal->id,
                    'savings_account_id' => $goal->linked_savings_account_id,
                    'allocated_amount' => 0,
                    'is_primary' => true,
                    'priority_rank' => 1,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        // Remove pivot rows that were created from the FK migration.
        // We only remove rows where is_primary = true and priority_rank = 1,
        // as those are the ones this migration created.
        if (! Schema::hasTable('goal_savings_account')) {
            return;
        }

        if (! Schema::hasColumn('goals', 'linked_savings_account_id')) {
            return;
        }

        $goals = DB::table('goals')
            ->whereNotNull('linked_savings_account_id')
            ->select('id', 'linked_savings_account_id')
            ->get();

        foreach ($goals as $goal) {
            DB::table('goal_savings_account')
                ->where('goal_id', $goal->id)
                ->where('savings_account_id', $goal->linked_savings_account_id)
                ->where('is_primary', true)
                ->where('priority_rank', 1)
                ->delete();
        }
    }
};
