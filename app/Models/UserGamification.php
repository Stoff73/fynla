<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserGamification extends Model
{
    protected $table = 'user_gamification';

    protected $fillable = [
        'user_id', 'total_points', 'level', 'pending_celebration_level',
        'last_login_award_date', 'login_streak_days', 'streak_started_on',
    ];

    protected $casts = [
        'total_points' => 'integer',
        'level' => 'integer',
        'pending_celebration_level' => 'integer',
        'last_login_award_date' => 'date',
        'login_streak_days' => 'integer',
        'streak_started_on' => 'date',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
