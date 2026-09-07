<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Adds 'civil_partnership' to the iht_profiles.marital_status enum — W-0509.
 *
 * `2026_04_15_091500_add_civil_partnership_to_users_marital_status` widened the users
 * column and **missed the table one over**. So a civil partnership could declare their
 * status on their profile and then not save an Inheritance Tax profile at all: the
 * column rejected the value, and `IHTController` rejected it earlier still with a 422.
 * Not a wrong figure — a submission they could not complete.
 *
 * `ComprehensiveEstatePlanService:64` has been building an in-memory `IHTProfile`
 * carrying `$user->marital_status` the whole time, constructing a value this column
 * would have refused the moment anything persisted it.
 *
 * **The new value is appended rather than slotted into the users column's order.** The
 * two columns then hold the same SET, which is what matters, and no existing row is
 * asked to survive an enum re-ordering — MySQL stores an enum by ordinal, and a MODIFY
 * that moves values around is the kind of silent remap nobody finds until the figures
 * are already wrong.
 *
 * Raw ALTER because Doctrine DBAL does not add MySQL ENUM values through the Blueprint
 * API — the same reason the 2026-04-15 migration gives. The column is NOT NULL with no
 * default and stays that way.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement(
            'ALTER TABLE iht_profiles MODIFY COLUMN marital_status '.
            "enum('single','married','widowed','divorced','civil_partnership') NOT NULL"
        );
    }

    public function down(): void
    {
        // A civil partnership is a marriage for every Inheritance Tax purpose the app
        // models (W-0480), so mapping the rows to 'married' before shrinking the enum
        // loses the distinction but not the tax treatment. Without it the ALTER fails
        // on the values it is about to stop allowing.
        DB::statement("UPDATE iht_profiles SET marital_status = 'married' WHERE marital_status = 'civil_partnership'");
        DB::statement(
            'ALTER TABLE iht_profiles MODIFY COLUMN marital_status '.
            "enum('single','married','widowed','divorced') NOT NULL"
        );
    }
};
