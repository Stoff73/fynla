<?php

declare(strict_types=1);

namespace App\Models\Investment;

use App\Models\Household;
use App\Models\Trust;
use App\Models\User;
use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class InvestmentAccount extends Model
{
    use Auditable, HasFactory;

    protected $fillable = [
        'user_id',
        'account_name',
        'joint_owner_id',
        'household_id',
        'trust_id',
        'ownership_type',
        'ownership_percentage',
        'account_type',
        'account_type_other',
        'country',
        'provider',
        'account_number',
        'platform',
        'current_value',
        'contributions_ytd',
        'monthly_contribution_amount',
        'contribution_frequency',
        'planned_lump_sum_amount',
        'planned_lump_sum_date',
        'tax_year',
        'platform_fee_percent',
        'platform_fee_amount',
        'platform_fee_type',
        'platform_fee_frequency',
        'advisor_fee_percent',
        'isa_type',
        'isa_subscription_current_year',
        'risk_preference',
        'has_custom_risk',
        'rebalance_threshold_percent',
    ];

    protected $casts = [
        'current_value' => 'float',
        'contributions_ytd' => 'float',
        'monthly_contribution_amount' => 'float',
        'planned_lump_sum_amount' => 'float',
        'planned_lump_sum_date' => 'date',
        'platform_fee_percent' => 'float',
        'platform_fee_amount' => 'float',
        'advisor_fee_percent' => 'float',
        'isa_subscription_current_year' => 'float',
        'ownership_percentage' => 'decimal:2',
        'has_custom_risk' => 'boolean',
        'rebalance_threshold_percent' => 'float',
    ];

    protected $attributes = [
        'contributions_ytd' => 0,
        'platform_fee_percent' => 0,
        'advisor_fee_percent' => 0,
        'isa_subscription_current_year' => 0,
        'has_custom_risk' => false,
        'rebalance_threshold_percent' => 10.00,
    ];

    /**
     * User relationship
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Holdings relationship (polymorphic)
     */
    public function holdings(): MorphMany
    {
        return $this->morphMany(Holding::class, 'holdable');
    }

    /**
     * Get the household this investment account belongs to (for joint ownership).
     */
    public function household(): BelongsTo
    {
        return $this->belongsTo(Household::class);
    }

    /**
     * Get the trust that holds this investment account (if applicable).
     */
    public function trust(): BelongsTo
    {
        return $this->belongsTo(Trust::class);
    }
}
