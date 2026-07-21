<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;

/**
 * Regression test for audit I-01 (May/May12Updates/review-database.md).
 * The audit_logs purge sweep filters by `event_type` and `created_at`.
 * Migration 2026_05_12_000002 adds the composite covering index so
 * MySQL can satisfy both predicates from the index instead of reading
 * every row from the table. This test pins the index so a future
 * "redundant index" cleanup doesn't drop it.
 */
describe('audit_logs has covering index for purge sweep', function () {
    it('has audit_logs_event_type_created_idx on (event_type, created_at)', function () {
        $database = DB::connection()->getDatabaseName();

        $rows = DB::select(
            'SELECT COLUMN_NAME, SEQ_IN_INDEX
             FROM information_schema.STATISTICS
             WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND INDEX_NAME = ?
             ORDER BY SEQ_IN_INDEX',
            [$database, 'audit_logs', 'audit_logs_event_type_created_idx']
        );

        expect($rows)->toHaveCount(2);
        expect($rows[0]->COLUMN_NAME)->toBe('event_type');
        expect($rows[1]->COLUMN_NAME)->toBe('created_at');
    });
});
