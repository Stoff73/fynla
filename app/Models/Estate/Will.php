<?php

declare(strict_types=1);

namespace App\Models\Estate;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Will extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'has_will',
        'death_scenario',
        'spouse_primary_beneficiary',
        'spouse_bequest_percentage',
        'executor_notes',
        'last_reviewed_date',
    ];

    protected $casts = [
        'has_will' => 'boolean',
        'spouse_primary_beneficiary' => 'boolean',
        'spouse_bequest_percentage' => 'decimal:2',
        'last_reviewed_date' => 'date',
    ];

    /**
     * Get the user that owns the will
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get all bequests for this will
     */
    public function bequests(): HasMany
    {
        return $this->hasMany(Bequest::class);
    }

    /**
     * Check if this will involves simultaneous death scenario
     */
    public function isBothDying(): bool
    {
        return $this->death_scenario === 'both_simultaneous';
    }

    /**
     * Get total percentage allocated to non-spouse beneficiaries
     */
    public function getNonSpouseAllocationPercentage(): float
    {
        return $this->bequests()
            ->where('bequest_type', 'percentage')
            ->sum('percentage_of_estate');
    }
}
