<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Immutable record of the award that reached a planning level. */
class UserLevelCrossing extends Model
{
    protected $fillable = ['user_id', 'level', 'point_award_id', 'reached_at'];

    protected $casts = [
        'level' => 'integer',
        'reached_at' => 'datetime',
    ];

    public function pointAward(): BelongsTo
    {
        return $this->belongsTo(PointAward::class);
    }
}
