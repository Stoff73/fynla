<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiMessage extends Model
{
    protected $fillable = [
        'conversation_id',
        'role',
        'content',
        'system_prompt',
        'tool_calls',
        'tool_results',
        'input_tokens',
        'output_tokens',
        'model_used',
        'metadata',
    ];

    protected $casts = [
        'tool_calls' => 'array',
        'tool_results' => 'array',
        'input_tokens' => 'integer',
        'output_tokens' => 'integer',
        'metadata' => 'array',
    ];

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(AiConversation::class, 'conversation_id');
    }
}
