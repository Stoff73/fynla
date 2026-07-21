<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Laravel\Sanctum\PersonalAccessToken;

class NativeDeviceSession extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'user_id',
        'platform',
        'device_label',
        'app_version',
        'app_build',
        'current_access_token_id',
        'authenticated_at',
        'absolute_expires_at',
        'last_used_at',
        'revoked_at',
        'revoke_reason',
    ];

    protected $casts = [
        'authenticated_at' => 'datetime',
        'absolute_expires_at' => 'datetime',
        'last_used_at' => 'datetime',
        'revoked_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function currentAccessToken(): BelongsTo
    {
        return $this->belongsTo(PersonalAccessToken::class, 'current_access_token_id');
    }

    public function refreshTokens(): HasMany
    {
        return $this->hasMany(NativeRefreshToken::class);
    }

    public function isActive(?CarbonInterface $at = null): bool
    {
        $at ??= now();

        return $this->revoked_at === null
            && $this->absolute_expires_at !== null
            && $this->absolute_expires_at->isAfter($at);
    }
}
