<?php

declare(strict_types=1);

namespace App\Models\Estate;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Will extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id',
        'has_will',
        'spouse_primary_beneficiary',
        'spouse_bequest_percentage',
        'executor_name',
        'executor_notes',
        'will_last_updated',
    ];

    protected $casts = [
        'has_will' => 'boolean',
        'spouse_primary_beneficiary' => 'boolean',
        'spouse_bequest_percentage' => 'decimal:2',
        'will_last_updated' => 'date',
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
     * Get total percentage allocated to non-spouse beneficiaries
     */
    public function getNonSpouseAllocationPercentage(): float
    {
        return $this->bequests()
            ->where('bequest_type', 'percentage')
            ->sum('percentage_of_estate');
    }
}
