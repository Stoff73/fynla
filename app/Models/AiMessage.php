<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\AiMessageStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiMessage extends Model
{
    use HasFactory;

    protected $fillable = [
        'conversation_id',
        'role',
        'status',
        'content',
        'persona',
        'system_prompt',
        'assembled_context',
        'tool_calls',
        'tool_results',
        'input_tokens',
        'output_tokens',
        'model_used',
        'metadata',
        'procedural_version',
        'semantic_snapshot_id',
        'fetch_provenance',
        'blob_md_path',
        'blob_md_sha256',
    ];

    protected $casts = [
        'status' => AiMessageStatus::class,
        'tool_calls' => 'array',
        'tool_results' => 'array',
        'input_tokens' => 'integer',
        'output_tokens' => 'integer',
        'metadata' => 'array',
        'procedural_version' => 'array',
        'fetch_provenance' => 'array',
    ];

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(AiConversation::class, 'conversation_id');
    }
}
