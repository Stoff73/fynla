<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A financial milestone a user has crossed (net-worth threshold or goal
 * progress), recorded once so the celebratory share prompt fires a single time.
 */
class UserMilestone extends Model
{
    protected $fillable = [
        'user_id',
        'milestone_type',
        'reference_id',
        'threshold',
        'achieved_at',
        'shared_at',
    ];

    protected $casts = [
        'threshold' => 'decimal:2',
        'achieved_at' => 'datetime',
        'shared_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
