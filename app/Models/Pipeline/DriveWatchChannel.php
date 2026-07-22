<?php

declare(strict_types=1);

namespace App\Models\Pipeline;

use Illuminate\Database\Eloquent\Model;

/**
 * The active Google Drive push-notification channel (see the drive webhook).
 * There is normally one active row — the most recent — which the webhook uses
 * for its page token and the renewal command replaces before expiry.
 */
class DriveWatchChannel extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'expires_at' => 'datetime',
    ];

    public static function active(): ?self
    {
        return static::query()->latest('id')->first();
    }
}
