<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * `state_pensions.ni_years_required` was `NOT NULL DEFAULT 35`, a typed-in
 * figure (Rule 2): every new State Pension record stored 35 whether or not
 * anyone said so, and the stored 35 would outlive a change in the law.
 * Nullable with no default: a record with no figure reads the qualifying years
 * from tax config (`pension.state_pension.qualifying_years`, through
 * StatePension::ni_years_for_full_pension). Existing rows are left as they are.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('state_pensions', 'ni_years_required')) {
            return;
        }

        DB::statement('ALTER TABLE `state_pensions` MODIFY `ni_years_required` INT NULL DEFAULT NULL');
    }

    public function down(): void
    {
        DB::statement('UPDATE `state_pensions` SET `ni_years_required` = 35 WHERE `ni_years_required` IS NULL');
        DB::statement("ALTER TABLE `state_pensions` MODIFY `ni_years_required` INT NOT NULL DEFAULT '35'");
    }
};
