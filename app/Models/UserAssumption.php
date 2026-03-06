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

    protected $fillable = [
        'user_id',
        'assumption_type',
        'inflation_rate',
        'return_rate',
        'compound_periods',
    ];

    protected $casts = [
        'inflation_rate' => 'decimal:2',
        'return_rate' => 'decimal:2',
        'compound_periods' => 'integer',
    ];

    /**
     * Get the user that owns this assumption.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
