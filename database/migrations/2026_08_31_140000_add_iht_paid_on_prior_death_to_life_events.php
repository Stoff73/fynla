<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * W-0527 — the one figure quick succession relief needs and the app never asked for.
 *
 * IHTA 1984 s141 reduces the tax on a death where the same property was taxed on
 * an earlier death within five years. Its formula multiplies the tax borne on
 * THAT earlier death, and everything else it needs was already here: an
 * `inheritance` life event carries the amount received and the date.
 *
 * Nullable on purpose, and it must stay nullable. Most inheritances bear no tax
 * at all, and "the user has not told us" is a different fact from "no tax was
 * paid" — a NOT NULL DEFAULT 0 would turn silence into an assertion and make a
 * household that qualifies look like one that does not.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('life_events', function (Blueprint $table): void {
            $table->decimal('iht_paid_on_prior_death', 15, 2)
                ->nullable()
                ->after('amount')
                ->comment('IHTA 1984 s141 — Inheritance Tax borne on the earlier death, for quick succession relief. NULL means not stated, which is not the same as zero.');
        });
    }

    public function down(): void
    {
        Schema::table('life_events', function (Blueprint $table): void {
            $table->dropColumn('iht_paid_on_prior_death');
        });
    }
};
