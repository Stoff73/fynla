<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Laravel\Sanctum\PersonalAccessToken;

class UserSession extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'token_id',
        'ip_address',
        'user_agent',
        'device_name',
        'device_id',
        'last_activity_at',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'last_activity_at' => 'datetime',
    ];

    /**
     * User relationship
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Token relationship
     */
    public function token(): BelongsTo
    {
        return $this->belongsTo(PersonalAccessToken::class, 'token_id');
    }

    /**
     * Create a session for a token
     */
    public static function createForToken(User $user, PersonalAccessToken $token): self
    {
        $userAgent = request()->userAgent();

        return self::create([
            'user_id' => $user->id,
            'token_id' => $token->id,
            'ip_address' => request()->ip(),
            'user_agent' => $userAgent,
            'device_name' => self::parseDeviceName($userAgent),
            'last_activity_at' => now(),
        ]);
    }

    /**
     * Update last activity timestamp
     */
    public function touchActivity(): bool
    {
        $this->last_activity_at = now();

        return $this->save();
    }

    /**
     * Revoke this session (delete token and session)
     */
    public function revoke(): void
    {
        $this->token?->delete();
        $this->delete();
    }

    /**
     * Parse device name from user agent
     */
    public static function parseDeviceName(?string $userAgent): string
    {
        if (empty($userAgent)) {
            return 'Unknown Device';
        }

        // Basic device detection
        if (str_contains($userAgent, 'iPhone')) {
            return 'iPhone';
        }
        if (str_contains($userAgent, 'iPad')) {
            return 'iPad';
        }
        if (str_contains($userAgent, 'Android')) {
            if (str_contains($userAgent, 'Mobile')) {
                return 'Android Phone';
            }

            return 'Android Tablet';
        }
        if (str_contains($userAgent, 'Mac OS')) {
            return 'Mac';
        }
        if (str_contains($userAgent, 'Windows')) {
            return 'Windows PC';
        }
        if (str_contains($userAgent, 'Linux')) {
            return 'Linux PC';
        }

        return 'Web Browser';
    }

    /**
     * Check if this is the current session
     */
    public function isCurrentSession(): bool
    {
        $currentToken = request()->user()?->currentAccessToken();
        if (! $currentToken) {
            return false;
        }

        return $this->token_id === $currentToken->id;
    }

    /**
     * Get formatted last activity
     */
    public function getLastActivityLabelAttribute(): string
    {
        if (! $this->last_activity_at) {
            return 'Never';
        }

        if ($this->last_activity_at->diffInMinutes(now()) < 5) {
            return 'Just now';
        }

        return $this->last_activity_at->diffForHumans();
    }

    /**
     * Scope to get sessions for a user
     */
    public function scopeForUser(Builder $query, int $userId): Builder
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Scope to order by most recent activity
     */
    public function scopeLatestActivity(Builder $query): Builder
    {
        return $query->orderByDesc('last_activity_at');
    }
}
