<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * CoALA Phase 5 item 7 (FR-M9) — resumption check.
 *
 * A turn that ended without finishing (a fatal mid-stream error, an unanswered
 * clarifying question, an undispatched write intent) writes a resumption record
 * here. On the user's next session the chat surfaces it — "last time we didn't
 * finish — continue, or start fresh?" — and the record is cleared either way.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ai_conversations', function (Blueprint $table) {
            $table->json('pending_resumption')->nullable()->after('summarised_at');
        });
    }

    public function down(): void
    {
        Schema::table('ai_conversations', function (Blueprint $table) {
            $table->dropColumn('pending_resumption');
        });
    }
};
