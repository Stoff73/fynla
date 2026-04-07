<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureMFAVerified
{
    /**
     * Handle an incoming request.
     *
     * Ensures that if a user has MFA enabled, they have verified it for this session.
     * For API token requests, MFA was verified at login before the token was issued.
     * For session-based requests, checks the session flag set during MFA verification.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        // For API token requests, check MFA claim on token
        if ($request->bearerToken()) {
            $user = $request->user();
            if ($user && $user->mfa_enabled && ! $user->currentAccessToken()?->can('mfa_verified')) {
                return response()->json(['message' => 'MFA verification required.'], 403);
            }

            return $next($request);
        }

        // For session-based requests, check session flag
        if ($user->mfa_enabled && ! session('mfa_verified', false)) {
            return response()->json([
                'message' => 'MFA verification required.',
                'mfa_required' => true,
            ], 403);
        }

        return $next($request);
    }
}
