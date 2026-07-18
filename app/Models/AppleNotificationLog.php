<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AppleNotificationLog extends Model
{
    public const ENVIRONMENT_SANDBOX = 'sandbox';

    public const ENVIRONMENT_PRODUCTION = 'production';

    public const STATUS_RECEIVED = 'received';

    public const STATUS_PROCESSED = 'processed';

    public const STATUS_DUPLICATE = 'duplicate';

    public const STATUS_REJECTED = 'rejected';

    public const STATUS_FAILED = 'failed';

    public const STATUSES = [
        self::STATUS_RECEIVED,
        self::STATUS_PROCESSED,
        self::STATUS_DUPLICATE,
        self::STATUS_REJECTED,
        self::STATUS_FAILED,
    ];

    protected $fillable = [
        'notification_uuid',
        'environment',
        'notification_type',
        'subtype',
        'signed_payload_sha256',
        'status',
        'error_code',
        'processed_at',
    ];

    protected $casts = [
        'processed_at' => 'datetime',
    ];
}
