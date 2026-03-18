<?php

declare(strict_types=1);

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * State Pension Model
 *
 * Represents UK State Pension information including NI contributions and forecast.
 */
class StatePension extends Model
{
    use Auditable, HasFactory, SoftDeletes;

    protected $table = 'state_pensions';

    protected $fillable = [
        'user_id',
        'ni_years_completed',
        'ni_years_required',
        'state_pension_forecast_annual',
        'state_pension_age',
        'already_receiving',
        'ni_gaps',
        'gap_fill_cost',
    ];

    protected $casts = [
        'ni_years_completed' => 'integer',
        'ni_years_required' => 'integer',
        'state_pension_forecast_annual' => 'decimal:2',
        'state_pension_age' => 'integer',
        'already_receiving' => 'boolean',
        'ni_gaps' => 'array',
        'gap_fill_cost' => 'decimal:2',
    ];

    /**
     * Get the user that owns the state pension record.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
