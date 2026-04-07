<?php

declare(strict_types=1);

namespace App\Models\Estate;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LpaAttorney extends Model
{
    use Auditable, HasFactory;

    protected $fillable = [
        'lasting_power_of_attorney_id',
        'attorney_type',
        'full_name',
        'date_of_birth',
        'address_line_1',
        'address_line_2',
        'address_city',
        'address_county',
        'address_postcode',
        'relationship_to_donor',
        'sort_order',
    ];

    protected $casts = [
        'date_of_birth' => 'date',
        'sort_order' => 'integer',
    ];

    public function lastingPowerOfAttorney(): BelongsTo
    {
        return $this->belongsTo(LastingPowerOfAttorney::class, 'lasting_power_of_attorney_id');
    }

    /**
     * Scope for primary attorneys.
     */
    public function scopePrimary(Builder $query): Builder
    {
        return $query->where('attorney_type', 'primary');
    }

    /**
     * Scope for replacement attorneys.
     */
    public function scopeReplacement(Builder $query): Builder
    {
        return $query->where('attorney_type', 'replacement');
    }
}
