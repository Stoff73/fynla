<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class AiConversation extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'user_id',
        'title',
        'status',
        'model_used',
        'total_input_tokens',
        'total_output_tokens',
        'message_count',
        'last_message_at',
        'metadata',
        'persona_state',
        'onboarding_parked_facts',
    ];

    protected $casts = [
        'total_input_tokens' => 'integer',
        'total_output_tokens' => 'integer',
        'message_count' => 'integer',
        'last_message_at' => 'datetime',
        'metadata' => 'array',
        'persona_state' => 'array',
        'onboarding_parked_facts' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(AiMessage::class, 'conversation_id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }

    public function scopeForUser(Builder $query, int $userId): Builder
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Filter to conversations created by the Fyn onboarding flow.
     *
     * The onboarding conversation's `title` legitimately changes as the
     * conversation evolves (HasAiChat updates it from a user message), so
     * the resume + status flows pivot on the immutable `metadata.source`
     * flag set at creation in AiChatController::startOnboarding. Without
     * this scope, getOnboardingStatus's prior `where('title','Onboarding')`
     * filter returned null once the title was updated, breaking the
     * welcome-back resume on every sign-in past the first user message.
     */
    public function scopeOnboarding(Builder $query): Builder
    {
        return $query->where('metadata->source', 'fyn_onboarding');
    }

    public function incrementTokenUsage(int $inputTokens, int $outputTokens): void
    {
        $this->increment('total_input_tokens', $inputTokens);
        $this->increment('total_output_tokens', $outputTokens);
        $this->increment('message_count');
        $this->update(['last_message_at' => now()]);
    }
}
