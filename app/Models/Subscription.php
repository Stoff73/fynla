<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Model;

class Subscription extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id',
        'plan',
        'billing_cycle',
        'status',
        'amount',
        'trial_started_at',
        'trial_ends_at',
        'current_period_start',
        'current_period_end',
        'revolut_order_id',
    ];

    protected $casts = [
        'trial_started_at' => 'datetime',
        'trial_ends_at' => 'datetime',
        'current_period_start' => 'datetime',
        'current_period_end' => 'datetime',
        'amount' => 'float',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
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
        return $this->status === 'active';
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
