<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Investment\Holding;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * DC Pension Model
 *
 * Represents a Defined Contribution pension scheme (workplace, SIPP, or personal pension).
 */
class DCPension extends Model
{
    use HasFactory;

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
        'retirement_age',
        'expected_return_percent',
        'projected_value_at_retirement',
        'risk_preference',
        'has_custom_risk',
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
        'retirement_age' => 'integer',
        'expected_return_percent' => 'decimal:2',
        'projected_value_at_retirement' => 'decimal:2',
        'has_custom_risk' => 'boolean',
    ];

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
     * Get all holdings for this DC pension (polymorphic relationship)
     */
    public function holdings(): MorphMany
    {
        return $this->morphMany(Holding::class, 'holdable');
    }
}
