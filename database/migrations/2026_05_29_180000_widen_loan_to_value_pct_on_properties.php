<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Widen properties.loan_to_value_pct from DECIMAL(5,2) (max 999.99) to
 * DECIMAL(7,2) (max 99999.99). LTV is a percentage that can legitimately
 * exceed 100% (negative equity), and a single mis-entered mortgage balance
 * on a low-value property — or genuinely deep negative equity — produced an
 * LTV over 999.99% that overflowed the column and threw SQLSTATE 22003 on the
 * derived-column write (PropertyStore::recalculateDerived). The write must not
 * crash on extreme-but-valid data.
 *
 * doctrine/dbal is not installed, so ->change() is unavailable; raw MySQL
 * MODIFY is used (prod, csjones, and the test DB are all MySQL).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('properties', 'loan_to_value_pct')) {
            return;
        }

        DB::statement('ALTER TABLE `properties` MODIFY `loan_to_value_pct` DECIMAL(7,2) NULL');
    }

    public function down(): void
    {
        if (! Schema::hasColumn('properties', 'loan_to_value_pct')) {
            return;
        }

        DB::statement('ALTER TABLE `properties` MODIFY `loan_to_value_pct` DECIMAL(5,2) NULL');
    }
};
