<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The composite unique on (user_id, plan_type, action_category, target_account_id)
 * can only serve queries that include user_id at the leftmost position. Reverse
 * lookups — "where is this savings account used as a funding source?" — table-scan
 * without a dedicated index. Surfaced by the 2026-05-23 tech-debt audit (B48).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('plan_action_funding_selections')) {
            return;
        }

        Schema::table('plan_action_funding_selections', function (Blueprint $table) {
            $table->index('funding_source_id', 'pafs_funding_source_id_idx');
            $table->index(['funding_source_type', 'funding_source_id'], 'pafs_funding_source_poly_idx');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('plan_action_funding_selections')) {
            return;
        }

        Schema::table('plan_action_funding_selections', function (Blueprint $table) {
            $table->dropIndex('pafs_funding_source_id_idx');
            $table->dropIndex('pafs_funding_source_poly_idx');
        });
    }
};
