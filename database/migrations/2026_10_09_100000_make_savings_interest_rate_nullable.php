<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * savings_accounts.interest_rate was NOT NULL DEFAULT 0.0000, so a rate the
 * user never gave (the onboarding Cash ISA and current account rates are
 * optional) was stored, and shown, as 0% (regression walk 2026-10-09, R10).
 * Null now means "not recorded". Existing rows keep their value: a stored 0
 * cannot be told apart from a typed 0, so none is rewritten.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('savings_accounts', 'interest_rate')) {
            return;
        }

        DB::statement('ALTER TABLE `savings_accounts` MODIFY `interest_rate` DECIMAL(8,4) NULL DEFAULT NULL');
    }

    public function down(): void
    {
        DB::statement('UPDATE `savings_accounts` SET `interest_rate` = 0 WHERE `interest_rate` IS NULL');
        DB::statement('ALTER TABLE `savings_accounts` MODIFY `interest_rate` DECIMAL(8,4) NOT NULL DEFAULT 0.0000');
    }
};
