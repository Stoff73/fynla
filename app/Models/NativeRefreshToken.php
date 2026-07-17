<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NativeRefreshToken extends Model
{
    use HasFactory;

    protected $fillable = [
        'native_device_session_id',
        'expires_at',
        'used_at',
        'revoked_at',
    ];

    protected $hidden = [
        'token_hash',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'used_at' => 'datetime',
        'revoked_at' => 'datetime',
    ];

    public function deviceSession(): BelongsTo
    {
        return $this->belongsTo(NativeDeviceSession::class, 'native_device_session_id');
    }

    public function canRotate(?CarbonInterface $at = null): bool
    {
        $at ??= now();

        return $this->used_at === null
            && $this->revoked_at === null
            && $this->expires_at !== null
            && $this->expires_at->isAfter($at);
    }
}
