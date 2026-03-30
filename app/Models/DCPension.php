<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Investment\Holding;
use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * DC Pension Model
 *
 * Represents a Defined Contribution pension scheme (workplace, SIPP, or personal pension).
 */
class DCPension extends Model
{
    use Auditable, HasFactory, SoftDeletes;

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
    ];

    protected $casts = [
        'current_fund_value' => 'decimal:2',
        'annual_salary' => 'decimal:2',
        'employee_contribution_percent' => 'decimal:2',
        'employer_contribution_percent' => 'decimal:2',
        'employer_matching_limit' => 'decimal:2',
        'monthly_contribution_amount' => 'decimal:2',
        'lump_sum_contribution' => 'decimal:2',
        'platform_fee_percent' => 'decimal:4',
        'platform_fee_amount' => 'decimal:2',
        'advisor_fee_percent' => 'decimal:4',
        'retirement_age' => 'integer',
        'expected_return_percent' => 'decimal:2',
        'projected_value_at_retirement' => 'decimal:2',
        'has_custom_risk' => 'boolean',
        'has_flexibly_accessed' => 'boolean',
        'flexible_access_date' => 'date',
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
}
