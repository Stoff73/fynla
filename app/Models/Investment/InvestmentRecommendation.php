<?php

declare(strict_types=1);

namespace App\Models\Investment;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InvestmentRecommendation extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'investment_plan_id',
        'category',
        'priority',
        'title',
        'description',
        'action_required',
        'impact_level',
        'potential_saving',
        'estimated_effort',
        'status',
        'due_date',
        'completed_at',
        'dismissed_at',
        'dismissal_reason',
    ];

    protected $casts = [
        'priority' => 'integer',
        'potential_saving' => 'float',
        'due_date' => 'date',
        'completed_at' => 'datetime',
        'dismissed_at' => 'datetime',
    ];

    /**
     * Get the user that owns the recommendation
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the investment plan this recommendation belongs to
     */
    public function investmentPlan(): BelongsTo
    {
        return $this->belongsTo(InvestmentPlan::class);
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
     * Scope for high priority recommendations
     */
    public function scopeHighPriority(Builder $query): Builder
    {
        return $query->where('priority', '<=', 3)->orderBy('priority');
    }
}
