<?php

declare(strict_types=1);

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProtectionProfile extends Model
{
    use Auditable, HasFactory, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'annual_income',
        'monthly_expenditure',
        'mortgage_balance',
        'other_debts',
        'number_of_dependents',
        'dependents_ages',
        'retirement_age',
        'occupation',
        'smoker_status',
        'health_status',
        'has_no_policies',
        'death_in_service_multiple',
        'group_ip_benefit_percent',
        'group_ip_benefit_months',
        'group_ip_definition',
        'group_ci_amount',
        'has_employer_pmi',
        'employer_name',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'annual_income' => 'float',
        'monthly_expenditure' => 'float',
        'mortgage_balance' => 'float',
        'other_debts' => 'float',
        'number_of_dependents' => 'integer',
        'dependents_ages' => 'array',
        'retirement_age' => 'integer',
        'smoker_status' => 'boolean',
        'has_no_policies' => 'boolean',
        'death_in_service_multiple' => 'float',
        'group_ip_benefit_percent' => 'float',
        'group_ip_benefit_months' => 'integer',
        'group_ci_amount' => 'float',
        'has_employer_pmi' => 'boolean',
    ];

    /**
     * Get the user that owns the profile.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
