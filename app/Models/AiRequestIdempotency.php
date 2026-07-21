<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * S0.11.3 — Per-request idempotency record.
 *
 * Written by IdempotencyKeyMiddleware on first request, looked up on
 * every subsequent request bearing the same Idempotency-Key header.
 * A row is "live" while `expires_at > now()` (24h window). Cleanup is
 * by AiIdempotencyCleanupJob.
 */
class AiRequestIdempotency extends Model
{
    protected $table = 'ai_request_idempotency';

    protected $fillable = [
        'user_id',
        'key_hash',
        'client_key',
        'method',
        'request_uri',
        'body_hash',
        'response_status',
        'response_payload',
        'expires_at',
    ];

    protected $casts = [
        'response_status' => 'integer',
        'response_payload' => 'array',
        'expires_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
