<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Add a covering composite index for the audit_logs purge sweep.
 *
 * `app/Console/Commands/PurgeAuditLogs.php` runs:
 *
 *     AuditLog::where('created_at', '<', $cutoff)
 *         ->where('event_type', '!=', AuditLog::EVENT_GDPR);
 *
 * The existing single-column `audit_logs_created_at_index` filters by
 * cutoff, then reads every row's `event_type` from the table — wasted
 * I/O on a table that grows monotonically. A composite (event_type,
 * created_at) lets MySQL satisfy both predicates from the index.
 *
 * Same shape as `ai_audit_events_operation_created_idx` added by
 * 2026_05_06_000003 for the equivalent AI-audit purge query.
 *
 * Audit reference: May/May12Updates/review-database.md §I-01.
 * Expected speedup: 10-100x on tables >10M rows.
 * Write cost: ~5% overhead on insert, +~1.5GB at 100M rows.
 */
return new class extends Migration
{
    private const INDEX_NAME = 'audit_logs_event_type_created_idx';

    public function up(): void
    {
        if (! Schema::hasTable('audit_logs')) {
            return;
        }

        // Guard against re-application.
        $exists = collect(\DB::select(
            "SELECT 1 FROM information_schema.STATISTICS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = 'audit_logs'
               AND INDEX_NAME = ?",
            [self::INDEX_NAME]
        ))->isNotEmpty();

        if ($exists) {
            return;
        }

        Schema::table('audit_logs', function (Blueprint $table) {
            $table->index(['event_type', 'created_at'], self::INDEX_NAME);
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('audit_logs')) {
            return;
        }

        $exists = collect(\DB::select(
            "SELECT 1 FROM information_schema.STATISTICS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = 'audit_logs'
               AND INDEX_NAME = ?",
            [self::INDEX_NAME]
        ))->isNotEmpty();

        if (! $exists) {
            return;
        }

        Schema::table('audit_logs', function (Blueprint $table) {
            $table->dropIndex(self::INDEX_NAME);
        });
    }
};
