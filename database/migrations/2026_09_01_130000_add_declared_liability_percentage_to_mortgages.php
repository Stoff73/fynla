<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * W-0483 — CSJ amended W-0228 on 2026-08-30: "W-0228 can allow mortgage share that
 * is not the same as ownership share."
 *
 * A **new** column rather than believing `mortgages.ownership_percentage`, and the
 * distinction is the whole design. That column is populated on every row, was never
 * reviewed, and is exactly the unread value W-0228 stopped trusting: the persona
 * carries `joint 50%` on a mortgage secured on a `tenants_in_common 40%` property,
 * so reading it as authoritative would move that household's liabilities from
 * £293,000 to £305,000 and break a verified figure.
 *
 * Nullable, with no default, so "nobody has stated a borrowing split" stays
 * distinguishable from "someone stated 50%" — the distinction a NOT NULL DEFAULT
 * would destroy. Null means the property remains authoritative, which is every
 * existing row.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mortgages', function (Blueprint $table) {
            $table->decimal('declared_liability_percentage', 5, 2)
                ->nullable()
                ->after('ownership_percentage');
        });
    }

    public function down(): void
    {
        Schema::table('mortgages', function (Blueprint $table) {
            $table->dropColumn('declared_liability_percentage');
        });
    }
};
