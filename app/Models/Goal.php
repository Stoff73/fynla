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
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Goal extends Model
{
    use Auditable, HasFactory, HasJointOwnership, SoftDeletes;

    /**
     * @var array<int, string>
     *
     * Note on linking mechanisms:
     *
     * - linked_account_ids (JSON array): Investment-goal-specific field. Stores an array of
     *   InvestmentAccount IDs that fund this goal. Used by the Investment GoalForm.vue and
     *   InvestmentGoal model. NOT used for savings-goal linking — do NOT repurpose for savings.
     *
     * - linked_savings_account_id (FK): Legacy single savings account link. Being superseded
     *   by the goal_savings_account pivot table (many-to-many). Retained for backwards
     *   compatibility; do NOT use for new code — use the savingsAccounts() relationship instead.
     *
     * - linked_investment_account_id (FK): Single investment account link used by the
     *   TracksGoalContributions trait for auto-recording contributions when account value changes.
     */
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
        'linked_investment_account_id',
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
     * Linked investment account relationship.
     */
    public function linkedInvestmentAccount(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Investment\InvestmentAccount::class, 'linked_investment_account_id');
    }

    /**
     * Contributions relationship.
     */
    public function contributions(): HasMany
    {
        return $this->hasMany(GoalContribution::class);
    }

    /**
     * Goals that this goal depends on (prerequisites).
     */
    public function dependsOn(): BelongsToMany
    {
        return $this->belongsToMany(self::class, 'goal_dependencies', 'goal_id', 'depends_on_goal_id')
            ->withPivot('dependency_type', 'notes')
            ->withTimestamps();
    }

    /**
     * Savings accounts linked to this goal via pivot table.
     */
    public function savingsAccounts(): BelongsToMany
    {
        return $this->belongsToMany(SavingsAccount::class, 'goal_savings_account')
            ->withPivot('allocated_amount', 'is_primary', 'priority_rank')
            ->withTimestamps();
    }

    /**
     * Goals that depend on this goal (dependants).
     */
    public function dependedOnBy(): BelongsToMany
    {
        return $this->belongsToMany(self::class, 'goal_dependencies', 'depends_on_goal_id', 'goal_id')
            ->withPivot('dependency_type', 'notes')
            ->withTimestamps();
    }

    /**
     * Check if this goal is blocked by any incomplete prerequisite.
     */
    public function isBlocked(): bool
    {
        return $this->dependsOn()
            ->wherePivot('dependency_type', 'blocks')
            ->where('status', '!=', 'completed')
            ->exists();
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
}
