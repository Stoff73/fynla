<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Records whether `users.annual_employment_income` is the pre- or post-sacrifice
 * figure — W-0204.
 *
 * FA 2004 s228ZA(3) adds pay given up under a salary sacrifice arrangement back to
 * threshold income, precisely so sacrifice cannot be used to duck the tapered Annual
 * Allowance. `IncomeDefinitionsService` never applied it, and could not: the field is
 * labelled "Employment Income" with no guidance, so **both readings of the data give a
 * different answer and nothing recorded which one the user meant**.
 *
 * Assuming one moves a user's taper position on a guess, which is why W-0189 shipped the
 * honest interim instead. CSJ decided on 2026-08-28 to ask rather than assume.
 *
 * Nullable, and null means "not asked" rather than a default. Nobody has declared salary
 * sacrifice yet — `dc_pensions.salary_sacrifice` is null on every row — so there is no
 * legacy answer to migrate and no user whose figures move on the day this lands.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement(
            'ALTER TABLE users ADD COLUMN employment_income_basis '.
            "enum('gross','post_sacrifice') NULL DEFAULT NULL ".
            "COMMENT 'W-0204: is annual_employment_income before or after salary sacrifice? NULL = not asked'"
        );
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE users DROP COLUMN employment_income_basis');
    }
};
