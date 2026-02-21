<?php

declare(strict_types=1);

namespace App\Models;

use App\Traits\HasJointOwnership;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class CashAccount extends Model
{
    use HasFactory, HasJointOwnership, SoftDeletes;

    protected $fillable = [
        'user_id',
        'household_id',
        'trust_id',
        'account_name',
        'institution_name',
        'account_number',
        'sort_code',
        'account_type',
        'purpose',
        'ownership_type',
        'country',
        'ownership_percentage',
        'joint_owner_id',
        'current_balance',
        'interest_rate',
        'rate_valid_until',
        'is_isa',
        'isa_subscription_current_year',
        'tax_year',
        'notes',
    ];

    protected $casts = [
        'rate_valid_until' => 'date',
        'current_balance' => 'decimal:2',
        'interest_rate' => 'decimal:4',
        'ownership_percentage' => 'decimal:2',
        'isa_subscription_current_year' => 'decimal:2',
        'is_isa' => 'boolean',
    ];

    /**
     * Get the user that owns this cash account.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the household this cash account belongs to (for joint ownership).
     */
    public function household(): BelongsTo
    {
        return $this->belongsTo(Household::class);
    }

    /**
     * Get the trust that holds this cash account (if applicable).
     */
    public function trust(): BelongsTo
    {
        return $this->belongsTo(Trust::class);
    }

    /**
     * Get the joint owner of this cash account.
     */
    public function jointOwner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'joint_owner_id');
    }
}
