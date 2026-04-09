<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class LoginAttempt extends Model
{
    public $timestamps = false;

    protected $hidden = [
        'ip_address',
        'user_agent',
    ];

    protected $fillable = [
        'email',
        'ip_address',
        'user_agent',
        'successful',
        'failure_reason',
    ];

    protected $casts = [
        'successful' => 'boolean',
        'created_at' => 'datetime',
    ];

    /**
     * Failure reason constants
     */
    public const REASON_INVALID_CREDENTIALS = 'invalid_credentials';

    public const REASON_ACCOUNT_LOCKED = 'account_locked';

    public const REASON_MFA_REQUIRED = 'mfa_required';

    public const REASON_MFA_FAILED = 'mfa_failed';

    public const REASON_EMAIL_NOT_VERIFIED = 'email_not_verified';

    /**
     * Record a login attempt
     */
    public static function record(
        string $email,
        bool $successful,
        ?string $failureReason = null
    ): self {
        return self::create([
            'email' => $email,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'successful' => $successful,
            'failure_reason' => $failureReason,
        ]);
    }

    /**
     * Get recent failed attempts for an email
     */
    public static function recentFailedAttempts(string $email, int $minutes = 60): int
    {
        return self::where('email', $email)
            ->where('successful', false)
            ->where('created_at', '>=', now()->subMinutes($minutes))
            ->count();
    }

    /**
     * Get recent failed attempts from an IP
     */
    public static function recentFailedAttemptsFromIp(string $ip, int $minutes = 60): int
    {
        return self::where('ip_address', $ip)
            ->where('successful', false)
            ->where('created_at', '>=', now()->subMinutes($minutes))
            ->count();
    }

    /**
     * Scope for recent attempts
     */
    public function scopeRecent(Builder $query, int $minutes = 60): Builder
    {
        return $query->where('created_at', '>=', now()->subMinutes($minutes));
    }
}
