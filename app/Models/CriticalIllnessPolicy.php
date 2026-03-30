<?php

declare(strict_types=1);

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class CriticalIllnessPolicy extends Model
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
        'premium_amount',
        'premium_frequency',
        'policy_start_date',
        'policy_term_years',
        'conditions_covered',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'sum_assured' => 'decimal:2',
        'premium_amount' => 'decimal:2',
        'policy_start_date' => 'date',
        'policy_term_years' => 'integer',
        'conditions_covered' => 'array',
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
