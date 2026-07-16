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

    public const STATUS_PENDING = 'pending';

    public const STATUS_TRIALING = 'trialing';

    public const PROVISIONAL_STATUSES = [
        self::STATUS_PENDING,
        self::STATUS_TRIALING,
    ];

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
        'revolut_subscription_id',
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

    public function scopeExpired($query)
    {
        return $query->where('status', 'expired');
    }

    public function isActive(): bool
    {
        if ($this->status === 'active') {
            return $this->current_period_end === null || $this->current_period_end->isFuture();
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
}
