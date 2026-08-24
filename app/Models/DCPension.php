<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\AwardsDataEntryPoints;
use App\Models\Investment\Holding;
use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * DC Pension Model
 *
 * Represents a Defined Contribution pension scheme (workplace, SIPP, or personal pension).
 */
class DCPension extends Model
{
    use Auditable, AwardsDataEntryPoints, HasFactory, SoftDeletes;

    public function gamificationCategory(): string
    {
        return 'pension';
    }

    protected $auditExcludeFields = ['updated_at', 'created_at'];

    protected $table = 'dc_pensions';

    protected $fillable = [
        'user_id',
        'scheme_name',
        'scheme_type',
        'provider',
        'pension_type',
        'member_number',
        'current_fund_value',
        'annual_salary',
        'employee_contribution_percent',
        'employer_contribution_percent',
        'employer_matching_limit',
        'monthly_contribution_amount',
        'lump_sum_contribution',
        'investment_strategy',
        'entered_allocation_baseline',
        'entered_allocation_source',
        'entered_allocation_effective_at',
        'platform_fee_percent',
        'platform_fee_type',
        'platform_fee_amount',
        'platform_fee_frequency',
        'advisor_fee_percent',
        'retirement_age',
        'expected_return_percent',
        'projected_value_at_retirement',
        'risk_preference',
        'has_custom_risk',
        'beneficiary_id',
        'beneficiary_name',
        'has_flexibly_accessed',
        'flexible_access_date',
        'salary_sacrifice',
        'employer_ni_rebate_pct',
        // SP1 Pass 3 / PR 6 — derived columns
        'current_fund_value_gbp',
        'current_fund_value_gbp_calculated_at',
        'projected_value_at_retirement_gbp',
        'projected_value_at_retirement_gbp_calculated_at',
        'annual_contribution_gbp',
        'annual_contribution_gbp_calculated_at',
        'years_to_drawdown',
        'years_to_drawdown_calculated_at',
        'annual_allowance_used_gbp',
        'annual_allowance_used_gbp_calculated_at',
    ];

    /**
     * THE monthly contribution into this pension (Rule 20).
     *
     * A pension records its contributions in one of two ways — a flat monthly
     * amount, or employee/employer percentages of salary — and something has to
     * decide which wins when both are present. Two places decided, differently:
     * `RetirementStrategyService::calculateTotalContributions()` takes the flat
     * amount first and falls back to the percentages, while `/m`'s
     * `RetirementPensionDetail.vue` took the PERCENTAGES first and fell back to the
     * flat amount — so a pension holding both was described differently depending
     * which screen you were on.
     *
     * The flat amount wins, matching the backend: it is what the user actually
     * typed as their contribution, where the percentages may be a stale echo of a
     * salary that has since changed.
     *
     * Appended so every serialisation carries it and no client has to work it out —
     * CSJ, 2026-08-23: `/m` displays what the backend computed, it never calculates.
     *
     * @var list<string>
     */
    protected $appends = ['monthly_contribution'];

    public function getMonthlyContributionAttribute(): float
    {
        if ((float) ($this->monthly_contribution_amount ?? 0) > 0) {
            return round((float) $this->monthly_contribution_amount, 2);
        }

        $salary = (float) ($this->annual_salary ?? 0);
        if ($salary <= 0) {
            return 0.0;
        }

        $percent = (float) ($this->employee_contribution_percent ?? 0)
            + (float) ($this->employer_contribution_percent ?? 0);

        return round($salary * $percent / 100 / 12, 2);
    }

    protected $casts = [
        'current_fund_value' => 'decimal:2',
        'annual_salary' => 'decimal:2',
        'employee_contribution_percent' => 'decimal:2',
        'employer_contribution_percent' => 'decimal:2',
        'employer_matching_limit' => 'decimal:2',
        'monthly_contribution_amount' => 'decimal:2',
        'lump_sum_contribution' => 'decimal:2',
        'entered_allocation_baseline' => 'array',
        'entered_allocation_effective_at' => 'date',
        'platform_fee_percent' => 'decimal:4',
        'platform_fee_amount' => 'decimal:2',
        'advisor_fee_percent' => 'decimal:4',
        'retirement_age' => 'integer',
        'expected_return_percent' => 'decimal:2',
        'projected_value_at_retirement' => 'decimal:2',
        'has_custom_risk' => 'boolean',
        'has_flexibly_accessed' => 'boolean',
        'flexible_access_date' => 'date',
        'salary_sacrifice' => 'boolean',
        'employer_ni_rebate_pct' => 'decimal:4',
        // SP1 Pass 3 / PR 6 — derived columns
        'current_fund_value_gbp' => 'decimal:2',
        'current_fund_value_gbp_calculated_at' => 'datetime',
        'projected_value_at_retirement_gbp' => 'decimal:2',
        'projected_value_at_retirement_gbp_calculated_at' => 'datetime',
        'annual_contribution_gbp' => 'decimal:2',
        'annual_contribution_gbp_calculated_at' => 'datetime',
        'years_to_drawdown' => 'integer',
        'years_to_drawdown_calculated_at' => 'datetime',
        'annual_allowance_used_gbp' => 'decimal:2',
        'annual_allowance_used_gbp_calculated_at' => 'datetime',
    ];

    protected $hidden = ['member_number'];

    protected $attributes = [
        'has_custom_risk' => false,
    ];

    /**
     * Get the user that owns the DC pension.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the beneficiary user (if linked to an account).
     */
    public function beneficiary(): BelongsTo
    {
        return $this->belongsTo(User::class, 'beneficiary_id');
    }

    /**
     * Get all holdings for this DC pension (polymorphic relationship)
     */
    public function holdings(): MorphMany
    {
        return $this->morphMany(Holding::class, 'holdable');
    }

    public function valueSnapshots(): HasMany
    {
        return $this->hasMany(DCPensionValueSnapshot::class, 'dc_pension_id');
    }
}
