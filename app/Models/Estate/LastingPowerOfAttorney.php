<?php

declare(strict_types=1);

namespace App\Models\Estate;

use App\Models\Document;
use App\Models\User;
use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class LastingPowerOfAttorney extends Model
{
    use Auditable, HasFactory, SoftDeletes;

    protected $table = 'lasting_powers_of_attorney';

    protected $fillable = [
        'user_id',
        'lpa_type',
        'status',
        'source',
        'donor_full_name',
        'donor_date_of_birth',
        'donor_address_line_1',
        'donor_address_line_2',
        'donor_address_city',
        'donor_address_county',
        'donor_address_postcode',
        'attorney_decision_type',
        'jointly_for_some_details',
        'when_attorneys_can_act',
        'preferences',
        'instructions',
        'life_sustaining_treatment',
        'certificate_provider_name',
        'certificate_provider_address',
        'certificate_provider_relationship',
        'certificate_provider_known_years',
        'certificate_provider_professional_details',
        'registration_date',
        'opg_reference',
        'is_registered_with_opg',
        'document_id',
        'notes',
        'completed_at',
    ];

    protected $casts = [
        'donor_date_of_birth' => 'date',
        'registration_date' => 'date',
        'is_registered_with_opg' => 'boolean',
        'certificate_provider_known_years' => 'integer',
        'completed_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function attorneys(): HasMany
    {
        return $this->hasMany(LpaAttorney::class, 'lasting_power_of_attorney_id')
            ->orderBy('sort_order');
    }

    public function notificationPersons(): HasMany
    {
        return $this->hasMany(LpaNotificationPerson::class, 'lasting_power_of_attorney_id')
            ->orderBy('sort_order');
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    /**
     * Scope to filter by user.
     */
    public function scopeForUser(Builder $query, int $userId): Builder
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Scope for Property & Financial Affairs LPAs.
     */
    public function scopePropertyFinancial(Builder $query): Builder
    {
        return $query->where('lpa_type', 'property_financial');
    }

    /**
     * Scope for Health & Welfare LPAs.
     */
    public function scopeHealthWelfare(Builder $query): Builder
    {
        return $query->where('lpa_type', 'health_welfare');
    }

    /**
     * Scope for registered LPAs.
     */
    public function scopeRegistered(Builder $query): Builder
    {
        return $query->where('status', 'registered');
    }

    /**
     * Get primary attorneys only.
     */
    public function primaryAttorneys(): HasMany
    {
        return $this->attorneys()->where('attorney_type', 'primary');
    }

    /**
     * Get replacement attorneys only.
     */
    public function replacementAttorneys(): HasMany
    {
        return $this->attorneys()->where('attorney_type', 'replacement');
    }

    /**
     * Check if this is a Property & Financial Affairs LPA.
     */
    public function isPropertyFinancial(): bool
    {
        return $this->lpa_type === 'property_financial';
    }

    /**
     * Check if this is a Health & Welfare LPA.
     */
    public function isHealthWelfare(): bool
    {
        return $this->lpa_type === 'health_welfare';
    }
}
