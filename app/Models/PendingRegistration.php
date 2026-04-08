<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Pending Registration Model
 *
 * Stores registration data until email is verified.
 * - Expires after 24 hours
 * - Same email can re-register (overwrites previous pending)
 * - Once verified, user is created and pending record deleted
 */
class PendingRegistration extends Model
{
    use HasFactory;

    /**
     * Pending registration expiry in hours.
     */
    public const EXPIRY_HOURS = 24;

    protected $fillable = [
        'email',
        'first_name',
        'middle_name',
        'surname',
        'password',
        'verification_code',
        'verification_attempts',
        'registration_source',
        'preview_persona_id',
        'plan',
        'billing_cycle',
        'referral_code',
        'expires_at',
    ];

    protected $hidden = [
        'password',
        'verification_code',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
    ];

    /**
     * Generate a random 6-digit verification code.
     */
    public static function generateCode(): string
    {
        return str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    }

    /**
     * Create or update a pending registration.
     * If email already has a pending registration, it gets overwritten.
     */
    public static function createOrUpdate(array $data): self
    {
        return self::updateOrCreate(
            ['email' => $data['email']],
            [
                'first_name' => $data['first_name'],
                'middle_name' => $data['middle_name'] ?? null,
                'surname' => $data['surname'],
                'password' => $data['password'], // Already hashed
                'verification_code' => self::generateCode(),
                'registration_source' => $data['registration_source'] ?? null,
                'preview_persona_id' => $data['preview_persona_id'] ?? null,
                'plan' => $data['plan'] ?? null,
                'billing_cycle' => $data['billing_cycle'] ?? null,
                'referral_code' => $data['referral_code'] ?? null,
                'expires_at' => now()->addHours(self::EXPIRY_HOURS),
            ]
        );
    }

    /**
     * Check if this pending registration has expired.
     */
    public function isExpired(): bool
    {
        return $this->expires_at && $this->expires_at->isPast();
    }

    /**
     * Verify the code and return the pending registration if valid and not expired.
     */
    public static function verify(string $email, string $code): ?self
    {
        $pending = self::where('email', $email)
            ->where('verification_code', $code)
            ->first();

        if ($pending && $pending->isExpired()) {
            return null;
        }

        return $pending;
    }

    /**
     * Regenerate verification code for resend.
     */
    public function regenerateCode(): string
    {
        $this->verification_code = self::generateCode();
        $this->save();

        return $this->verification_code;
    }
}
