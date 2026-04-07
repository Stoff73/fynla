<?php

declare(strict_types=1);

namespace App\Models\Estate;

use App\Models\User;
use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Will extends Model
{
    use Auditable, HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id',
        'has_will',
        'spouse_primary_beneficiary',
        'spouse_bequest_percentage',
        'executor_name',
        'executor_notes',
        'will_last_updated',
        'last_reviewed_date',
        'will_document_id',
    ];

    protected $casts = [
        'has_will' => 'boolean',
        'spouse_primary_beneficiary' => 'boolean',
        'spouse_bequest_percentage' => 'decimal:2',
        'will_last_updated' => 'date',
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
     * Get the generated will document (from Will Builder)
     */
    public function willDocument(): BelongsTo
    {
        return $this->belongsTo(WillDocument::class);
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
