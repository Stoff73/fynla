<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\AwardsDataEntryPoints;
use App\Traits\Auditable;
use App\Traits\HasJointOwnership;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Crypt;

class SavingsAccount extends Model
{
    use Auditable, AwardsDataEntryPoints, HasFactory, HasJointOwnership, SoftDeletes;

    public function gamificationCategory(): string
    {
        return 'savings_account';
    }

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
        'joint_owner_name',
        'trust_id',
        // Junior ISA beneficiary fields
        'beneficiary_id',
        'beneficiary_name',
        'beneficiary_dob',
        // Retirement planning
        'include_in_retirement',
        // Canonical derived columns (sub-project 1, pass 1 — materialised by SavingsStore)
        'balance_gbp',
        'balance_gbp_calculated_at',
        'annual_interest_projected_gbp',
        'annual_interest_projected_gbp_calculated_at',
        'isa_allowance_used_pct',
        'isa_allowance_used_pct_calculated_at',
    ];

    protected $hidden = [
        'account_number',
    ];

    /**
     * The interest this account earns in a year at its stated rate (Rule 20).
     *
     * `/m`'s `SavingsAccount.vue` computed `balance * (rate / 100)` and
     * `annualInterest / 12` in Vue computed properties. CSJ, 2026-08-23: `/m`
     * displays what the backend computed, it never works anything out — a client
     * that calculates is a second answer waiting to disagree, and the Personal
     * Savings Allowance work already reads an `annual_interest` of its own.
     *
     * Simple interest on the CURRENT balance, deliberately: it answers "what is this
     * account earning me", not "what will it be worth" — compounding, contributions
     * and rate changes belong to the projection services, not to a balance readout.
     *
     * @var list<string>
     */
    protected $appends = ['annual_interest', 'monthly_interest'];

    /**
     * The one predicate for "is this a Junior ISA" (Rule 20). Seeded and
     * form-created rows carry it in account_type; older rows carry it in
     * isa_type as either "junior" or "junior_isa".
     */
    public function isJuniorIsa(): bool
    {
        return $this->account_type === 'junior_isa'
            || in_array((string) $this->isa_type, ['junior', 'junior_isa'], true);
    }

    public function getAnnualInterestAttribute(): float
    {
        return round((float) ($this->current_balance ?? 0) * ((float) ($this->interest_rate ?? 0) / 100), 2);
    }

    public function getMonthlyInterestAttribute(): float
    {
        return round($this->annual_interest / 12, 2);
    }

    /** @var array<string, string> account_type => how the product is named to the user */
    private const TYPE_LABELS = [
        'current_account' => 'Current Account',
        'business_current' => 'Business Current Account',
        'business_savings' => 'Business Savings',
        'savings' => 'Savings Account',
        'easy_access' => 'Easy Access Savings',
        'instant_access' => 'Instant Access Savings',
        'notice' => 'Notice Account',
        'fixed_term' => 'Fixed Term Savings',
        'fixed_rate' => 'Fixed Rate Savings',
        'cash_isa' => 'Cash ISA',
        'junior_isa' => 'Junior ISA',
        'lifetime_isa' => 'Lifetime ISA',
        'premium_bonds' => 'Premium Bonds',
        'nsi_savings' => 'NS&I Savings',
    ];

    /**
     * What the account is called wherever a recommendation or row names it:
     * the user's own name for it, else institution + product ("HSBC Current
     * Account"). The web form captures institution and product but no name,
     * so the fallback is the common case there.
     */
    public function getDisplayNameAttribute(): string
    {
        $name = trim((string) ($this->account_name ?? ''));
        if ($name !== '') {
            return $name;
        }

        $type = (string) ($this->account_type ?? '');
        $product = self::TYPE_LABELS[$type] ?? ucwords(str_replace('_', ' ', $type));
        $label = trim(trim((string) ($this->institution ?? '')).' '.$product);

        return $label !== '' ? $label : 'Unnamed account';
    }

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
        // Canonical derived columns (sub-project 1, pass 1)
        'balance_gbp' => 'decimal:2',
        'balance_gbp_calculated_at' => 'datetime',
        'annual_interest_projected_gbp' => 'decimal:2',
        'annual_interest_projected_gbp_calculated_at' => 'datetime',
        'isa_allowance_used_pct' => 'decimal:2',
        'isa_allowance_used_pct_calculated_at' => 'datetime',
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
        return $this->belongsTo(User::class, 'joint_owner_id')->withTrashed();
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
                } catch (DecryptException $e) {
                    return $value;
                }
            },
            set: fn (?string $value) => $value ? Crypt::encryptString($value) : null,
        );
    }
}
