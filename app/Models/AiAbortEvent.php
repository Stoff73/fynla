<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * S0.11.2 — Forensic record of an SSE chat stream that aborted mid-flight.
 *
 * Written by HasAiChat::recordAbort when connection_aborted() trips
 * inside the chat() generator loop. Partial writes from the
 * already-completed tool calls in the same generator pass are
 * deliberately NOT rolled back (per INV-2.9.2) — the row here records
 * what the loop was about to do next so an operator can reconcile.
 */
class AiAbortEvent extends Model
{
    protected $table = 'ai_abort_events';

    protected $fillable = [
        'conversation_id',
        'user_id',
        'last_tool_call',
        'partial_write_count',
    ];

    protected $casts = [
        'partial_write_count' => 'integer',
    ];

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(AiConversation::class, 'conversation_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
