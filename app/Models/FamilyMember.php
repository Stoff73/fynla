<?php

declare(strict_types=1);

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Crypt;

class FamilyMember extends Model
{
    use Auditable, HasFactory, SoftDeletes;

    protected $hidden = [
        'national_insurance_number',
    ];

    protected $fillable = [
        'user_id',
        'household_id',
        'relationship',
        'name',
        'first_name',
        'middle_name',
        'last_name',
        'date_of_birth',
        'gender',
        // SECURITY: national_insurance_number intentionally excluded from $fillable.
        // Set explicitly in FamilyMembersController to prevent mass assignment of PII.
        'annual_income',
        'is_dependent',
        'education_status',
        'receives_child_benefit',
        'linked_user_id',
        'notes',
    ];

    protected $casts = [
        'date_of_birth' => 'date',
        'annual_income' => 'decimal:2',
        'is_dependent' => 'boolean',
        'receives_child_benefit' => 'boolean',
    ];

    /**
     * Append the computed age so it always appears in toArray / toJson output.
     * Without this, API consumers and tests that expect `family_member.age`
     * see nothing even when date_of_birth is populated correctly (B-3).
     *
     * @var list<string>
     */
    protected $appends = ['age'];

    /**
     * Get the user that owns this family member record.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the household this family member belongs to.
     */
    public function household(): BelongsTo
    {
        return $this->belongsTo(Household::class);
    }

    /**
     * Get the linked user account (for spouse records that map to a real user).
     */
    public function linkedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'linked_user_id');
    }

    /**
     * B-3 — computed age from date_of_birth. Returns null when DOB is
     * missing. Uses diffInYears (not floor(diffInMonths/12)) so leap
     * years and month boundaries work correctly.
     */
    public function getAgeAttribute(): ?int
    {
        if ($this->date_of_birth === null) {
            return null;
        }

        return (int) $this->date_of_birth->diffInYears(now());
    }

    /**
     * Accessor: Get the full name from name parts (for backward compatibility)
     */
    public function getFullNameAttribute(): string
    {
        $parts = array_filter([
            $this->first_name,
            $this->middle_name,
            $this->last_name,
        ]);

        return implode(' ', $parts);
    }

    /**
     * Encrypted national insurance number accessor
     */
    protected function nationalInsuranceNumber(): Attribute
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
