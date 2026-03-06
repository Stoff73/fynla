<?php

declare(strict_types=1);

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProtectionProfile extends Model
{
    use Auditable, HasFactory;

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
    ];

    /**
     * Get the user that owns the profile.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
