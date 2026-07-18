<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AppleTransaction extends Model
{
    public const ENVIRONMENT_SANDBOX = 'sandbox';

    public const ENVIRONMENT_PRODUCTION = 'production';

    public const ENVIRONMENTS = [
        self::ENVIRONMENT_SANDBOX,
        self::ENVIRONMENT_PRODUCTION,
    ];

    protected $fillable = [
        'user_id',
        'premium_entitlement_id',
        'transaction_id',
        'original_transaction_id',
        'app_account_token',
        'product_id',
        'environment',
        'purchased_at',
        'expires_at',
        'revoked_at',
        'ownership_type',
        'transaction_reason',
        'signed_payload_sha256',
        'received_at',
        'reconciled_at',
    ];

    protected $hidden = [
        'app_account_token',
    ];

    protected $casts = [
        'purchased_at' => 'datetime',
        'expires_at' => 'datetime',
        'revoked_at' => 'datetime',
        'received_at' => 'datetime',
        'reconciled_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function premiumEntitlement(): BelongsTo
    {
        return $this->belongsTo(PremiumEntitlement::class);
    }
}
