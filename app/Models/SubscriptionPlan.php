<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SubscriptionPlan extends Model
{
    protected $fillable = [
        'slug',
        'name',
        'monthly_price',
        'yearly_price',
        'trial_days',
        'is_active',
        'features',
        'sort_order',
    ];

    protected $casts = [
        'monthly_price' => 'integer',
        'yearly_price' => 'integer',
        'trial_days' => 'integer',
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
     * Find an active plan by slug.
     */
    public static function findBySlug(string $slug): ?self
    {
        return static::active()->where('slug', $slug)->first();
    }

    /**
     * Scope to only active plans.
     */
    public function scopeActive(\Illuminate\Database\Eloquent\Builder $query): \Illuminate\Database\Eloquent\Builder
    {
        return $query->where('is_active', true);
    }
}
