<?php

declare(strict_types=1);

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Retirement Profile Model
 *
 * Represents a user's retirement planning profile including target retirement age
 * and income requirements.
 */
class RetirementProfile extends Model
{
    use Auditable, HasFactory, SoftDeletes;

    protected $table = 'retirement_profiles';

    protected $fillable = [
        'user_id',
        'current_age',
        'target_retirement_age',
        'current_annual_salary',
        'target_retirement_income',
        'essential_expenditure',
        'lifestyle_expenditure',
        'life_expectancy',
        'spouse_life_expectancy',
        'care_cost_annual',
        'care_start_age',
        'prior_year_unused_allowance',
    ];

    protected $casts = [
        'current_age' => 'integer',
        'target_retirement_age' => 'integer',
        'current_annual_salary' => 'decimal:2',
        'target_retirement_income' => 'decimal:2',
        'essential_expenditure' => 'decimal:2',
        'lifestyle_expenditure' => 'decimal:2',
        'life_expectancy' => 'integer',
        'spouse_life_expectancy' => 'integer',
        'care_cost_annual' => 'decimal:2',
        'care_start_age' => 'integer',
        'prior_year_unused_allowance' => 'array',
    ];

    /**
     * Get the user that owns the retirement profile.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
