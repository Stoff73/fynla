<?php

declare(strict_types=1);

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;
use Laravel\Sanctum\PersonalAccessToken;

class RecommendationTracking extends Model
{
    use Auditable, HasFactory;

    protected $table = 'recommendation_tracking';

    protected $fillable = [
        'user_id',
        'recommendation_id',
        'module',
        'recommendation_text',
        'priority_score',
        'recommended_amount',
        'timeline',
        'status',
        'completed_at',
        'notes',
    ];

    protected $casts = [
        'priority_score' => 'decimal:2',
        'recommended_amount' => 'decimal:2',
        'completed_at' => 'datetime',
    ];

    /**
     * A demo persona is one account shared by every demo visitor, each on
     * their own token. A row written in a demo carries that token
     * (`preview_token_id`, deleted with it) and is seen by that token only;
     * outside a demo session no demo row is seen. Real users' rows carry null
     * and are seen as before.
     */
    protected static function booted(): void
    {
        static::addGlobalScope('preview_session', function (Builder $query): void {
            $tokenId = self::previewTokenId();
            $column = $query->getModel()->qualifyColumn('preview_token_id');

            $query->where(function (Builder $visible) use ($column, $tokenId): void {
                $visible->whereNull($column);
                if ($tokenId !== null) {
                    $visible->orWhere($column, $tokenId);
                }
            });
        });

        // A demo row with no visitor token would be every visitor's: refused.
        static::creating(function (self $tracking): ?bool {
            $tracking->preview_token_id ??= self::previewTokenId();

            return $tracking->preview_token_id === null && $tracking->user?->is_preview_user ? false : null;
        });
    }

    /**
     * A demo visitor's token was swapped for a new one (/m rotates on boot):
     * their completions go with them.
     */
    public static function movePreviewSession(int $fromTokenId, int $toTokenId): void
    {
        static::withoutGlobalScopes()
            ->where('preview_token_id', $fromTokenId)
            ->update(['preview_token_id' => $toTokenId]);
    }

    /**
     * The demo visitor's token id, or null when this request is not a demo
     * session.
     */
    public static function previewTokenId(): ?int
    {
        if (! Auth::hasUser()) {
            return null;
        }

        $user = Auth::user();
        if (! $user instanceof User || ! $user->is_preview_user) {
            return null;
        }

        $token = $user->currentAccessToken();

        return $token instanceof PersonalAccessToken ? (int) $token->getKey() : null;
    }

    /**
     * User relationship
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Scope for pending recommendations
     */
    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', 'pending');
    }

    /**
     * Scope for completed recommendations
     */
    public function scopeCompleted(Builder $query): Builder
    {
        return $query->where('status', 'completed');
    }

    /**
     * Scope for in progress recommendations
     */
    public function scopeInProgress(Builder $query): Builder
    {
        return $query->where('status', 'in_progress');
    }

    /**
     * Scope for active recommendations (pending or in progress)
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->whereIn('status', ['pending', 'in_progress']);
    }

    /**
     * Scope by module
     */
    public function scopeByModule(Builder $query, string $module): Builder
    {
        return $query->where('module', $module);
    }

    /**
     * Scope by timeline
     */
    public function scopeByTimeline(Builder $query, string $timeline): Builder
    {
        return $query->where('timeline', $timeline);
    }

    /**
     * Mark recommendation as completed
     */
    public function markAsCompleted(): void
    {
        $this->update([
            'status' => 'completed',
            'completed_at' => now(),
        ]);
    }

    /**
     * Mark recommendation as dismissed
     */
    public function dismiss(): void
    {
        $this->update([
            'status' => 'dismissed',
        ]);
    }

    /**
     * Mark recommendation as in progress
     */
    public function markAsInProgress(): void
    {
        $this->update([
            'status' => 'in_progress',
        ]);
    }
}
