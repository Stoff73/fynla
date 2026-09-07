<?php

declare(strict_types=1);

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * User Assumption Model
 *
 * Stores user overrides for planning assumptions used in pension and investment projections.
 */
class UserAssumption extends Model
{
    use Auditable, HasFactory;

    /**
     * W-0520 — the three estate planning columns were missing from here.
     *
     * `2026_02_03_100002` added `property_growth_rate`, `investment_growth_method` and
     * `custom_investment_rate` to the table and nothing added them to this list, so
     * `AssumptionsService::updateEstateAssumptions()` — which writes through
     * `updateOrCreate()`, and therefore through `fill()` — had all three SILENTLY DROPPED.
     * Mass assignment does not complain about an unfillable attribute; it discards it. The
     * request validated, the row was written, and the user's setting was not in it.
     */
    protected $fillable = [
        'user_id',
        'assumption_type',
        'inflation_rate',
        'return_rate',
        'compound_periods',
        'property_growth_rate',
        'investment_growth_method',
        'custom_investment_rate',
    ];

    protected $casts = [
        'inflation_rate' => 'decimal:2',
        'return_rate' => 'decimal:2',
        'compound_periods' => 'integer',
        'property_growth_rate' => 'decimal:2',
        'custom_investment_rate' => 'decimal:2',
    ];

    /**
     * Get the user that owns this assumption.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
