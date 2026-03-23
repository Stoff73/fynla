<?php

declare(strict_types=1);

namespace App\Models;

use App\Traits\Auditable;
use App\Traits\HasJointOwnership;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Life Event Model
 *
 * Represents future occurrences that will impact a user's financial position,
 * such as inheritances, bonuses, large purchases, or major expenses.
 *
 * Unlike Goals (which you save towards), Life Events are things that happen TO you.
 */
class LifeEvent extends Model
{
    use Auditable, HasFactory, HasJointOwnership, SoftDeletes;

    /**
     * Event types categorised by impact.
     */
    public const INCOME_EVENT_TYPES = [
        'inheritance',
        'gift_received',
        'bonus',
        'redundancy_payment',
        'property_sale',
        'business_sale',
        'pension_lump_sum',
        'lottery_windfall',
        'custom_income',
    ];

    public const EXPENSE_EVENT_TYPES = [
        'large_purchase',
        'home_improvement',
        'wedding',
        'education_fees',
        'gift_given',
        'medical_expense',
        'custom_expense',
    ];

    protected $fillable = [
        'user_id',
        'event_name',
        'event_type',
        'description',
        'amount',
        'impact_type',
        'expected_date',
        'certainty',
        'icon',
        'show_in_projection',
        'show_in_household_view',
        'ownership_type',
        'joint_owner_id',
        'ownership_percentage',
        'status',
        'occurred_at',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'expected_date' => 'date',
        'ownership_percentage' => 'decimal:2',
        'show_in_projection' => 'boolean',
        'show_in_household_view' => 'boolean',
        'occurred_at' => 'datetime',
    ];

    protected $appends = [
        'signed_amount',
        'display_event_type',
        'years_until_event',
    ];

    /**
     * User relationship.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Allocations relationship.
     */
    public function allocations(): HasMany
    {
        return $this->hasMany(LifeEventAllocation::class)->orderBy('display_order');
    }

    /**
     * Joint owner relationship.
     */
    public function jointOwner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'joint_owner_id');
    }

    /**
     * Check if this is a positive (income) event.
     */
    public function isPositive(): bool
    {
        return $this->impact_type === 'income';
    }

    /**
     * Check if this is a negative (expense) event.
     */
    public function isNegative(): bool
    {
        return $this->impact_type === 'expense';
    }

    /**
     * Get the signed amount (+/- based on impact type).
     */
    public function getSignedAmountAttribute(): float
    {
        return $this->isPositive() ? (float) $this->amount : -(float) $this->amount;
    }

    /**
     * Get display-friendly event type name.
     */
    public function getDisplayEventTypeAttribute(): string
    {
        return match ($this->event_type) {
            // Income events
            'inheritance' => 'Inheritance',
            'gift_received' => 'Gift Received',
            'bonus' => 'Bonus',
            'redundancy_payment' => 'Redundancy Payment',
            'property_sale' => 'Property Sale',
            'business_sale' => 'Business Sale',
            'pension_lump_sum' => 'Pension Lump Sum',
            'lottery_windfall' => 'Lottery/Windfall',
            'custom_income' => 'Other Income',
            // Expense events
            'large_purchase' => 'Large Purchase',
            'home_improvement' => 'Home Improvement',
            'wedding' => 'Wedding',
            'education_fees' => 'Education Fees',
            'gift_given' => 'Gift Given',
            'medical_expense' => 'Medical Expense',
            'custom_expense' => 'Other Expense',
            // Life change events
            'divorce' => 'Divorce',
            'marriage' => 'Marriage',
            'new_child' => 'New Child',
            'job_loss' => 'Job Loss',
            'income_change' => 'Income Change',
            default => ucfirst(str_replace('_', ' ', $this->event_type ?? '')),
        };
    }

    /**
     * Get years until the event occurs.
     */
    public function getYearsUntilEventAttribute(): ?int
    {
        if (! $this->expected_date) {
            return null;
        }

        $diff = now()->startOfYear()->diffInYears($this->expected_date, false);

        return max(0, (int) ceil($diff));
    }

    /**
     * Get the user's age at the time of this event.
     */
    public function getAgeAtEvent(User $user): ?int
    {
        if (! $this->expected_date || ! $user->date_of_birth) {
            return null;
        }

        return (int) $user->date_of_birth->diffInYears($this->expected_date);
    }

    /**
     * Scope for active events (expected or confirmed, not completed/cancelled).
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->whereIn('status', ['expected', 'confirmed']);
    }

    /**
     * Scope for events that should show in projections.
     */
    public function scopeForProjection(Builder $query): Builder
    {
        return $query->where('show_in_projection', true);
    }

    /**
     * Scope for events that should show in household view.
     */
    public function scopeForHousehold(Builder $query): Builder
    {
        return $query->where('show_in_household_view', true);
    }

    /**
     * Scope for income events only.
     */
    public function scopeIncome(Builder $query): Builder
    {
        return $query->where('impact_type', 'income');
    }

    /**
     * Scope for expense events only.
     */
    public function scopeExpense(Builder $query): Builder
    {
        return $query->where('impact_type', 'expense');
    }

    /**
     * Scope for events within a date range.
     */
    public function scopeInDateRange(Builder $query, string $from, string $to): Builder
    {
        return $query->whereBetween('expected_date', [$from, $to]);
    }

    /**
     * Scope for events by certainty level.
     */
    public function scopeByCertainty(Builder $query, string $certainty): Builder
    {
        return $query->where('certainty', $certainty);
    }

    /**
     * Check if event is jointly owned.
     */
    public function isJoint(): bool
    {
        return $this->ownership_type === 'joint' && $this->joint_owner_id !== null;
    }

    /**
     * Get the user's share of this event.
     */
    public function getUserShare(int $userId): float
    {
        if ($this->user_id === $userId) {
            return (float) ($this->ownership_percentage ?? 100) / 100;
        }

        if ($this->joint_owner_id === $userId) {
            return (100 - (float) ($this->ownership_percentage ?? 50)) / 100;
        }

        return 0;
    }

    /**
     * Get the amount attributed to a specific user.
     */
    public function getAmountForUser(int $userId): float
    {
        return (float) $this->amount * $this->getUserShare($userId);
    }
}
