<?php

declare(strict_types=1);

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class DisabilityPolicy extends Model
{
    use Auditable, HasFactory, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'provider',
        'policy_number',
        'benefit_amount',
        'benefit_frequency',
        'deferred_period_weeks',
        'benefit_period_months',
        'premium_amount',
        'premium_frequency',
        'occupation_class',
        'policy_start_date',
        'policy_term_years',
        'coverage_type',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'benefit_amount' => 'decimal:2',
        'premium_amount' => 'decimal:2',
        'deferred_period_weeks' => 'integer',
        'benefit_period_months' => 'integer',
        'policy_start_date' => 'date',
        'policy_term_years' => 'integer',
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
