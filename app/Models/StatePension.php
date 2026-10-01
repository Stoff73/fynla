<?php

declare(strict_types=1);

namespace App\Models;

use App\Services\Retirement\StatePensionAgeResolver;
use App\Services\TaxConfigService;
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
        // SP1 Pass 3 / PR 6 — derived columns
        'state_pension_forecast_annual_gbp',
        'state_pension_forecast_annual_gbp_calculated_at',
        'ni_completion_pct',
        'ni_completion_pct_calculated_at',
        'years_to_state_pension_age',
        'years_to_state_pension_age_calculated_at',
    ];

    protected $casts = [
        'ni_years_completed' => 'integer',
        'ni_years_required' => 'integer',
        'state_pension_forecast_annual' => 'decimal:2',
        'state_pension_age' => 'integer',
        'already_receiving' => 'boolean',
        'ni_gaps' => 'array',
        'gap_fill_cost' => 'decimal:2',
        // SP1 Pass 3 / PR 6 — derived columns
        'state_pension_forecast_annual_gbp' => 'decimal:2',
        'state_pension_forecast_annual_gbp_calculated_at' => 'datetime',
        'ni_completion_pct' => 'decimal:2',
        'ni_completion_pct_calculated_at' => 'datetime',
        'years_to_state_pension_age' => 'integer',
        'years_to_state_pension_age_calculated_at' => 'datetime',
    ];

    /**
     * Figures every surface shows, worked out here once (CSJ 2026-10-01: one
     * figure, every surface). Web, /m and iOS each divided by 52, typed in 35
     * qualifying years and 67 for the age, or read fields that do not exist.
     */
    protected $appends = ['weekly_forecast', 'ni_years_for_full_pension', 'ni_years_needed', 'resolved_state_pension_age'];

    /** The forecast a week (the State Pension is set as a weekly rate). */
    public function getWeeklyForecastAttribute(): ?float
    {
        return $this->state_pension_forecast_annual === null ? null : round((float) $this->state_pension_forecast_annual / 52, 2);
    }

    /** Qualifying years for the full amount: the record's own, else tax config `pension.state_pension.qualifying_years`. */
    public function getNiYearsForFullPensionAttribute(): int
    {
        return (int) ($this->ni_years_required
            ?? (app(TaxConfigService::class)->getPensionAllowances()['state_pension']['qualifying_years'] ?? 0));
    }

    /** Qualifying years still needed for the full amount. */
    public function getNiYearsNeededAttribute(): int
    {
        return max(0, $this->ni_years_for_full_pension - (int) ($this->ni_years_completed ?? 0));
    }

    /** State Pension age from its one home (W-0516, the birth-cohort schedule). */
    public function getResolvedStatePensionAgeAttribute(): ?int
    {
        $user = $this->user;

        return $user === null ? $this->state_pension_age : app(StatePensionAgeResolver::class)->forUser($user);
    }

    /**
     * Get the user that owns the state pension record.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
