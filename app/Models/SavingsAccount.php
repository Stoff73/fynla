<?php

declare(strict_types=1);

namespace App\Models;

use App\Traits\Auditable;
use App\Traits\HasJointOwnership;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Crypt;

class SavingsAccount extends Model
{
    use Auditable, HasFactory, HasJointOwnership, SoftDeletes;

    protected $fillable = [
        'user_id',
        'account_name',
        'account_type',
        'institution',
        'account_number',
        'current_balance',
        'interest_rate',
        'rate_valid_until',
        'access_type',
        'notice_period_days',
        'maturity_date',
        'is_emergency_fund',
        'is_isa',
        'country',
        'isa_type',
        'isa_subscription_year',
        'isa_subscription_amount',
        // ISA regular contribution fields
        'regular_contribution_amount',
        'contribution_frequency',
        'planned_lump_sum_amount',
        'planned_lump_sum_date',
        // Ownership fields
        'ownership_type',
        'ownership_percentage',
        'joint_owner_id',
        'trust_id',
        // Junior ISA beneficiary fields
        'beneficiary_id',
        'beneficiary_name',
        'beneficiary_dob',
        // Retirement planning
        'include_in_retirement',
    ];

    protected $hidden = [
        'account_number',
    ];

    protected $casts = [
        'current_balance' => 'decimal:2',
        'interest_rate' => 'decimal:4',
        'rate_valid_until' => 'date',
        'notice_period_days' => 'integer',
        'maturity_date' => 'date',
        'is_emergency_fund' => 'boolean',
        'is_isa' => 'boolean',
        'isa_subscription_amount' => 'decimal:2',
        'regular_contribution_amount' => 'decimal:2',
        'planned_lump_sum_amount' => 'decimal:2',
        'planned_lump_sum_date' => 'date',
        'beneficiary_dob' => 'date',
        'include_in_retirement' => 'boolean',
    ];

    /**
     * User relationship
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Joint owner relationship
     */
    public function jointOwner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'joint_owner_id');
    }

    /**
     * Beneficiary relationship (for Junior ISAs)
     */
    public function beneficiary(): BelongsTo
    {
        return $this->belongsTo(FamilyMember::class, 'beneficiary_id');
    }

    /**
     * Goals linked to this savings account via pivot table.
     */
    public function goals(): BelongsToMany
    {
        return $this->belongsToMany(Goal::class, 'goal_savings_account')
            ->withPivot('allocated_amount', 'is_primary', 'priority_rank')
            ->withTimestamps();
    }

    /**
     * Encrypted account number accessor
     */
    protected function accountNumber(): Attribute
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
