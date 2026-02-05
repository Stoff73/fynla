<?php

declare(strict_types=1);

namespace App\Services\Auth;

use App\Mail\PasswordResetCode;
use App\Models\AuditLog;
use App\Models\PasswordResetSession;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;

class PasswordResetService
{
    public function __construct(
        private readonly MFAService $mfaService
    ) {}

    /**
     * Initiate password reset process
     * Always returns success to prevent account enumeration
     */
    public function initiateReset(string $email): array
    {
        $user = User::where('email', $email)
            ->where('is_preview_user', false)
            ->first();

        if (! $user) {
            // Return success to prevent account enumeration
            return [
                'success' => true,
                'message' => 'If an account exists with this email, you will receive a verification code.',
            ];
        }

        // Create session and send email
        $session = PasswordResetSession::generate($user);

        // Send email with verification code
        Mail::to($user->email)->send(new PasswordResetCode($user, $session->email_code));

        // Log the request
        AuditLog::logAuth(
            AuditLog::ACTION_PASSWORD_RESET_REQUESTED,
            $user->id,
            ['ip_address' => request()->ip()]
        );

        return [
            'success' => true,
            'message' => 'If an account exists with this email, you will receive a verification code.',
            'data' => [
                'reset_token' => $session->token,
            ],
        ];
    }

    /**
     * Verify email code
     */
    public function verifyEmailCode(PasswordResetSession $session, string $code): array
    {
        if ($session->isExpired()) {
            return [
                'success' => false,
                'message' => 'This reset session has expired. Please request a new password reset.',
            ];
        }

        if ($session->isEmailVerified()) {
            return [
                'success' => false,
                'message' => 'Email has already been verified.',
            ];
        }

        if ($session->email_code !== $code) {
            return [
                'success' => false,
                'message' => 'Invalid verification code.',
            ];
        }

        $session->markEmailVerified();

        $user = $session->user;
        $requiresMfa = $user->mfa_enabled;

        return [
            'success' => true,
            'message' => 'Email verified successfully.',
            'data' => [
                'requires_mfa' => $requiresMfa,
                'can_reset_password' => ! $requiresMfa,
            ],
        ];
    }

    /**
     * Verify MFA TOTP code
     */
    public function verifyMfaCode(PasswordResetSession $session, string $code): array
    {
        if ($session->isExpired()) {
            return [
                'success' => false,
                'message' => 'This reset session has expired. Please request a new password reset.',
            ];
        }

        if (! $session->isEmailVerified()) {
            return [
                'success' => false,
                'message' => 'Email must be verified first.',
            ];
        }

        $user = $session->user;

        if (! $user->mfa_enabled) {
            return [
                'success' => false,
                'message' => 'MFA is not enabled for this account.',
            ];
        }

        if (! $this->mfaService->verifyCode($user, $code)) {
            return [
                'success' => false,
                'message' => 'Invalid MFA code.',
            ];
        }

        $session->markMfaVerified();

        return [
            'success' => true,
            'message' => 'MFA verified successfully.',
            'data' => [
                'can_reset_password' => true,
            ],
        ];
    }

    /**
     * Verify MFA recovery code
     */
    public function verifyRecoveryCode(PasswordResetSession $session, string $code): array
    {
        if ($session->isExpired()) {
            return [
                'success' => false,
                'message' => 'This reset session has expired. Please request a new password reset.',
            ];
        }

        if (! $session->isEmailVerified()) {
            return [
                'success' => false,
                'message' => 'Email must be verified first.',
            ];
        }

        $user = $session->user;

        if (! $user->mfa_enabled) {
            return [
                'success' => false,
                'message' => 'MFA is not enabled for this account.',
            ];
        }

        if (! $this->mfaService->verifyRecoveryCode($user, $code)) {
            return [
                'success' => false,
                'message' => 'Invalid recovery code.',
            ];
        }

        $session->markMfaVerified();

        return [
            'success' => true,
            'message' => 'Recovery code verified successfully.',
            'data' => [
                'can_reset_password' => true,
                'remaining_recovery_codes' => $this->mfaService->getRemainingRecoveryCodeCount($user),
            ],
        ];
    }

    /**
     * Reset the password
     */
    public function resetPassword(PasswordResetSession $session, string $newPassword): array
    {
        if ($session->isExpired()) {
            return [
                'success' => false,
                'message' => 'This reset session has expired. Please request a new password reset.',
            ];
        }

        if (! $session->canResetPassword()) {
            return [
                'success' => false,
                'message' => 'Verification not complete. Please verify your email and MFA.',
            ];
        }

        $user = $session->user;

        // Update password
        $user->password = Hash::make($newPassword);
        $user->must_change_password = false;
        $user->save();

        // Mark session as used
        $session->markUsed();

        // Revoke all existing tokens for security
        $user->tokens()->delete();

        // Log the password reset
        AuditLog::logAuth(
            AuditLog::ACTION_PASSWORD_RESET_COMPLETED,
            $user->id,
            ['ip_address' => request()->ip()]
        );

        return [
            'success' => true,
            'message' => 'Password has been reset successfully. Please log in with your new password.',
        ];
    }

    /**
     * Resend email verification code
     */
    public function resendCode(PasswordResetSession $session): array
    {
        if ($session->isExpired()) {
            return [
                'success' => false,
                'message' => 'This reset session has expired. Please request a new password reset.',
            ];
        }

        if ($session->isEmailVerified()) {
            return [
                'success' => false,
                'message' => 'Email has already been verified.',
            ];
        }

        if (! $session->canResendCode()) {
            return [
                'success' => false,
                'message' => 'Maximum resend limit reached. Please request a new password reset.',
            ];
        }

        if (! $session->regenerateCode()) {
            return [
                'success' => false,
                'message' => 'Unable to resend code. Please try again.',
            ];
        }

        // Send new code
        $user = $session->user;
        Mail::to($user->email)->send(new PasswordResetCode($user, $session->email_code));

        return [
            'success' => true,
            'message' => 'A new verification code has been sent to your email.',
            'data' => [
                'remaining_resends' => $session->getRemainingResends(),
            ],
        ];
    }
}
