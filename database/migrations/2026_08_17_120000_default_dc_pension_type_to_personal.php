<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * BUG-02 (CSJ 2026-08-17) — the default scheme type is ALWAYS a personal pension.
 *
 * `pension_type` is NOT NULL, so a capture that does not yet know the scheme type
 * must write something. The column defaulted to 'occupational', so an unstated type
 * silently became a workplace pension: the user said "Sip" and the app recorded an
 * Aviva workplace pension. `PensionNormaliser` was corrected to default to
 * 'personal', but the database default sat below it and still said workplace for any
 * write that bypasses the normaliser.
 *
 * Column default only. Existing rows are left exactly as they are — re-classifying
 * live pensions would be an unrequested data change, and a wrong one for anyone
 * genuinely holding a workplace pension.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement(
            'ALTER TABLE dc_pensions MODIFY COLUMN pension_type '
            ."ENUM('occupational', 'sipp', 'personal', 'stakeholder') NOT NULL DEFAULT 'personal'"
        );
    }

    public function down(): void
    {
        DB::statement(
            'ALTER TABLE dc_pensions MODIFY COLUMN pension_type '
            ."ENUM('occupational', 'sipp', 'personal', 'stakeholder') NOT NULL DEFAULT 'occupational'"
        );
    }
};
