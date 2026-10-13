<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Cash and Junior ISAs added through Fyn were saved with no isa_type, so the
 * account page showed "ISA Type:" blank (regression walk 2026-10-09, R32).
 * SavingsStore now fills it from the account type; this fills the rows saved
 * before. The two calculations that read isa_type (ISATracker) already fall
 * back to the account type for these, so no figure moves.
 */
return new class extends Migration
{
    private const TYPES = ['cash_isa' => 'cash', 'junior_isa' => 'junior'];

    public function up(): void
    {
        if (! Schema::hasColumn('savings_accounts', 'isa_type')) {
            return;
        }

        foreach (self::TYPES as $accountType => $isaType) {
            $count = DB::table('savings_accounts')
                ->where('account_type', $accountType)
                ->where(fn ($q) => $q->whereNull('isa_type')->orWhere('isa_type', ''))
                ->update(['isa_type' => $isaType]);
            Log::info('[R32] filled savings_accounts.isa_type', ['account_type' => $accountType, 'isa_type' => $isaType, 'rows' => $count]);
        }
    }

    public function down(): void
    {
        // The filled values are what the account type has always implied;
        // there is nothing to put back.
    }
};
