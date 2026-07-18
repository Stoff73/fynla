<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class AppleNotificationRecovery extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_FAILED = 'failed';

    public const STATUS_PROCESSED = 'processed';

    public const STATUS_REJECTED = 'rejected';

    public const STATUSES = [
        self::STATUS_PENDING,
        self::STATUS_FAILED,
        self::STATUS_PROCESSED,
        self::STATUS_REJECTED,
    ];

    protected $fillable = [
        'apple_notification_log_id',
        'user_id',
        'original_transaction_id',
        'status',
        'error_code',
        'attempt_count',
        'next_attempt_at',
        'last_attempted_at',
        'processed_at',
    ];

    protected $casts = [
        'attempt_count' => 'integer',
        'next_attempt_at' => 'datetime',
        'last_attempted_at' => 'datetime',
        'processed_at' => 'datetime',
    ];

    public function notificationLog(): BelongsTo
    {
        return $this->belongsTo(
            AppleNotificationLog::class,
            'apple_notification_log_id',
        );
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
