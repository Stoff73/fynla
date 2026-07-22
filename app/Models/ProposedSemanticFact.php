<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProposedSemanticFact extends Model
{
    protected $fillable = [
        'user_id', 'derived_from_conversation_id', 'derived_from_episode_id',
        'category', 'fact_id', 'title', 'body', 'valid_from', 'valid_to',
        'status', 'reviewed_by', 'reviewed_at',
    ];

    protected $casts = [
        'valid_from' => 'date',
        'valid_to' => 'date',
        'reviewed_at' => 'datetime',
    ];
}
