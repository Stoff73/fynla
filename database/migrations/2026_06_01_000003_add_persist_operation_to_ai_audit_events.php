<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * CoALA Phase 2 — extend the `operation` enum with `persist` so the per-turn
 * `__episode__` attestation row (hash_scheme = 2) can record
 * `operation = 'persist'` as the approved spec §4 requires. The v2 episode
 * row is one `ai_audit_events` row written by
 * `AuditChainService::appendEpisode()`.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement(
            'ALTER TABLE `ai_audit_events` MODIFY `operation` '
            ."ENUM('read', 'write', 'handoff', 'classify', 'persist') NOT NULL"
        );
    }

    public function down(): void
    {
        DB::statement(
            'ALTER TABLE `ai_audit_events` MODIFY `operation` '
            ."ENUM('read', 'write', 'handoff', 'classify') NOT NULL"
        );
    }
};
