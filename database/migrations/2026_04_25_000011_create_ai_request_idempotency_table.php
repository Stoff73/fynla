<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * S0.11.3 — Idempotency dedup for AI chat write endpoints.
 *
 * Holds the canonical (user_id, request URI, method, body hash,
 * supplied key) tuple for every authenticated POST that travels through
 * the IdempotencyKeyMiddleware. A retry within 24h hits the unique
 * `key_hash` index and the middleware short-circuits with a stored
 * acknowledgement instead of re-running the controller.
 *
 * Cleaned up daily by AiIdempotencyCleanupJob (rows > 24h old).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_request_idempotency', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->foreignId('user_id')
                ->constrained('users')
                ->cascadeOnDelete();
            // sha256 of (user_id|method|uri|body_hash|client_key) — 64 hex chars.
            $table->char('key_hash', 64)->unique();
            // The literal Idempotency-Key the client supplied — kept for
            // debugging/audit only, not used for lookup (key_hash is the
            // canonical lookup column).
            $table->string('client_key', 191);
            $table->string('method', 10);
            $table->string('request_uri', 500);
            $table->char('body_hash', 64);
            $table->unsignedSmallInteger('response_status')->default(200);
            // Captured JSON response body for non-streaming endpoints. Null
            // for SSE / streamed responses — middleware returns a generic
            // duplicate-acknowledgement on hit instead of replaying the
            // stream.
            $table->json('response_payload')->nullable();
            $table->timestamp('expires_at')->index();
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_request_idempotency');
    }
};
