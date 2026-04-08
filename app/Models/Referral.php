<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Referral extends Model
{
    protected $fillable = [
        'referrer_id',
        'referee_id',
        'referral_code',
        'referee_email',
        'status',
        'bonus_applied',
        'referred_at',
        'registered_at',
        'converted_at',
    ];

    protected $casts = [
        'bonus_applied' => 'boolean',
        'referred_at' => 'datetime',
        'registered_at' => 'datetime',
        'converted_at' => 'datetime',
    ];

    public function referrer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'referrer_id');
    }

    public function referee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'referee_id');
    }
}
