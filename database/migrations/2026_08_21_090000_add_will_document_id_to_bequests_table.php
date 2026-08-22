<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * W-0023 — marks which bequests the will builder wrote.
 *
 * Completing a will syncs its specific gifts into bequests. That sync has to be
 * able to replace its own rows without disturbing bequests the user created by
 * hand through the Estate bequest API, which keep a NULL will_document_id.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bequests', function (Blueprint $table) {
            $table->foreignId('will_document_id')
                ->nullable()
                ->after('will_id')
                ->constrained('will_documents')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('bequests', function (Blueprint $table) {
            $table->dropForeign(['will_document_id']);
            $table->dropColumn('will_document_id');
        });
    }
};
