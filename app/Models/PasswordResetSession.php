<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class PasswordResetSession extends Model
{
    public $timestamps = false;

    protected $hidden = [
        'token',
        'email_code',
    ];

    protected $fillable = [
        'user_id',
        'token',
        'email_code',
        'email_code_resend_count',
        'email_verified_at',
        'mfa_verified_at',
        'ip_address',
        'expires_at',
        'used_at',
    ];

    protected $casts = [
        'email_code_resend_count' => 'integer',
        'email_verified_at' => 'datetime',
        'mfa_verified_at' => 'datetime',
        'expires_at' => 'datetime',
        'used_at' => 'datetime',
        'created_at' => 'datetime',
    ];

    /**
     * Maximum number of code resends allowed
     */
    public const MAX_RESEND_COUNT = 2;

    /**
     * Session expiry time in minutes
     */
    public const SESSION_EXPIRY_MINUTES = 15;

    /**
     * User relationship
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Generate a new password reset session for a user
     */
    public static function generate(User $user): self
    {
        // Invalidate any existing sessions for this user
        self::where('user_id', $user->id)
            ->whereNull('used_at')
            ->update(['used_at' => now()]);

        return self::create([
            'user_id' => $user->id,
            'token' => Str::random(64),
            'email_code' => str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT),
            'email_code_resend_count' => 0,
            'ip_address' => request()->ip(),
            'expires_at' => now()->addMinutes(self::SESSION_EXPIRY_MINUTES),
        ]);
    }

    /**
     * Find a valid session by token
     */
    public static function findByToken(string $token): ?self
    {
        return self::where('token', $token)
            ->whereNull('used_at')
            ->where('expires_at', '>', now())
            ->first();
    }

    /**
     * Check if session is expired
     */
    public function isExpired(): bool
    {
        return $this->expires_at->isPast() || $this->used_at !== null;
    }

    /**
     * Check if email has been verified
     */
    public function isEmailVerified(): bool
    {
        return $this->email_verified_at !== null;
    }

    /**
     * Check if MFA has been verified (or not required)
     */
    public function isMfaVerified(): bool
    {
        return $this->mfa_verified_at !== null;
    }

    /**
     * Check if password can be reset (all verifications complete)
     */
    public function canResetPassword(): bool
    {
        if ($this->isExpired()) {
            return false;
        }

        if (! $this->isEmailVerified()) {
            return false;
        }

        // If user has MFA enabled, it must be verified
        if ($this->user->mfa_enabled && ! $this->isMfaVerified()) {
            return false;
        }

        return true;
    }

    /**
     * Mark email as verified
     */
    public function markEmailVerified(): void
    {
        $this->email_verified_at = now();
        $this->save();
    }

    /**
     * Mark MFA as verified
     */
    public function markMfaVerified(): void
    {
        $this->mfa_verified_at = now();
        $this->save();
    }

    /**
     * Mark session as used
     */
    public function markUsed(): void
    {
        $this->used_at = now();
        $this->save();
    }

    /**
     * Regenerate the email code (for resend)
     * Returns false if max resends reached
     */
    public function regenerateCode(): bool
    {
        if ($this->email_code_resend_count >= self::MAX_RESEND_COUNT) {
            return false;
        }

        $this->email_code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $this->email_code_resend_count++;
        // Extend expiry when resending
        $this->expires_at = now()->addMinutes(self::SESSION_EXPIRY_MINUTES);
        $this->save();

        return true;
    }

    /**
     * Check if more resends are allowed
     */
    public function canResendCode(): bool
    {
        return $this->email_code_resend_count < self::MAX_RESEND_COUNT;
    }

    /**
     * Get remaining resend attempts
     */
    public function getRemainingResends(): int
    {
        return max(0, self::MAX_RESEND_COUNT - $this->email_code_resend_count);
    }
}
