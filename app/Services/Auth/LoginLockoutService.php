<?php

declare(strict_types=1);

namespace App\Services\Auth;

use App\Models\LoginAttempt;
use App\Models\User;
use Carbon\Carbon;

class LoginLockoutService
{
    /**
     * Get lockout thresholds from config
     * Format: [failed_attempts => lockout_minutes]
     */
    private function getLockoutThresholds(): array
    {
        return config('auth.lockout.thresholds', [
            3 => 1,      // 3 failures = 1 minute lockout
            5 => 5,      // 5 failures = 5 minute lockout
            10 => 30,    // 10 failures = 30 minute lockout
            15 => 1440,  // 15+ failures = 24 hour lockout
        ]);
    }

    /**
     * Get max IP attempts from config
     */
    private function getIpMaxAttempts(): int
    {
        return config('auth.lockout.ip_max_attempts', 50);
    }

    /**
     * Check if a user/email is currently locked out
     */
    public function isLocked(string $email): bool
    {
        $user = User::where('email', $email)->first();

        if (! $user) {
            // For non-existent users, check by IP rate limiting only
            return $this->isIpLocked();
        }

        if ($user->locked_until && Carbon::parse($user->locked_until)->isFuture()) {
            return true;
        }

        return false;
    }

    /**
     * Check if current IP is locked due to too many attempts
     */
    public function isIpLocked(): bool
    {
        $ip = request()->ip();
        $recentFailures = LoginAttempt::recentFailedAttemptsFromIp($ip, 60);

        return $recentFailures >= $this->getIpMaxAttempts();
    }

    /**
     * Get remaining lockout time in seconds
     */
    public function getRemainingLockoutSeconds(string $email): int
    {
        $user = User::where('email', $email)->first();

        if (! $user || ! $user->locked_until) {
            return 0;
        }

        $lockedUntil = Carbon::parse($user->locked_until);

        if ($lockedUntil->isPast()) {
            return 0;
        }

        return (int) now()->diffInSeconds($lockedUntil);
    }

    /**
     * Record a failed login attempt
     */
    public function recordFailedAttempt(string $email, string $reason = LoginAttempt::REASON_INVALID_CREDENTIALS): void
    {
        // Record the attempt
        LoginAttempt::record($email, false, $reason);

        // Update user's failed login count
        $user = User::where('email', $email)->first();

        if ($user) {
            $user->failed_login_count++;
            $user->last_failed_login_at = now();

            // Calculate lockout duration based on failed count
            $lockoutMinutes = $this->calculateLockoutDuration($user->failed_login_count);

            if ($lockoutMinutes > 0) {
                $user->locked_until = now()->addMinutes($lockoutMinutes);

                // Log significant lockouts
                if ($lockoutMinutes >= 30) {
                    \Log::warning('Account locked due to repeated failed login attempts', [
                        'email' => $email,
                        'failed_count' => $user->failed_login_count,
                        'locked_until' => $user->locked_until,
                        'ip' => request()->ip(),
                    ]);
                }
            }

            $user->save();
        }
    }

    /**
     * Record a successful login
     */
    public function recordSuccessfulLogin(string $email): void
    {
        LoginAttempt::record($email, true);

        // Reset failed login counter on successful login
        $user = User::where('email', $email)->first();

        if ($user) {
            $user->failed_login_count = 0;
            $user->locked_until = null;
            $user->last_failed_login_at = null;
            $user->save();
        }
    }

    /**
     * Reset failed attempts for a user (e.g., after password reset)
     */
    public function resetFailedAttempts(User $user): void
    {
        $user->failed_login_count = 0;
        $user->locked_until = null;
        $user->last_failed_login_at = null;
        $user->save();
    }

    /**
     * Calculate lockout duration based on failed attempt count
     */
    private function calculateLockoutDuration(int $failedCount): int
    {
        $lockoutMinutes = 0;

        foreach ($this->getLockoutThresholds() as $threshold => $minutes) {
            if ($failedCount >= $threshold) {
                $lockoutMinutes = $minutes;
            }
        }

        return $lockoutMinutes;
    }

    /**
     * Get lockout status info for API response
     */
    public function getLockoutInfo(string $email): array
    {
        $remainingSeconds = $this->getRemainingLockoutSeconds($email);

        if ($remainingSeconds <= 0) {
            return [
                'locked' => false,
                'remaining_seconds' => 0,
                'message' => null,
            ];
        }

        $minutes = (int) ceil($remainingSeconds / 60);

        return [
            'locked' => true,
            'remaining_seconds' => $remainingSeconds,
            'message' => "Account temporarily locked. Try again in {$minutes} minute(s).",
        ];
    }
}
