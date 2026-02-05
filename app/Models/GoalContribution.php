<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GoalContribution extends Model
{
    use HasFactory;

    protected $fillable = [
        'goal_id',
        'user_id',
        'amount',
        'contribution_date',
        'contribution_type',
        'notes',
        'goal_balance_after',
        'streak_qualifying',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'contribution_date' => 'date',
        'goal_balance_after' => 'decimal:2',
        'streak_qualifying' => 'boolean',
    ];

    /**
     * Goal relationship.
     */
    public function goal(): BelongsTo
    {
        return $this->belongsTo(Goal::class);
    }

    /**
     * User relationship.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Scope for streak-qualifying contributions.
     */
    public function scopeStreakQualifying(Builder $query): Builder
    {
        return $query->where('streak_qualifying', true);
    }

    /**
     * Scope by contribution type.
     */
    public function scopeOfType(Builder $query, string $type): Builder
    {
        return $query->where('contribution_type', $type);
    }

    /**
     * Scope for contributions in a date range.
     */
    public function scopeInDateRange(Builder $query, $startDate, $endDate): Builder
    {
        return $query->whereBetween('contribution_date', [$startDate, $endDate]);
    }
}
