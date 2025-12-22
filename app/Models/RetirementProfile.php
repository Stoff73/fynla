<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Retirement Profile Model
 *
 * Represents a user's retirement planning profile including target retirement age
 * and income requirements.
 *
 * @property string|null $risk_tolerance DEPRECATED. Use RiskPreferenceService::getRiskProfile() for user's main risk level.
 *                                       This field is kept for backward compatibility only.
 *                                       New code should use the Risk module (RiskProfile model via RiskPreferenceService).
 */
class RetirementProfile extends Model
{
    use HasFactory;

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
        'risk_tolerance',
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
    ];

    /**
     * Get the user that owns the retirement profile.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
