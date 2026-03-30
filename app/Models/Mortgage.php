<?php

declare(strict_types=1);

namespace App\Models;

use App\Traits\Auditable;
use App\Traits\HasJointOwnership;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Crypt;

class Mortgage extends Model
{
    use Auditable, HasFactory, HasJointOwnership, SoftDeletes;

    protected $fillable = [
        'property_id',
        'country',
        'user_id',
        'lender_name',
        'mortgage_account_number',
        'mortgage_type',
        'repayment_percentage',
        'interest_only_percentage',
        'original_loan_amount',
        'outstanding_balance',
        'interest_rate',
        'rate_type',
        'fixed_rate_percentage',
        'variable_rate_percentage',
        'fixed_interest_rate',
        'variable_interest_rate',
        'rate_fix_end_date',
        'monthly_payment',
        'monthly_interest_portion',
        'start_date',
        'maturity_date',
        'remaining_term_months',
        'ownership_type',
        'ownership_percentage',
        'joint_owner_id',
        'joint_owner_name',
        'notes',
    ];

    protected $hidden = [
        'mortgage_account_number',
    ];

    protected $casts = [
        'rate_fix_end_date' => 'date',
        'start_date' => 'date',
        'maturity_date' => 'date',
        'original_loan_amount' => 'decimal:2',
        'outstanding_balance' => 'decimal:2',
        'interest_rate' => 'decimal:4',
        'repayment_percentage' => 'decimal:2',
        'interest_only_percentage' => 'decimal:2',
        'fixed_rate_percentage' => 'decimal:2',
        'variable_rate_percentage' => 'decimal:2',
        'fixed_interest_rate' => 'decimal:4',
        'variable_interest_rate' => 'decimal:4',
        'monthly_payment' => 'decimal:2',
        'monthly_interest_portion' => 'decimal:2',
        'remaining_term_months' => 'integer',
        'ownership_percentage' => 'decimal:2',
    ];

    /**
     * Get the property this mortgage is for.
     */
    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    /**
     * Get the user that owns this mortgage.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Scope to a specific mortgage type.
     */
    public function scopeOfType($query, string $type)
    {
        return $query->where('mortgage_type', $type);
    }

    /**
     * Encrypted mortgage account number accessor
     */
    protected function mortgageAccountNumber(): Attribute
    {
        return Attribute::make(
            get: function (?string $value) {
                if (! $value) {
                    return null;
                }
                try {
                    return Crypt::decryptString($value);
                } catch (\Illuminate\Contracts\Encryption\DecryptException $e) {
                    return $value;
                }
            },
            set: fn (?string $value) => $value ? Crypt::encryptString($value) : null,
        );
    }
}
