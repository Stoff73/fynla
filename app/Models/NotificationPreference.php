<?php

declare(strict_types=1);

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NotificationPreference extends Model
{
    use Auditable, HasFactory;

    protected $fillable = [
        'user_id',
        'policy_renewals',
        'goal_milestones',
        'contribution_reminders',
        'market_updates',
        'fyn_daily_insight',
        'security_alerts',
        'payment_alerts',
        'mortgage_rate_alerts',
        'estate_alerts',
        'lifecycle_churned_subscriber',
        'lifecycle_lapsed_subscriber',
    ];

    protected $casts = [
        'policy_renewals' => 'boolean',
        'goal_milestones' => 'boolean',
        'contribution_reminders' => 'boolean',
        'market_updates' => 'boolean',
        'fyn_daily_insight' => 'boolean',
        'security_alerts' => 'boolean',
        'payment_alerts' => 'boolean',
        'mortgage_rate_alerts' => 'boolean',
        'estate_alerts' => 'boolean',
        'lifecycle_churned_subscriber' => 'boolean',
        'lifecycle_lapsed_subscriber' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public static function getOrCreateForUser(int $userId): self
    {
        return self::firstOrCreate(
            ['user_id' => $userId],
            [
                'policy_renewals' => true,
                'goal_milestones' => true,
                'contribution_reminders' => true,
                'market_updates' => false,
                'fyn_daily_insight' => true,
                'security_alerts' => true,
                'payment_alerts' => true,
                'mortgage_rate_alerts' => true,
                'estate_alerts' => true,
                'lifecycle_churned_subscriber' => true,
                'lifecycle_lapsed_subscriber' => true,
            ]
        );
    }
}
