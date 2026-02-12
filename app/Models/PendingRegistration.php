<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Pending Registration Model
 *
 * Stores registration data until email is verified.
 * - No expiry timer - user can verify at any time
 * - Same email can re-register (overwrites previous pending)
 * - Once verified, user is created and pending record deleted
 */
class PendingRegistration extends Model
{
    use HasFactory;

    protected $fillable = [
        'email',
        'first_name',
        'middle_name',
        'surname',
        'password',
        'verification_code',
        'registration_source',
        'preview_persona_id',
        'plan',
        'billing_cycle',
    ];

    protected $hidden = [
        'password',
        'verification_code',
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
            ]
        );
    }

    /**
     * Verify the code and return the pending registration if valid.
     */
    public static function verify(string $email, string $code): ?self
    {
        return self::where('email', $email)
            ->where('verification_code', $code)
            ->first();
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
