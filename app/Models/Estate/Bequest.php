<?php

declare(strict_types=1);

namespace App\Models\Estate;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Bequest extends Model
{
    use HasFactory;

    protected $fillable = [
        'will_id',
        'user_id',
        'beneficiary_name',
        'beneficiary_user_id',
        'bequest_type',
        'percentage_of_estate',
        'specific_amount',
        'specific_asset_description',
        'asset_id',
        'priority_order',
        'conditions',
    ];

    protected $casts = [
        'percentage_of_estate' => 'decimal:2',
        'specific_amount' => 'decimal:2',
        'priority_order' => 'integer',
    ];

    /**
     * Get the will that this bequest belongs to
     */
    public function will(): BelongsTo
    {
        return $this->belongsTo(Will::class);
    }

    /**
     * Get the user that created this bequest
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the beneficiary user if applicable
     */
    public function beneficiaryUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'beneficiary_user_id');
    }
}
