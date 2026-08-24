<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * W-0032 — give `scheme_status` the column it never had.
 *
 * Both Defined Benefit pension forms have always asked whether a scheme is
 * Active, Deferred or In Payment, and the answer was thrown away on every save
 * because no column existed to hold it. Fyn's `create_pension` tool schema asks
 * for it too and its answer went the same way.
 *
 * It is not a cosmetic field. `DBPension::isInPayment()` decides whether an
 * accrued pension counts as income today, and without this column it can only
 * compare the user's age against the scheme's Normal Retirement Age (W-0036).
 * That heuristic is wrong in both directions for cases common in Fynla's
 * audience — someone drawing a pension at 57 against a scheme age of 60 has real
 * income counted as zero, and someone at 62 who has deferred a scheme age of 60
 * has no income counted in full. A stated status settles it.
 *
 * NULLABLE ON PURPOSE, and deliberately not backfilled. Every row that predates
 * this column has an unknown status, and guessing one would be inventing the
 * fact the column exists to record. Null keeps the age heuristic as the
 * fallback, so behaviour for existing rows is exactly what W-0036 landed.
 *
 * Values are stored lower snake_case — `active`, `deferred`, `in_payment` —
 * matching every other enum on this table (`scheme_type`, `inflation_protection`)
 * and on `investment_accounts.scheme_status`. The title-case forms the web forms
 * and Fyn's tool schema use are display labels; `PensionNormaliser` maps them.
 * `DBPension::SCHEME_STATUSES` is the one declaration of the vocabulary.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('db_pensions', function (Blueprint $table) {
            $table->string('scheme_status', 20)
                ->nullable()
                ->after('scheme_type')
                ->comment('active|deferred|in_payment. NULL = unknown (row predates the column); DBPension::isInPayment() then falls back to age vs normal_retirement_age.');
        });
    }

    public function down(): void
    {
        Schema::table('db_pensions', function (Blueprint $table) {
            $table->dropColumn('scheme_status');
        });
    }
};
