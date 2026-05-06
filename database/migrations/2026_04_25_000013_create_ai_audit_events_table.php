<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * S0.12 — Hash-chain audit table.
 *
 * Replaces the file-channel `[AI-AUDIT]` log at CoordinatingAgent::executeTool
 * with an append-only, tamper-evident table. Each row carries the SHA-256
 * `row_hash` of the previous row chained with its own serialised payload, so
 * mutating any historical row invalidates every subsequent hash and the
 * `php artisan ai:audit:verify-chain` walker reports the break point.
 *
 * `signature` is HMAC-SHA256 of `row_hash` keyed on `config('app.ai_audit_hmac_key')`,
 * which means a chain dump alone is not enough to forge new rows — an attacker
 * also needs the HMAC key.
 *
 * INV-2.10.2 (hash-chain audit) and INV-2.5.4 (audit trail matches reality).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('ai_audit_events')) {
            return;
        }

        Schema::create('ai_audit_events', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->foreignId('user_id')
                ->constrained('users')
                ->cascadeOnDelete();
            $table->foreignId('conversation_id')
                ->nullable()
                ->constrained('ai_conversations')
                ->nullOnDelete();
            $table->string('tool_name', 64);
            $table->enum('operation', ['read', 'write', 'handoff', 'classify']);
            $table->enum('status', ['dispatched', 'persisted', 'failed', 'stripped']);
            $table->json('input_summary')->nullable();
            $table->json('result_summary')->nullable();
            $table->string('entity_type', 32)->nullable();
            $table->unsignedBigInteger('entity_id')->nullable();
            $table->char('prev_hash', 64);
            $table->char('row_hash', 64);
            $table->timestamp('signed_at');
            $table->char('signature', 64);
            $table->timestamp('created_at')->useCurrent();

            $table->index(['user_id', 'created_at']);
            $table->index(['tool_name', 'status']);
            $table->index('row_hash');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_audit_events');
    }
};
