<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OnboardingProgress extends Model
{
    use HasFactory;

    protected $table = 'onboarding_progress';

    protected $fillable = [
        'user_id',
        'focus_area',
        'step_name',
        'step_data',
        'completed',
        'skipped',
        'skip_reason_shown',
        'completed_at',
    ];

    protected $casts = [
        'step_data' => 'array',
        'completed' => 'boolean',
        'skipped' => 'boolean',
        'skip_reason_shown' => 'boolean',
        'completed_at' => 'datetime',
    ];

    /**
     * Get the user that owns the onboarding progress.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Scope a query to only include completed steps.
     */
    public function scopeCompleted(Builder $query): Builder
    {
        return $query->where('completed', true);
    }

    /**
     * Scope a query to only include skipped steps.
     */
    public function scopeSkipped(Builder $query): Builder
    {
        return $query->where('skipped', true);
    }

    /**
     * Scope a query to filter by focus area.
     */
    public function scopeForFocusArea(Builder $query, string $focusArea): Builder
    {
        return $query->where('focus_area', $focusArea);
    }

    /**
     * Scope a query to filter by step name.
     */
    public function scopeForStep(Builder $query, string $stepName): Builder
    {
        return $query->where('step_name', $stepName);
    }
}
