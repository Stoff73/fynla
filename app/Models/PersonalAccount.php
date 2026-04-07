<?php

declare(strict_types=1);

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PersonalAccount extends Model
{
    use Auditable, HasFactory;

    protected $fillable = [
        'user_id',
        'account_type',
        'period_start',
        'period_end',
        'line_item',
        'category',
        'amount',
        'notes',
    ];

    protected $casts = [
        'period_start' => 'date',
        'period_end' => 'date',
        'amount' => 'decimal:2',
    ];

    /**
     * Get the user that owns this personal account entry.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
