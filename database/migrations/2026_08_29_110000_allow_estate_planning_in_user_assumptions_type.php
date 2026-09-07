<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * W-0520 — let an estate planning assumption be SAVED at all.
 *
 * `2026_02_03_100002` added the three estate columns and, in the same migration, an
 * `ALTER TABLE ... MODIFY COLUMN assumption_type ENUM('pensions','investments',
 * 'estate_planning')` as a raw `DB::statement`. The columns landed everywhere; the enum
 * change landed nowhere. `database/schema/mysql-schema.sql:3864` carries
 * `enum('pensions','investments')` with the three estate columns beside it, and every
 * database on this machine — the dev `laravel` database included — matches the dump rather
 * than the migration.
 *
 * The consequence is that `AssumptionsService::updateEstateAssumptions()` cannot write its
 * row: `assumption_type = 'estate_planning'` is not a member of the enum, so the insert is
 * rejected. A user who sets a property growth rate or a custom investment rate in
 * Settings → Assumptions has never been able to save one.
 *
 * Re-stated as its own migration because the original is marked as run, and because a
 * squashed schema load applies the dump and then only the migrations that came after it.
 * This one comes after.
 *
 * `ALTER TABLE` on an enum that only GAINS a member preserves every existing row.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement(
            "ALTER TABLE user_assumptions
             MODIFY COLUMN assumption_type ENUM('pensions', 'investments', 'estate_planning') NOT NULL"
        );
    }

    public function down(): void
    {
        DB::table('user_assumptions')->where('assumption_type', 'estate_planning')->delete();

        DB::statement(
            "ALTER TABLE user_assumptions
             MODIFY COLUMN assumption_type ENUM('pensions', 'investments') NOT NULL"
        );
    }
};
