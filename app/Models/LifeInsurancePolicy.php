<?php

declare(strict_types=1);

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class LifeInsurancePolicy extends Model
{
    use Auditable, HasFactory, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'policy_type',
        'provider',
        'policy_number',
        'sum_assured',
        'start_value',
        'decreasing_rate',
        'premium_amount',
        'premium_frequency',
        'policy_start_date',
        'policy_end_date',
        'policy_term_years',
        'indexation_rate',
        'in_trust',
        'is_mortgage_protection',
        'beneficiaries',
        'joint_life',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'sum_assured' => 'decimal:2',
        'start_value' => 'decimal:2',
        'decreasing_rate' => 'decimal:4',
        'premium_amount' => 'decimal:2',
        'indexation_rate' => 'decimal:4',
        'policy_start_date' => 'date',
        'policy_end_date' => 'date',
        'policy_term_years' => 'integer',
        'in_trust' => 'boolean',
        'is_mortgage_protection' => 'boolean',
        'joint_life' => 'boolean',
    ];

    protected $hidden = ['policy_number'];

    /**
     * Get the user that owns the policy.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
