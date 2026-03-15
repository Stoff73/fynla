<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmailVerificationCode extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'code',
        'type',
        'challenge_token',
        'resend_count',
        'failed_attempts',
        'expires_at',
        'verified_at',
    ];

    protected $hidden = [
        'code',
        'challenge_token',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'verified_at' => 'datetime',
        'resend_count' => 'integer',
        'failed_attempts' => 'integer',
    ];

    /**
     * Get the user that owns this verification code.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Check if the code has expired.
     */
    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    /**
     * Check if the code is valid (not expired and not already verified).
     */
    public function isValid(): bool
    {
        return ! $this->isExpired() && is_null($this->verified_at);
    }

    /**
     * Check if the code can be resent (max 2 resends).
     */
    public function canResend(): bool
    {
        return $this->resend_count < 2;
    }

    /**
     * Increment the resend count.
     */
    public function incrementResendCount(): void
    {
        $this->increment('resend_count');
    }

    /**
     * Mark the code as verified.
     */
    public function markAsVerified(): void
    {
        $this->update(['verified_at' => now()]);
    }

    /**
     * Generate a new 6-digit verification code for a user.
     */
    public static function generate(int $userId, string $type, ?string $challengeToken = null): self
    {
        // Invalidate any existing codes for this user and type
        self::where('user_id', $userId)
            ->where('type', $type)
            ->whereNull('verified_at')
            ->delete();

        // Generate a random 6-digit code
        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        return self::create([
            'user_id' => $userId,
            'code' => $code,
            'type' => $type,
            'challenge_token' => $challengeToken,
            'resend_count' => 0,
            'expires_at' => Carbon::now()->addMinutes(15), // 15 minute expiry
        ]);
    }

    /**
     * Regenerate a code (for resending).
     */
    public function regenerate(): self
    {
        if (! $this->canResend()) {
            throw new \Exception('Maximum resend limit reached');
        }

        // Generate new code and reset expiry
        $newCode = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        $this->update([
            'code' => $newCode,
            'expires_at' => Carbon::now()->addMinutes(15), // 15 minute expiry
        ]);

        $this->incrementResendCount();

        return $this->fresh();
    }

    /**
     * Find a valid code for verification.
     */
    public static function findValidCode(int $userId, string $code, string $type): ?self
    {
        return self::where('user_id', $userId)
            ->where('code', $code)
            ->where('type', $type)
            ->whereNull('verified_at')
            ->where('expires_at', '>', now())
            ->where('failed_attempts', '<', 5)
            ->first();
    }

    /**
     * Record a failed verification attempt for a user and type.
     */
    public static function recordFailedAttempt(int $userId, string $type): void
    {
        self::where('user_id', $userId)
            ->where('type', $type)
            ->whereNull('verified_at')
            ->where('expires_at', '>', now())
            ->increment('failed_attempts');
    }

    /**
     * Find a valid, unverified code by its challenge token.
     */
    public static function findByChallengeToken(string $challengeToken): ?self
    {
        return self::where('challenge_token', $challengeToken)
            ->whereNull('verified_at')
            ->where('expires_at', '>', now())
            ->where('failed_attempts', '<', 5)
            ->first();
    }

    /**
     * Get the latest unverified code for a user and type.
     */
    public static function getLatest(int $userId, string $type): ?self
    {
        return self::where('user_id', $userId)
            ->where('type', $type)
            ->whereNull('verified_at')
            ->latest()
            ->first();
    }
}
