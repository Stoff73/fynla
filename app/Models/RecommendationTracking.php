<?php

declare(strict_types=1);

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
        'priority_score' => 'float',
        'recommended_amount' => 'float',
        'completed_at' => 'datetime',
    ];

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
