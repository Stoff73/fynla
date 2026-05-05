<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class DiscountCode extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'code',
        'type',
        'value',
        'max_uses',
        'times_used',
        'max_uses_per_user',
        'applicable_plans',
        'applicable_cycles',
        'starts_at',
        'expires_at',
        'is_active',
        'created_by',
        'user_id',
        'metadata',
    ];

    protected $casts = [
        'applicable_plans' => 'array',
        'applicable_cycles' => 'array',
        'metadata' => 'array',
        'starts_at' => 'datetime',
        'expires_at' => 'datetime',
        'is_active' => 'boolean',
        'value' => 'integer',
        'max_uses' => 'integer',
        'times_used' => 'integer',
        'max_uses_per_user' => 'integer',
    ];

    public function usages(): HasMany
    {
        return $this->hasMany(DiscountCodeUsage::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the user this code is locked to (for per-user lifecycle codes).
     * Returns null for shared codes.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Check if this discount code is currently valid (ignoring user-specific checks).
     */
    public function isValid(): bool
    {
        if (! $this->is_active) {
            return false;
        }

        if ($this->expires_at && $this->expires_at->isPast()) {
            return false;
        }

        if ($this->starts_at && $this->starts_at->isFuture()) {
            return false;
        }

        if (! $this->hasUsesRemaining()) {
            return false;
        }

        return true;
    }

    /**
     * Check if this code is applicable to the given plan slug.
     */
    public function isValidForPlan(string $planSlug): bool
    {
        if (empty($this->applicable_plans)) {
            return true;
        }

        return in_array($planSlug, $this->applicable_plans, true);
    }

    /**
     * Check if this code is applicable to the given billing cycle.
     */
    public function isValidForCycle(string $billingCycle): bool
    {
        if (empty($this->applicable_cycles)) {
            return true;
        }

        return in_array($billingCycle, $this->applicable_cycles, true);
    }

    /**
     * Check if the code has remaining uses globally.
     */
    public function hasUsesRemaining(): bool
    {
        if ($this->max_uses === null) {
            return true;
        }

        return $this->times_used < $this->max_uses;
    }

    /**
     * Get how many times a specific user has used this code.
     */
    public function userUsageCount(int $userId): int
    {
        return $this->usages()->where('user_id', $userId)->count();
    }

    /**
     * Calculate the discount amount in pence for a given order amount.
     */
    public function calculateDiscount(int $amountPence, ?string $planSlug = null, ?string $billingCycle = null): int
    {
        return match ($this->type) {
            'percentage' => (int) round($amountPence * $this->value / 100),
            'fixed_amount' => min($this->value, $amountPence),
            'lifecycle_welcome' => $this->calculateLifecycleAmount($amountPence, $planSlug, $billingCycle),
            'trial_extension' => 0,
            default => 0,
        };
    }

    private function calculateLifecycleAmount(int $amountPence, ?string $planSlug, ?string $billingCycle): int
    {
        if ($planSlug === null || $billingCycle === null) {
            return 0;
        }
        $key = "{$planSlug}.{$billingCycle}";
        $amount = $this->metadata['plan_amounts'][$key] ?? 0;

        return min((int) $amount, $amountPence);
    }
}
