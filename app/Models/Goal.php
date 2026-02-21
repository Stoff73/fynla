<?php

declare(strict_types=1);

namespace App\Models;

use App\Services\Goals\GoalCalculationService;
use App\Traits\Auditable;
use App\Traits\HasJointOwnership;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Goal extends Model
{
    use Auditable, HasFactory, HasJointOwnership, SoftDeletes;

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
        'show_in_projection',
        'show_in_household_view',
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
        'show_in_projection' => 'boolean',
        'show_in_household_view' => 'boolean',
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
        return app(GoalCalculationService::class)->calculateProgressPercentage($this);
    }

    /**
     * Get days remaining until target date.
     */
    public function getDaysRemainingAttribute(): int
    {
        return app(GoalCalculationService::class)->calculateDaysRemaining($this);
    }

    /**
     * Get months remaining until target date.
     */
    public function getMonthsRemainingAttribute(): int
    {
        return app(GoalCalculationService::class)->calculateMonthsRemaining($this);
    }

    /**
     * Check if goal is on track based on linear projection.
     */
    public function getIsOnTrackAttribute(): bool
    {
        return app(GoalCalculationService::class)->calculateIsOnTrack($this);
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
        return app(GoalCalculationService::class)->calculateAmountRemaining($this);
    }

    /**
     * Get required monthly contribution to reach target on time.
     */
    public function getRequiredMonthlyContributionAttribute(): float
    {
        return app(GoalCalculationService::class)->calculateRequiredMonthlyContribution($this);
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
        return app(GoalCalculationService::class)->calculateCurrentMilestone($this);
    }

    /**
     * Get the next milestone target (25, 50, 75, or 100).
     */
    public function getNextMilestoneAttribute(): ?int
    {
        return app(GoalCalculationService::class)->calculateNextMilestone($this);
    }

    /**
     * Scope for active goals.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }

    /**
     * Scope for completed goals.
     */
    public function scopeCompleted(Builder $query): Builder
    {
        return $query->where('status', 'completed');
    }

    /**
     * Scope by assigned module.
     */
    public function scopeForModule(Builder $query, string $module): Builder
    {
        return $query->where('assigned_module', $module);
    }

    /**
     * Scope by priority.
     */
    public function scopeByPriority(Builder $query, string $priority): Builder
    {
        return $query->where('priority', $priority);
    }

    /**
     * Scope for on-track goals.
     *
     * Note: This scope filters active goals with progress > 0 (basic SQL filtering).
     * Full on-track calculation requires PHP accessors, so use:
     * Goal::active()->get()->filter(fn($goal) => $goal->is_on_track)
     * for complete filtering.
     */
    public function scopeOnTrack(Builder $query): Builder
    {
        return $query->where('status', 'active')
            ->where('current_amount', '>', 0);
    }
}
