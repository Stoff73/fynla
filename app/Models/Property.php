<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Estate\Trust;
use App\Traits\Auditable;
use App\Traits\HasJointOwnership;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Property extends Model
{
    use Auditable, HasFactory, HasJointOwnership;

    protected $fillable = [
        'user_id',
        'household_id',
        'trust_id',
        'property_type',
        'ownership_type',
        'joint_ownership_type',
        'joint_owner_id',
        'joint_owner_name',
        'trust_name',
        'tenure_type',
        'lease_remaining_years',
        'lease_expiry_date',
        'country',
        'ownership_percentage',
        'address_line_1',
        'address_line_2',
        'city',
        'county',
        'postcode',
        'purchase_date',
        'purchase_price',
        'current_value',
        'valuation_date',
        'sdlt_paid',
        'monthly_rental_income',
        'outstanding_mortgage',
        'tenant_name',
        'tenant_email',
        'managing_agent_name',
        'managing_agent_company',
        'managing_agent_email',
        'managing_agent_phone',
        'managing_agent_fee',
        'lease_start_date',
        'lease_end_date',
        'monthly_council_tax',
        'monthly_gas',
        'monthly_electricity',
        'monthly_water',
        'monthly_building_insurance',
        'monthly_contents_insurance',
        'monthly_service_charge',
        'monthly_maintenance_reserve',
        'other_monthly_costs',
        'notes',
    ];

    protected $casts = [
        'purchase_date' => 'date',
        'valuation_date' => 'date',
        'lease_start_date' => 'date',
        'lease_end_date' => 'date',
        'lease_expiry_date' => 'date',
        'purchase_price' => 'decimal:2',
        'current_value' => 'decimal:2',
        'sdlt_paid' => 'decimal:2',
        'monthly_rental_income' => 'decimal:2',
        'outstanding_mortgage' => 'decimal:2',
        'managing_agent_fee' => 'decimal:2',
        'monthly_council_tax' => 'decimal:2',
        'monthly_gas' => 'decimal:2',
        'monthly_electricity' => 'decimal:2',
        'monthly_water' => 'decimal:2',
        'monthly_building_insurance' => 'decimal:2',
        'monthly_contents_insurance' => 'decimal:2',
        'monthly_service_charge' => 'decimal:2',
        'monthly_maintenance_reserve' => 'decimal:2',
        'other_monthly_costs' => 'decimal:2',
        'ownership_percentage' => 'decimal:2',
        'lease_remaining_years' => 'integer',
    ];

    /**
     * Accessors to append to model's array form.
     */
    protected $appends = [
        'equity',
    ];

    /**
     * Get the user that owns this property.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the household this property belongs to (for joint ownership).
     */
    public function household(): BelongsTo
    {
        return $this->belongsTo(Household::class);
    }

    /**
     * Get the trust that holds this property (if applicable).
     */
    public function trust(): BelongsTo
    {
        return $this->belongsTo(Trust::class);
    }

    /**
     * Get the mortgages associated with this property.
     */
    public function mortgages(): HasMany
    {
        return $this->hasMany(Mortgage::class);
    }

    /**
     * Get the joint owner (if property is jointly owned and linked to system user).
     */
    public function jointOwner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'joint_owner_id');
    }

    /**
     * Get the joint owner display name (from linked user or free text).
     */
    public function getJointOwnerDisplayNameAttribute(): ?string
    {
        if ($this->ownership_type !== 'joint') {
            return null;
        }

        return $this->jointOwner?->name ?? $this->joint_owner_name;
    }

    /**
     * Get the trust display name (from linked trust or free text).
     */
    public function getTrustDisplayNameAttribute(): ?string
    {
        if ($this->ownership_type !== 'trust') {
            return null;
        }

        return $this->trust?->trust_name ?? $this->trust_name;
    }

    /**
     * Check if property is leasehold and approaching end of term.
     * UK government phasing out leaseholds for new builds.
     */
    public function isLeaseholdExpiringAttribute(): bool
    {
        if ($this->tenure_type !== 'leasehold') {
            return false;
        }

        // Properties with less than 80 years are harder to mortgage
        // Properties with less than 60 years significantly lose value
        return $this->lease_remaining_years !== null && $this->lease_remaining_years < 80;
    }

    /**
     * Get ownership type description with context.
     */
    public function getOwnershipDescriptionAttribute(): string
    {
        $description = match ($this->ownership_type) {
            'individual' => 'Individual ownership',
            'joint' => 'Joint ownership',
            'trust' => 'Trust ownership',
            default => ucfirst($this->ownership_type ?? 'Unknown'),
        };

        if ($this->ownership_type === 'joint' && $this->joint_ownership_type) {
            $jointType = match ($this->joint_ownership_type) {
                'joint_tenancy' => 'Joint Tenancy (equal rights, passes to survivor)',
                'tenants_in_common' => 'Tenants in Common (specified shares, passes via will)',
                default => ucfirst(str_replace('_', ' ', $this->joint_ownership_type)),
            };
            $description .= ' - '.$jointType;
        }

        return $description;
    }

    /**
     * Calculate equity for this property.
     *
     * IMPORTANT: Both current_value and mortgage balances are already stored as the user's share
     * in the database (divided by ownership_percentage when saving). Therefore, we do NOT
     * multiply by ownership_percentage here - that would divide the equity in half again.
     *
     * Equity = current_value - sum(all mortgages for this property)
     */
    public function getEquityAttribute(): float
    {
        $currentValue = (float) ($this->current_value ?? 0);

        // Sum all mortgages for this property (already user's share from database)
        $totalMortgages = (float) $this->mortgages->sum('outstanding_balance');

        // Fallback to outstanding_mortgage field if mortgages relationship not loaded
        if ($totalMortgages === 0.0 && $this->outstanding_mortgage) {
            $totalMortgages = (float) $this->outstanding_mortgage;
        }

        return $currentValue - $totalMortgages;
    }
}
