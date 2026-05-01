<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * M15 — covering index for AiAuditRetentionJob's two retention queries
 * (`WHERE created_at < ? AND operation = 'write' OR tool_name = ...`
 * and `WHERE created_at < ? AND operation != 'write' AND tool_name != ...`).
 *
 * Without this index every retention sweep is a full-table scan once the
 * audit table grows past a few million rows. (operation, created_at) is the
 * right shape because retention partitions on operation first then filters
 * by created_at — the leading column lets the planner narrow each branch
 * before evaluating the date predicate.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('ai_audit_events')) {
            return;
        }

        Schema::table('ai_audit_events', function (Blueprint $table): void {
            $table->index(['operation', 'created_at'], 'ai_audit_events_operation_created_idx');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('ai_audit_events')) {
            return;
        }

        Schema::table('ai_audit_events', function (Blueprint $table): void {
            $table->dropIndex('ai_audit_events_operation_created_idx');
        });
    }
};
