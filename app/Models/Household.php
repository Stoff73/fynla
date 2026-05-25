<?php

declare(strict_types=1);

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Household extends Model
{
    use Auditable, HasFactory;

    protected $fillable = [
        'household_name',
        'notes',
    ];

    /**
     * Get the users in this household.
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /**
     * Get the family members in this household.
     */
    public function familyMembers(): HasMany
    {
        return $this->hasMany(FamilyMember::class);
    }

    /**
     * Get the properties owned by this household.
     */
    public function properties(): HasMany
    {
        return $this->hasMany(Property::class);
    }

    /**
     * Get the business interests owned by this household.
     */
    public function businessInterests(): HasMany
    {
        return $this->hasMany(BusinessInterest::class);
    }

    /**
     * Get the chattels owned by this household.
     */
    public function chattels(): HasMany
    {
        return $this->hasMany(Chattel::class);
    }

    /**
     * Get the cash accounts owned by this household.
     */
    public function cashAccounts(): HasMany
    {
        return $this->hasMany(CashAccount::class);
    }

    /**
     * Get the investment accounts owned by this household.
     */
    public function investmentAccounts(): HasMany
    {
        return $this->hasMany(InvestmentAccount::class);
    }

    /**
     * Get the trusts associated with this household.
     */
    public function trusts(): HasMany
    {
        return $this->hasMany(Trust::class);
    }
}
