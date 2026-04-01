<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiAdviceLog extends Model
{
    protected $fillable = [
        'user_id',
        'conversation_id',
        'message_id',
        'query_type',
        'classification',
        'kyc_status',
        'recommendations',
        'tools_called',
        'user_data_snapshot',
    ];

    protected $casts = [
        'classification' => 'array',
        'kyc_status' => 'array',
        'recommendations' => 'array',
        'tools_called' => 'array',
        'user_data_snapshot' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(AiConversation::class);
    }

    // ─── Scopes ──────────────────────────────────────────────────────

    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeRecent($query, int $days = 30)
    {
        return $query->where('created_at', '>=', now()->subDays($days));
    }

    public function scopeForModule($query, string $module)
    {
        return $query->whereJsonContains('classification->modules', $module);
    }

    public function scopeForQueryType($query, string $queryType)
    {
        return $query->where('query_type', $queryType);
    }
}
