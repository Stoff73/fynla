<?php

declare(strict_types=1);

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LifeEventAllocation extends Model
{
    use Auditable;

    protected $fillable = [
        'life_event_id',
        'user_id',
        'allocation_type',
        'allocation_step',
        'account_type',
        'account_id',
        'account_label',
        'suggested_amount',
        'amount',
        'enabled',
        'rationale',
        'display_order',
    ];

    protected $casts = [
        'suggested_amount' => 'decimal:2',
        'amount' => 'decimal:2',
        'enabled' => 'boolean',
        'display_order' => 'integer',
    ];

    public function lifeEvent(): BelongsTo
    {
        return $this->belongsTo(LifeEvent::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
