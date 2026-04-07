<?php

declare(strict_types=1);

namespace App\Models\Estate;

use App\Models\User;
use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Asset extends Model
{
    use Auditable, HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id',
        'asset_type',
        'asset_name',
        'current_value',
        'liquidity',
        'is_giftable',
        'not_giftable_reason',
        'is_main_residence',
        'ownership_type',
        'beneficiary_designation',
        'is_iht_exempt',
        'exemption_reason',
        'valuation_date',
    ];

    protected $casts = [
        'current_value' => 'float',
        'is_iht_exempt' => 'boolean',
        'is_giftable' => 'boolean',
        'is_main_residence' => 'boolean',
        'valuation_date' => 'date',
    ];

    /**
     * User relationship
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
