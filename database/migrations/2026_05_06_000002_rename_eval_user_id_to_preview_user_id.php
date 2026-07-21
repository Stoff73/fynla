<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * P0.1 (companion) — rename eval_recording_sessions.eval_user_id to
 * preview_user_id. The column stores the actual preview persona user's id
 * (the canonical contract uses preview personas as the eval substrate, not a
 * separate "eval user"); the old name was misleading and made the forensic
 * chain harder to read.
 *
 * Uses raw SQL `ALTER TABLE ... CHANGE COLUMN` for MySQL — preserves the
 * foreign-key constraint without requiring the doctrine/dbal dev dependency
 * for Schema::renameColumn. MySQL 8 auto-updates the FK to point at the
 * renamed column.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('eval_recording_sessions')) {
            return;
        }

        if (Schema::hasColumn('eval_recording_sessions', 'eval_user_id')
            && ! Schema::hasColumn('eval_recording_sessions', 'preview_user_id')) {
            DB::statement('ALTER TABLE eval_recording_sessions CHANGE COLUMN eval_user_id preview_user_id BIGINT UNSIGNED NOT NULL');
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('eval_recording_sessions')) {
            return;
        }

        if (Schema::hasColumn('eval_recording_sessions', 'preview_user_id')
            && ! Schema::hasColumn('eval_recording_sessions', 'eval_user_id')) {
            DB::statement('ALTER TABLE eval_recording_sessions CHANGE COLUMN preview_user_id eval_user_id BIGINT UNSIGNED NOT NULL');
        }
    }
};
