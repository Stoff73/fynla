<?php

declare(strict_types=1);

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Subscription plan pricing tiers.
 *
 * Note: All price fields (monthly_price, yearly_price, launch_monthly_price,
 * launch_yearly_price) are stored in PENCE (integer). Convert to pounds
 * by dividing by 100 when displaying. This differs from Payment.amount
 * and Subscription.amount which use decimal pounds (decimal:2).
 */
class SubscriptionPlan extends Model
{
    use Auditable;

    protected $fillable = [
        'slug',
        'name',
        'monthly_price',
        'yearly_price',
        'launch_monthly_price',
        'launch_yearly_price',
        'is_active',
        'features',
        'sort_order',
    ];

    protected $casts = [
        'monthly_price' => 'integer',
        'yearly_price' => 'integer',
        'launch_monthly_price' => 'integer',
        'launch_yearly_price' => 'integer',
        'is_active' => 'boolean',
        'features' => 'array',
        'sort_order' => 'integer',
    ];

    /**
     * Get the price in pence for a given billing cycle.
     */
    public function getPriceForCycle(string $billingCycle): int
    {
        return $billingCycle === 'monthly' ? $this->monthly_price : $this->yearly_price;
    }

    /**
     * Get the launch (discounted) price in pence for a given billing cycle, or null if not set.
     */
    public function getLaunchPriceForCycle(string $billingCycle): ?int
    {
        return $billingCycle === 'monthly' ? $this->launch_monthly_price : $this->launch_yearly_price;
    }

    /**
     * Find an active plan by slug.
     */
    public static function findBySlug(string $slug): ?self
    {
        return static::active()->where('slug', $slug)->first();
    }

    /**
     * Scope to only active plans.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
