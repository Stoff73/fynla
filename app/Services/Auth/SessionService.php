<?php

declare(strict_types=1);

namespace App\Services\Auth;

use App\Models\User;
use App\Models\UserSession;
use Illuminate\Support\Collection;
use Laravel\Sanctum\PersonalAccessToken;

class SessionService
{
    /**
     * Create a session for a token
     */
    public function createSession(User $user, PersonalAccessToken $token): UserSession
    {
        return UserSession::createForToken($user, $token);
    }

    /**
     * Get all sessions for a user
     */
    public function getUserSessions(User $user): Collection
    {
        return UserSession::forUser($user->id)
            ->whereHas('token')
            ->latestActivity()
            ->get()
            ->map(function (UserSession $session) {
                return [
                    'id' => $session->id,
                    'device_name' => $session->device_name,
                    'ip_address' => $session->ip_address,
                    'last_activity' => $session->last_activity_label,
                    'last_activity_at' => $session->last_activity_at?->toIso8601String(),
                    'created_at' => $session->created_at?->toIso8601String(),
                    'is_current' => $session->isCurrentSession(),
                ];
            });
    }

    /**
     * Revoke a specific session
     */
    public function revokeSession(UserSession $session): void
    {
        $session->revoke();
    }

    /**
     * Revoke all sessions except current
     */
    public function revokeAllExceptCurrent(User $user): int
    {
        $currentToken = request()->user()?->currentAccessToken();
        $currentTokenId = $currentToken?->id;

        $sessions = UserSession::forUser($user->id)->with('token')->get();
        $revokedCount = 0;

        foreach ($sessions as $session) {
            if ($session->token_id !== $currentTokenId) {
                $session->revoke();
                $revokedCount++;
            }
        }

        return $revokedCount;
    }

    /**
     * Revoke all sessions for a user
     */
    public function revokeAllSessions(User $user): int
    {
        $sessions = UserSession::forUser($user->id)->with('token')->get();
        $revokedCount = 0;

        foreach ($sessions as $session) {
            $session->revoke();
            $revokedCount++;
        }

        return $revokedCount;
    }

    /**
     * Update last activity for current session
     */
    public function updateCurrentSessionActivity(User $user): void
    {
        $currentToken = $user->currentAccessToken();

        if (! $currentToken) {
            return;
        }

        $session = UserSession::where('token_id', $currentToken->id)->first();

        if ($session) {
            $session->touchActivity();
        }
    }

    /**
     * Find session by ID for a user
     */
    public function findSession(User $user, int $sessionId): ?UserSession
    {
        return UserSession::forUser($user->id)
            ->where('id', $sessionId)
            ->first();
    }

    /**
     * Clean up expired/orphaned sessions
     */
    public function cleanupOrphanedSessions(): int
    {
        // Find sessions where the token no longer exists
        $orphaned = UserSession::whereDoesntHave('token')->get();
        $count = $orphaned->count();

        foreach ($orphaned as $session) {
            $session->delete();
        }

        return $count;
    }

    /**
     * Get session count for a user
     */
    public function getSessionCount(User $user): int
    {
        return UserSession::forUser($user->id)->count();
    }
}
