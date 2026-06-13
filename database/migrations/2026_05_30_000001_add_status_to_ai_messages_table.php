<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * CoALA Phase 5 item 6 (FR-M7) — concurrent-turn queue.
 *
 * Adds the lifecycle status to ai_messages. A turn sent while another is still
 * streaming is persisted immediately as `queued`; the queue pops it to
 * `processing` when the in-flight turn ends, and it finishes `answered`. Existing
 * rows (and every non-queued turn) are `answered` from creation.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ai_messages', function (Blueprint $table) {
            $table->enum('status', ['queued', 'processing', 'answered', 'cancelled', 'expired'])
                ->default('answered')
                ->after('role');

            // Queue pop reads the oldest live `queued` row per conversation.
            $table->index(['conversation_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::table('ai_messages', function (Blueprint $table) {
            $table->dropIndex(['conversation_id', 'status']);
            $table->dropColumn('status');
        });
    }
};
