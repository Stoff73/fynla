<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Add indexes on joint_owner_id columns for improved query performance.
 *
 * Joint ownership queries frequently filter by both user_id and joint_owner_id:
 * WHERE user_id = ? OR joint_owner_id = ?
 *
 * Without indexes on joint_owner_id, these queries perform full table scans
 * for the OR condition.
 */
return new class extends Migration
{
    /**
     * Tables with joint_owner_id that need indexes.
     */
    private array $tables = [
        'properties',
        'savings_accounts',
        'investment_accounts',
        'mortgages',
        'chattels',
        'business_interests',
        'liabilities',
    ];

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        foreach ($this->tables as $table) {
            if (Schema::hasTable($table) && Schema::hasColumn($table, 'joint_owner_id')) {
                // Check if index already exists
                $indexName = "{$table}_joint_owner_id_index";
                if (! $this->indexExists($table, $indexName)) {
                    Schema::table($table, function (Blueprint $table) {
                        $table->index('joint_owner_id');
                    });
                }
            }
        }

        // Add composite index for common query patterns on goals
        if (Schema::hasTable('goals') && Schema::hasColumn('goals', 'joint_owner_id')) {
            $indexName = 'goals_joint_owner_id_status_index';
            if (! $this->indexExists('goals', $indexName)) {
                Schema::table('goals', function (Blueprint $table) {
                    $table->index(['joint_owner_id', 'status'], 'goals_joint_owner_id_status_index');
                });
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        foreach ($this->tables as $tableName) {
            if (Schema::hasTable($tableName)) {
                Schema::table($tableName, function (Blueprint $table) use ($tableName) {
                    $indexName = "{$tableName}_joint_owner_id_index";
                    if ($this->indexExists($tableName, $indexName)) {
                        $table->dropIndex($indexName);
                    }
                });
            }
        }

        if (Schema::hasTable('goals')) {
            Schema::table('goals', function (Blueprint $table) {
                if ($this->indexExists('goals', 'goals_joint_owner_id_status_index')) {
                    $table->dropIndex('goals_joint_owner_id_status_index');
                }
            });
        }
    }

    /**
     * Check if an index exists on a table.
     */
    private function indexExists(string $table, string $indexName): bool
    {
        $indexes = DB::select("SHOW INDEX FROM {$table} WHERE Key_name = ?", [$indexName]);

        return count($indexes) > 0;
    }
};
