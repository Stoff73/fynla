<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One LLM call's spend, tagged to the action it served
 * (CoALA Phase 5 item 8 — FR-M11).
 */
class AiCostAttribution extends Model
{
    use HasFactory;

    protected $table = 'ai_cost_attribution';

    protected $fillable = [
        'user_id',
        'conversation_id',
        'session_mode',
        'action_type',
        'stage',
        'cycle_id',
        'procedural_version',
        'model',
        'input_tokens',
        'output_tokens',
        'cache_hit_tokens',
        'cache_miss_tokens',
        'gbp_cost',
        'gbp_cost_priced',
    ];

    protected $casts = [
        'cycle_id' => 'integer',
        'input_tokens' => 'integer',
        'output_tokens' => 'integer',
        'cache_hit_tokens' => 'integer',
        'cache_miss_tokens' => 'integer',
        'gbp_cost' => 'decimal:6',
        'gbp_cost_priced' => 'boolean',
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
