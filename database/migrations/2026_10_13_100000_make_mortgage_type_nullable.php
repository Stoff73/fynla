<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * mortgages.mortgage_type was NOT NULL, so a mortgage the user gave only a
 * balance for was stored as "repayment" (walk 2026-10-11, Q1, N7: Brett's
 * interest-only mortgage read as repayment). Null now means "not recorded".
 * Existing rows keep their value: a stored "repayment" cannot be told apart
 * from a chosen one, so none is rewritten (as R10 did for savings rates).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('mortgages', 'mortgage_type')) {
            return;
        }

        DB::statement("ALTER TABLE `mortgages` MODIFY `mortgage_type` ENUM('repayment','interest_only','mixed') NULL DEFAULT NULL");
    }

    public function down(): void
    {
        DB::statement("UPDATE `mortgages` SET `mortgage_type` = 'repayment' WHERE `mortgage_type` IS NULL");
        DB::statement("ALTER TABLE `mortgages` MODIFY `mortgage_type` ENUM('repayment','interest_only','mixed') NOT NULL");
    }
};
