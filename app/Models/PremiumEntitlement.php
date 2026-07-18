<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PremiumEntitlement extends Model
{
    public const PROVIDER_APPLE = 'apple';

    public const PROVIDER_REVOLUT = 'revolut';

    public const PROVIDERS = [
        self::PROVIDER_APPLE,
        self::PROVIDER_REVOLUT,
    ];

    public const STATUS_ACTIVE = 'active';

    public const STATUS_GRACE_PERIOD = 'grace_period';

    public const STATUS_BILLING_RETRY = 'billing_retry';

    public const STATUS_CANCELLED = 'cancelled';

    public const STATUS_EXPIRED = 'expired';

    public const STATUS_REVOKED = 'revoked';

    public const STATUSES = [
        self::STATUS_ACTIVE,
        self::STATUS_GRACE_PERIOD,
        self::STATUS_BILLING_RETRY,
        self::STATUS_CANCELLED,
        self::STATUS_EXPIRED,
        self::STATUS_REVOKED,
    ];

    protected $fillable = [
        'user_id',
        'provider',
        'provider_reference',
        'product_id',
        'status',
        'will_renew',
        'period_start',
        'period_end',
        'grace_period_ends_at',
        'revoked_at',
        'last_verified_at',
        'provider_metadata',
    ];

    protected $casts = [
        'will_renew' => 'boolean',
        'period_start' => 'datetime',
        'period_end' => 'datetime',
        'grace_period_ends_at' => 'datetime',
        'revoked_at' => 'datetime',
        'last_verified_at' => 'datetime',
        'provider_metadata' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function appleTransactions(): HasMany
    {
        return $this->hasMany(AppleTransaction::class);
    }
}
