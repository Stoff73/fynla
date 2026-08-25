<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * W-0022 — records which letter sections Fynla still owns.
 *
 * The letter's sections were auto-populated once, at row creation, and never
 * refreshed. A user who opened the page before adding any financial data got
 * "No outstanding liabilities recorded." frozen into a document written for a
 * grieving partner, while a mortgage existed.
 *
 * Sections listed here are recomputed from live data on every read. A section
 * leaves the list the moment the user edits it, and never returns — their words
 * are never overwritten.
 *
 * NULL means a row that predates this column. Those are treated conservatively
 * on read: only sections still holding a generator sentinel or nothing at all
 * are adopted, so no text a user may have typed is touched.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('letters_to_spouse', function (Blueprint $table) {
            $table->json('auto_populated_fields')->nullable()->after('user_id');
        });
    }

    public function down(): void
    {
        Schema::table('letters_to_spouse', function (Blueprint $table) {
            $table->dropColumn('auto_populated_fields');
        });
    }
};
