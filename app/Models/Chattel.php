<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Estate\Trust;
use App\Traits\Auditable;
use App\Traits\HasJointOwnership;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Chattel extends Model
{
    use Auditable, HasFactory, HasJointOwnership, SoftDeletes;

    protected $fillable = [
        'user_id',
        'joint_owner_id',
        'joint_owner_name',
        'household_id',
        'trust_id',
        'chattel_type',
        'name',
        'description',
        'ownership_type',
        'country',
        'ownership_percentage',
        'purchase_price',
        'purchase_date',
        'current_value',
        'valuation_date',
        'make',
        'model',
        'year',
        'registration_number',
        'notes',
    ];

    protected $casts = [
        'purchase_date' => 'date',
        'valuation_date' => 'date',
        'purchase_price' => 'decimal:2',
        'current_value' => 'decimal:2',
        'ownership_percentage' => 'decimal:2',
        'year' => 'integer',
    ];

    /**
     * Get the user that owns this chattel.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the joint owner for this chattel.
     */
    public function jointOwner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'joint_owner_id');
    }

    /**
     * Get the household this chattel belongs to (for joint ownership).
     */
    public function household(): BelongsTo
    {
        return $this->belongsTo(Household::class);
    }

    /**
     * Get the trust that holds this chattel (if applicable).
     */
    public function trust(): BelongsTo
    {
        return $this->belongsTo(Trust::class);
    }
}
