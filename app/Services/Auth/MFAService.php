<?php

declare(strict_types=1);

namespace App\Services\Auth;

use App\Models\User;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use PragmaRX\Google2FA\Google2FA;

class MFAService
{
    private Google2FA $google2fa;

    public function __construct()
    {
        $this->google2fa = new Google2FA;
    }

    /**
     * Generate a new MFA secret
     */
    public function generateSecret(): string
    {
        return $this->google2fa->generateSecretKey(32);
    }

    /**
     * Get QR code data URI for MFA setup
     */
    public function getQRCodeDataUri(User $user, string $secret): string
    {
        $appName = config('app.name', 'Fynla');

        $qrCodeUrl = $this->google2fa->getQRCodeUrl(
            $appName,
            $user->email,
            $secret
        );

        // Generate QR code using bacon/bacon-qr-code
        $renderer = new \BaconQrCode\Renderer\ImageRenderer(
            new \BaconQrCode\Renderer\RendererStyle\RendererStyle(300),
            new \BaconQrCode\Renderer\Image\SvgImageBackEnd
        );
        $writer = new \BaconQrCode\Writer($renderer);

        $svg = $writer->writeString($qrCodeUrl);

        return 'data:image/svg+xml;base64,'.base64_encode($svg);
    }

    /**
     * Verify a TOTP code
     */
    public function verifyCode(User $user, string $code): bool
    {
        if (! $user->mfa_secret) {
            return false;
        }

        // Decrypt the stored secret
        $secret = Crypt::decryptString($user->mfa_secret);

        return $this->google2fa->verifyKey($secret, $code, 2); // Allow 2 windows of tolerance
    }

    /**
     * Verify a code during setup (before MFA is confirmed)
     */
    public function verifySetupCode(string $secret, string $code): bool
    {
        return $this->google2fa->verifyKey($secret, $code, 2);
    }

    /**
     * Enable MFA for a user
     */
    public function enableMFA(User $user, string $secret): array
    {
        // Generate recovery codes
        $recoveryCodes = $this->generateRecoveryCodes();
        $hashedCodes = $this->hashRecoveryCodes($recoveryCodes);

        // Store encrypted secret and hashed recovery codes
        $user->mfa_enabled = true;
        $user->mfa_secret = Crypt::encryptString($secret);
        $user->mfa_recovery_codes = $hashedCodes;
        $user->mfa_confirmed_at = now();
        $user->save();

        return $recoveryCodes; // Return plain codes for user to save
    }

    /**
     * Disable MFA for a user
     */
    public function disableMFA(User $user): void
    {
        $user->mfa_enabled = false;
        $user->mfa_secret = null;
        $user->mfa_recovery_codes = null;
        $user->mfa_confirmed_at = null;
        $user->save();
    }

    /**
     * Generate recovery codes
     */
    public function generateRecoveryCodes(int $count = 10): array
    {
        $codes = [];

        for ($i = 0; $i < $count; $i++) {
            // Format: XXXX-XXXX-XXXX (12 chars with dashes)
            $codes[] = strtoupper(
                Str::random(4).'-'.Str::random(4).'-'.Str::random(4)
            );
        }

        return $codes;
    }

    /**
     * Hash recovery codes for storage
     */
    public function hashRecoveryCodes(array $codes): array
    {
        return array_map(fn ($code) => Hash::make($code), $codes);
    }

    /**
     * Verify a recovery code
     */
    public function verifyRecoveryCode(User $user, string $code): bool
    {
        if (! $user->mfa_recovery_codes) {
            return false;
        }

        $hashedCodes = $user->mfa_recovery_codes;
        $normalizedCode = strtoupper(trim($code));

        foreach ($hashedCodes as $index => $hashedCode) {
            if (Hash::check($normalizedCode, $hashedCode)) {
                // Remove used code
                unset($hashedCodes[$index]);
                $user->mfa_recovery_codes = array_values($hashedCodes);
                $user->save();

                return true;
            }
        }

        return false;
    }

    /**
     * Regenerate recovery codes
     */
    public function regenerateRecoveryCodes(User $user): array
    {
        $recoveryCodes = $this->generateRecoveryCodes();
        $hashedCodes = $this->hashRecoveryCodes($recoveryCodes);

        $user->mfa_recovery_codes = $hashedCodes;
        $user->save();

        return $recoveryCodes;
    }

    /**
     * Get remaining recovery code count
     */
    public function getRemainingRecoveryCodeCount(User $user): int
    {
        if (! $user->mfa_recovery_codes) {
            return 0;
        }

        return count($user->mfa_recovery_codes);
    }

    /**
     * Check if user has MFA enabled
     */
    public function hasMFAEnabled(User $user): bool
    {
        return $user->mfa_enabled && $user->mfa_secret !== null;
    }
}
