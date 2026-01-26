<?php

declare(strict_types=1);

namespace App\Models;

use App\Traits\Auditable;
use App\Traits\HasJointOwnership;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Crypt;

class SavingsAccount extends Model
{
    use Auditable, HasFactory, HasJointOwnership;

    protected $fillable = [
        'user_id',
        'account_type',
        'institution',
        'account_number',
        'current_balance',
        'interest_rate',
        'access_type',
        'notice_period_days',
        'maturity_date',
        'is_emergency_fund',
        'is_isa',
        'country',
        'isa_type',
        'isa_subscription_year',
        'isa_subscription_amount',
        // Ownership fields
        'ownership_type',
        'ownership_percentage',
        'joint_owner_id',
        'trust_id',
        // Junior ISA beneficiary fields
        'beneficiary_id',
        'beneficiary_name',
        'beneficiary_dob',
    ];

    protected $casts = [
        'current_balance' => 'decimal:2',
        'interest_rate' => 'decimal:4',
        'notice_period_days' => 'integer',
        'maturity_date' => 'date',
        'is_emergency_fund' => 'boolean',
        'is_isa' => 'boolean',
        'isa_subscription_amount' => 'decimal:2',
        'beneficiary_dob' => 'date',
    ];

    /**
     * User relationship
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Beneficiary relationship (for Junior ISAs)
     */
    public function beneficiary(): BelongsTo
    {
        return $this->belongsTo(FamilyMember::class, 'beneficiary_id');
    }

    /**
     * Encrypted account number accessor
     */
    protected function accountNumber(): Attribute
    {
        return Attribute::make(
            get: fn (?string $value) => $value ? Crypt::decryptString($value) : null,
            set: fn (?string $value) => $value ? Crypt::encryptString($value) : null,
        );
    }
}
