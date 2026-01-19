<?php

declare(strict_types=1);

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Goal extends Model
{
    use Auditable, HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id',
        'goal_name',
        'goal_type',
        'custom_goal_type_name',
        'description',
        'target_amount',
        'current_amount',
        'target_date',
        'start_date',
        'assigned_module',
        'module_override',
        'priority',
        'is_essential',
        'status',
        'monthly_contribution',
        'contribution_frequency',
        'contribution_streak',
        'longest_streak',
        'last_contribution_date',
        'linked_account_ids',
        'linked_savings_account_id',
        'risk_preference',
        'use_global_risk_profile',
        'ownership_type',
        'joint_owner_id',
        'ownership_percentage',
        'property_location',
        'property_type',
        'is_first_time_buyer',
        'estimated_property_price',
        'deposit_percentage',
        'stamp_duty_estimate',
        'additional_costs_estimate',
        'milestones',
        'projection_data',
        'completed_at',
        'completion_notes',
    ];

    protected $casts = [
        'target_amount' => 'decimal:2',
        'current_amount' => 'decimal:2',
        'target_date' => 'date',
        'start_date' => 'date',
        'module_override' => 'boolean',
        'is_essential' => 'boolean',
        'monthly_contribution' => 'decimal:2',
        'contribution_streak' => 'integer',
        'longest_streak' => 'integer',
        'last_contribution_date' => 'date',
        'linked_account_ids' => 'array',
        'risk_preference' => 'integer',
        'use_global_risk_profile' => 'boolean',
        'ownership_percentage' => 'decimal:2',
        'is_first_time_buyer' => 'boolean',
        'estimated_property_price' => 'decimal:2',
        'deposit_percentage' => 'decimal:2',
        'stamp_duty_estimate' => 'decimal:2',
        'additional_costs_estimate' => 'decimal:2',
        'milestones' => 'array',
        'projection_data' => 'array',
        'completed_at' => 'datetime',
    ];

    protected $appends = [
        'progress_percentage',
        'days_remaining',
        'months_remaining',
        'is_on_track',
        'display_goal_type',
    ];

    /**
     * User relationship.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Joint owner relationship.
     */
    public function jointOwner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'joint_owner_id');
    }

    /**
     * Linked savings account relationship.
     */
    public function linkedSavingsAccount(): BelongsTo
    {
        return $this->belongsTo(SavingsAccount::class, 'linked_savings_account_id');
    }

    /**
     * Contributions relationship.
     */
    public function contributions(): HasMany
    {
        return $this->hasMany(GoalContribution::class);
    }

    /**
     * Get progress percentage.
     */
    public function getProgressPercentageAttribute(): float
    {
        if ($this->target_amount <= 0) {
            return 0;
        }

        $percentage = ($this->current_amount / $this->target_amount) * 100;

        return min(round($percentage, 2), 100);
    }

    /**
     * Get days remaining until target date.
     */
    public function getDaysRemainingAttribute(): int
    {
        if (! $this->target_date) {
            return 0;
        }

        $diff = now()->startOfDay()->diffInDays($this->target_date, false);

        return max(0, (int) $diff);
    }

    /**
     * Get months remaining until target date.
     */
    public function getMonthsRemainingAttribute(): int
    {
        if (! $this->target_date) {
            return 0;
        }

        $diff = now()->startOfMonth()->diffInMonths($this->target_date, false);

        return max(0, (int) ceil($diff));
    }

    /**
     * Check if goal is on track based on linear projection.
     */
    public function getIsOnTrackAttribute(): bool
    {
        if ($this->status !== 'active') {
            return $this->status === 'completed';
        }

        if (! $this->start_date || ! $this->target_date) {
            return false;
        }

        $totalDays = $this->start_date->diffInDays($this->target_date);
        if ($totalDays <= 0) {
            return $this->progress_percentage >= 100;
        }

        $daysElapsed = $this->start_date->diffInDays(now());
        $expectedProgress = min(($daysElapsed / $totalDays) * 100, 100);

        // Allow 10% margin for being "on track"
        return $this->progress_percentage >= ($expectedProgress - 10);
    }

    /**
     * Get display-friendly goal type.
     */
    public function getDisplayGoalTypeAttribute(): string
    {
        if ($this->goal_type === 'custom' && $this->custom_goal_type_name) {
            return $this->custom_goal_type_name;
        }

        return match ($this->goal_type) {
            'emergency_fund' => 'Emergency Fund',
            'property_purchase' => 'Property Purchase',
            'home_deposit' => 'Home Deposit',
            'education' => 'Education',
            'retirement' => 'Retirement',
            'wealth_accumulation' => 'Wealth Building',
            'wedding' => 'Wedding',
            'holiday' => 'Holiday',
            'car_purchase' => 'Car Purchase',
            'debt_repayment' => 'Debt Repayment',
            'custom' => 'Custom Goal',
            default => ucfirst(str_replace('_', ' ', $this->goal_type ?? '')),
        };
    }

    /**
     * Get amount remaining to reach target.
     */
    public function getAmountRemainingAttribute(): float
    {
        return max(0, (float) $this->target_amount - (float) $this->current_amount);
    }

    /**
     * Get required monthly contribution to reach target on time.
     */
    public function getRequiredMonthlyContributionAttribute(): float
    {
        $monthsRemaining = $this->months_remaining;
        if ($monthsRemaining <= 0) {
            return 0;
        }

        return round($this->amount_remaining / $monthsRemaining, 2);
    }

    /**
     * Check if goal is a property goal.
     */
    public function isPropertyGoal(): bool
    {
        return in_array($this->goal_type, ['property_purchase', 'home_deposit']);
    }

    /**
     * Check if goal is an investment goal.
     */
    public function isInvestmentGoal(): bool
    {
        return $this->assigned_module === 'investment';
    }

    /**
     * Check if goal is jointly owned.
     */
    public function isJoint(): bool
    {
        return $this->ownership_type === 'joint' && $this->joint_owner_id !== null;
    }

    /**
     * Get the current milestone reached (25, 50, 75, or 100).
     */
    public function getCurrentMilestoneAttribute(): ?int
    {
        $progress = $this->progress_percentage;

        if ($progress >= 100) {
            return 100;
        }
        if ($progress >= 75) {
            return 75;
        }
        if ($progress >= 50) {
            return 50;
        }
        if ($progress >= 25) {
            return 25;
        }

        return null;
    }

    /**
     * Get the next milestone target (25, 50, 75, or 100).
     */
    public function getNextMilestoneAttribute(): ?int
    {
        $progress = $this->progress_percentage;

        if ($progress >= 100) {
            return null;
        }
        if ($progress >= 75) {
            return 100;
        }
        if ($progress >= 50) {
            return 75;
        }
        if ($progress >= 25) {
            return 50;
        }

        return 25;
    }

    /**
     * Scope for active goals.
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    /**
     * Scope for completed goals.
     */
    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    /**
     * Scope by assigned module.
     */
    public function scopeForModule($query, string $module)
    {
        return $query->where('assigned_module', $module);
    }

    /**
     * Scope by priority.
     */
    public function scopeByPriority($query, string $priority)
    {
        return $query->where('priority', $priority);
    }

    /**
     * Scope for on-track goals.
     */
    public function scopeOnTrack($query)
    {
        return $query->active()->get()->filter(fn ($goal) => $goal->is_on_track);
    }
}
