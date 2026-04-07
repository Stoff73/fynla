<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Add FK on bequests.asset_id
        if (Schema::hasTable('bequests') && Schema::hasColumn('bequests', 'asset_id')) {
            try {
                Schema::table('bequests', function (Blueprint $table) {
                    $table->foreign('asset_id')->references('id')->on('assets')->nullOnDelete();
                });
            } catch (\Exception $e) {
                // FK already exists
            }
        }

        // Add missing indexes
        $indexes = [
            ['bequests', 'asset_id', 'idx_bequests_asset_id'],
            ['life_event_allocations', 'account_id', 'idx_life_event_allocations_account_id'],
            ['plan_action_funding_selections', 'funding_source_id', 'idx_plan_action_funding_selections_funding_source_id'],
        ];

        foreach ($indexes as [$table, $column, $indexName]) {
            if (Schema::hasTable($table) && Schema::hasColumn($table, $column)) {
                try {
                    Schema::table($table, function (Blueprint $blueprint) use ($column, $indexName) {
                        $blueprint->index($column, $indexName);
                    });
                } catch (\Exception $e) {
                    // Index already exists
                }
            }
        }

        // Drop duplicate indexes
        $duplicates = [
            ['monte_carlo_cache', 'monte_carlo_cache_cache_key_index'],
            ['occupation_codes', 'occupation_codes_title_index'],
            ['protection_profiles', 'protection_profiles_user_id_index'],
            ['spouse_permissions', 'spouse_permissions_user_id_spouse_id_unique'],
            ['tax_configurations', 'tax_configurations_tax_year_index'],
        ];

        foreach ($duplicates as [$table, $indexName]) {
            if (Schema::hasTable($table)) {
                try {
                    Schema::table($table, function (Blueprint $blueprint) use ($indexName) {
                        $blueprint->dropIndex($indexName);
                    });
                } catch (\Exception $e) {
                    // Index doesn't exist
                }
            }
        }
    }

    public function down(): void
    {
        // Remove FK on bequests.asset_id
        if (Schema::hasTable('bequests')) {
            try {
                Schema::table('bequests', function (Blueprint $table) {
                    $table->dropForeign(['asset_id']);
                });
            } catch (\Exception $e) {
            }
        }

        // Remove added indexes
        $indexes = [
            ['bequests', 'idx_bequests_asset_id'],
            ['life_event_allocations', 'idx_life_event_allocations_account_id'],
            ['plan_action_funding_selections', 'idx_plan_action_funding_selections_funding_source_id'],
        ];

        foreach ($indexes as [$table, $indexName]) {
            if (Schema::hasTable($table)) {
                try {
                    Schema::table($table, function (Blueprint $blueprint) use ($indexName) {
                        $blueprint->dropIndex($indexName);
                    });
                } catch (\Exception $e) {
                }
            }
        }
    }
};
