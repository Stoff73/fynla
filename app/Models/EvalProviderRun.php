<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One row per (eval_recording_session, provider, model) — the structured
 * forensic record of a single chat turn captured for eval. Pairs with a
 * JSONL fixture file at {fixture_path} for raw SSE replay.
 */
final class EvalProviderRun extends Model
{
    use HasFactory;

    protected $fillable = [
        'eval_recording_session_id',
        'provider',
        'model',
        'conversation_id',
        'user_message',
        'assistant_text',
        'tool_calls',
        'sse_event_count',
        'sse_event_types',
        'forbidden_hits',
        'db_writes_made',
        'end_state_snapshot',
        'fixture_path',
        'duration_ms',
        'started_at',
        'completed_at',
    ];

    protected $casts = [
        'tool_calls' => 'array',
        'sse_event_types' => 'array',
        'forbidden_hits' => 'array',
        'db_writes_made' => 'array',
        'end_state_snapshot' => 'array',
        'sse_event_count' => 'integer',
        'duration_ms' => 'integer',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function session(): BelongsTo
    {
        return $this->belongsTo(EvalRecordingSession::class, 'eval_recording_session_id');
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(AiConversation::class, 'conversation_id');
    }
}
