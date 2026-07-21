<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Per-user daily AI token usage row.
 *
 * One row per (user_id, usage_date). Written via
 * HasAiGuardrails::recordTokenUsage inside a DB::transaction with
 * `SELECT ... FOR UPDATE` so concurrent writes serialise on the row
 * lock. Read by HasAiGuardrails::getTodayTokenUsage on every chat()
 * entry — no cache layer between (S0.11.1).
 */
class AiDailyUsage extends Model
{
    use HasFactory;

    protected $table = 'ai_daily_usage';

    protected $fillable = [
        'user_id',
        'usage_date',
        'tokens_used',
    ];

    protected $casts = [
        'usage_date' => 'date',
        'tokens_used' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
