<?php

declare(strict_types=1);

namespace App\Models\Estate;

use App\Models\User;
use App\Traits\Auditable;
use App\Traits\HasJointOwnership;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Liability extends Model
{
    use Auditable, HasFactory, HasJointOwnership, SoftDeletes;

    protected $fillable = [
        'user_id',
        'ownership_type',
        'joint_owner_id',
        'trust_id',
        'liability_type',
        'country',
        'liability_name',
        'current_balance',
        'monthly_payment',
        'interest_rate',
        'maturity_date',
        'secured_against',
        'is_priority_debt',
        'mortgage_type',
        'fixed_until',
        'notes',
    ];

    protected $casts = [
        'current_balance' => 'float',
        'monthly_payment' => 'float',
        'interest_rate' => 'float',
        'maturity_date' => 'date',
        'is_priority_debt' => 'boolean',
        'fixed_until' => 'date',
    ];

    /**
     * User relationship
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Joint owner relationship (for joint liabilities)
     */
    public function jointOwner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'joint_owner_id');
    }

    /**
     * Trust relationship (for trust-owned liabilities)
     */
    public function trust(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Estate\Trust::class);
    }

    /**
     * Scope to a specific liability type.
     */
    public function scopeOfType($query, string $type)
    {
        return $query->where('liability_type', $type);
    }

    /**
     * Scope to priority debts only.
     */
    public function scopePriorityDebt($query)
    {
        return $query->where('is_priority_debt', true);
    }
}
