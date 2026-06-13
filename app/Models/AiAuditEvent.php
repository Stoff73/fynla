<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * S0.12 — A single row in the hash-chained AI audit log.
 *
 * Rows are append-only. Mutating any field after insert breaks the chain
 * and `AuditChainService::verifyChain()` reports the break. See
 * INV-2.10.2 in `April/April24Updates/spec/01-invariants.md`.
 */
class AiAuditEvent extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'conversation_id',
        'tool_name',
        'operation',
        'status',
        'input_summary',
        'result_summary',
        'entity_type',
        'entity_id',
        'prev_hash',
        'row_hash',
        'signed_at',
        'signature',
        'hash_scheme',
        'created_at',
    ];

    protected $casts = [
        'input_summary' => 'array',
        'result_summary' => 'array',
        'entity_id' => 'integer',
        'hash_scheme' => 'integer',
        'signed_at' => 'datetime',
        'created_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(AiConversation::class, 'conversation_id');
    }
}
