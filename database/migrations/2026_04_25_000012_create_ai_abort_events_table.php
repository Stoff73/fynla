<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * S0.11.2 — Audit row written when an SSE chat stream aborts mid-flight.
 *
 * Holds a forensic record of every detected disconnect so we can later
 * (a) reconcile partial writes, (b) detect users who repeatedly drop
 * mid-tool-call, and (c) drive the eventual reconnect-and-resume UX.
 *
 * Per INV-2.9.2 partial writes are NOT rolled back — each direct-write
 * tool call lands inside its own DB::transaction and any work it
 * persisted before the abort is real and stays. The row here records
 * what the loop was about to do next so an operator can manually
 * reconcile if a user complains.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_abort_events', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->foreignId('conversation_id')
                ->constrained('ai_conversations')
                ->cascadeOnDelete();
            $table->foreignId('user_id')
                ->constrained('users')
                ->cascadeOnDelete();
            // The last tool name the loop dispatched before the abort
            // was detected. Null when the abort was caught before any
            // tool ran (e.g. on the very first stream chunk).
            $table->string('last_tool_call', 100)->nullable();
            // Count of completed tool calls in this generator pass. This
            // is the "how many DB writes already happened" estimate the
            // operator uses for reconciliation.
            $table->unsignedSmallInteger('partial_write_count')->default(0);
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_abort_events');
    }
};
