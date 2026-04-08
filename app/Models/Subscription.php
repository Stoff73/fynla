<?php

declare(strict_types=1);

namespace App\Models;

use App\Traits\Auditable;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Subscription extends Model
{
    use Auditable, HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id',
        'plan',
        'billing_cycle',
        'amount',
        'trial_started_at',
        'trial_ends_at',
        'current_period_start',
        'current_period_end',
        'cancelled_at',
        'cancellation_reason',
        'status',
        'revolut_order_id',
        'revolut_plan_id',
        'revolut_plan_variation_id',
        'auto_renew',
        'payment_method_saved',
        'data_retention_starts_at',
    ];

    protected $casts = [
        'trial_started_at' => 'datetime',
        'trial_ends_at' => 'datetime',
        'current_period_start' => 'datetime',
        'current_period_end' => 'datetime',
        'cancelled_at' => 'datetime',
        'data_retention_starts_at' => 'datetime',
        'amount' => 'decimal:2',
        'auto_renew' => 'boolean',
        'payment_method_saved' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeTrialing($query)
    {
        return $query->where('status', 'trialing');
    }

    public function scopeExpired($query)
    {
        return $query->where('status', 'expired');
    }

    public function isTrialing(): bool
    {
        return $this->status === 'trialing' && $this->trial_ends_at && $this->trial_ends_at->isFuture();
    }

    public function isActive(): bool
    {
        if ($this->status === 'active') {
            return true;
        }

        // Cancelled and past_due subscriptions retain access until the current period ends
        if (in_array($this->status, ['cancelled', 'past_due']) && $this->current_period_end && $this->current_period_end->isFuture()) {
            return true;
        }

        return false;
    }

    /**
     * Check if this subscription is in the 30-day data retention grace period.
     */
    public function isInGracePeriod(): bool
    {
        if (! $this->data_retention_starts_at) {
            return false;
        }

        return $this->data_retention_starts_at->copy()->addDays(30)->isFuture();
    }

    /**
     * Get the date when the grace period ends and data will be deleted.
     */
    public function gracePeriodEndsAt(): ?Carbon
    {
        if (! $this->data_retention_starts_at) {
            return null;
        }

        return $this->data_retention_starts_at->copy()->addDays(30);
    }

    public function daysLeftInTrial(): int
    {
        if (! $this->trial_ends_at) {
            return 0;
        }

        return max(0, (int) Carbon::now()->diffInDays($this->trial_ends_at, false));
    }

    public function trialProgress(): float
    {
        if (! $this->trial_started_at || ! $this->trial_ends_at) {
            return 0;
        }

        $totalDays = $this->trial_started_at->diffInDays($this->trial_ends_at);
        if ($totalDays === 0) {
            return 100;
        }

        $elapsed = $this->trial_started_at->diffInDays(Carbon::now());

        return min(100, round(($elapsed / $totalDays) * 100, 1));
    }
}
